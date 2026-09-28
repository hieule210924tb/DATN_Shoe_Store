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

$shippingFee = SHIPPING_FEE_EXPRESS;
if ($subtotal >= 300000) {
    $shippingFee = 0;  // Miễn phí ship cho đơn >= 300K
}

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
    $paymentMethod = $_POST['payment_method'] ?? 'cod';
    $voucherCode = trim($_POST['voucher_code'] ?? '');
    $note = trim($_POST['note'] ?? '');

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

            // VNPay/MoMo đã thanh toán online → xác nhận luôn
            // COD → chờ xác nhận
            $initialStatus        = in_array($paymentMethod, ['vnpay', 'momo']) ? 'confirmed' : 'pending';
            $initialPaymentStatus = in_array($paymentMethod, ['vnpay', 'momo']) ? 'paid'      : 'unpaid';

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

            $successMsg = in_array($paymentMethod, ['vnpay', 'momo'])
                ? "Đặt hàng thành công! Đơn hàng $orderCode đã được xác nhận (đã thanh toán online)."
                : "Đặt hàng thành công! Mã đơn hàng: $orderCode. Chúng tôi sẽ xác nhận sớm nhất.";
            setFlashMessage('success', $successMsg);
            redirect(url('pages/order_detail.php?id=' . $orderId));
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors['general'] = 'Có lỗi xảy ra. Vui lòng thử lại.';
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
                    
                    <!-- Phương thức thanh toán -->
                    <div class="bg-white rounded-3 shadow-sm p-4">
                        <h5 class="fw-bold mb-3"><i class="fas fa-wallet me-2" style="color:var(--primary);"></i>Phương thức thanh toán</h5>
                        <div class="d-flex flex-column gap-2">
                            <label class="d-flex align-items-center gap-3 p-3 rounded-3 border" style="cursor:pointer;">
                                <input type="radio" name="payment_method" value="cod" checked class="form-check-input">
                                <i class="fas fa-money-bill-wave fa-lg" style="color:#28a745;"></i>
                                <div>
                                    <strong>Thanh toán khi nhận hàng (COD)</strong>
                                    <br><small class="text-muted">Thanh toán bằng tiền mặt khi nhận hàng</small>
                                </div>
                            </label>
                            <label class="d-flex align-items-center gap-3 p-3 rounded-3 border" style="cursor:pointer;">
                                <input type="radio" name="payment_method" value="vnpay" class="form-check-input">
                                <i class="fas fa-credit-card fa-lg" style="color:#0066b3;"></i>
                                <div>
                                    <strong>VNPay</strong>
                                    <br><small class="text-muted">Thanh toán qua ví VNPay / ATM / Visa</small>
                                </div>
                            </label>
                            <label class="d-flex align-items-center gap-3 p-3 rounded-3 border" style="cursor:pointer;">
                                <input type="radio" name="payment_method" value="momo" class="form-check-input">
                                <i class="fas fa-mobile-alt fa-lg" style="color:#a50064;"></i>
                                <div>
                                    <strong>MoMo</strong>
                                    <br><small class="text-muted">Thanh toán qua ví điện tử MoMo</small>
                                </div>
                            </label>
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
                            <span><?php echo $shippingFee > 0 ? formatPrice($shippingFee) : '<span class="text-success">Miễn phí</span>'; ?></span>
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
                            <strong style="color:var(--primary);font-size:22px;"><?php echo formatPrice($subtotal + $shippingFee - $voucherDiscount); ?></strong>
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
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
