<?php
/** Trang thanh toán - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Lấy giỏ hàng
$stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = ?');
$stmt->execute([$userId]);
$cart = $stmt->fetch();

if (!$cart) {
    setFlashMessage('warning', 'Giỏ hàng trống.');
    redirect(url('pages/products.php'));
}

$stmt = $pdo->prepare('
    SELECT ci.*, p.name, p.slug, pv.size, pv.color, pv.stock_quantity,
           p.thumbnail as image_path
    FROM cart_items ci
    INNER JOIN products p ON ci.product_id = p.id
    INNER JOIN product_variants pv ON ci.variant_id = pv.id
    WHERE ci.cart_id = ?
');
$stmt->execute([$cart['id']]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    setFlashMessage('warning', 'Giỏ hàng trống.');
    redirect(url('pages/products.php'));
}

$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Phí vận chuyển theo phương thức
$shippingMethod = $_POST['shipping_method'] ?? 'standard';
$shippingFees   = [
    'standard' => SHIPPING_FEE_STANDARD,  // 0đ (miễn phí)
    'express'  => SHIPPING_FEE_EXPRESS,   // 60.000đ
];
$shippingFee = $shippingFees[$shippingMethod] ?? SHIPPING_FEE_STANDARD;

// Lấy thông tin user
$stmtUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch();

// Lấy voucher hiện có
$vouchers = $pdo->query("SELECT * FROM vouchers WHERE status = 'active' AND start_date <= NOW() AND end_date >= NOW() AND (usage_limit = 0 OR used_count < usage_limit)")->fetchAll();

$errors = [];
$voucherDiscount = 0;
$appliedVoucher = null;

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');          // tên tỉnh/thành (từ hidden)
    $ward = trim($_POST['ward'] ?? '');           // tên xã/phường (từ hidden)
    $streetAddress = trim($_POST['street_address'] ?? ''); // số nhà, tên đường
    // Ghép địa chỉ đầy đủ
    $address = implode(', ', array_filter([$streetAddress, $ward, $city]));
    $paymentMethod  = $_POST['payment_method']  ?? 'cod';
    $shippingMethod = $_POST['shipping_method']  ?? 'standard';
    $shippingFee    = $shippingFees[$shippingMethod] ?? SHIPPING_FEE_STANDARD;
    $voucherCode    = trim($_POST['voucher_code'] ?? '');
    $note           = trim($_POST['note']         ?? '');

    // Validate
    if (empty($fullName))
        $errors['full_name'] = 'Vui lòng nhập họ tên.';
    if (empty($phone))
        $errors['phone'] = 'Vui lòng nhập số điện thoại.';
    elseif (!isValidPhone($phone))
        $errors['phone'] = 'Số điện thoại không hợp lệ.';
    if (empty($city))
        $errors['city'] = 'Vui lòng chọn tỉnh/thành phố.';
    if (empty($ward))
        $errors['ward'] = 'Vui lòng chọn xã/phường.';
    if (empty($streetAddress))
        $errors['street_address'] = 'Vui lòng nhập số nhà, tên đường.';

    // Áp dụng voucher
    if (!empty($voucherCode)) {
        $stmtV = $pdo->prepare("SELECT * FROM vouchers WHERE code = ? AND status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
        $stmtV->execute([$voucherCode]);
        $voucher = $stmtV->fetch();

        if (!$voucher) {
            $errors['voucher'] = 'Mã giảm giá không hợp lệ hoặc đã hết hạn.';
        } elseif ($voucher['usage_limit'] > 0 && $voucher['used_count'] >= $voucher['usage_limit']) {
            $errors['voucher'] = 'Mã giảm giá đã hết lượt sử dụng.';
        } elseif ($subtotal < $voucher['min_order_amount']) {
            $errors['voucher'] = 'Đơn hàng chưa đạt giá trị tối thiểu ' . formatPrice($voucher['min_order_amount']);
        } else {
            $appliedVoucher = $voucher;
            if ($voucher['discount_type'] === 'percentage') {
                $voucherDiscount = $subtotal * $voucher['discount_value'] / 100;
                if ($voucher['max_discount'] && $voucherDiscount > $voucher['max_discount']) {
                    $voucherDiscount = $voucher['max_discount'];
                }
            } else {
                $voucherDiscount = $voucher['discount_value'];
            }
        }
    }

    $totalAmount = $subtotal + $shippingFee - $voucherDiscount;
    if ($totalAmount < 0)
        $totalAmount = 0;

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Tạo đơn hàng
            $orderCode = generateOrderCode();

            // VNPay/MoMo → pending/unpaid cho đến khi gateway xác nhận
            // COD → pending/unpaid, xác nhận thủ công
            $initialStatus        = 'pending';
            $initialPaymentStatus = 'unpaid';

            try {
                $stmtOrder = $pdo->prepare("
                    INSERT INTO orders (user_id, order_code, full_name, phone, city, address, note, 
                        subtotal, shipping_fee, discount_amount, voucher_id, total_amount, 
                        payment_method, payment_status, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtOrder->execute([
                    $userId, $orderCode, $fullName, $phone, $city, $address, $note,
                    $subtotal, $shippingFee, $voucherDiscount,
                    $appliedVoucher ? $appliedVoucher['id'] : null,
                    $totalAmount, $paymentMethod, $initialPaymentStatus, $initialStatus
                ]);
            } catch (PDOException $exOrder) {
                $stmtOrder = $pdo->prepare("
                    INSERT INTO orders (user_id, order_code, receiver_name, receiver_phone, receiver_province, receiver_address, note, 
                        subtotal, shipping_fee, discount_amount, voucher_id, total_amount, 
                        payment_method, payment_status, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtOrder->execute([
                    $userId, $orderCode, $fullName, $phone, $city, $address, $note,
                    $subtotal, $shippingFee, $voucherDiscount,
                    $appliedVoucher ? $appliedVoucher['id'] : null,
                    $totalAmount, $paymentMethod, $initialPaymentStatus, $initialStatus
                ]);
            }
            $orderId = $pdo->lastInsertId();

            // Thêm order items
            foreach ($cartItems as $item) {
                $pdo->prepare('
                    INSERT INTO order_items (order_id, product_id, variant_id, product_name, size, color, quantity, price)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([
                    $orderId, $item['product_id'], $item['variant_id'],
                    $item['name'], $item['size'], $item['color'],
                    $item['quantity'], $item['price']
                ]);

                // Giảm tồn kho & cập nhật trạng thái tự động
                $pdo->prepare('
                    UPDATE product_variants
                    SET stock_quantity = GREATEST(0, stock_quantity - ?),
                        status = CASE
                            WHEN GREATEST(0, stock_quantity - ?) = 0    THEN "out_of_stock"
                            WHEN GREATEST(0, stock_quantity - ?) <= 5   THEN "low_stock"
                            ELSE "in_stock"
                        END
                    WHERE id = ?
                ')->execute([$item['quantity'], $item['quantity'], $item['quantity'], $item['variant_id']]);

                // Tăng số lượng đã bán
                $pdo
                    ->prepare('UPDATE products SET total_sold = total_sold + ? WHERE id = ?')
                    ->execute([$item['quantity'], $item['product_id']]);
            }

            // Cập nhật voucher
            if ($appliedVoucher) {
                $pdo
                    ->prepare('UPDATE vouchers SET used_count = used_count + 1 WHERE id = ?')
                    ->execute([$appliedVoucher['id']]);
            }

            // Xóa giỏ hàng
            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cart['id']]);

            $pdo->commit();

            // Với VNPay/MoMo → chuyển tới cổng thanh toán
            if ($paymentMethod === 'vnpay') {
                redirect(url('payment/vnpay_create.php?order_id=' . $orderId));
            } elseif ($paymentMethod === 'momo') {
                redirect(url('payment/momo_create.php?order_id=' . $orderId));
            } else {
                setFlashMessage('success', "Đặt hàng thành công! Mã đơn hàng: $orderCode. Chúng tôi sẽ xác nhận sớm nhất.");
                redirect(url('pages/order_detail.php?id=' . $orderId));
            }
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors['general'] = 'Có lỗi xảy ra: ' . $ex->getMessage() . '. Vui lòng thử lại.';
        }
    }
}

$pageTitle = 'Thanh toán - WinK Shoe Store';
$extraJS = ['address.js'];
include dirname(__DIR__) . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <h4 class="fw-bold mb-4"><i class="fas fa-credit-card me-2"></i>Thanh toán</h4>
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?php echo e($errors['general']); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row g-4">
                <!-- Thông tin giao hàng -->
                <div class="col-lg-7">
                    <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-truck me-2" style="color:var(--primary);"></i>Thông tin giao hàng</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control <?php echo !empty($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo e($_POST['full_name'] ?? $user['full_name']); ?>" required>
                                <?php if (!empty($errors['full_name'])): ?><div class="invalid-feedback"><?php echo e($errors['full_name']); ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control <?php echo !empty($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo e($_POST['phone'] ?? $user['phone']); ?>" required>
                                <?php if (!empty($errors['phone'])): ?><div class="invalid-feedback"><?php echo e($errors['phone']); ?></div><?php endif; ?>
                            </div>
                            <!-- Hidden fields lưu tên địa chỉ -->
                            <input type="hidden" name="city" id="hidden_city" value="<?php echo e($_POST['city'] ?? $user['city'] ?? $user['province'] ?? ''); ?>">
                            <input type="hidden" name="ward" id="hidden_ward" value="<?php echo e($_POST['ward'] ?? ''); ?>">

                            <div class="col-md-6">
                                <label class="form-label">Tỉnh/Thành phố <span class="text-danger">*</span></label>
                                <select id="select_province" class="form-select <?php echo !empty($errors['city']) ? 'is-invalid' : ''; ?>">
                                    <option value="">-- Chọn Tỉnh/Thành phố --</option>
                                </select>
                                <?php if (!empty($errors['city'])): ?><div class="invalid-feedback d-block"><?php echo e($errors['city']); ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Xã/Phường/Thị trấn <span class="text-danger">*</span></label>
                                <select id="select_ward" class="form-select <?php echo !empty($errors['ward']) ? 'is-invalid' : ''; ?>" disabled>
                                    <option value="">-- Chọn sau khi chọn Tỉnh --</option>
                                </select>
                                <?php if (!empty($errors['ward'])): ?><div class="invalid-feedback d-block"><?php echo e($errors['ward']); ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Số nhà, tên đường <span class="text-danger">*</span></label>
                                <input type="text" name="street_address" id="street_address"
                                       class="form-control <?php echo !empty($errors['street_address']) ? 'is-invalid' : ''; ?>"
                                       placeholder="VD: 123 Nguyễn Trãi"
                                       value="<?php echo e($_POST['street_address'] ?? ''); ?>">
                                <?php if (!empty($errors['street_address'])): ?><div class="invalid-feedback"><?php echo e($errors['street_address']); ?></div><?php endif; ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Ghi chú</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="VD: Giao giờ hành chính..."><?php echo e($_POST['note'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Phương thức vận chuyển -->
                    <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-truck me-2" style="color:var(--primary);"></i>Phương thức vận chuyển</h5>
                        <div class="d-flex flex-column gap-2">
                            <label class="d-flex align-items-center gap-3 p-3 rounded-3 border shipping-option <?php echo ($shippingMethod === 'standard') ? 'border-primary' : ''; ?>" style="cursor:pointer;">
                                <input type="radio" name="shipping_method" value="standard" class="form-check-input shipping-radio"
                                       <?php echo ($shippingMethod === 'standard') ? 'checked' : ''; ?>>
                                <i class="fas fa-box fa-lg" style="color:#6c757d;"></i>
                                <div class="flex-grow-1">
                                    <strong>Giao hàng tiêu chuẩn</strong>
                                    <br><small class="text-muted">Giao trong 3–5 ngày làm việc</small>
                                </div>
                                <span class="fw-bold text-success">Miễn phí</span>
                            </label>
                            <label class="d-flex align-items-center gap-3 p-3 rounded-3 border shipping-option <?php echo ($shippingMethod === 'express') ? 'border-primary' : ''; ?>" style="cursor:pointer;">
                                <input type="radio" name="shipping_method" value="express" class="form-check-input shipping-radio"
                                       <?php echo ($shippingMethod === 'express') ? 'checked' : ''; ?>>
                                <i class="fas fa-shipping-fast fa-lg" style="color:#f36811;"></i>
                                <div class="flex-grow-1">
                                    <strong>Giao hàng nhanh</strong>
                                    <br><small class="text-muted">Giao trong 1–2 ngày làm việc</small>
                                </div>
                                <span class="fw-bold" style="color:#f36811;"><?php echo formatPrice(SHIPPING_FEE_EXPRESS); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Phương thức thanh toán -->
                    <div class="bg-white rounded-3 shadow-sm p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-wallet me-2" style="color:var(--primary);"></i>Phương thức thanh toán</h5>
                        <div class="d-flex flex-column gap-2" id="payment-methods">

                            <label class="payment-option d-flex align-items-center gap-3 p-3 rounded-3 border" style="cursor:pointer;" id="label-cod">
                                <input type="radio" name="payment_method" value="cod" checked class="form-check-input" id="pm-cod">
                                <div class="d-flex align-items-center justify-content-center rounded-2" style="width:40px;height:40px;background:#e8f5e9;flex-shrink:0;">
                                    <i class="fas fa-money-bill-wave" style="color:#2e7d32;font-size:18px;"></i>
                                </div>
                                <div>
                                    <strong>Thanh toán khi nhận hàng (COD)</strong>
                                    <br><small class="text-muted">Trả tiền mặt khi nhận hàng, an toàn và tiện lợi</small>
                                </div>
                            </label>

                            <label class="payment-option d-flex align-items-center gap-3 p-3 rounded-3 border" style="cursor:pointer;" id="label-vnpay">
                                <input type="radio" name="payment_method" value="vnpay" class="form-check-input" id="pm-vnpay">
                                <div class="d-flex align-items-center justify-content-center rounded-2" style="width:40px;height:40px;background:#e3f2fd;flex-shrink:0;">
                                    <svg width="28" height="18" viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="120" height="40" rx="6" fill="#0066b3"/>
                                        <text x="10" y="28" font-family="Arial" font-weight="bold" font-size="20" fill="white">VNPay</text>
                                    </svg>
                                </div>
                                <div>
                                    <strong>VNPay</strong>
                                    <br><small class="text-muted">Thanh toán qua VNPay, ATM, Visa / Mastercard, QR Code</small>
                                </div>
                                <span class="ms-auto badge" style="background:#0066b3;font-size:10px;">Nhanh</span>
                            </label>

                            <label class="payment-option d-flex align-items-center gap-3 p-3 rounded-3 border" style="cursor:pointer;" id="label-momo">
                                <input type="radio" name="payment_method" value="momo" class="form-check-input" id="pm-momo">
                                <div class="d-flex align-items-center justify-content-center rounded-2" style="width:40px;height:40px;background:#fce4ec;flex-shrink:0;">
                                    <svg width="28" height="28" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="50" cy="50" r="48" fill="#a50064"/>
                                        <text x="50" y="65" text-anchor="middle" font-family="Arial" font-weight="bold" font-size="36" fill="white">M</text>
                                    </svg>
                                </div>
                                <div>
                                    <strong>Ví MoMo</strong>
                                    <br><small class="text-muted">Thanh toán qua ứng dụng MoMo, QR Code</small>
                                </div>
                                <span class="ms-auto badge" style="background:#a50064;font-size:10px;">Phổ biến</span>
                            </label>

                        </div>

                        <!-- Thông báo khi chọn online -->
                        <div id="online-payment-notice" class="alert alert-info mt-3 d-none" style="font-size:13px;">
                            <i class="fas fa-info-circle me-1"></i>
                            Bạn sẽ được chuyển hướng đến trang thanh toán an toàn sau khi đặt hàng.
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-5">
                    <div class="bg-white rounded-3 shadow-sm p-4" style="position:sticky;top:90px;">
                        <h5 class="fw-bold mb-3">Đơn hàng (<?php echo count($cartItems); ?> sản phẩm)</h5>
                        
                        <!-- Items -->
                        <?php foreach ($cartItems as $item): ?>
                        <div class="d-flex gap-3 mb-3 pb-3" style="border-bottom:1px solid var(--gray-200);">
                            <img src="<?php echo !empty($item['image_path']) ? PRODUCT_UPLOAD_URL . '/' . e($item['image_path']) : asset('images/default/no-product.png'); ?>" 
                                 style="width:55px;height:55px;object-fit:cover;border-radius:6px;">
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:13px;"><?php echo e(mb_substr($item['name'], 0, 40)); ?></div>
                                <small class="text-muted"><?php echo e($item['size']); ?> / <?php echo e($item['color']); ?> x<?php echo $item['quantity']; ?></small>
                            </div>
                            <div class="fw-bold" style="color:var(--primary);font-size:14px;white-space:nowrap;"><?php echo formatPrice($item['price'] * $item['quantity']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Voucher -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:14px;">Mã giảm giá</label>
                            <div class="input-group">
                                <input type="text" name="voucher_code" class="form-control <?php echo !empty($errors['voucher']) ? 'is-invalid' : ''; ?>" 
                                       placeholder="Nhập mã giảm giá" value="<?php echo e($_POST['voucher_code'] ?? ''); ?>">
                                <button type="submit" class="btn" style="background:var(--primary);color:white;">Áp dụng</button>
                                <?php if (!empty($errors['voucher'])): ?><div class="invalid-feedback"><?php echo e($errors['voucher']); ?></div><?php endif; ?>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Totals -->
                        <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                            <span>Tạm tính:</span>
                            <span><?php echo formatPrice($subtotal); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2" style="font-size:14px;">
                            <span>Phí vận chuyển:</span>
                            <span id="shipping-fee-display"><?php echo $shippingFee > 0 ? formatPrice($shippingFee) : '<span class="text-success">Miễn phí</span>'; ?></span>
                        </div>
                        <?php if ($voucherDiscount > 0): ?>
                        <div class="d-flex justify-content-between mb-2" style="font-size:14px;color:var(--success);">
                            <span>Giảm giá:</span>
                            <span>-<?php echo formatPrice($voucherDiscount); ?></span>
                        </div>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong style="font-size:16px;">Tổng cộng:</strong>
                            <strong id="total-display" style="color:var(--primary);font-size:22px;"><?php echo formatPrice($subtotal + $shippingFee - $voucherDiscount); ?></strong>
                        </div>
                        
                        <button type="submit" class="btn-wink w-100 justify-content-center" style="padding:14px;">
                            <i class="fas fa-check-circle me-1"></i> Đặt hàng
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initAddressSelector({
        defaultProvince: <?php echo json_encode($_POST['city'] ?? $user['province'] ?? $user['city'] ?? ''); ?>,
        defaultWard:     <?php echo json_encode($_POST['ward'] ?? ''); ?>
    });

    // Cập nhật phí ship + tổng khi đổi phương thức vận chuyển
    const subtotal       = <?php echo (int)$subtotal; ?>;
    const voucherDiscount= <?php echo (int)$voucherDiscount; ?>;
    const feeStandard    = <?php echo SHIPPING_FEE_STANDARD; ?>;
    const feeExpress     = <?php echo SHIPPING_FEE_EXPRESS; ?>;

    function formatVND(amount) {
        return amount.toLocaleString('vi-VN') + 'đ';
    }

    function updateShippingUI() {
        const selected = document.querySelector('input[name="shipping_method"]:checked');
        if (!selected) return;

        const fee   = selected.value === 'express' ? feeExpress : feeStandard;
        const total = Math.max(0, subtotal + fee - voucherDiscount);

        document.getElementById('shipping-fee-display').innerHTML =
            fee > 0 ? '<strong>' + formatVND(fee) + '</strong>' : '<span class="text-success">Miễn phí</span>';
        document.getElementById('total-display').textContent = formatVND(total);

        // Highlight option được chọn
        document.querySelectorAll('.shipping-option').forEach(el => el.classList.remove('border-primary', 'bg-light'));
        selected.closest('.shipping-option').classList.add('border-primary', 'bg-light');
    }

    document.querySelectorAll('.shipping-radio').forEach(r => r.addEventListener('change', updateShippingUI));
    updateShippingUI(); // chạy lần đầu

    // ── Payment method highlight & notice ──
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const submitBtn     = document.querySelector('button[type="submit"]');
    const notice        = document.getElementById('online-payment-notice');

    function updatePaymentUI() {
        const selected = document.querySelector('input[name="payment_method"]:checked');
        const val      = selected ? selected.value : 'cod';

        // Highlight tất cả options
        document.querySelectorAll('.payment-option').forEach(el => {
            el.classList.remove('border-primary', 'border-danger', 'bg-light');
        });

        const activeLabel = selected ? selected.closest('.payment-option') : null;
        if (activeLabel) activeLabel.classList.add('border-primary', 'bg-light');

        // Hiện thông báo + đổi text nút
        if (val === 'vnpay') {
            notice.classList.remove('d-none');
            notice.innerHTML = '<i class="fas fa-shield-alt me-1" style="color:#0066b3;"></i>'
                + ' Bạn sẽ được chuyển đến cổng thanh toán <strong>VNPay</strong> an toàn sau khi đặt hàng.';
            submitBtn.innerHTML = '<i class="fas fa-credit-card me-1"></i> Đặt hàng & Thanh toán VNPay';
        } else if (val === 'momo') {
            notice.classList.remove('d-none');
            notice.innerHTML = '<i class="fas fa-shield-alt me-1" style="color:#a50064;"></i>'
                + ' Bạn sẽ được chuyển đến ví điện tử <strong>MoMo</strong> an toàn sau khi đặt hàng.';
            submitBtn.innerHTML = '<i class="fas fa-mobile-alt me-1"></i> Đặt hàng & Thanh toán MoMo';
        } else {
            notice.classList.add('d-none');
            submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Đặt hàng';
        }
    }

    paymentRadios.forEach(r => r.addEventListener('change', updatePaymentUI));
    updatePaymentUI(); // chạy lần đầu
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
