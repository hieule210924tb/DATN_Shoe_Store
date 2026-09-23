<?php
/**
 * Admin - Danh sách danh mục
 */
$pageTitle = 'Quản lý danh mục - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();
$search = $_GET['search'] ?? '';

$where = '';
$params = [];
if (!empty($search)) {
    $where = "WHERE name LIKE ?";
    $params[] = '%' . $search . '%';
}

$categories = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c $where ORDER BY c.created_at DESC");
$categories->execute($params);
$categories = $categories->fetchAll();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Quản lý danh mục</h1></div>
            <div class="col-sm-6">
                <a href="<?php echo url('admin/categories/add.php'); ?>" class="btn btn-primary float-sm-right">
                    <i class="fas fa-plus mr-1"></i> Thêm danh mục
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Tìm danh mục..." value="<?php echo e($search); ?>">
                    <button class="btn btn-primary btn-sm ml-2"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Tên danh mục</th>
                            <th>Slug</th>
                            <th>Số SP</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th width="120">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $index => $cat): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td class="font-weight-bold"><?php echo e($cat['name']); ?></td>
                                <td><code><?php echo e($cat['slug']); ?></code></td>
                                <td><?php echo $cat['product_count']; ?></td>
                                <td>
                                    <span class="badge <?php echo $cat['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                        <?php echo $cat['status'] === 'active' ? 'Hoạt động' : 'Ẩn'; ?>
                                    </span>
                                </td>
                                <td><?php echo formatDate($cat['created_at'], 'd/m/Y'); ?></td>
                                <td>
                                    <a href="<?php echo url('admin/categories/edit.php?id=' . $cat['id']); ?>" class="btn btn-sm btn-info" title="Sửa"><i class="fas fa-edit"></i></a>
                                    <?php if ($cat['product_count'] == 0): ?>
                                        <a href="<?php echo url('admin/categories/delete.php?id=' . $cat['id']); ?>" class="btn btn-sm btn-danger" title="Xóa" onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')"><i class="fas fa-trash"></i></a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Không thể xóa - có sản phẩm liên quan"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Không có danh mục nào</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
