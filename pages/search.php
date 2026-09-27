<?php
/** Trang tìm kiếm - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();
$query = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;
$products = [];
$totalProducts = 0;

if (!empty($query)) {
    $searchTerm = '%' . $query . '%';

    // Đếm kết quả
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.status = 'active' AND (p.name LIKE ? OR b.name LIKE ?)
    ");
    $stmtCount->execute([$searchTerm, $searchTerm]);
    $totalProducts = (int) $stmtCount->fetchColumn();
    $totalPages = ceil($totalProducts / $perPage);
    $offset = ($page - 1) * $perPage;

    // Lấy sản phẩm
    $stmt = $pdo->prepare("
        SELECT p.*, p.thumbnail as primary_image, c.name as category_name, b.name as brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.status = 'active' AND (p.name LIKE ? OR b.name LIKE ?)
        ORDER BY p.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $products = $stmt->fetchAll();
} else {
    $totalPages = 0;
}

$pageTitle = !empty($query) ? 'Tìm kiếm: ' . e($query) . ' - WinK' : 'Tìm kiếm - WinK';
$extraCSS = ['product.css'];
$baseUrl = url('pages/search.php') . '?q=' . urlencode($query);

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <?php if (!empty($query)): ?>
            <div class="mb-4">
                <h4 class="fw-bold">Kết quả tìm kiếm cho "<?php echo e($query); ?>"</h4>
                <p class="text-muted">Tìm thấy <strong><?php echo $totalProducts; ?></strong> sản phẩm</p>
            </div>
            
            <?php if (!empty($products)): ?>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <?php include dirname(__DIR__) . '/includes/product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4">
                    <?php echo renderPagination($page, $totalPages, $baseUrl); ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5>Không tìm thấy sản phẩm nào</h5>
                    <p class="text-muted">Thử tìm kiếm với từ khóa khác.</p>
                    <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink mt-2">Xem tất cả sản phẩm</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5>Nhập từ khóa để tìm kiếm</h5>
                <p class="text-muted">Tìm kiếm theo tên sản phẩm hoặc thương hiệu.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
