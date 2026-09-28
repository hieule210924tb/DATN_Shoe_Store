<?php
/**
 * Admin - Quản lý Đơn hàng (Danh sách)
 * WinK Shoe Store
 */
$pageTitle = 'Quản lý Đơn hàng - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

// ── Tham số lọc / tìm kiếm / phân trang ─────────────────────────
$search         = trim($_GET['search']  ?? '');
$statusFilter   = $_GET['status']       ?? '';
$payFilter      = $_GET['payment']      ?? '';
$perPage        = 15;
$currentPage    = max(1, (int)($_GET['page'] ?? 1));
$offset         = ($currentPage - 1) * $perPage;

// ── WHERE clause ─────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = "(o.order_code LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR o.receiver_phone LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like;
    $params[] = $like; $params[] = $like;
}
if ($statusFilter !== '') {
    $where[]  = "o.status = ?";
    $params[] = $statusFilter;
}
if ($payFilter !== '') {
    $where[]  = "o.payment_status = ?";
    $params[] = $payFilter;
}

$whereSQL = implode(' AND ', $where);

// ── Đếm tổng ─────────────────────────────────────────────────────
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE $whereSQL
");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages  = max(1, ceil($totalOrders / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset      = ($currentPage - 1) * $perPage;

// ── Lấy danh sách ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.id, o.order_code, o.total_amount, o.status, o.payment_method,
           o.payment_status, o.shipping_method, o.created_at,
           u.full_name, u.email
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE $whereSQL
    ORDER BY o.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// ── Thống kê nhanh ───────────────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                          AS total,
        SUM(CASE WHEN status='pending'   THEN 1 ELSE 0 END)              AS pending,
        SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END)              AS confirmed,
        SUM(CASE WHEN status='shipping'  THEN 1 ELSE 0 END)              AS shipping,
        SUM(CASE WHEN status='delivered' THEN 1 ELSE 0 END)              AS delivered,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END)              AS cancelled,
        COALESCE(SUM(CASE WHEN status='delivered' AND payment_status='paid'
                          THEN total_amount ELSE 0 END), 0)              AS revenue
    FROM orders
")->fetch();

