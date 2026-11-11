<?php
/**
 * Admin - Thêm thương hiệu
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Thêm thương hiệu - WinK Admin';
$pdo = getDBConnection();
$errors = [];
$brand = ['name' => '', 'slug' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name'] ?? '');
    $slug   = trim($_POST['slug'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($name)) {
        $errors['name'] = 'Vui lòng nhập tên thương hiệu.';
    } else {
        $stmtCheck = $pdo->prepare('SELECT id FROM brands WHERE name = ?');
        $stmtCheck->execute([$name]);
        if ($stmtCheck->fetch()) {
            $errors['name'] = 'Tên thương hiệu đã tồn tại.';
        }
    }
    if (empty($slug)) {
        $errors['slug'] = 'Vui lòng nhập slug.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO brands (name, slug, status) VALUES (?, ?, ?)');
        $stmt->execute([$name, $slug, $status]);
        setFlashMessage('success', 'Thêm thương hiệu thành công!');
        redirect(url('admin/brands/list.php'));
    }

    $brand = array_merge($brand, ['name' => $name, 'slug' => $slug, 'status' => $status]);
}

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <h1>Thêm thương hiệu</h1>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <form method="POST">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Tên thương hiệu <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name"
                                       class="form-control <?php echo !empty($errors['name']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($brand['name']); ?>" required>
                                <?php if (!empty($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="slug">Slug <span class="text-danger">*</span></label>
                                <input type="text" name="slug" id="slug"
                                       class="form-control <?php echo !empty($errors['slug']) ? 'is-invalid' : ''; ?>"
                                       value="<?php echo e($brand['slug']); ?>" required>
                                <?php if (!empty($errors['slug'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['slug']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng thái</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="active"   <?php echo $brand['status'] === 'active'   ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="inactive" <?php echo $brand['status'] === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i> Thêm mới
                            </button>
                            <a href="<?php echo url('admin/brands/list.php'); ?>" class="btn btn-secondary ml-2">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Tự động tạo slug từ tên
document.getElementById('name').addEventListener('input', function () {
    const slug = this.value
        .toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd').replace(/Đ/g, 'd')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim().replace(/\s+/g, '-');
    document.getElementById('slug').value = slug;
});
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
