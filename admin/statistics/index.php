<?php
/**
 * Admin - Trang Thống kê / Báo cáo
 * WinK Shoe Store
 */
$pageTitle = 'Thống kê - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

// ══════════════════════════════════════════════════════════════════
// BỘ LỌC THỜI GIAN
// ══════════════════════════════════════════════════════════════════
$period = $_GET['period'] ?? 'month'; // today | week | month | year | custom
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

switch ($period) {
    case 'today':
        $dateFrom = date('Y-m-d');
        $dateTo   = date('Y-m-d');
        break;
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo   = date('Y-m-d');
        break;
    case 'month':
        $dateFrom = date('Y-m-01');
        $dateTo   = date('Y-m-d');
        break;
    case 'year':
        $dateFrom = date('Y-01-01');
        $dateTo   = date('Y-m-d');
        break;
    case 'custom':
        // giữ nguyên giá trị từ GET
        break;
    default:
        $period   = 'month';
        $dateFrom = date('Y-m-01');
        $dateTo   = date('Y-m-d');
}

$tsFrom = $dateFrom . ' 00:00:00';
$tsTo   = $dateTo   . ' 23:59:59';

// ══════════════════════════════════════════════════════════════════
// KPI TỔNG QUAN (trong khoảng thời gian)
// ══════════════════════════════════════════════════════════════════
$kpi = $pdo->prepare("
    SELECT
        COUNT(*)                                                        AS total_orders,
        SUM(CASE WHEN status='delivered' AND payment_status='paid'
                 THEN total_amount ELSE 0 END)                          AS revenue,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END)            AS cancelled,
        COUNT(DISTINCT user_id)                                         AS unique_customers,
        COALESCE(AVG(CASE WHEN status='delivered' AND payment_status='paid'
                          THEN total_amount END), 0)                    AS avg_order_value
    FROM orders
    WHERE created_at BETWEEN ? AND ?
");
$kpi->execute([$tsFrom, $tsTo]);
$kpi = $kpi->fetch();

// KPI kỳ trước (để so sánh)
$daysDiff = (strtotime($dateTo) - strtotime($dateFrom)) / 86400 + 1;
$prevTo   = date('Y-m-d', strtotime($dateFrom) - 86400);
$prevFrom = date('Y-m-d', strtotime($prevTo) - ($daysDiff - 1) * 86400);

$kpiPrev = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status='delivered' AND payment_status='paid'
                          THEN total_amount ELSE 0 END), 0) AS revenue,
        COUNT(*)                                             AS total_orders
    FROM orders
    WHERE created_at BETWEEN ? AND ?
");
$kpiPrev->execute([$prevFrom . ' 00:00:00', $prevTo . ' 23:59:59']);
$kpiPrev = $kpiPrev->fetch();

// Tính % thay đổi
function calcChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round(($current - $previous) / $previous * 100, 1);
}

$revenueChange = calcChange($kpi['revenue'], $kpiPrev['revenue']);
$ordersChange  = calcChange($kpi['total_orders'], $kpiPrev['total_orders']);

// ══════════════════════════════════════════════════════════════════
// BIỂU ĐỒ: DOANH THU THEO THỜI GIAN
// ══════════════════════════════════════════════════════════════════
// Nhóm theo ngày nếu <= 31 ngày, theo tuần nếu <= 90 ngày, theo tháng nếu > 90
$diffDays = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;

if ($diffDays <= 31) {
    $groupFormat = '%Y-%m-%d';
    $labelFormat = 'd/m';
    $phpFormat   = 'd/m';
} elseif ($diffDays <= 90) {
    $groupFormat = '%Y-%u';      // ISO week
    $labelFormat = 'Tuần %u/%Y';
    $phpFormat   = null;         // handled specially
} else {
    $groupFormat = '%Y-%m';
    $labelFormat = '%m/%Y';
    $phpFormat   = 'm/Y';
}

