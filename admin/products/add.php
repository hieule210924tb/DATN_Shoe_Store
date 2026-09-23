<?php
/**
 * Admin - Thêm sản phẩm mới
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Thêm sản phẩm - WinK Admin';

$pdo = getDBConnection();
$errors = [];
$old = [
    'name' => '', 'description' => '', 'price' => '', 'sale_price' => '',
    'category_id' => '', 'brand_id' => '', 'is_featured' => 0, 'is_new' => 1, 'status' => 'active'
];

$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'        => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price'       => (int)($_POST['price'] ?? 0),
        'sale_price'  => !empty($_POST['sale_price']) ? (int)$_POST['sale_price'] : null,
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'brand_id'    => !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_new'      => isset($_POST['is_new']) ? 1 : 0,
        'status'      => $_POST['status'] ?? 'active',
    ];
    
    // Validate
    if (empty($old['name'])) $errors['name'] = 'Vui lòng nhập tên sản phẩm.';
    if ($old['price'] <= 0) $errors['price'] = 'Giá phải lớn hơn 0.';
    if ($old['category_id'] <= 0) $errors['category_id'] = 'Vui lòng chọn danh mục.';
    if ($old['sale_price'] !== null && $old['sale_price'] >= $old['price']) $errors['sale_price'] = 'Giá khuyến mãi phải nhỏ hơn giá gốc.';
    
    // Upload ảnh
    $uploadedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $key => $name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['images']['name'][$key],
                    'type' => $_FILES['images']['type'][$key],
                    'tmp_name' => $_FILES['images']['tmp_name'][$key],
                    'error' => $_FILES['images']['error'][$key],
                    'size' => $_FILES['images']['size'][$key],
                ];
                $upload = uploadImage($file, UPLOAD_PATH . '/products');
                if ($upload['success']) {
                    $uploadedImages[] = $upload['filename'];
                } else {
                    $errors['images'] = $upload['error'];
                    break;
                }
            }
        }
    }
    
    if (empty($errors)) {
        $slug = createSlug($old['name']);
        
        // Kiểm tra slug trùng
        $stmtSlug = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
        $stmtSlug->execute([$slug]);
        if ($stmtSlug->fetch()) {
            $slug .= '-' . time();
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO products (name, slug, description, price, sale_price, category_id, brand_id, is_featured, is_new, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $old['name'], $slug, $old['description'], $old['price'], $old['sale_price'],
            $old['category_id'], $old['brand_id'], $old['is_featured'], $old['is_new'], $old['status']
        ]);
        $productId = $pdo->lastInsertId();
        
        // Lưu ảnh
        foreach ($uploadedImages as $index => $imgFile) {
            $isPrimary = $index === 0 ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$productId, $imgFile, $isPrimary, $index]);
        }
        
        setFlashMessage('success', 'Thêm sản phẩm thành công! Giờ hãy thêm biến thể (size/color).');
        redirect(url('admin/products/variants.php?id=' . $productId));
    }
}

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid"><h1>Thêm sản phẩm mới</h1></div>
</div>

<section class="content">
    <div class="container-fluid">
        <form method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Thông tin sản phẩm</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Tên sản phẩm <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control <?php echo !empty($errors['name']) ? 'is-invalid' : ''; ?>" value="<?php echo e($old['name']); ?>" required>
                                <?php if (!empty($errors['name'])): ?><div class="invalid-feedback"><?php echo e($errors['name']); ?></div><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>Mô tả</label>
                                <textarea name="description" class="form-control" rows="5"><?php echo e($old['description']); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Giá gốc (VNĐ) <span class="text-danger">*</span></label>
                                        <input type="number" name="price" class="form-control <?php echo !empty($errors['price']) ? 'is-invalid' : ''; ?>" value="<?php echo $old['price']; ?>" min="0" required>
                                        <?php if (!empty($errors['price'])): ?><div class="invalid-feedback"><?php echo e($errors['price']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Giá khuyến mãi (VNĐ)</label>
                                        <input type="number" name="sale_price" class="form-control <?php echo !empty($errors['sale_price']) ? 'is-invalid' : ''; ?>" value="<?php echo $old['sale_price']; ?>" min="0">
                                        <?php if (!empty($errors['sale_price'])): ?><div class="invalid-feedback"><?php echo e($errors['sale_price']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Hình ảnh sản phẩm</label>
                                <input type="file" name="images[]" class="form-control-file" accept="image/*" multiple>
                                <small class="text-muted">Ảnh đầu tiên sẽ là ảnh chính. Tối đa 5 ảnh.</small>
                                <?php if (!empty($errors['images'])): ?><div class="text-danger mt-1"><?php echo e($errors['images']); ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Phân loại & Trạng thái</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Danh mục <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-control <?php echo !empty($errors['category_id']) ? 'is-invalid' : ''; ?>" required>
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $old['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['category_id'])): ?><div class="invalid-feedback"><?php echo e($errors['category_id']); ?></div><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>Thương hiệu</label>
                                <select name="brand_id" class="form-control">
                                    <option value="">-- Chọn thương hiệu --</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo $brand['id']; ?>" <?php echo $old['brand_id'] == $brand['id'] ? 'selected' : ''; ?>><?php echo e($brand['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Trạng thái</label>
                                <select name="status" class="form-control">
                                    <option value="active" <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>>Đang bán</option>
                                    <option value="inactive" <?php echo $old['status'] === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" <?php echo $old['is_featured'] ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="is_featured">Sản phẩm nổi bật</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="is_new" name="is_new" <?php echo $old['is_new'] ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="is_new">Sản phẩm mới</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i> Thêm sản phẩm</button>
                            <a href="<?php echo url('admin/products/list.php'); ?>" class="btn btn-secondary btn-block">Hủy</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
