<?php
/**
 * Admin - Danh sách voucher
 */
$pageTitle = 'Quản lý voucher - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

$search      = $_GET['search']      ?? '';
$statusFilter = $_GET['status']     ?? '';
$typeFilter  = $_GET['type']        ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 15;

$where  = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(v.code LIKE ? OR v.name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (!empty($statusFilter)) {
    $where[] = "v.status = ?";
    $params[] = $statusFilter;
}
if (!empty($typeFilter)) {
    $where[] = "v.discount_type = ?";
    $params[] = $typeFilter;
}

$whereClause = implode(' AND ', $where);

// Đếm tổng
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM vouchers v WHERE $whereClause");
$stmtCount->execute($params);
$totalVouchers = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalVouchers / $perPage);
$offset = ($page - 1) * $perPage;

// Lấy voucher
$stmt = $pdo->prepare("
    SELECT v.*,
           (SELECT COUNT(*) FROM orders o WHERE o.voucher_id = v.id) as order_count
    FROM vouchers v
    WHERE $whereClause
    ORDER BY v.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$vouchers = $stmt->fetchAll();

$now = new DateTime();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="fas fa-tags mr-2 text-primary"></i>
                    Quản lý voucher
                    <span class="badge badge-primary ml-2" style="font-size:14px;"><?php echo $totalVouchers; ?></span>
                </h1>
            </div>
            <div class="col-sm-6">
                <a href="<?php echo url('admin/vouchers/add.php'); ?>" class="btn btn-primary float-sm-right">
                    <i class="fas fa-plus mr-1"></i> Thêm voucher
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Thống kê nhanh -->
        <?php
        $statsStmt = $pdo->query("
            SELECT
                COUNT(*) as total,
                SUM(status = 'active') as active_count,
                SUM(status = 'inactive') as inactive_count,
                SUM(status = 'active' AND NOW() BETWEEN start_date AND end_date) as valid_now,
                SUM(status = 'active' AND end_date < NOW()) as expired
            FROM vouchers
        ");
        $stats = $statsStmt->fetch();
        ?>
        <div class="row mb-3">
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-info">
                    <div class="inner"><h3><?php echo $stats['total']; ?></h3><p>Tổng voucher</p></div>
                    <div class="icon"><i class="fas fa-tags"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-success">
                    <div class="inner"><h3><?php echo $stats['valid_now']; ?></h3><p>Đang hiệu lực</p></div>
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-warning">
                    <div class="inner"><h3><?php echo $stats['inactive_count']; ?></h3><p>Tạm dừng</p></div>
                    <div class="icon"><i class="fas fa-pause-circle"></i></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-danger">
                    <div class="inner"><h3><?php echo $stats['expired']; ?></h3><p>Đã hết hạn</p></div>
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <input type="text" name="search" class="form-control form-control-sm"
                           style="max-width:220px;" placeholder="Mã hoặc tên voucher..."
                           value="<?php echo e($search); ?>">

                    <select name="type" class="form-control form-control-sm" style="max-width:160px;">
                        <option value="">-- Loại giảm --</option>
                        <option value="percentage" <?php echo $typeFilter === 'percentage' ? 'selected' : ''; ?>>Phần trăm (%)</option>
                        <option value="fixed"      <?php echo $typeFilter === 'fixed'      ? 'selected' : ''; ?>>Số tiền cố định</option>
                    </select>

                    <select name="status" class="form-control form-control-sm" style="max-width:140px;">
                        <option value="">-- Trạng thái --</option>
                        <option value="active"   <?php echo $statusFilter === 'active'   ? 'selected' : ''; ?>>Kích hoạt</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Tạm dừng</option>
                    </select>

                    <button class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i>Lọc</button>

                    <?php if (!empty($search) || !empty($statusFilter) || !empty($typeFilter)): ?>
                        <a href="<?php echo url('admin/vouchers/list.php'); ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-times mr-1"></i>Xóa lọc
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th width="40">#</th>
                                <th>Mã voucher</th>
                                <th>Tên chương trình</th>
                                <th>Loại giảm</th>
                                <th>Giá trị</th>
                                <th>Đơn tối thiểu</th>
                                <th>Đã dùng</th>
                                <th>Thời gian</th>
                                <th>Trạng thái</th>
                                <th width="120">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($vouchers)): ?>
                                <?php foreach ($vouchers as $index => $v): ?>
                                <?php
                                    $startDate = new DateTime($v['start_date']);
                                    $endDate   = new DateTime($v['end_date']);
                                    $isExpired = $endDate < $now;
                                    $isNotStarted = $startDate > $now;
                                    $isActive = ($v['status'] === 'active') && !$isExpired && !$isNotStarted;
                                    $isFull = ($v['usage_limit'] > 0 && $v['used_count'] >= $v['usage_limit']);

                                    if ($v['status'] === 'inactive') {
                                        $statusBadge = '<span class="badge badge-secondary">Tạm dừng</span>';
                                    } elseif ($isExpired) {
                                        $statusBadge = '<span class="badge badge-danger">Hết hạn</span>';
                                    } elseif ($isNotStarted) {
                                        $statusBadge = '<span class="badge badge-info">Chưa bắt đầu</span>';
                                    } elseif ($isFull) {
                                        $statusBadge = '<span class="badge badge-warning">Hết lượt</span>';
                                    } else {
                                        $statusBadge = '<span class="badge badge-success">Hiệu lực</span>';
                                    }
                                    $usagePercent = ($v['usage_limit'] > 0) ? round($v['used_count'] / $v['usage_limit'] * 100) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td>
                                        <code style="font-size:13px;background:#fff3e0;color:#e65c00;padding:3px 8px;border-radius:4px;font-weight:600;letter-spacing:1px;">
                                            <?php echo e($v['code']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span title="<?php echo e($v['name']); ?>">
                                            <?php echo e(mb_substr($v['name'], 0, 40)); ?>
                                        </span>
                                        <?php if ($v['order_count'] > 0): ?>
                                            <br><small class="text-muted"><i class="fas fa-shopping-cart mr-1"></i><?php echo $v['order_count']; ?> đơn hàng đã dùng</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($v['discount_type'] === 'percentage'): ?>
                                            <span class="badge badge-info"><i class="fas fa-percent mr-1"></i>Phần trăm</span>
                                        <?php else: ?>
                                            <span class="badge badge-primary"><i class="fas fa-money-bill-alt mr-1"></i>Cố định</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color:#f36811;">
                                            <?php if ($v['discount_type'] === 'percentage'): ?>
                                                <?php echo $v['discount_value']; ?>%
                                                <?php if ($v['max_discount']): ?>
                                                    <br><small class="text-muted">Tối đa: <?php echo formatPrice($v['max_discount']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo formatPrice($v['discount_value']); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $v['min_order_amount'] > 0 ? formatPrice($v['min_order_amount']) : '<span class="text-muted">Không giới hạn</span>'; ?></td>
                                    <td>
                                        <?php if ($v['usage_limit'] > 0): ?>
                                            <div style="min-width:90px;">
                                                <small><?php echo $v['used_count']; ?>/<?php echo $v['usage_limit']; ?></small>
                                                <div class="progress mt-1" style="height:5px;">
                                                    <div class="progress-bar <?php echo $usagePercent >= 100 ? 'bg-danger' : ($usagePercent >= 70 ? 'bg-warning' : 'bg-success'); ?>"
                                                         style="width:<?php echo min($usagePercent, 100); ?>%"></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo $v['used_count']; ?> lần</span>
                                            <br><small class="text-muted">Không giới hạn</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="far fa-calendar-alt mr-1 text-success"></i><?php echo date('d/m/Y', strtotime($v['start_date'])); ?>
                                            <br>
                                            <i class="far fa-calendar-times mr-1 text-danger"></i><?php echo date('d/m/Y', strtotime($v['end_date'])); ?>
                                        </small>
                                    </td>
                                    <td><?php echo $statusBadge; ?></td>
                                    <td>
                                        <a href="<?php echo url('admin/vouchers/edit.php?id=' . $v['id']); ?>"
                                           class="btn btn-sm btn-info" title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo url('admin/vouchers/delete.php?id=' . $v['id']); ?>"
                                           class="btn btn-sm btn-danger" title="Xóa"
                                           onclick="return confirm('Xóa voucher \'<?php echo e(addslashes($v['code'])); ?>\'?\n⚠️ Lưu ý: Không thể xóa voucher đã được dùng trong đơn hàng.')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">
                                        <i class="fas fa-tags fa-3x mb-3 d-block" style="opacity:.3;"></i>
                                        Không tìm thấy voucher nào
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $baseUrl = url('admin/vouchers/list.php') . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
                echo renderPagination($page, $totalPages, $baseUrl);
                ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
