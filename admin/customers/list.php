<?php
/**
 * Admin - Quản lý Khách hàng (Danh sách)
 * WinK Shoe Store
 */
$pageTitle = 'Quản lý Khách hàng - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();

// ── Tham số lọc / tìm kiếm / phân trang ─────────────────────────
$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status']  ?? '';   // '' | 'active' | 'locked'
$perPage = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($currentPage - 1) * $perPage;

// ── Xây WHERE clause ────────────────────────────────────────────
$where  = ["u.role = 'user'"];
$params = [];

if ($search !== '') {
    $where[]  = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like     = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '') {
    $where[]  = "u.status = ?";
    $params[] = $status;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Tổng số khách hàng (phân trang) ─────────────────────────────
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSQL");
$countStmt->execute($params);
$totalCustomers = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalCustomers / $perPage));
$currentPage = min($currentPage, $totalPages);

// ── Lấy danh sách khách hàng ────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, u.status, u.created_at,
           COUNT(DISTINCT o.id)             AS total_orders,
           COALESCE(SUM(CASE WHEN o.status = 'delivered' AND o.payment_status = 'paid'
                             THEN o.total_amount ELSE 0 END), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    $whereSQL
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

// ── Thống kê nhanh ──────────────────────────────────────────────
$totalAll    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalActive = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user' AND status='active'")->fetchColumn();
$totalLocked = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user' AND status='locked'")->fetchColumn();

// URL cơ sở cho phân trang
$baseUrl = url('admin/customers/list.php') . '?search=' . urlencode($search) . '&status=' . urlencode($status);
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><i class="fas fa-users mr-2" style="color:#f36811;"></i>Quản lý Khách hàng</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Khách hàng</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">

        <!-- Thống kê nhanh -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Tổng khách hàng</span>
                        <span class="info-box-number"><?php echo number_format($totalAll); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-success"><i class="fas fa-user-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Đang hoạt động</span>
                        <span class="info-box-number"><?php echo number_format($totalActive); ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-danger"><i class="fas fa-user-slash"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Đã bị khoá</span>
                        <span class="info-box-number"><?php echo number_format($totalLocked); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card chính -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">
                    <i class="fas fa-list mr-2"></i>Danh sách Khách hàng
                    <span class="badge badge-secondary ml-2"><?php echo number_format($totalCustomers); ?></span>
                </h3>
            </div>

            <!-- Bộ lọc & tìm kiếm -->
            <div class="card-body border-bottom pb-3">
                <form method="GET" action="<?php echo url('admin/customers/list.php'); ?>" class="form-inline flex-wrap gap-2">
                    <div class="input-group mr-2 mb-2" style="min-width:260px;">
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Tên, email, số điện thoại..."
                               value="<?php echo e($search); ?>">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <select name="status" id="status_filter" class="form-control mr-2 mb-2" onchange="this.form.submit()">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="active"  <?php echo $status === 'active'  ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="banned"  <?php echo $status === 'banned'  ? 'selected' : ''; ?>>Đã bị khoá</option>
                    </select>
                    <?php if ($search !== '' || $status !== ''): ?>
                    <a href="<?php echo url('admin/customers/list.php'); ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-times mr-1"></i>Xoá bộ lọc
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card-body p-0">
                <?php if (!empty($customers)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background:#f8f9fa;">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Khách hàng</th>
                                <th>Liên hệ</th>
                                <th class="text-center">Đơn hàng</th>
                                <th class="text-right">Tổng chi tiêu</th>
                                <th class="text-center">Ngày đăng ký</th>
                                <th class="text-center">Trạng thái</th>
                                <th class="text-center" style="width:130px;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $i => $c): ?>
                            <tr>
                                <td class="text-muted"><?php echo $offset + $i + 1; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="customer-avatar mr-3" style="
                                            width:40px;height:40px;border-radius:50%;
                                            background:linear-gradient(135deg,#f36811,#f7a459);
                                            display:flex;align-items:center;justify-content:center;
                                            color:#fff;font-weight:700;font-size:16px;flex-shrink:0;">
                                            <?php echo mb_strtoupper(mb_substr($c['full_name'], 0, 1, 'UTF-8'), 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <strong style="font-size:14px;"><?php echo e($c['full_name']); ?></strong>
                                            <br><small class="text-muted">ID: #<?php echo $c['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><i class="fas fa-envelope fa-xs text-muted mr-1"></i><?php echo e($c['email']); ?></div>
                                    <?php if ($c['phone']): ?>
                                    <div><i class="fas fa-phone fa-xs text-muted mr-1"></i><?php echo e($c['phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-light border"><?php echo (int)$c['total_orders']; ?> đơn</span>
                                </td>
                                <td class="text-right font-weight-bold" style="color:#f36811;">
                                    <?php echo formatPrice($c['total_spent']); ?>
                                </td>
                                <td class="text-center text-muted" style="font-size:13px;">
                                    <?php echo formatDate($c['created_at'], 'd/m/Y'); ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge badge-success px-2 py-1">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1">Bị khoá</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?php echo url('admin/customers/detail.php?id=' . $c['id']); ?>"
                                       class="btn btn-sm btn-info" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($c['status'] === 'active'): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-warning btn-toggle-status"
                                            data-id="<?php echo $c['id']; ?>"
                                            data-action="lock"
                                            data-name="<?php echo e($c['full_name']); ?>"
                                            title="Khoá tài khoản">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php else: ?>
                                    <button type="button"
                                            class="btn btn-sm btn-success btn-toggle-status"
                                            data-id="<?php echo $c['id']; ?>"
                                            data-action="unlock"
                                            data-name="<?php echo e($c['full_name']); ?>"
                                            title="Mở khoá tài khoản">
                                        <i class="fas fa-unlock"></i>
                                    </button>
                                    <?php endif; ?>
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
                            Hiển thị <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalCustomers); ?>
                            trong <?php echo number_format($totalCustomers); ?> khách hàng
                        </small>
                        <?php echo renderPagination($currentPage, $totalPages, $baseUrl); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Không tìm thấy khách hàng nào</h5>
                    <?php if ($search !== '' || $status !== ''): ?>
                        <a href="<?php echo url('admin/customers/list.php'); ?>" class="btn btn-sm btn-outline-primary mt-2">Xoá bộ lọc</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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