$baseUrl = url('admin/orders/list.php') . '?search=' . urlencode($search)
         . '&status=' . urlencode($statusFilter)
         . '&payment=' . urlencode($payFilter);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-shopping-cart mr-2" style="color:#f36811;"></i>Quản lý Đơn hàng</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Đơn hàng</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Thống kê nhanh -->
        <div class="row mb-3">
            <?php
            $statCards = [
                ['label'=>'Tổng đơn',      'val'=>$stats['total'],     'icon'=>'fa-list',         'color'=>'bg-secondary', 'key'=>''],
                ['label'=>'Chờ xác nhận',  'val'=>$stats['pending'],   'icon'=>'fa-clock',        'color'=>'bg-warning',   'key'=>'pending'],
                ['label'=>'Đã xác nhận',   'val'=>$stats['confirmed'], 'icon'=>'fa-check',        'color'=>'bg-info',      'key'=>'confirmed'],
                ['label'=>'Đang giao',     'val'=>$stats['shipping'],  'icon'=>'fa-truck',        'color'=>'bg-primary',   'key'=>'shipping'],
                ['label'=>'Đã giao',       'val'=>$stats['delivered'], 'icon'=>'fa-check-circle', 'color'=>'bg-success',   'key'=>'delivered'],
                ['label'=>'Đã huỷ',        'val'=>$stats['cancelled'], 'icon'=>'fa-times-circle', 'color'=>'bg-danger',    'key'=>'cancelled'],
            ];
            foreach ($statCards as $card):
                $cardUrl = url('admin/orders/list.php') . ($card['key'] !== '' ? '?status=' . $card['key'] : '');
            ?>
            <div class="col-6 col-md-2 mb-2">
                <a href="<?php echo $cardUrl; ?>" style="text-decoration:none;">
                    <div class="small-box <?php echo $card['color']; ?> shadow-sm mb-0">
                        <div class="inner">
                            <h4><?php echo number_format($card['val']); ?></h4>
                            <p style="font-size:12px;"><?php echo $card['label']; ?></p>
                        </div>
                        <div class="icon"><i class="fas <?php echo $card['icon']; ?>"></i></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Card chính -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">
                    <i class="fas fa-list mr-2"></i>Danh sách đơn hàng
                    <span class="badge badge-secondary ml-2"><?php echo number_format($totalOrders); ?></span>
                </h3>
            </div>

            <!-- Bộ lọc -->
            <div class="card-body border-bottom pb-3">
                <form method="GET" action="<?php echo url('admin/orders/list.php'); ?>" class="form-inline flex-wrap gap-2">
                    <div class="input-group mr-2 mb-2" style="min-width:260px;">
                        <input type="text" name="search" id="search_input" class="form-control"
                               placeholder="Mã đơn, tên KH, email, SĐT..."
                               value="<?php echo e($search); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <select name="status" id="status_filter" class="form-control mr-2 mb-2" onchange="this.form.submit()">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="pending"   <?php echo $statusFilter==='pending'   ? 'selected':''; ?>>Chờ xác nhận</option>
                        <option value="confirmed" <?php echo $statusFilter==='confirmed' ? 'selected':''; ?>>Đã xác nhận</option>
                        <option value="shipping"  <?php echo $statusFilter==='shipping'  ? 'selected':''; ?>>Đang giao</option>
                        <option value="delivered" <?php echo $statusFilter==='delivered' ? 'selected':''; ?>>Đã giao</option>
                        <option value="cancelled" <?php echo $statusFilter==='cancelled' ? 'selected':''; ?>>Đã huỷ</option>
                    </select>

                    <select name="payment" id="payment_filter" class="form-control mr-2 mb-2" onchange="this.form.submit()">
                        <option value="">-- Thanh toán --</option>
                        <option value="unpaid"   <?php echo $payFilter==='unpaid'   ? 'selected':''; ?>>Chưa thanh toán</option>
                        <option value="paid"     <?php echo $payFilter==='paid'     ? 'selected':''; ?>>Đã thanh toán</option>
                        <option value="refunded" <?php echo $payFilter==='refunded' ? 'selected':''; ?>>Đã hoàn tiền</option>
                    </select>

                    <?php if ($search !== '' || $statusFilter !== '' || $payFilter !== ''): ?>
                    <a href="<?php echo url('admin/orders/list.php'); ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-times mr-1"></i>Xoá bộ lọc
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if (!empty($orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th class="text-right">Tổng tiền</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center">Thanh toán</th>
                                <th class="text-center">PT giao hàng</th>
                                <th class="text-center">Ngày đặt</th>
                                <th class="text-center" style="width:80px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo url('admin/orders/detail.php?id=' . $order['id']); ?>"
                                       class="font-weight-bold" style="color:#f36811;">
                                        <?php echo e($order['order_code']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-size:14px;"><?php echo e($order['full_name']); ?></div>
                                    <small class="text-muted"><?php echo e($order['email']); ?></small>
                                </td>
                                <td class="text-right font-weight-bold">
                                    <?php echo formatPrice($order['total_amount']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo getOrderStatusBadge($order['status']); ?> px-2 py-1">
                                        <?php echo getOrderStatusText($order['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $payClass = [
                                        'unpaid'   => 'badge-secondary',
                                        'paid'     => 'badge-success',
                                        'refunded' => 'badge-warning',
                                    ][$order['payment_status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?php echo $payClass; ?> px-2 py-1">
                                        <?php echo getPaymentStatusText($order['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light border">
                                        <?php echo $order['shipping_method'] === 'express' ? '⚡ Nhanh' : '📦 Thường'; ?>
                                    </span>
                                </td>
                                <td class="text-center text-muted" style="font-size:13px;">
                                    <?php echo formatDate($order['created_at'], 'd/m/Y'); ?>
                                    <br><small><?php echo formatDate($order['created_at'], 'H:i'); ?></small>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo url('admin/orders/detail.php?id=' . $order['id']); ?>"
                                       class="btn btn-sm btn-info" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted">
                            Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalOrders); ?>
                            trong <?php echo number_format($totalOrders); ?> đơn hàng
                        </small>
                        <?php echo renderPagination($currentPage, $totalPages, $baseUrl); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Không tìm thấy đơn hàng nào</h5>
                    <?php if ($search !== '' || $statusFilter !== '' || $payFilter !== ''): ?>
                        <a href="<?php echo url('admin/orders/list.php'); ?>" class="btn btn-sm btn-outline-primary mt-2">Xoá bộ lọc</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>