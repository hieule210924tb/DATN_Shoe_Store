<?php
/**
 * Admin - Chi tiết Khách hàng
 * WinK Shoe Store
 */
$pageTitle = 'Chi tiết Khách hàng - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    setFlashMessage('error', 'ID khách hàng không hợp lệ.');
    redirect(url('admin/customers/list.php'));
}

// Lấy thông tin khách hàng
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    setFlashMessage('error', 'Không tìm thấy khách hàng.');
    redirect(url('admin/customers/list.php'));
}

// Thống kê đơn hàng
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                    AS total_orders,
        COALESCE(SUM(total_amount), 0)                             AS total_spent,
        COALESCE(SUM(CASE WHEN status='delivered' AND payment_status='paid' THEN total_amount ELSE 0 END), 0) AS total_paid,
        SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END)     AS pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END)     AS confirmed,
        SUM(CASE WHEN status = 'shipping'  THEN 1 ELSE 0 END)     AS shipping,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END)     AS delivered,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)     AS cancelled
    FROM orders
    WHERE user_id = ?
");
$statsStmt->execute([$customerId]);
$stats = $statsStmt->fetch();

// Lịch sử đơn hàng (20 đơn gần nhất)
$ordersStmt = $pdo->prepare("
    SELECT id, order_code, total_amount, status, payment_method, payment_status, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$ordersStmt->execute([$customerId]);
$orders = $ordersStmt->fetchAll();

// Địa chỉ lấy trực tiếp từ bảng users (address, ward, province)
$hasAddress = !empty($customer['address']) || !empty($customer['ward']) || !empty($customer['province']);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-user mr-2" style="color:#f36811;"></i>Chi tiết Khách hàng</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/customers/list.php'); ?>">Khách hàng</a></li>
                    <li class="breadcrumb-item active"><?php echo e($customer['full_name']); ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">

            <!-- Cột trái: Thông tin cá nhân -->
            <div class="col-lg-4">

                <!-- Profile card -->
                <div class="card shadow-sm text-center mb-3">
                    <div class="card-body pt-4">
                        <div style="
                            width:80px;height:80px;border-radius:50%;
                            background:linear-gradient(135deg,#f36811,#f7a459);
                            display:flex;align-items:center;justify-content:center;
                            color:#fff;font-weight:700;font-size:32px;
                            margin:0 auto 12px;">
                            <?php echo mb_strtoupper(mb_substr($customer['full_name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                        </div>
                        <h4 class="mb-1 font-weight-bold"><?php echo e($customer['full_name']); ?></h4>
                        <p class="text-muted mb-2" style="font-size:13px;">ID: #<?php echo $customer['id']; ?></p>
                        <?php if ($customer['status'] === 'active'): ?>
                            <span class="badge badge-success px-3 py-2">Đang hoạt động</span>
                        <?php else: ?>
                            <span class="badge badge-danger px-3 py-2">Bị khoá</span>
                        <?php endif; ?>

                        <div class="mt-3 d-flex justify-content-center gap-2">
                            <?php if ($customer['status'] === 'active'): ?>
                            <button class="btn btn-sm btn-warning btn-toggle-status"
                                    data-id="<?php echo $customer['id']; ?>" data-action="lock"
                                    data-name="<?php echo e($customer['full_name']); ?>">
                                <i class="fas fa-ban mr-1"></i>Khoá tài khoản
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-success btn-toggle-status"
                                    data-id="<?php echo $customer['id']; ?>" data-action="unlock"
                                    data-name="<?php echo e($customer['full_name']); ?>">
                                <i class="fas fa-unlock mr-1"></i>Mở khoá
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Thông tin liên hệ -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-address-card mr-2"></i>Thông tin liên hệ</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted"><i class="fas fa-envelope mr-2"></i>Email</span>
                                <span><?php echo e($customer['email']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted"><i class="fas fa-phone mr-2"></i>Điện thoại</span>
                                <span><?php echo $customer['phone'] ? e($customer['phone']) : '<span class="text-muted">—</span>'; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span class="text-muted"><i class="fas fa-calendar mr-2"></i>Đăng ký</span>
                                <span><?php echo formatDate($customer['created_at'], 'd/m/Y'); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Địa chỉ -->
                <?php if ($hasAddress): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-map-marker-alt mr-2"></i>Địa chỉ</h3></div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item" style="font-size:13px;">
                                <strong><?php echo e($customer['full_name']); ?></strong><br>
                                <span class="text-muted">
                                    <?php
                                    $parts = array_filter([
                                        $customer['address']  ?? '',
                                        $customer['ward']     ?? '',
                                        $customer['province'] ?? '',
                                    ]);
                                    echo e(implode(', ', $parts));
                                    ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Cột phải: Thống kê + Đơn hàng -->
            <div class="col-lg-8">

                <!-- Thống kê -->
                <div class="row mb-3">
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-info mb-0 shadow-sm">
                            <div class="inner">
                                <h3><?php echo (int)$stats['total_orders']; ?></h3>
                                <p>Tổng đơn</p>
                            </div>
                            <div class="icon"><i class="fas fa-shopping-bag"></i></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-success mb-0 shadow-sm">
                            <div class="inner">
                                <h3><?php echo (int)$stats['delivered']; ?></h3>
                                <p>Đã giao</p>
                            </div>
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small-box bg-danger mb-0 shadow-sm">
                            <div class="inner">
                                <h3><?php echo (int)$stats['cancelled']; ?></h3>
                                <p>Đã huỷ</p>
                            </div>
                            <div class="icon"><i class="fas fa-times-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small-box mb-0 shadow-sm" style="background:#f36811;color:#fff;">
                            <div class="inner">
                                <h3 style="font-size:18px;"><?php echo formatPrice($stats['total_paid']); ?></h3>
                                <p>Đã chi tiêu</p>
                            </div>
                            <div class="icon"><i class="fas fa-wallet"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Lịch sử đơn hàng -->
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-history mr-2"></i>Lịch sử đơn hàng</h3>
                        <span class="badge badge-secondary ml-2"><?php echo (int)$stats['total_orders']; ?> đơn</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead style="background:#f8f9fa;">
                                    <tr>
                                        <th>Mã đơn</th>
                                        <th class="text-right">Tổng tiền</th>
                                        <th class="text-center">Trạng thái</th>
                                        <th class="text-center">Thanh toán</th>
                                        <th class="text-center">Ngày đặt</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo e($order['order_code']); ?></strong></td>
                                        <td class="text-right font-weight-bold" style="color:#f36811;">
                                            <?php echo formatPrice($order['total_amount']); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?php echo getOrderStatusBadge($order['status']); ?>">
                                                <?php echo getOrderStatusText($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $payBadge = $order['payment_status'] === 'paid' ? 'badge-success' : ($order['payment_status'] === 'refunded' ? 'badge-warning' : 'badge-secondary');
                                            ?>
                                            <span class="badge <?php echo $payBadge; ?>">
                                                <?php echo getPaymentStatusText($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center text-muted" style="font-size:13px;">
                                            <?php echo formatDate($order['created_at'], 'd/m/Y'); ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo url('admin/orders/detail.php?id=' . $order['id']); ?>"
                                               class="btn btn-xs btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                            <p>Khách hàng chưa có đơn hàng nào.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
document.querySelectorAll('.btn-toggle-status').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id     = this.dataset.id;
        const action = this.dataset.action;
        const name   = this.dataset.name;
        const label  = action === 'lock' ? 'khoá' : 'mở khoá';

        if (!confirm('Bạn có chắc muốn ' + label + ' tài khoản của "' + name + '"?')) return;

        const formData = new FormData();
        formData.append('id', id);
        formData.append('action', action);

        fetch('<?php echo url('admin/customers/toggle_status.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Lỗi: ' + (data.message || 'Không thể thực hiện.'));
            }
        })
        .catch(() => alert('Đã xảy ra lỗi kết nối.'));
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
