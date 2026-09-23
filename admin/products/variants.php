<?php
/**
 * Admin - Quản lý biến thể sản phẩm (Size, Color, Stock)
 */
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

$pageTitle = 'Biến thể sản phẩm - WinK Admin';

$pdo = getDBConnection();
$productId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('error', 'Sản phẩm không tồn tại.');
    redirect(url('admin/products/list.php'));
}

// Xử lý thêm biến thể
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $size = trim($_POST['size'] ?? '');
        $color = trim($_POST['color'] ?? '');
        $stock = (int)($_POST['stock_quantity'] ?? 0);
        $sku = trim($_POST['sku'] ?? '') ?: null;
        
        if (!empty($size) && !empty($color)) {
            // Kiểm tra trùng
            $stmtCheck = $pdo->prepare("SELECT id FROM product_variants WHERE product_id = ? AND size = ? AND color = ?");
            $stmtCheck->execute([$productId, $size, $color]);
            if ($stmtCheck->fetch()) {
                setFlashMessage('error', "Biến thể Size $size - Màu $color đã tồn tại.");
            } else {
                $stmtInsert = $pdo->prepare("INSERT INTO product_variants (product_id, size, color, stock_quantity, sku) VALUES (?, ?, ?, ?, ?)");
                $stmtInsert->execute([$productId, $size, $color, $stock, $sku]);
                setFlashMessage('success', 'Thêm biến thể thành công!');
            }
        } else {
            setFlashMessage('error', 'Vui lòng nhập đầy đủ Size và Màu.');
        }
    } elseif ($action === 'update') {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $stock = (int)($_POST['stock_quantity'] ?? 0);
        $sku = trim($_POST['sku'] ?? '') ?: null;
        
        $stmtUpdate = $pdo->prepare("UPDATE product_variants SET stock_quantity = ?, sku = ? WHERE id = ? AND product_id = ?");
        $stmtUpdate->execute([$stock, $sku, $variantId, $productId]);
        setFlashMessage('success', 'Cập nhật biến thể thành công!');
    } elseif ($action === 'delete') {
        $variantId = (int)($_POST['variant_id'] ?? 0);
        $pdo->prepare("DELETE FROM product_variants WHERE id = ? AND product_id = ?")->execute([$variantId, $productId]);
        setFlashMessage('success', 'Xóa biến thể thành công!');
    } elseif ($action === 'bulk_add') {
        $colors = array_filter(array_map('trim', explode(',', $_POST['colors'] ?? '')));
        $sizes = array_filter(array_map('trim', explode(',', $_POST['sizes'] ?? '')));
        $defaultStock = (int)($_POST['default_stock'] ?? 10);
        $count = 0;
        
        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $stmtCheck = $pdo->prepare("SELECT id FROM product_variants WHERE product_id = ? AND size = ? AND color = ?");
                $stmtCheck->execute([$productId, $size, $color]);
                if (!$stmtCheck->fetch()) {
                    $pdo->prepare("INSERT INTO product_variants (product_id, size, color, stock_quantity) VALUES (?, ?, ?, ?)")
                        ->execute([$productId, $size, $color, $defaultStock]);
                    $count++;
                }
            }
        }
        setFlashMessage('success', "Đã thêm $count biến thể mới!");
    }
    
    redirect(url('admin/products/variants.php?id=' . $productId));
}

// Lấy biến thể
$variants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY color, CAST(size AS UNSIGNED)");
$variants->execute([$productId]);
$variants = $variants->fetchAll();

include dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-8">
                <h1>Biến thể: <?php echo e($product['name']); ?></h1>
            </div>
            <div class="col-sm-4 text-right">
                <a href="<?php echo url('admin/products/edit.php?id=' . $productId); ?>" class="btn btn-info"><i class="fas fa-edit mr-1"></i> Sửa SP</a>
                <a href="<?php echo url('admin/products/list.php'); ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> DS sản phẩm</a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Thêm nhanh nhiều biến thể -->
            <div class="col-md-5">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-magic mr-1"></i> Thêm nhanh nhiều biến thể</h3></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="bulk_add">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Danh sách màu <span class="text-danger">*</span></label>
                                <input type="text" name="colors" class="form-control" placeholder="VD: Đen, Trắng, Đỏ" required>
                                <small class="text-muted">Phân cách bằng dấu phẩy</small>
                            </div>
                            <div class="form-group">
                                <label>Danh sách size <span class="text-danger">*</span></label>
                                <input type="text" name="sizes" class="form-control" placeholder="VD: 38, 39, 40, 41, 42" required>
                                <small class="text-muted">Phân cách bằng dấu phẩy</small>
                            </div>
                            <div class="form-group">
                                <label>Tồn kho mặc định</label>
                                <input type="number" name="default_stock" class="form-control" value="10" min="0">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> Tạo biến thể</button>
                        </div>
                    </form>
                </div>
                
                <!-- Thêm từng biến thể -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-plus mr-1"></i> Thêm 1 biến thể</h3></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-4">
                                    <div class="form-group">
                                        <label>Size</label>
                                        <input type="text" name="size" class="form-control" placeholder="VD: 42" required>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label>Màu</label>
                                        <input type="text" name="color" class="form-control" placeholder="VD: Đen" required>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-group">
                                        <label>Tồn kho</label>
                                        <input type="number" name="stock_quantity" class="form-control" value="10" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>SKU (tùy chọn)</label>
                                <input type="text" name="sku" class="form-control" placeholder="VD: NK-AM-42-BLK">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-success"><i class="fas fa-plus mr-1"></i> Thêm</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Danh sách biến thể -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-layer-group mr-1"></i> Biến thể (<?php echo count($variants); ?>)</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Màu</th>
                                    <th>SKU</th>
                                    <th>Tồn kho</th>
                                    <th width="140">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($variants)): ?>
                                    <?php foreach ($variants as $v): ?>
                                    <tr>
                                        <td><strong><?php echo e($v['size']); ?></strong></td>
                                        <td><?php echo e($v['color']); ?></td>
                                        <td><code><?php echo e($v['sku'] ?? '-'); ?></code></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="variant_id" value="<?php echo $v['id']; ?>">
                                                <input type="hidden" name="sku" value="<?php echo e($v['sku']); ?>">
                                                <input type="number" name="stock_quantity" value="<?php echo $v['stock_quantity']; ?>" 
                                                       class="form-control form-control-sm d-inline" style="width:70px;" min="0"
                                                       onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa biến thể này?')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="variant_id" value="<?php echo $v['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Chưa có biến thể nào</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
