<?php
/**
 * Admin - Danh sách sản phẩm
 */
$pageTitle = 'Quản lý sản phẩm - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$pdo = getDBConnection();
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = (int)($_GET['category_id'] ?? 0);
$styleFilter = $_GET['style'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "p.name LIKE ?";
    $params[] = '%' . $search . '%';
}
if (!empty($statusFilter)) {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}
if (!empty($styleFilter)) {
    $where[] = "p.style = ?";
    $params[] = $styleFilter;
}

$whereClause = implode(' AND ', $where);

// Đếm
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);
$offset = ($page - 1) * $perPage;

// Lấy sản phẩm
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, b.name as brand_name, p.thumbnail as primary_image,
           (SELECT SUM(stock_quantity) FROM product_variants WHERE product_id = p.id) as total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE $whereClause
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Danh mục cho filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Quản lý sản phẩm (<?php echo $totalProducts; ?>)</h1></div>
            <div class="col-sm-6">
                <a href="<?php echo url('admin/products/add.php'); ?>" class="btn btn-primary float-sm-right">
                    <i class="fas fa-plus mr-1"></i> Thêm sản phẩm
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <form method="GET" class="d-flex align-items-center flex-wrap gap-2">
                    <input type="text" name="search" class="form-control form-control-sm mr-2" style="max-width:200px;" placeholder="Tìm sản phẩm..." value="<?php echo e($search); ?>">
                    <select name="category_id" class="form-control form-control-sm mr-2" style="max-width:180px;">
                        <option value="">-- Danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="form-control form-control-sm mr-2" style="max-width:140px;">
                        <option value="">-- Trạng thái --</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Đang bán</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Ẩn</option>
                    </select>
                    <select name="style" class="form-control form-control-sm mr-2" style="max-width:160px;">
                        <option value="">-- Kiểu dáng cổ --</option>
                        <option value="low_top"  <?php echo $styleFilter === 'low_top'  ? 'selected' : ''; ?>>Cổ thấp (Low-top)</option>
                        <option value="mid_top"  <?php echo $styleFilter === 'mid_top'  ? 'selected' : ''; ?>>Cổ lửng (Mid-top)</option>
                        <option value="high_top" <?php echo $styleFilter === 'high_top' ? 'selected' : ''; ?>>Cổ cao (High-top)</option>
                    </select>
                    <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Lọc</button>
                    <?php if (!empty($search) || !empty($statusFilter) || $categoryFilter > 0 || !empty($styleFilter)): ?>
                        <a href="<?php echo url('admin/products/list.php'); ?>" class="btn btn-secondary btn-sm ml-1">Xóa lọc</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th width="60">Ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Kiểu cổ</th>
                                <th>Giá gốc</th>
                                <th>Giá KM</th>
                                <th>Tồn kho</th>
                                <th>Đã bán</th>
                                <th>Trạng thái</th>
                                <th width="130">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $index => $p): ?>
                                <tr>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td>
                                        <img src="<?php echo !empty($p['primary_image']) ? PRODUCT_UPLOAD_URL . '/' . $p['primary_image'] : asset('images/default/no-product.png'); ?>" 
                                             style="width:45px;height:45px;object-fit:cover;border-radius:6px;">
                                    </td>
                                    <td>
                                        <strong><?php echo e(mb_substr($p['name'], 0, 50)); ?></strong>
                                        <?php if ($p['is_featured']): ?><span class="badge badge-warning ml-1">Hot</span><?php endif; ?>
                                        <?php if ($p['is_new']): ?><span class="badge badge-info ml-1">Mới</span><?php endif; ?>
                                    </td>
                                    <td><?php echo e($p['category_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $styleLabels = ['low_top' => 'Cổ thấp', 'mid_top' => 'Cổ lửng', 'high_top' => 'Cổ cao'];
                                        $styleColors = ['low_top' => 'badge-light border', 'mid_top' => 'badge-info', 'high_top' => 'badge-warning'];
                                        $s = $p['style'] ?? 'low_top';
                                        ?>
                                        <span class="badge <?php echo $styleColors[$s] ?? 'badge-light'; ?>">
                                            <?php echo $styleLabels[$s] ?? $s; ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatPrice($p['price']); ?></td>
                                    <td><?php echo $p['sale_price'] ? formatPrice($p['sale_price']) : '-'; ?></td>
                                    <td>
                                        <?php 
                                        $stock = (int)$p['total_stock'];
                                        $stockBadge = $stock > 10 ? 'badge-success' : ($stock > 0 ? 'badge-warning' : 'badge-danger');
                                        ?>
                                        <span class="badge <?php echo $stockBadge; ?>"><?php echo $stock; ?></span>
                                    </td>
                                    <td><?php echo $p['total_sold']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $p['status'] === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $p['status'] === 'active' ? 'Đang bán' : 'Ẩn'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo url('admin/products/edit.php?id=' . $p['id']); ?>" class="btn btn-sm btn-info" title="Sửa"><i class="fas fa-edit"></i></a>
                                        <a href="<?php echo url('admin/products/variants.php?id=' . $p['id']); ?>" class="btn btn-sm btn-warning" title="Biến thể"><i class="fas fa-layer-group"></i></a>
                                        <a href="<?php echo url('admin/products/delete.php?id=' . $p['id']); ?>" class="btn btn-sm btn-danger" title="Xóa" onclick="return confirm('Xóa sản phẩm này?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">Không có sản phẩm nào</td></tr>
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
                $baseUrl = url('admin/products/list.php') . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
                echo renderPagination($page, $totalPages, $baseUrl); 
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
