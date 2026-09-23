<?php
/**
 * Trang danh sách sản phẩm - WinK Shoe Store
 * Hỗ trợ: phân trang, lọc theo danh mục/thương hiệu/giá/size, sắp xếp
 */
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();

// === Lấy tham số filter từ URL ===
$categorySlug = $_GET['category'] ?? '';
$brandSlug    = $_GET['brand'] ?? '';
$minPrice     = (int)($_GET['min_price'] ?? 0);
$maxPrice     = (int)($_GET['max_price'] ?? 0);
$sizeFilter   = $_GET['size'] ?? '';
$sort         = $_GET['sort'] ?? 'newest';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = ITEMS_PER_PAGE;

// === Xây dựng query ===
$where = ["p.status = 'active'"];
$params = [];

// Lọc theo danh mục
if (!empty($categorySlug)) {
    $where[] = "c.slug = ?";
    $params[] = $categorySlug;
}

// Lọc theo thương hiệu
if (!empty($brandSlug)) {
    $where[] = "b.slug = ?";
    $params[] = $brandSlug;
}

// Lọc theo khoảng giá
if ($minPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $minPrice;
}
if ($maxPrice > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $maxPrice;
}

// Lọc theo size
if (!empty($sizeFilter)) {
    $where[] = "p.id IN (SELECT product_id FROM product_variants WHERE size = ? AND stock_quantity > 0)";
    $params[] = $sizeFilter;
}

$whereClause = implode(' AND ', $where);

// Sắp xếp
$orderBy = match($sort) {
    'price_asc'     => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc'    => 'COALESCE(p.sale_price, p.price) DESC',
    'best_selling'  => 'p.total_sold DESC',
    'featured'      => 'p.is_featured DESC, p.created_at DESC',
    'name_asc'      => 'p.name ASC',
    'name_desc'     => 'p.name DESC',
    default         => 'p.created_at DESC', // newest
};

// === Đếm tổng sản phẩm ===
$countSql = "SELECT COUNT(*) FROM products p 
             LEFT JOIN categories c ON p.category_id = c.id 
             LEFT JOIN brands b ON p.brand_id = b.id 
             WHERE $whereClause";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);
$offset = ($page - 1) * $perPage;

// === Lấy sản phẩm ===
$sql = "SELECT p.*, p.thumbnail as primary_image, c.name as category_name, b.name as brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset";
$stmtProducts = $pdo->prepare($sql);
$stmtProducts->execute($params);
$products = $stmtProducts->fetchAll();

// === Lấy danh mục, thương hiệu, size cho filter sidebar ===
$categories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name")->fetchAll();
$brands = $pdo->query("SELECT * FROM brands WHERE status = 'active' ORDER BY name")->fetchAll();
$sizes = $pdo->query("SELECT DISTINCT size FROM product_variants WHERE stock_quantity > 0 ORDER BY CAST(size AS UNSIGNED)")->fetchAll(PDO::FETCH_COLUMN);

// === Tên danh mục hiện tại ===
$currentCategory = null;
if (!empty($categorySlug)) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $currentCategory = $cat;
            break;
        }
    }
}

$pageTitle = $currentCategory ? e($currentCategory['name']) . ' - WinK' : 'Sản phẩm - WinK Shoe Store';
$pageDescription = 'Mua giày online chính hãng tại WinK. Đa dạng mẫu mã, giá cả hợp lý.';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';

// Build base URL cho phân trang
$queryParams = $_GET;
unset($queryParams['page']);
$baseUrl = url('pages/products.php') . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
?>

<!-- Breadcrumb -->
<div class="wink-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Trang chủ</a></li>
                <?php if ($currentCategory): ?>
                    <li class="breadcrumb-item"><a href="<?php echo url('pages/products.php'); ?>">Sản phẩm</a></li>
                    <li class="breadcrumb-item active"><?php echo e($currentCategory['name']); ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item active">Sản phẩm</li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>