$chartStmt = $pdo->prepare("
    SELECT
        DATE_FORMAT(created_at, '$groupFormat')                           AS period_key,
        DATE_FORMAT(MIN(created_at), '%Y-%m-%d')                         AS period_start,
        COALESCE(SUM(CASE WHEN status='delivered' AND payment_status='paid'
                          THEN total_amount ELSE 0 END), 0)              AS revenue,
        COUNT(*)                                                          AS orders
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY period_key
    ORDER BY period_key
");
$chartStmt->execute([$tsFrom, $tsTo]);
$chartRows = $chartStmt->fetchAll();

$chartLabels  = [];
$chartRevenue = [];
$chartOrders  = [];
foreach ($chartRows as $row) {
    if ($phpFormat) {
        $chartLabels[] = date($phpFormat, strtotime($row['period_start']));
    } else {
        $chartLabels[] = 'T' . date('W', strtotime($row['period_start'])) . '/' . date('Y', strtotime($row['period_start']));
    }
    $chartRevenue[] = (float)$row['revenue'];
    $chartOrders[]  = (int)$row['orders'];
}

// ══════════════════════════════════════════════════════════════════
// BIỂU ĐỒ: TRẠNG THÁI ĐƠN HÀNG (Donut)
// ══════════════════════════════════════════════════════════════════
$statusData = $pdo->prepare("
    SELECT status, COUNT(*) as cnt
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");
$statusData->execute([$tsFrom, $tsTo]);
$statusRows = $statusData->fetchAll(PDO::FETCH_KEY_PAIR);

$statusLabels = ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao', 'Đã giao', 'Đã hủy'];
$statusKeys   = ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
$statusColors = ['#ffc107', '#17a2b8', '#007bff', '#28a745', '#dc3545'];
$statusCounts = array_map(fn($k) => (int)($statusRows[$k] ?? 0), $statusKeys);

// ══════════════════════════════════════════════════════════════════
// BIỂU ĐỒ: PHƯƠNG THỨC THANH TOÁN (Pie)
// ══════════════════════════════════════════════════════════════════
$paymentData = $pdo->prepare("
    SELECT payment_method, COUNT(*) as cnt
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY payment_method
");
$paymentData->execute([$tsFrom, $tsTo]);
$paymentRows = $paymentData->fetchAll(PDO::FETCH_KEY_PAIR);

$payLabels = ['COD', 'VNPay', 'MoMo'];
$payKeys   = ['cod', 'vnpay', 'momo'];
$payColors = ['#6c757d', '#0066cc', '#a50064'];
$payCounts = array_map(fn($k) => (int)($paymentRows[$k] ?? 0), $payKeys);

// ══════════════════════════════════════════════════════════════════
// TOP 10 SẢN PHẨM BÁN CHẠY
// ══════════════════════════════════════════════════════════════════
$topProducts = $pdo->prepare("
    SELECT p.id, p.name, p.thumbnail, p.slug,
           SUM(oi.quantity)  AS total_qty,
           SUM(oi.subtotal)  AS total_revenue,
           COUNT(DISTINCT oi.order_id) AS order_count
    FROM order_items oi
    INNER JOIN products p ON oi.product_id = p.id
    INNER JOIN orders   o ON oi.order_id   = o.id
    WHERE o.created_at BETWEEN ? AND ?
      AND o.status NOT IN ('cancelled')
    GROUP BY p.id, p.name, p.thumbnail, p.slug
    ORDER BY total_qty DESC
    LIMIT 10
");
$topProducts->execute([$tsFrom, $tsTo]);
$topProducts = $topProducts->fetchAll();

// ══════════════════════════════════════════════════════════════════
// TOP 10 DANH MỤC
// ══════════════════════════════════════════════════════════════════
$topCategories = $pdo->prepare("
    SELECT c.name, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS total_revenue
    FROM order_items oi
    INNER JOIN products  p ON oi.product_id = p.id
    INNER JOIN categories c ON p.category_id = c.id
    INNER JOIN orders    o ON oi.order_id    = o.id
    WHERE o.created_at BETWEEN ? AND ?
      AND o.status NOT IN ('cancelled')
    GROUP BY c.id, c.name
    ORDER BY total_revenue DESC
    LIMIT 10
");
$topCategories->execute([$tsFrom, $tsTo]);
$topCategoryRows = $topCategories->fetchAll();

$catLabels   = array_column($topCategoryRows, 'name');
$catRevenues = array_map(fn($r) => (float)$r['total_revenue'], $topCategoryRows);

// ══════════════════════════════════════════════════════════════════
// TOP 10 KHÁCH HÀNG
// ══════════════════════════════════════════════════════════════════
$topCustomers = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.avatar,
           COUNT(o.id)          AS order_count,
           SUM(o.total_amount)  AS total_spent
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    WHERE o.created_at BETWEEN ? AND ?
      AND o.status NOT IN ('cancelled')
    GROUP BY u.id, u.full_name, u.email, u.avatar
    ORDER BY total_spent DESC
    LIMIT 10
");
$topCustomers->execute([$tsFrom, $tsTo]);
$topCustomers = $topCustomers->fetchAll();

// ══════════════════════════════════════════════════════════════════
// TỔNG HỢP KHO / TỒN KHO
// ══════════════════════════════════════════════════════════════════
$inventoryStats = $pdo->query("
    SELECT
        COUNT(DISTINCT product_id)                                          AS total_products,
        SUM(stock_quantity)                                                 AS total_stock,
        SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END)                AS out_of_stock,
        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= 5 THEN 1 ELSE 0 END) AS low_stock
    FROM product_variants
")->fetch();

// Doanh thu theo phương thức thanh toán
$revenueByPayment = $pdo->prepare("
    SELECT payment_method,
           SUM(CASE WHEN status='delivered' AND payment_status='paid' THEN total_amount ELSE 0 END) AS revenue
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY payment_method
");
$revenueByPayment->execute([$tsFrom, $tsTo]);
$revByPay = $revenueByPayment->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fa-solid fa-chart-simple mr-2" style="color:#f36811;"></i>Thống kê & Báo cáo
                </h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Thống kê</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid">

<!-- ════════════════════════════════════════════════════════
     BỘ LỌC THỜI GIAN
════════════════════════════════════════════════════════ -->
<div class="card shadow-sm mb-4" style="border-radius:12px;">
    <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2" id="filterForm">
            <div class="btn-group mr-3 mb-2">
                <?php
                $periods = [
                    'today' => 'Hôm nay',
                    'week'  => 'Tuần này',
                    'month' => 'Tháng này',
                    'year'  => 'Năm nay',
                    'custom'=> 'Tùy chỉnh',
                ];
                foreach ($periods as $key => $label):
                ?>
                <a href="?period=<?php echo $key; ?>"
                   class="btn btn-sm <?php echo $period === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    <?php echo $label; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($period === 'custom'): ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?php echo e($dateFrom); ?>" style="width:145px;">
                <span class="mx-1">→</span>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?php echo e($dateTo); ?>" style="width:145px;">
                <button type="submit" class="btn btn-sm btn-primary ml-2">
                    <i class="fas fa-search mr-1"></i>Lọc
                </button>
            </div>
            <?php endif; ?>

            <div class="ml-auto mb-2">
                <span class="text-muted" style="font-size:13px;">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <?php echo date('d/m/Y', strtotime($dateFrom)); ?>
                    <?php if ($dateFrom !== $dateTo): ?>
                        → <?php echo date('d/m/Y', strtotime($dateTo)); ?>
                    <?php endif; ?>
                </span>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     KPI CARDS
════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <!-- Doanh thu -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#f36811,#ff8c42);">
            <div class="kpi-icon"><i class="fas fa-coins"></i></div>
            <div class="kpi-label">Doanh thu</div>
            <div class="kpi-value"><?php echo formatPrice($kpi['revenue']); ?></div>
            <div class="kpi-change <?php echo $revenueChange >= 0 ? 'up' : 'down'; ?>">
                <i class="fas fa-arrow-<?php echo $revenueChange >= 0 ? 'up' : 'down'; ?>"></i>
                <?php echo abs($revenueChange); ?>% so với kỳ trước
            </div>
        </div>
    </div>
    <!-- Đơn hàng -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#17a2b8,#36c5dd);">
            <div class="kpi-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="kpi-label">Đơn hàng</div>
            <div class="kpi-value"><?php echo number_format($kpi['total_orders']); ?></div>
            <div class="kpi-change <?php echo $ordersChange >= 0 ? 'up' : 'down'; ?>">
                <i class="fas fa-arrow-<?php echo $ordersChange >= 0 ? 'up' : 'down'; ?>"></i>
                <?php echo abs($ordersChange); ?>% so với kỳ trước
            </div>
        </div>
    </div>
    <!-- Khách hàng riêng biệt -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#28a745,#48c774);">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-label">Khách đặt hàng</div>
            <div class="kpi-value"><?php echo number_format($kpi['unique_customers']); ?></div>
            <div class="kpi-change neutral">
                <i class="fas fa-users"></i> khách hàng riêng biệt
            </div>
        </div>
    </div>
    <!-- Giá trị TB -->
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#9b6ee8);">
            <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
            <div class="kpi-label">Giá trị đơn TB</div>
            <div class="kpi-value"><?php echo formatPrice(round((float)($kpi['avg_order_value'] ?? 0))); ?></div>
            <div class="kpi-change neutral">
                <i class="fas fa-ban"></i>
                <?php echo number_format((int)($kpi['cancelled'] ?? 0)); ?> đơn đã hủy
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ROW 1: Biểu đồ doanh thu theo thời gian
════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-chart-bar mr-2" style="color:#f36811;"></i>Doanh thu & Đơn hàng theo thời gian
                </h6>
                <div class="btn-group btn-group-sm" id="chartToggle">
                    <button class="btn btn-primary active" onclick="switchChart('both')" id="btnBoth">Cả hai</button>
                    <button class="btn btn-outline-secondary" onclick="switchChart('revenue')" id="btnRevenue">Doanh thu</button>
                    <button class="btn btn-outline-secondary" onclick="switchChart('orders')" id="btnOrders">Đơn hàng</button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($chartRows)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-chart-bar fa-3x mb-3"></i>
                    <p>Không có dữ liệu trong khoảng thời gian này</p>
                </div>
                <?php else: ?>
                <canvas id="revenueChart" height="120"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Trạng thái đơn hàng -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-chart-bar mr-2" style="color:#17a2b8;"></i>Trạng thái đơn hàng
                </h6>
            </div>
            <div class="card-body">
                <?php $totalStatus = array_sum($statusCounts); ?>
                <?php if ($totalStatus > 0): ?>
                <canvas id="statusChart" height="200"></canvas>
                <div class="mt-3 w-100">
                    <?php foreach ($statusKeys as $idx => $sk): ?>
                    <?php if ($statusCounts[$idx] > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:13px;">
                        <span><span class="legend-dot" style="background:<?php echo $statusColors[$idx]; ?>;"></span><?php echo $statusLabels[$idx]; ?></span>
                        <span class="font-weight-bold"><?php echo number_format($statusCounts[$idx]); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-chart-bar fa-3x mb-2"></i><br>Không có dữ liệu</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ROW 2: Phương thức thanh toán + Danh mục
════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <!-- Phương thức thanh toán -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-credit-card mr-2" style="color:#6f42c1;"></i>Phương thức thanh toán
                </h6>
            </div>
            <div class="card-body">
                <?php $totalPay = array_sum($payCounts); ?>
                <?php if ($totalPay > 0): ?>
                <canvas id="paymentChart" height="200"></canvas>
                <div class="mt-3">
                    <?php foreach ($payKeys as $idx => $pk): ?>
                    <?php $pct = $totalPay > 0 ? round($payCounts[$idx] / $totalPay * 100) : 0; ?>
                    <div class="d-flex justify-content-between align-items-center mb-2" style="font-size:13px;">
                        <span><span class="legend-dot" style="background:<?php echo $payColors[$idx]; ?>;"></span><?php echo $payLabels[$idx]; ?></span>
                        <div class="text-right">
                            <span class="font-weight-bold"><?php echo $payCounts[$idx]; ?></span>
                            <small class="text-muted ml-1">(<?php echo $pct; ?>%)</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-credit-card fa-3x mb-2"></i><br>Không có dữ liệu</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Doanh thu theo danh mục -->
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-chart-bar mr-2" style="color:#28a745;"></i>Doanh thu theo danh mục
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($topCategoryRows)): ?>
                <canvas id="categoryChart" height="120"></canvas>
                <?php else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-folder fa-3x mb-2"></i><br>Không có dữ liệu</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ROW 3: Top sản phẩm + Top khách hàng
════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <!-- Top sản phẩm bán chạy -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-trophy mr-2" style="color:#ffc107;"></i>Top 10 sản phẩm bán chạy
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($topProducts)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Sản phẩm</th>
                                <th class="text-center">SL bán</th>
                                <th class="text-right">Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $idx => $prod): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($idx < 3): ?>
                                    <span class="rank-badge rank-<?php echo $idx + 1; ?>"><?php echo $idx + 1; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size:13px;"><?php echo $idx + 1; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo !empty($prod['thumbnail']) ? PRODUCT_UPLOAD_URL . '/' . e($prod['thumbnail']) : asset('images/default/no-product.png'); ?>"
                                             style="width:38px;height:38px;border-radius:8px;object-fit:cover;" alt="">
                                        <div>
                                            <a href="<?php echo url('pages/product_detail.php?slug=' . e($prod['slug'])); ?>"
                                               target="_blank" style="font-size:13px;font-weight:600;color:#333;text-decoration:none;">
                                                <?php echo e(mb_strimwidth($prod['name'], 0, 40, '...')); ?>
                                            </a>
                                            <br>
                                            <small class="text-muted"><?php echo $prod['order_count']; ?> đơn hàng</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-info"><?php echo number_format($prod['total_qty']); ?></span>
                                </td>
                                <td class="text-right font-weight-bold" style="color:#f36811;">
                                    <?php echo formatPrice($prod['total_revenue']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-box-open fa-3x mb-2"></i><br>Không có dữ liệu
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top khách hàng -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-crown mr-2" style="color:#f36811;"></i>Top 10 khách hàng
                </h6>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($topCustomers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="width:36px;">#</th>
                                <th>Khách hàng</th>
                                <th class="text-center">Đơn</th>
                                <th class="text-right">Chi tiêu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topCustomers as $idx => $cust): ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($idx < 3): ?>
                                    <span class="rank-badge rank-<?php echo $idx + 1; ?>"><?php echo $idx + 1; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size:13px;"><?php echo $idx + 1; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo !empty($cust['avatar']) ? AVATAR_UPLOAD_URL . '/' . e($cust['avatar']) : asset('images/default/default-avatar.png'); ?>"
                                             style="width:32px;height:32px;border-radius:50%;object-fit:cover;" alt="">
                                        <div>
                                            <div style="font-size:13px;font-weight:600;"><?php echo e($cust['full_name']); ?></div>
                                            <small class="text-muted"><?php echo e(mb_strimwidth($cust['email'], 0, 25, '...')); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?php echo $cust['order_count']; ?></span>
                                </td>
                                <td class="text-right font-weight-bold" style="font-size:13px;color:#28a745;">
                                    <?php echo formatPrice($cust['total_spent']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-users fa-3x mb-2"></i><br>Không có dữ liệu
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════
     ROW 4: Tồn kho snapshot
════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm" style="border-radius:12px;">
            <div class="card-header">
                <h6 class="mb-0 font-weight-bold">
                    <i class="fas fa-warehouse mr-2" style="color:#6c757d;"></i>Tổng quan kho hàng (toàn thời gian)
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="inventory-stat-box" style="border-color:#f36811;">
                            <div class="inventory-stat-num" style="color:#f36811;"><?php echo number_format($inventoryStats['total_products']); ?></div>
                            <div class="inventory-stat-label">Sản phẩm đang quản lý</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="inventory-stat-box" style="border-color:#17a2b8;">
                            <div class="inventory-stat-num" style="color:#17a2b8;"><?php echo number_format($inventoryStats['total_stock']); ?></div>
                            <div class="inventory-stat-label">Tổng tồn kho (biến thể)</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="inventory-stat-box" style="border-color:#ffc107;">
                            <div class="inventory-stat-num" style="color:#ffc107;"><?php echo number_format($inventoryStats['low_stock']); ?></div>
                            <div class="inventory-stat-label">Biến thể sắp hết (≤5)</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="inventory-stat-box" style="border-color:#dc3545;">
                            <div class="inventory-stat-num" style="color:#dc3545;"><?php echo number_format($inventoryStats['out_of_stock']); ?></div>
                            <div class="inventory-stat-label">Biến thể hết hàng</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /.container-fluid -->
</section>

<!-- ════════════════════════════════════════════════════════
     CSS
════════════════════════════════════════════════════════ -->
<style>
/* ── KPI Cards ── */
.kpi-card {
    border-radius: 14px;
    padding: 22px 20px;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,.13);
    min-height: 130px;
}
.kpi-card::after {
    content: '';
    position: absolute;
    top: -20px; right: -20px;
    width: 100px; height: 100px;
    background: rgba(255,255,255,.12);
    border-radius: 50%;
}
.kpi-icon {
    font-size: 28px;
    opacity: .75;
    margin-bottom: 6px;
}
.kpi-label {
    font-size: 13px;
    opacity: .85;
    font-weight: 500;
}
.kpi-value {
    font-size: 22px;
    font-weight: 700;
    margin: 4px 0 6px;
    letter-spacing: -.5px;
}
.kpi-change {
    font-size: 12px;
    opacity: .9;
}
.kpi-change.up   { color: #d4ffd4; }
.kpi-change.down { color: #ffd4d4; }
.kpi-change.neutral { opacity: .7; }

/* ── Legend dot ── */
.legend-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-right: 6px;
}

/* ── Rank badge ── */
.rank-badge {
    display: inline-flex;
    width: 24px; height: 24px;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}
.rank-1 { background: #FFD700; }
.rank-2 { background: #C0C0C0; }
.rank-3 { background: #CD7F32; }

/* ── Inventory stat box ── */
.inventory-stat-box {
    border: 2px solid;
    border-radius: 12px;
    padding: 18px 10px;
}
.inventory-stat-num {
    font-size: 32px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 6px;
}
.inventory-stat-label {
    font-size: 13px;
    color: #666;
}

/* ── Dark mode adjustments ── */
body.dark-mode .kpi-card::after { background: rgba(255,255,255,.06); }
body.dark-mode .inventory-stat-label { color: #aaa; }
body.dark-mode .inventory-stat-box { background: #1e2235; }
</style>

<!-- ════════════════════════════════════════════════════════
     CHARTS (Chart.js)
════════════════════════════════════════════════════════ -->
<script>
// ── Data from PHP ─────────────────────────────────────────
const chartLabels  = <?php echo json_encode($chartLabels); ?>;
const chartRevenue = <?php echo json_encode($chartRevenue); ?>;
const chartOrders  = <?php echo json_encode($chartOrders); ?>;

const statusLabels = <?php echo json_encode($statusLabels); ?>;
const statusCounts = <?php echo json_encode($statusCounts); ?>;
const statusColors = <?php echo json_encode($statusColors); ?>;

const payLabels = <?php echo json_encode($payLabels); ?>;
const payCounts = <?php echo json_encode($payCounts); ?>;
const payColors = <?php echo json_encode($payColors); ?>;

const catLabels   = <?php echo json_encode($catLabels); ?>;
const catRevenues = <?php echo json_encode($catRevenues); ?>;



// ── 1. Revenue & Orders Line/Bar Chart ───────────────────
let revenueChart;
function buildRevenueChart(mode) {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    if (revenueChart) revenueChart.destroy();

    const datasets = [];
    if (mode === 'revenue' || mode === 'both') {
        datasets.push({
            label: 'Doanh thu (đ)',
            data: chartRevenue,
            type: 'bar',
            backgroundColor: 'rgba(243,104,17,.75)',
            borderColor: '#f36811',
            borderWidth: 1.5,
            borderRadius: 5,
            yAxisID: 'yRevenue',
            order: 1,
        });
    }
    if (mode === 'orders' || mode === 'both') {
        datasets.push({
            label: 'Đơn hàng',
            data: chartOrders,
            type: 'bar',
            backgroundColor: 'rgba(23,162,184,.55)',
            borderColor: '#17a2b8',
            borderWidth: 1.5,
            borderRadius: 5,
            yAxisID: 'yOrders',
            order: 2,
        });
    }

    revenueChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: chartLabels, datasets },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            if (ctx.dataset.label.includes('đ')) {
                                return ' ' + ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString('vi-VN') + 'đ';
                            }
                            return ' ' + ctx.dataset.label + ': ' + ctx.raw;
                        }
                    }
                }
            },
            scales: {
                yRevenue: {
                    type: 'linear',
                    position: 'left',
                    display: mode !== 'orders',
                    ticks: {
                        callback: v => {
                            if (v >= 1000000) return (v/1000000).toFixed(1) + 'M';
                            if (v >= 1000)    return (v/1000).toFixed(0) + 'K';
                            return v;
                        }
                    }
                },
                yOrders: {
                    type: 'linear',
                    position: 'right',
                    display: mode !== 'revenue',
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
}

function switchChart(mode) {
    ['btnBoth','btnRevenue','btnOrders'].forEach(id => {
        const btn = document.getElementById(id);
        btn.classList.remove('btn-primary','btn-outline-secondary');
        btn.classList.add('btn-outline-secondary');
    });
    const map = { both:'btnBoth', revenue:'btnRevenue', orders:'btnOrders' };
    const btn = document.getElementById(map[mode]);
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-primary');
    buildRevenueChart(mode);
}

// ── 2. Status Bar Chart ───────────────────────────────────
function buildStatusChart() {
    const ctx = document.getElementById('statusChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: statusLabels,
            datasets: [{
                label: 'Số lượng đơn',
                data: statusCounts,
                backgroundColor: statusColors,
                borderRadius: 5,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + c.raw + ' đơn' } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}

// ── 3. Payment Bar Chart ──────────────────────────────────
function buildPaymentChart() {
    const ctx = document.getElementById('paymentChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: payLabels,
            datasets: [{
                label: 'Số lượng đơn',
                data: payCounts,
                backgroundColor: payColors,
                borderRadius: 5,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + c.raw + ' đơn' } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });
}

// ── 4. Category Bar ───────────────────────────────────────
function buildCategoryChart() {
    const ctx = document.getElementById('categoryChart');
    if (!ctx) return;
    const colors = ['#f36811','#17a2b8','#28a745','#6f42c1','#fd7e14','#e83e8c','#20c997','#ffc107','#dc3545','#6c757d'];
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: catLabels,
            datasets: [{
                label: 'Doanh thu (đ)',
                data: catRevenues,
                backgroundColor: colors,
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: c => ' ' + Number(c.raw).toLocaleString('vi-VN') + 'đ'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: v => {
                            if (v >= 1000000) return (v/1000000).toFixed(1) + 'M';
                            if (v >= 1000)    return (v/1000).toFixed(0) + 'K';
                            return v;
                        }
                    }
                }
            }
        }
    });
}

// ── Init all charts ─────────────────────────────────────────────
// Dùng window.onload để đảm bảo Chart.js (load ở footer) đã sẵn sàng
window.addEventListener('load', () => {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js chưa được tải!');
        return;
    }
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size   = 12;
    buildRevenueChart('both');
    buildStatusChart();
    buildPaymentChart();
    buildCategoryChart();
});
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>