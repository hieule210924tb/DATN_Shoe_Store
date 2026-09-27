<?php
/** Trang lịch sử đơn hàng - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Lấy danh sách đơn hàng
$stmt = $pdo->prepare('
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
');
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$pageTitle = 'Đơn hàng của tôi - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?> 

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <h4 class="fw-bold mb-4"><i class="fas fa-box me-2"></i>Đơn hàng của tôi (<?php echo count($orders); ?>)</h4>
        
        <?php if (!empty($orders)): ?>
        <div class="row">
            <div class="col-12">
                <div class="bg-white rounded-3 shadow-sm overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background:var(--gray-100);">
                                <tr>
                                    <th>Mã đơn hàng</th>
                                    <th>Ngày đặt</th>
                                    <th>Người nhận</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Thanh toán</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary"><?php echo e($order['order_code']); ?></span>
                                    </td>
                                    <td><?php echo formatDate($order['created_at'], 'd/m/Y H:i'); ?></td>
                                    <td>
                                        <div><?php echo e($order['receiver_name']); ?></div>
                                        <small class="text-muted"><?php echo e($order['receiver_phone']); ?></small>
                                    </td>
                                    <td class="fw-bold" style="color:var(--primary);"><?php echo formatPrice($order['total_amount']); ?></td>
                                    <td>
                                        <span class="badge <?php echo getOrderStatusBadge($order['status']); ?>">
                                            <?php echo getOrderStatusText($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                            <?php echo getPaymentStatusText($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo url('pages/order_detail.php?id=' . $order['id']); ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>Chi tiết
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h5>Chưa có đơn hàng nào</h5>
                <p class="text-muted">Bạn chưa đặt đơn hàng nào. Hãy mua sắm ngay!</p>
                <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink mt-2">Mua sắm ngay</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