</div>

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <div class="row g-4">
            <!-- Sidebar Filter -->
            <div class="col-lg-3">
                <div class="filter-sidebar">
                    <div class="filter-header d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-filter me-2"></i>Bộ lọc</h5>
                        <a href="<?php echo url('pages/products.php'); ?>" class="text-muted" style="font-size:13px;">Xóa lọc</a>
                    </div>
                    
                    <form id="filterForm" method="GET" action="<?php echo url('pages/products.php'); ?>">
                        <!-- Danh mục -->
                        <div class="filter-group">
                            <h6 class="filter-title">Danh mục</h6>
                            <div class="filter-list">
                                <?php foreach ($categories as $cat): ?>
                                <label class="filter-item">
                                    <input type="radio" name="category" value="<?php echo e($cat['slug']); ?>" 
                                           <?php echo $categorySlug === $cat['slug'] ? 'checked' : ''; ?>
                                           onchange="this.form.submit()">
                                    <span><?php echo e($cat['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Thương hiệu -->
                        <div class="filter-group">
                            <h6 class="filter-title">Thương hiệu</h6>
                            <div class="filter-list">
                                <?php foreach ($brands as $brand): ?>
                                <label class="filter-item">
                                    <input type="radio" name="brand" value="<?php echo e($brand['slug']); ?>"
                                           <?php echo $brandSlug === $brand['slug'] ? 'checked' : ''; ?>
                                           onchange="this.form.submit()">
                                    <span><?php echo e($brand['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Khoảng giá -->
                        <div class="filter-group">
                            <h6 class="filter-title">Khoảng giá</h6>
                            <div class="filter-list">
                                <?php 
                                $priceRanges = [
                                    ['label' => 'Dưới 500.000đ', 'min' => 0, 'max' => 500000],
                                    ['label' => '500.000đ - 1.000.000đ', 'min' => 500000, 'max' => 1000000],
                                    ['label' => '1.000.000đ - 2.000.000đ', 'min' => 1000000, 'max' => 2000000],
                                    ['label' => '2.000.000đ - 5.000.000đ', 'min' => 2000000, 'max' => 5000000],
                                    ['label' => 'Trên 5.000.000đ', 'min' => 5000000, 'max' => 0],
                                ];
                                foreach ($priceRanges as $range): 
                                    $isChecked = ($minPrice == $range['min'] && $maxPrice == $range['max']);
                                ?>
                                <label class="filter-item">
                                    <input type="radio" name="price_range" value="<?php echo $range['min'] . '-' . $range['max']; ?>"
                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                           onchange="setPriceRange(this.value); this.form.submit();">
                                    <span><?php echo $range['label']; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="min_price" id="minPriceInput" value="<?php echo $minPrice; ?>">
                            <input type="hidden" name="max_price" id="maxPriceInput" value="<?php echo $maxPrice; ?>">
                        </div>
                        
                        <!-- Size -->
                        <?php if (!empty($sizes)): ?>
                        <div class="filter-group">
                            <h6 class="filter-title">Size</h6>
                            <div class="filter-sizes">
                                <?php foreach ($sizes as $size): ?>
                                <label class="size-option <?php echo $sizeFilter === $size ? 'active' : ''; ?>">
                                    <input type="radio" name="size" value="<?php echo e($size); ?>"
                                           <?php echo $sizeFilter === $size ? 'checked' : ''; ?>
                                           onchange="this.form.submit()">
                                    <span><?php echo e($size); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Giữ sort khi filter -->
                        <?php if (!empty($sort) && $sort !== 'newest'): ?>
                            <input type="hidden" name="sort" value="<?php echo e($sort); ?>">
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Product Grid -->
            <div class="col-lg-9">
                <!-- Sort Bar -->
                <div class="sort-bar">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <p class="mb-0 text-muted" style="font-size:14px;">
                            Hiển thị <strong><?php echo count($products); ?></strong> / <strong><?php echo $totalProducts; ?></strong> sản phẩm
                        </p>
                        <div class="d-flex align-items-center gap-2">
                            <span style="font-size:14px;white-space:nowrap;">Sắp xếp:</span>
                            <select class="form-select form-select-sm" style="width:auto;" onchange="changeSort(this.value)">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                <option value="featured" <?php echo $sort === 'featured' ? 'selected' : ''; ?>>Nổi bật</option>
                                <option value="best_selling" <?php echo $sort === 'best_selling' ? 'selected' : ''; ?>>Bán chạy</option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Giá thấp đến cao</option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Giá cao đến thấp</option>
                                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($products)): ?>
                    <div class="row g-3 mt-1">
                        <?php foreach ($products as $product): ?>
                            <?php include dirname(__DIR__) . '/includes/product_card.php'; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Phân trang -->
                    <div class="mt-4">
                        <?php echo renderPagination($page, $totalPages, $baseUrl); ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>Không tìm thấy sản phẩm</h5>
                        <p class="text-muted">Thử thay đổi bộ lọc hoặc xem tất cả sản phẩm.</p>
                        <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink">Xem tất cả sản phẩm</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
function setPriceRange(value) {
    const parts = value.split('-');
    document.getElementById('minPriceInput').value = parts[0];
    document.getElementById('maxPriceInput').value = parts[1];
}

function changeSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
