<?php
/**
 * Admin Dashboard - WinK Shoe Store
 */
$pageTitle = 'Dashboard - WinK Admin';
include __DIR__ . '/includes/admin_header.php';

$pdo = getDBConnection();

// Tổng doanh thu (đơn delivered + paid)
$revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'delivered' AND payment_status = 'paid'")->fetchColumn();

// Tổng đơn hàng
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

// Tổng khách hàng
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

// Tổng sản phẩm
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();

// Đơn hàng mới (chờ xác nhận)
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

// Sản phẩm sắp hết hàng
$lowStock = $pdo->query("SELECT COUNT(DISTINCT product_id) FROM product_variants WHERE stock_quantity > 0 AND stock_quantity <= 5")->fetchColumn();

// Đơn hàng gần đây
$recentOrders = $pdo->query("
    SELECT o.*, u.full_name 
    FROM orders o 
    INNER JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 8
")->fetchAll();

// Sản phẩm sắp hết hàng (chi tiết)
$lowStockProducts = $pdo->query("
    SELECT p.name, pv.size, pv.color, pv.stock_quantity
    FROM product_variants pv
    INNER JOIN products p ON pv.product_id = p.id
    WHERE pv.stock_quantity > 0 AND pv.stock_quantity <= 5
    ORDER BY pv.stock_quantity ASC
    LIMIT 8
")->fetchAll();
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Info boxes -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo formatPrice($revenue); ?></h3>
                        <p>Tổng doanh thu</p>
                    </div>
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <a href="<?php echo url('admin/statistics/index.php'); ?>" class="small-box-footer">Xem thống kê <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $totalOrders; ?></h3>
                        <p>Tổng đơn hàng</p>
                    </div>
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                    <a href="<?php echo url('admin/orders/list.php'); ?>" class="small-box-footer">Xem đơn hàng <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $totalCustomers; ?></h3>
                        <p>Khách hàng</p>
                    </div>
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <a href="<?php echo url('admin/customers/list.php'); ?>" class="small-box-footer">Xem khách hàng <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo $totalProducts; ?></h3>
                        <p>Sản phẩm</p>
                    </div>
                    <div class="icon"><i class="fas fa-shoe-prints"></i></div>
                    <a href="<?php echo url('admin/products/list.php'); ?>" class="small-box-footer">Xem sản phẩm <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>
        
        <!-- Alert boxes -->
        <div class="row">
            <?php if ($pendingOrders > 0): ?>
            <div class="col-md-6">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong><?php echo $pendingOrders; ?> đơn hàng</strong> đang chờ xác nhận.
                    <a href="<?php echo url('admin/orders/list.php?status=pending'); ?>" class="alert-link">Xem ngay</a>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($lowStock > 0): ?>
            <div class="col-md-6">
                <div class="alert alert-danger">
                    <i class="fas fa-box mr-2"></i>
                    <strong><?php echo $lowStock; ?> sản phẩm</strong> sắp hết hàng.
                    <a href="<?php echo url('admin/inventory/list.php'); ?>" class="alert-link">Xem ngay</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <!-- Đơn hàng gần đây -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-shopping-cart mr-2"></i>Đơn hàng gần đây</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đặt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentOrders)): ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><a href="<?php echo url('admin/orders/detail.php?id=' . $order['id']); ?>"><?php echo e($order['order_code']); ?></a></td>
                                        <td><?php echo e($order['full_name']); ?></td>
                                        <td class="font-weight-bold" style="color:#f36811;"><?php echo formatPrice($order['total_amount']); ?></td>
                                        <td><span class="badge <?php echo getOrderStatusBadge($order['status']); ?>"><?php echo getOrderStatusText($order['status']); ?></span></td>
                                        <td><?php echo formatDate($order['created_at'], 'd/m/Y'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Chưa có đơn hàng nào</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Sản phẩm sắp hết -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2 text-warning"></i>Sắp hết hàng</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (!empty($lowStockProducts)): ?>
                                <?php foreach ($lowStockProducts as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong style="font-size:13px;"><?php echo e($item['name']); ?></strong>
                                        <br><small class="text-muted">Size: <?php echo e($item['size']); ?> | <?php echo e($item['color']); ?></small>
                                    </div>
                                    <span class="badge badge-danger"><?php echo $item['stock_quantity']; ?></span>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-center text-muted py-4">Không có sản phẩm sắp hết</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
