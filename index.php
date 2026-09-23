<?php
/**
 * Trang chủ - WinK Shoe Store
 */
require_once __DIR__ . '/config/config.php';

$pageTitle = 'WinK Shoe Store - Cửa hàng giày trực tuyến';
$pageDescription = 'WinK Shoe Store - Mua giày online chính hãng. Nike, Adidas, Puma, New Balance. Giao hàng toàn quốc, thanh toán đa dạng.';

$pdo = getDBConnection();

// Lấy sản phẩm mới (8 sản phẩm mới nhất)
$stmtNew = $pdo->query("
    SELECT p.*, pi.image_path as primary_image, c.name as category_name 
    FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.is_new = 1
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$newProducts = $stmtNew->fetchAll();

// Lấy sản phẩm bán chạy (8 sản phẩm bán nhiều nhất)
$stmtBestSelling = $pdo->query("
    SELECT p.*, pi.image_path as primary_image, c.name as category_name 
    FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.total_sold > 0
    ORDER BY p.total_sold DESC 
    LIMIT 8
");
$bestSellingProducts = $stmtBestSelling->fetchAll();

// Lấy sản phẩm nổi bật (8 sản phẩm featured)
$stmtFeatured = $pdo->query("
    SELECT p.*, pi.image_path as primary_image, c.name as category_name 
    FROM products p 
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.is_featured = 1
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$featuredProducts = $stmtFeatured->fetchAll();

// Lấy danh mục
$stmtCategories = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = $stmtCategories->fetchAll();

// Lấy thương hiệu
$stmtBrands = $pdo->query("SELECT * FROM brands WHERE status = 'active' ORDER BY name");
$brands = $stmtBrands->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner Slider -->
<section class="wink-hero">
    <div id="heroBanner" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <!-- Banner 1 -->
            <div class="carousel-item active" style="background: linear-gradient(135deg, #f36811 0%, #ff8a3d 50%, #ffb347 100%);">
                <div class="wink-hero-overlay" style="background: linear-gradient(135deg, rgba(26,26,46,0.6) 0%, rgba(0,0,0,0.1) 100%);"></div>
                <div class="wink-hero-content">
                    <h1>Bộ Sưu Tập<br>Mới Nhất 2026</h1>
                    <p>Khám phá những đôi giày thời trang nhất với giá cực ưu đãi. Miễn phí vận chuyển cho đơn từ 300K.</p>
                    <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink btn-wink-lg">
                        <i class="fas fa-shopping-bag"></i> Mua Sắm Ngay
                    </a>
                </div>
            </div>
            
            <!-- Banner 2 -->
            <div class="carousel-item" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);">
                <div class="wink-hero-overlay" style="background: linear-gradient(135deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.1) 100%);"></div>
                <div class="wink-hero-content">
                    <h1>Giày Thể Thao<br>Chính Hãng</h1>
                    <p>Nike, Adidas, Puma, New Balance - Đầy đủ các thương hiệu hàng đầu thế giới.</p>
                    <a href="<?php echo url('pages/products.php?category=giay-the-thao'); ?>" class="btn-wink btn-wink-lg">
                        <i class="fas fa-fire"></i> Xem Ngay
                    </a>
                </div>
            </div>
            
            <!-- Banner 3 -->
            <div class="carousel-item" style="background: linear-gradient(135deg, #d95a0a 0%, #f36811 50%, #ff6b35 100%);">
                <div class="wink-hero-overlay" style="background: linear-gradient(135deg, rgba(26,26,46,0.5) 0%, rgba(0,0,0,0.1) 100%);"></div>
                <div class="wink-hero-content">
                    <h1>Flash Sale<br>Giảm Đến 50%</h1>
                    <p>Chương trình khuyến mãi cực hot - Số lượng có hạn, nhanh tay đặt ngay!</p>
                    <a href="<?php echo url('pages/vouchers.php'); ?>" class="btn-wink btn-wink-lg" style="background:#fff;color:#f36811;">
                        <i class="fas fa-tags"></i> Xem Khuyến Mãi
                    </a>
                </div>
            </div>
        </div>
        
        <button class="carousel-control-prev" type="button" data-bs-target="#heroBanner" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroBanner" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
</section>

<!-- Features Bar -->
<section class="py-4" style="background: var(--white); border-bottom: 1px solid var(--gray-200);">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-shipping-fast fa-2x" style="color: var(--primary);"></i>
                    <div class="text-start">
                        <strong style="font-size: 14px;">Miễn phí ship</strong>
                        <p class="mb-0 text-muted" style="font-size: 12px;">Đơn từ 300K</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-shield-alt fa-2x" style="color: var(--primary);"></i>
                    <div class="text-start">
                        <strong style="font-size: 14px;">Chính hãng 100%</strong>
                        <p class="mb-0 text-muted" style="font-size: 12px;">Cam kết chất lượng</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-undo fa-2x" style="color: var(--primary);"></i>
                    <div class="text-start">
                        <strong style="font-size: 14px;">Đổi trả 30 ngày</strong>
                        <p class="mb-0 text-muted" style="font-size: 12px;">Miễn phí đổi trả</p>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    <i class="fas fa-headset fa-2x" style="color: var(--primary);"></i>
                    <div class="text-start">
                        <strong style="font-size: 14px;">Hỗ trợ 24/7</strong>
                        <p class="mb-0 text-muted" style="font-size: 12px;">Tư vấn miễn phí</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories Section -->
<?php if (!empty($categories)): ?>
<section class="section-padding">
    <div class="container">
        <div class="section-heading">
            <div class="section-line"></div>
            <h2>Danh Mục Sản Phẩm</h2>
            <p>Khám phá các loại giày phù hợp với phong cách của bạn</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php 
            $catIcons = ['fas fa-running', 'fas fa-shoe-prints', 'fas fa-star', 'fas fa-briefcase', 'fas fa-hiking', 'fas fa-tshirt'];
            foreach ($categories as $index => $category): 
                $icon = $catIcons[$index % count($catIcons)];
            ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="<?php echo url('pages/products.php?category=' . e($category['slug'])); ?>" 
                   class="d-block text-center p-3 rounded-3 text-decoration-none" 
                   style="background: var(--white); border: 1px solid var(--gray-200); transition: all 0.3s ease;">
                    <div class="mb-2">
                        <i class="<?php echo $icon; ?> fa-2x" style="color: var(--primary);"></i>
                    </div>
                    <span class="fw-semibold" style="color: var(--dark); font-size: 13px;">
                        <?php echo e($category['name']); ?>
                    </span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- New Products Section -->
<?php if (!empty($newProducts)): ?>
<section class="section-padding" style="background: var(--white);">
    <div class="container">
        <div class="section-heading">
            <div class="section-line"></div>
            <h2>Sản Phẩm Mới</h2>
            <p>Cập nhật những mẫu giày mới nhất</p>
        </div>
        <div class="row g-4">
            <?php foreach ($newProducts as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo url('pages/products.php?sort=newest'); ?>" class="btn-wink-outline">
                Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Best Selling Products Section -->
<?php if (!empty($bestSellingProducts)): ?>
<section class="section-padding">
    <div class="container">
        <div class="section-heading">
            <div class="section-line"></div>
            <h2>Sản Phẩm Bán Chạy</h2>
            <p>Được khách hàng yêu thích nhất</p>
        </div>
        <div class="row g-4">
            <?php foreach ($bestSellingProducts as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo url('pages/products.php?sort=best_selling'); ?>" class="btn-wink-outline">
                Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Featured Products Section -->
<?php if (!empty($featuredProducts)): ?>
<section class="section-padding" style="background: var(--white);">
    <div class="container">
        <div class="section-heading">
            <div class="section-line"></div>
            <h2>Sản Phẩm Nổi Bật</h2>
            <p>Lựa chọn hàng đầu từ WinK</p>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredProducts as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo url('pages/products.php?sort=featured'); ?>" class="btn-wink-outline">
                Xem tất cả <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Brands Section -->
<?php if (!empty($brands)): ?>
<section class="section-padding">
    <div class="container">
        <div class="section-heading">
            <div class="section-line"></div>
            <h2>Thương Hiệu</h2>
            <p>Các thương hiệu giày hàng đầu thế giới</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php foreach ($brands as $brand): ?>
            <div class="col-4 col-sm-3 col-md-2">
                <a href="<?php echo url('pages/products.php?brand=' . e($brand['slug'])); ?>" 
                   class="d-flex align-items-center justify-content-center p-3 rounded-3" 
                   style="background:var(--white);border:1px solid var(--gray-200);height:80px;transition:all 0.3s ease;"
                   title="<?php echo e($brand['name']); ?>">
                    <?php if (!empty($brand['logo'])): ?>
                        <img src="<?php echo UPLOAD_URL . '/brands/' . e($brand['logo']); ?>" alt="<?php echo e($brand['name']); ?>" style="max-height:40px;max-width:100%;">
                    <?php else: ?>
                        <span class="fw-bold" style="color:var(--dark);font-size:16px;"><?php echo e($brand['name']); ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Newsletter Section -->
<section class="py-5" style="background: linear-gradient(135deg, #f36811 0%, #ff8a3d 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center text-white">
                <h3 class="fw-bold mb-2">Đăng ký nhận tin</h3>
                <p class="mb-4 opacity-75">Nhận thông tin khuyến mãi và sản phẩm mới nhất từ WinK</p>
                <form class="d-flex gap-2 justify-content-center" onsubmit="event.preventDefault(); showToast('success', 'Đăng ký thành công!');">
                    <input type="email" class="form-control" placeholder="Nhập email của bạn..." 
                           style="max-width:340px;border-radius:50px;padding:10px 20px;border:none;">
                    <button type="submit" class="btn-wink" style="background:#1a1a2e;">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
