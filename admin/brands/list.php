<?php
/**
 * Admin - Danh sách thương hiệu
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Quản lý thương hiệu - WinK Admin';
$pdo = getDBConnection();

$search = $_GET['search'] ?? '';
$whereClauses = ['b.status != ?'];
$params = ['deleted']; // chỉ ẩn khi status = 'deleted', active + inactive đều hiện

// Thực ra ta lọc: chỉ hiện active, không hiện inactive (đã xóa mềm)
$whereClauses = [];
$params = [];
if (!empty($search)) {
    $whereClauses[] = 'b.name LIKE ?';
    $params[] = '%' . $search . '%';
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT COUNT(*) FROM products WHERE brand_id = b.id) AS product_count
    FROM brands b
    $where
    ORDER BY b.name ASC
");
$stmt->execute($params);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Quản lý Thương hiệu</h1></div>
            <div class="col-sm-6">
                <a href="<?php echo url('admin/brands/add.php'); ?>" class="btn btn-primary float-sm-right">
                    <i class="fas fa-plus mr-1"></i> Thêm thương hiệu
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="d-flex" style="max-width:300px;">
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Tìm thương hiệu..." value="<?php echo e($search); ?>">
                    <button class="btn btn-primary btn-sm ml-2"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Tên thương hiệu</th>
                            <th>Slug</th>
                            <th>Số SP</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th width="120">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($brands)): ?>
                            <?php foreach ($brands as $index => $b): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td class="font-weight-bold"><?php echo e($b['name']); ?></td>
                                <td><code><?php echo e($b['slug']); ?></code></td>
                                <td><?php echo $b['product_count']; ?></td>
                                <td>
                                    <span class="badge <?php echo $b['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $b['status'] === 'active' ? 'Hoạt động' : 'Ẩn'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($b['created_at'], 'd/m/Y'); ?></td>
                                <td>
                                    <a href="<?php echo url('admin/brands/edit.php?id=' . $b['id']); ?>"
                                       class="btn btn-sm btn-info" title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($b['product_count'] == 0): ?>
                                        <a href="<?php echo url('admin/brands/delete.php?id=' . $b['id']); ?>"
                                           class="btn btn-sm btn-danger" title="Xóa"
                                           onclick="return confirm('Bạn có chắc chắn muốn xóa thương hiệu này?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled
                                                title="Không thể xóa - có sản phẩm liên quan">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Không có thương hiệu nào</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
