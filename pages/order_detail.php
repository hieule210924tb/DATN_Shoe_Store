<?php
/** Trang chi tiết đơn hàng - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId <= 0) {
    setFlashMessage('error', 'Đơn hàng không tồn tại.');
    redirect(url('pages/orders.php'));
}

// Lấy thông tin đơn hàng
$stmt = $pdo->prepare('
    SELECT o.*, v.code as voucher_code, v.discount_type, v.discount_value
    FROM orders o
    LEFT JOIN vouchers v ON o.voucher_id = v.id
    WHERE o.id = ? AND o.user_id = ?
');
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('error', 'Đơn hàng không tồn tại.');
    redirect(url('pages/orders.php'));
}

// Lấy chi tiết sản phẩm trong đơn hàng
$stmtItems = $pdo->prepare('
    SELECT oi.*, p.thumbnail as product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
');
$stmtItems->execute([$orderId]);
$orderItems = $stmtItems->fetchAll();

$pageTitle = 'Chi tiết đơn hàng ' . e($order['order_code']) . ' - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-file-invoice me-2"></i>Chi tiết đơn hàng <?php echo e($order['order_code']); ?></h4>
            <span class="badge <?php echo getOrderStatusBadge($order['status']); ?> fs-6">
                <?php echo getOrderStatusText($order['status']); ?>
            </span>
        </div>
        
        <div class="row g-4">
            <!-- Order Info -->
            <div class="col-lg-8">
                <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-info-circle me-2"></i>Thông tin đơn hàng</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Mã đơn hàng:</small>
                            <div class="fw-bold"><?php echo e($order['order_code']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Ngày đặt:</small>
                            <div><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Phương thức thanh toán:</small>
                            <div><?php echo getPaymentMethodText($order['payment_method']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Trạng thái thanh toán:</small>
                            <div>
                                <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo getPaymentStatusText($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Info -->
                <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-truck me-2"></i>Thông tin giao hàng</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Người nhận:</small>
                            <div class="fw-bold"><?php echo e($order['receiver_name']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Số điện thoại:</small>
                            <div><?php echo e($order['receiver_phone']); ?></div>
                        </div>
                        <div class="col-12 mb-3">
                            <small class="text-muted">Địa chỉ:</small>
                            <div><?php echo e($order['receiver_address']); ?></div>
                            <div class="text-muted"><?php echo e($order['receiver_ward'] . ', ' . $order['receiver_province']); ?></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">Phương thức vận chuyển:</small>
                            <div><?php echo $order['shipping_method'] === 'express' ? 'Giao hàng hỏa tốc' : 'Giao hàng tiêu chuẩn'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="bg-white rounded-3 shadow-sm p-4">
                    <h6 class="fw-bold mb-3"><i class="fas fa-shopping-bag me-2"></i>Sản phẩm đã đặt</h6>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Giá</th>
                                    <th>Số lượng</th>
                                    <th>Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?php echo !empty($item['product_image']) ? PRODUCT_UPLOAD_URL . '/' . e($item['product_image']) : asset('images/default/no-product.png'); ?>" 
                                                 style="width:60px;height:60px;object-fit:cover;border-radius:8px;">
                                            <div>
                                                <div class="fw-bold"><?php echo e($item['product_name']); ?></div>
                                                <small class="text-muted">Size: <?php echo e($item['size']); ?> | Màu: <?php echo e($item['color']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo formatPrice($item['price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="fw-bold"><?php echo formatPrice($item['subtotal']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="bg-white rounded-3 shadow-sm p-4" style="position:sticky;top:90px;">
                    <h6 class="fw-bold mb-3"><i class="fas fa-calculator me-2"></i>Tóm tắt đơn hàng</h6>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính:</span>
                        <span><?php echo formatPrice($order['subtotal']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển:</span>
                        <span><?php echo formatPrice($order['shipping_fee']); ?></span>
                    </div>
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Giảm giá:</span>
                        <span>-<?php echo formatPrice($order['discount_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['voucher_code'])): ?>
                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <small>Mã voucher:</small>
                        <small><?php echo e($order['voucher_code']); ?></small>
                    </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Tổng cộng:</strong>
                        <strong style="color:var(--primary);font-size:20px;"><?php echo formatPrice($order['total_amount']); ?></strong>
                    </div>
                    
                    <?php if (!empty($order['note'])): ?>
                    <div class="mt-3 pt-3" style="border-top:1px solid var(--gray-200);">
                        <small class="text-muted">Ghi chú:</small>
                        <div class="mt-1"><?php echo nl2br(e($order['note'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($order['status'] === 'pending'): ?>
                    <a href="javascript:void(0)" onclick="if(confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) cancelOrder(<?php echo $order['id']; ?>)" 
                       class="btn btn-outline-danger w-100 mt-3">
                        <i class="fas fa-times me-1"></i>Hủy đơn hàng
                    </a>
                    <?php endif; ?>
                    
                    <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink-outline w-100 justify-content-center mt-2">
                        <i class="fas fa-shopping-bag me-1"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function cancelOrder(orderId) {
    const formData = new FormData();
    formData.append('action', 'cancel');
    formData.append('order_id', orderId);
    
    fetch(BASE_URL + '/ajax/order_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Đã hủy đơn hàng thành công.');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', data.message || 'Không thể hủy đơn hàng.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
    });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
