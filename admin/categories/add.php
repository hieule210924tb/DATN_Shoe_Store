<?php
/**
 * Admin - Thêm/Sửa danh mục
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$isEdit = basename($_SERVER['PHP_SELF']) === 'edit.php';
$pageTitle = ($isEdit ? 'Sửa' : 'Thêm') . ' danh mục - WinK Admin';

$pdo = getDBConnection();
$errors = [];
$category = ['name' => '', 'description' => '', 'status' => 'active'];

// Nếu sửa, lấy dữ liệu
if ($isEdit) {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) {
        setFlashMessage('error', 'Danh mục không tồn tại.');
        redirect(url('admin/categories/list.php'));
    }
}

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $slug = createSlug($name);
    
    if (empty($name)) {
        $errors['name'] = 'Vui lòng nhập tên danh mục.';
    } else {
        // Kiểm tra trùng tên
        $checkSql = "SELECT id FROM categories WHERE name = ?";
        $checkParams = [$name];
        if ($isEdit) {
            $checkSql .= " AND id != ?";
            $checkParams[] = $category['id'];
        }
        $stmtCheck = $pdo->prepare($checkSql);
        $stmtCheck->execute($checkParams);
        if ($stmtCheck->fetch()) {
            $errors['name'] = 'Tên danh mục đã tồn tại.';
        }
    }
    
    // Upload ảnh
    $imagePath = $isEdit ? $category['image'] : null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadImage($_FILES['image'], UPLOAD_PATH . '/categories');
        if ($upload['success']) {
            $imagePath = $upload['filename'];
        } else {
            $errors['image'] = $upload['error'];
        }
    }
    
    if (empty($errors)) {
        if ($isEdit) {
            $stmt = $pdo->prepare("UPDATE categories SET name=?, slug=?, description=?, image=?, status=? WHERE id=?");
            $stmt->execute([$name, $slug, $description, $imagePath, $status, $category['id']]);
            setFlashMessage('success', 'Cập nhật danh mục thành công!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $description, $imagePath, $status]);
            setFlashMessage('success', 'Thêm danh mục thành công!');
        }
        redirect(url('admin/categories/list.php'));
    }
    
    $category = array_merge($category, ['name' => $name, 'description' => $description, 'status' => $status]);
}

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <h1><?php echo $isEdit ? 'Sửa' : 'Thêm'; ?> danh mục</h1>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Tên danh mục <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="name" class="form-control <?php echo !empty($errors['name']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo e($category['name']); ?>" required>
                                <?php if (!empty($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo e($errors['name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="description">Mô tả</label>
                                <textarea name="description" id="description" class="form-control" rows="3"><?php echo e($category['description']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="image">Hình ảnh</label>
                                <input type="file" name="image" id="image" class="form-control-file <?php echo !empty($errors['image']) ? 'is-invalid' : ''; ?>" accept="image/*">
                                <?php if (!empty($errors['image'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo e($errors['image']); ?></div>
                                <?php endif; ?>
                                <?php if ($isEdit && !empty($category['image'])): ?>
                                    <img src="<?php echo UPLOAD_URL . '/categories/' . e($category['image']); ?>" class="mt-2" style="max-height:100px;">
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="status">Trạng thái</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="active" <?php echo $category['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                    <option value="inactive" <?php echo $category['status'] === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> <?php echo $isEdit ? 'Cập nhật' : 'Thêm mới'; ?></button>
                            <a href="<?php echo url('admin/categories/list.php'); ?>" class="btn btn-secondary ml-2">Hủy</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
