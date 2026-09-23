<?php
/**
 * Admin - Sửa sản phẩm
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Sửa sản phẩm - WinK Admin';

$pdo = getDBConnection();
$id = (int)($_GET['id'] ?? 0);
$errors = [];

// Lấy sản phẩm
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('error', 'Sản phẩm không tồn tại.');
    redirect(url('admin/products/list.php'));
}

// Lấy ảnh hiện tại từ thumbnail và images
$currentImages = [];
if (!empty($product['thumbnail'])) {
    $currentImages[] = ['path' => $product['thumbnail'], 'is_primary' => 1];
}
if (!empty($product['images'])) {
    $extraImages = json_decode($product['images'], true);
    if (is_array($extraImages)) {
        foreach ($extraImages as $img) {
            $currentImages[] = ['path' => $img, 'is_primary' => 0];
        }
    }
}

$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name")->fetchAll();

$old = $product;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']        = trim($_POST['name'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['price']       = (int)($_POST['price'] ?? 0);
    $old['sale_price']  = !empty($_POST['sale_price']) ? (int)$_POST['sale_price'] : null;
    $old['category_id'] = (int)($_POST['category_id'] ?? 0);
    $old['brand_id']    = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $old['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
    $old['is_new']      = isset($_POST['is_new']) ? 1 : 0;
    $old['status']      = $_POST['status'] ?? 'active';
    
    if (empty($old['name'])) $errors['name'] = 'Vui lòng nhập tên sản phẩm.';
    if ($old['price'] <= 0) $errors['price'] = 'Giá phải lớn hơn 0.';
    if ($old['category_id'] <= 0) $errors['category_id'] = 'Vui lòng chọn danh mục.';
    if ($old['sale_price'] !== null && $old['sale_price'] >= $old['price']) $errors['sale_price'] = 'Giá KM phải nhỏ hơn giá gốc.';
    
    // Xử lý xóa ảnh
    $deleteImages = $_POST['delete_images'] ?? [];
    $remainingImages = [];
    foreach ($currentImages as $img) {
        if (in_array($img['path'], $deleteImages)) {
            @unlink(UPLOAD_PATH . '/products/' . $img['path']);
        } else {
            $remainingImages[] = $img['path'];
        }
    }
    
    // Upload ảnh mới
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
                    $remainingImages[] = $upload['filename'];
                }
            }
        }
    }
    
    // Xác định ảnh chính
    $primaryImage = $_POST['primary_image'] ?? '';
    if (!empty($primaryImage) && in_array($primaryImage, $remainingImages)) {
        $thumbnail = $primaryImage;
        $otherImages = array_values(array_filter($remainingImages, fn($img) => $img !== $primaryImage));
    } else {
        // Ảnh đầu tiên làm thumbnail
        $thumbnail = !empty($remainingImages) ? $remainingImages[0] : null;
        $otherImages = array_slice($remainingImages, 1);
    }
    
    if (empty($errors)) {
        $slug = createSlug($old['name']);
        $stmtSlug = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
        $stmtSlug->execute([$slug, $id]);
        if ($stmtSlug->fetch()) $slug .= '-' . time();
        
        $imagesJson = !empty($otherImages) ? json_encode(array_values($otherImages), JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $pdo->prepare("
            UPDATE products SET name=?, slug=?, thumbnail=?, images=?, description=?, price=?, sale_price=?, category_id=?, brand_id=?, is_featured=?, is_new=?, status=?
            WHERE id=?
        ");
        $stmt->execute([
            $old['name'], $slug, $thumbnail, $imagesJson, $old['description'], $old['price'], $old['sale_price'],
            $old['category_id'], $old['brand_id'], $old['is_featured'], $old['is_new'], $old['status'], $id
        ]);
        
        setFlashMessage('success', 'Cập nhật sản phẩm thành công!');
        redirect(url('admin/products/edit.php?id=' . $id));
    }
    
    // Reload images after changes
    $currentImages = [];
    foreach ($remainingImages as $img) {
        $currentImages[] = ['path' => $img, 'is_primary' => ($img === ($thumbnail ?? '')) ? 1 : 0];
    }
}

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <h1>Sửa sản phẩm: <?php echo e($product['name']); ?></h1>
    </div>
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
                                        <label>Giá khuyến mãi</label>
                                        <input type="number" name="sale_price" class="form-control <?php echo !empty($errors['sale_price']) ? 'is-invalid' : ''; ?>" value="<?php echo $old['sale_price']; ?>" min="0">
                                        <?php if (!empty($errors['sale_price'])): ?><div class="invalid-feedback"><?php echo e($errors['sale_price']); ?></div><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ảnh hiện tại -->
                            <?php if (!empty($currentImages)): ?>
                            <div class="form-group">
                                <label>Ảnh hiện tại</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($currentImages as $img): ?>
                                    <div class="position-relative" style="width:100px;">
                                        <img src="<?php echo PRODUCT_UPLOAD_URL . '/' . e($img['path']); ?>" style="width:100px;height:100px;object-fit:cover;border-radius:8px;border:2px solid <?php echo $img['is_primary'] ? '#f36811' : '#ddd'; ?>;">
                                        <div class="mt-1 d-flex gap-1">
                                            <label style="font-size:10px;cursor:pointer;">
                                                <input type="radio" name="primary_image" value="<?php echo e($img['path']); ?>" <?php echo $img['is_primary'] ? 'checked' : ''; ?>> Chính
                                            </label>
                                            <label style="font-size:10px;cursor:pointer;color:red;">
                                                <input type="checkbox" name="delete_images[]" value="<?php echo e($img['path']); ?>"> Xóa
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label>Thêm ảnh mới</label>
                                <input type="file" name="images[]" class="form-control-file" accept="image/*" multiple>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Phân loại</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Danh mục <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-control" required>
                                    <option value="">-- Chọn --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $old['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo e($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Thương hiệu</label>
                                <select name="brand_id" class="form-control">
                                    <option value="">-- Chọn --</option>
                                    <?php foreach ($brands as $b): ?>
                                        <option value="<?php echo $b['id']; ?>" <?php echo $old['brand_id'] == $b['id'] ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
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
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" <?php echo $old['is_featured'] ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="is_featured">Nổi bật</label>
                            </div>
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="is_new" name="is_new" <?php echo $old['is_new'] ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="is_new">Mới</label>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i> Cập nhật</button>
                            <a href="<?php echo url('admin/products/variants.php?id=' . $id); ?>" class="btn btn-warning btn-block"><i class="fas fa-layer-group mr-1"></i> Quản lý biến thể</a>
                            <a href="<?php echo url('admin/products/list.php'); ?>" class="btn btn-secondary btn-block">Quay lại</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
