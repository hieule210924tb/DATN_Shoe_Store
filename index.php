<?php

require_once __DIR__ . '/config/config.php';

$pageTitle = 'WinK Shoe Store - Cửa hàng giày trực tuyến';
$pageDescription = 'WinK Shoe Store - Mua giày online chính hãng. Nike, Adidas, Puma, New Balance. Giao hàng toàn quốc, thanh toán đa dạng.';

$pdo = getDBConnection();

// Lấy sản phẩm mới (8 sản phẩm mới nhất)
$stmtNew = $pdo->query("
    SELECT p.*, p.thumbnail as primary_image, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.is_new = 1
    ORDER BY p.created_at DESC 
    LIMIT 8
");
$newProducts = $stmtNew->fetchAll();

// Lấy sản phẩm bán chạy (8 sản phẩm bán nhiều nhất)
$stmtBestSelling = $pdo->query("
    SELECT p.*, p.thumbnail as primary_image, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.total_sold > 0
    ORDER BY p.total_sold DESC 
    LIMIT 8
");
$bestSellingProducts = $stmtBestSelling->fetchAll();

// Lấy sản phẩm nổi bật (8 sản phẩm featured)
$stmtFeatured = $pdo->query("
    SELECT p.*, p.thumbnail as primary_image, c.name as category_name 
    FROM products p 
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

<!-- ═══════════════════════════════════════
     HERO BANNER — Full-bleed cinematic slider
     ═══════════════════════════════════════ -->
<section class="hp-hero">
    <div id="heroBanner" class="carousel slide hp-carousel" data-bs-ride="carousel" data-bs-interval="5500">

        <!-- Slides -->
        <div class="carousel-inner">

            <!-- Slide 1 — Bộ sưu tập mới -->
            <div class="carousel-item active hp-slide hp-slide--1">
                <div class="hp-slide-overlay"></div>
                <div class="container">
                    <div class="hp-slide-inner">
                        <div class="row align-items-center w-100">
                            <div class="col-lg-6 hp-hero-text">
                                <span class="hp-hero-tag">✦ Bộ sưu tập 2026</span>
                                <h1 class="hp-hero-title">
                                    Phong Cách<br>
                                    <span class="hp-hero-title-accent">Không Giới Hạn</span>
                                </h1>
                                <p class="hp-hero-desc">Khám phá những đôi giày thời trang nhất với giá cực ưu đãi. Miễn phí vận chuyển cho đơn từ 300K.</p>
                                <div class="hp-hero-actions">
                                    <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink btn-wink-lg">
                                        <i class="fas fa-shopping-bag"></i> Mua Sắm Ngay
                                    </a>
                                    <a href="<?php echo url('pages/products.php?sort=newest'); ?>" class="hp-btn-ghost">
                                        Xem mới nhất <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                                <div class="hp-hero-stats">
                                    <div class="hp-stat"><strong>500+</strong><span>Mẫu giày</span></div>
                                    <div class="hp-stat-divider"></div>
                                    <div class="hp-stat"><strong>50+</strong><span>Thương hiệu</span></div>
                                    <div class="hp-stat-divider"></div>
                                    <div class="hp-stat"><strong>10K+</strong><span>Khách hàng</span></div>
                                </div>
                            </div>
                            <div class="col-lg-6 hp-hero-visual d-none d-lg-flex">
                                <div class="hp-hero-ring hp-hero-ring--1"></div>
                                <div class="hp-hero-ring hp-hero-ring--2"></div>
                                <div class="hp-hero-shoe-wrap">
                                    <i class="fas fa-shoe-prints hp-hero-shoe-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 2 — Giày thể thao -->
            <div class="carousel-item hp-slide hp-slide--2">
                <div class="hp-slide-overlay"></div>
                <div class="container">
                    <div class="hp-slide-inner">
                        <div class="row align-items-center w-100">
                            <div class="col-lg-6 hp-hero-text">
                                <span class="hp-hero-tag">⚡ Thể thao chính hãng</span>
                                <h1 class="hp-hero-title">
                                    Giày Thể Thao<br>
                                    <span class="hp-hero-title-accent">Đỉnh Cao Hiệu Suất</span>
                                </h1>
                                <p class="hp-hero-desc">Nike, Adidas, Puma, New Balance — Đầy đủ các thương hiệu hàng đầu thế giới. Cam kết chính hãng 100%.</p>
                                <div class="hp-hero-actions">
                                    <a href="<?php echo url('pages/products.php?category=giay-the-thao'); ?>" class="btn-wink btn-wink-lg">
                                        <i class="fas fa-fire"></i> Khám Phá Ngay
                                    </a>
                                    <a href="<?php echo url('pages/products.php'); ?>" class="hp-btn-ghost">
                                        Tất cả sản phẩm <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6 hp-hero-visual d-none d-lg-flex">
                                <div class="hp-hero-ring hp-hero-ring--1"></div>
                                <div class="hp-hero-ring hp-hero-ring--2"></div>
                                <div class="hp-hero-shoe-wrap">
                                    <i class="fas fa-running hp-hero-shoe-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 3 — Flash Sale -->
            <div class="carousel-item hp-slide hp-slide--3">
                <div class="hp-slide-overlay"></div>
                <div class="container">
                    <div class="hp-slide-inner">
                        <div class="row align-items-center w-100">
                            <div class="col-lg-6 hp-hero-text">
                                <span class="hp-hero-tag hp-hero-tag--sale">🔥 Flash Sale</span>
                                <h1 class="hp-hero-title">
                                    Giảm Đến<br>
                                    <span class="hp-hero-title-accent hp-hero-title-accent--sale">50% OFF</span>
                                </h1>
                                <p class="hp-hero-desc">Chương trình khuyến mãi cực hot — Số lượng có hạn, nhanh tay đặt ngay để không bỏ lỡ!</p>
                                <div class="hp-hero-actions">
                                    <a href="<?php echo url('pages/vouchers.php'); ?>" class="btn-wink btn-wink-lg hp-btn-sale">
                                        <i class="fas fa-tags"></i> Xem Khuyến Mãi
                                    </a>
                                    <a href="<?php echo url('pages/products.php?sort=sale'); ?>" class="hp-btn-ghost">
                                        Sản phẩm sale <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6 hp-hero-visual d-none d-lg-flex">
                                <div class="hp-hero-ring hp-hero-ring--1 hp-hero-ring--sale"></div>
                                <div class="hp-hero-ring hp-hero-ring--2 hp-hero-ring--sale"></div>
                                <div class="hp-hero-shoe-wrap">
                                    <i class="fas fa-percentage hp-hero-shoe-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <button class="carousel-control-prev hp-carousel-btn hp-carousel-btn--prev" type="button" data-bs-target="#heroBanner" data-bs-slide="prev">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="carousel-control-next hp-carousel-btn hp-carousel-btn--next" type="button" data-bs-target="#heroBanner" data-bs-slide="next">
            <i class="fas fa-chevron-right"></i>
        </button>

        <!-- Indicators -->
        <div class="hp-carousel-dots">
            <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="0" class="hp-dot active"></button>
            <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="1" class="hp-dot"></button>
            <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="2" class="hp-dot"></button>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════
     FEATURES BAR — Glassmorphism icons
     ═══════════════════════════════════════ -->
<section class="hp-features-wrap">
    <div class="container">
        <div class="hp-features-grid reveal-stagger">
            <div class="hp-feat">
                <div class="hp-feat-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="hp-feat-text">
                    <strong>Miễn phí vận chuyển</strong>
                    <span>Đơn hàng từ 300.000đ</span>
                </div>
            </div>
            <div class="hp-feat">
                <div class="hp-feat-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="hp-feat-text">
                    <strong>Chính hãng 100%</strong>
                    <span>Cam kết chất lượng</span>
                </div>
            </div>
            <div class="hp-feat">
                <div class="hp-feat-icon">
                    <i class="fas fa-undo-alt"></i>
                </div>
                <div class="hp-feat-text">
                    <strong>Đổi trả 30 ngày</strong>
                    <span>Miễn phí đổi trả</span>
                </div>
            </div>
            <div class="hp-feat">
                <div class="hp-feat-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <div class="hp-feat-text">
                    <strong>Hỗ trợ 24/7</strong>
                    <span>Tư vấn miễn phí</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════
     CATEGORIES — Modern card grid
     ═══════════════════════════════════════ -->
<?php if (!empty($categories)): ?>
<section class="hp-section">
    <div class="container">
        <div class="hp-section-head reveal-up">
            <div class="hp-section-tag">Danh Mục</div>
            <h2 class="hp-section-title">Khám Phá Theo Phong Cách</h2>
            <p class="hp-section-sub">Tìm kiếm đôi giày hoàn hảo cho từng dịp của bạn</p>
        </div>
        <div class="row g-3 justify-content-center reveal-stagger">
            <?php
            $catConfig = [
                ['icon' => 'fas fa-running',    'color' => '#ff5e00', 'bg' => '#fff6f0'],
                ['icon' => 'fas fa-shoe-prints', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
                ['icon' => 'fas fa-star',        'color' => '#f59e0b', 'bg' => '#fffbeb'],
                ['icon' => 'fas fa-briefcase',   'color' => '#0ea5e9', 'bg' => '#f0f9ff'],
                ['icon' => 'fas fa-hiking',      'color' => '#10b981', 'bg' => '#ecfdf5'],
                ['icon' => 'fas fa-tshirt',      'color' => '#ec4899', 'bg' => '#fdf2f8'],
            ];
            foreach ($categories as $index => $category):
                $cfg = $catConfig[$index % count($catConfig)];
            ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="<?php echo url('pages/products.php?category=' . e($category['slug'])); ?>"
                   class="hp-cat-card"
                   style="--cat-color:<?php echo $cfg['color']; ?>; --cat-bg:<?php echo $cfg['bg']; ?>">
                    <div class="hp-cat-icon">
                        <i class="<?php echo $cfg['icon']; ?>"></i>
                    </div>
                    <span class="hp-cat-name"><?php echo e($category['name']); ?></span>
                    <div class="hp-cat-arrow"><i class="fas fa-arrow-right"></i></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     NEW PRODUCTS
     ═══════════════════════════════════════ -->
<?php if (!empty($newProducts)): ?>
<section class="hp-section hp-section--alt">
    <div class="container">
        <div class="hp-section-head reveal-up">
            <div class="hp-section-tag hp-section-tag--new">✦ Mới Nhất</div>
            <h2 class="hp-section-title">Sản Phẩm Mới</h2>
            <p class="hp-section-sub">Cập nhật những mẫu giày mới nhất, hot nhất mùa này</p>
        </div>
        <div class="row g-4 reveal-stagger">
            <?php foreach ($newProducts as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="hp-section-cta reveal-up">
            <a href="<?php echo url('pages/products.php?sort=newest'); ?>" class="btn-wink-outline">
                Xem tất cả sản phẩm mới <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     BANNER CTA — Mid-page promo strip
     ═══════════════════════════════════════ -->
<section class="hp-promo-strip">
    <div class="hp-promo-strip-bg"></div>
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-md-8 reveal-up">
                <div class="hp-promo-label">Ưu đãi đặc biệt</div>
                <h3 class="hp-promo-title">Mua 2 đôi — Giảm thêm 15%</h3>
                <p class="hp-promo-desc">Áp dụng cho toàn bộ sản phẩm chính hãng. Không giới hạn số lần sử dụng.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0 reveal-up">
                <a href="<?php echo url('pages/vouchers.php'); ?>" class="hp-promo-btn">
                    <i class="fas fa-ticket-alt me-2"></i>Xem Voucher
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════
     BEST SELLING
     ═══════════════════════════════════════ -->
<?php if (!empty($bestSellingProducts)): ?>
<section class="hp-section">
    <div class="container">
        <div class="hp-section-head reveal-up">
            <div class="hp-section-tag hp-section-tag--hot">🔥 Bán Chạy</div>
            <h2 class="hp-section-title">Sản Phẩm Bán Chạy</h2>
            <p class="hp-section-sub">Được hàng nghìn khách hàng tin tưởng và lựa chọn</p>
        </div>
        <div class="row g-4 reveal-stagger">
            <?php foreach ($bestSellingProducts as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="hp-section-cta reveal-up">
            <a href="<?php echo url('pages/products.php?sort=best_selling'); ?>" class="btn-wink-outline">
                Xem tất cả sản phẩm bán chạy <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     FEATURED PRODUCTS
     ═══════════════════════════════════════ -->
<?php if (!empty($featuredProducts)): ?>
<section class="hp-section hp-section--alt">
    <div class="container">
        <div class="hp-section-head reveal-up">
            <div class="hp-section-tag hp-section-tag--feat">⭐ Nổi Bật</div>
            <h2 class="hp-section-title">Sản Phẩm Nổi Bật</h2>
            <p class="hp-section-sub">Lựa chọn hàng đầu từ WinK Shoe Store</p>
        </div>
        <div class="row g-4 reveal-stagger">
            <?php foreach ($featuredProducts as $product): ?>
                <?php include __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <div class="hp-section-cta reveal-up">
            <a href="<?php echo url('pages/products.php?sort=featured'); ?>" class="btn-wink-outline">
                Xem tất cả sản phẩm nổi bật <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     BRANDS MARQUEE — Infinite scroll
     ═══════════════════════════════════════ -->
<?php if (!empty($brands)): ?>
<section class="hp-section hp-brands-section">
    <div class="container">
        <div class="hp-section-head reveal-up">
            <div class="hp-section-tag">Thương Hiệu</div>
            <h2 class="hp-section-title">Đối Tác Thương Hiệu</h2>
            <p class="hp-section-sub">Các thương hiệu giày hàng đầu thế giới</p>
        </div>
    </div>
    <div class="hp-marquee-wrap">
        <div class="hp-marquee-track">
            <?php foreach ($brands as $brand): ?>
            <a href="<?php echo url('pages/products.php?brand=' . e($brand['slug'])); ?>"
               class="hp-brand-chip" title="<?php echo e($brand['name']); ?>">
                <?php if (!empty($brand['logo'])): ?>
                    <img src="<?php echo UPLOAD_URL . '/brands/' . e($brand['logo']); ?>"
                         alt="<?php echo e($brand['name']); ?>">
                <?php else: ?>
                    <span><?php echo e($brand['name']); ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <!-- Duplicate for seamless loop -->
            <?php foreach ($brands as $brand): ?>
            <a href="<?php echo url('pages/products.php?brand=' . e($brand['slug'])); ?>"
               class="hp-brand-chip" title="<?php echo e($brand['name']); ?>">
                <?php if (!empty($brand['logo'])): ?>
                    <img src="<?php echo UPLOAD_URL . '/brands/' . e($brand['logo']); ?>"
                         alt="<?php echo e($brand['name']); ?>">
                <?php else: ?>
                    <span><?php echo e($brand['name']); ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════════════════════════════════════
     HOMEPAGE JS — Scroll reveal & animations
     ═══════════════════════════════════════ -->
<script>
(function () {
    'use strict';

    if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.reveal-up, .reveal-item').forEach(function(el) {
            el.classList.add('is-visible');
        });
        return;
    }

    /* ── Single observer cho .reveal-up ── */
    const upObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            upObserver.unobserve(entry.target);
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

    document.querySelectorAll('.reveal-up').forEach(function(el) {
        upObserver.observe(el);
    });

    /* ── Stagger observer cho .reveal-stagger containers ── */
    document.querySelectorAll('.reveal-stagger').forEach(function(parent) {
        var kids = Array.from(parent.children);
        kids.forEach(function(kid) { kid.classList.add('reveal-item'); });

        var staggerObs = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (!entry.isIntersecting) return;
                var idx = kids.indexOf(entry.target);
                entry.target.style.transitionDelay = (Math.max(0, idx) * 70) + 'ms';
                entry.target.classList.add('is-visible');
                staggerObs.unobserve(entry.target);
            });
        }, { threshold: 0.05, rootMargin: '0px 0px -20px 0px' });

        kids.forEach(function(kid) { staggerObs.observe(kid); });
    });

    /* ── Hero text animation on slide change ── */
    var heroBanner = document.getElementById('heroBanner');
    if (heroBanner) {
        var animateSlide = function(slideEl) {
            var texts = slideEl.querySelectorAll('.hp-hero-tag, .hp-hero-title, .hp-hero-desc, .hp-hero-actions, .hp-hero-stats');
            texts.forEach(function(el, i) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                setTimeout(function() {
                    el.style.transition = 'opacity 0.55s ease, transform 0.55s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 150 + i * 110);
            });
        };
        var activeSlide = heroBanner.querySelector('.carousel-item.active');
        if (activeSlide) setTimeout(function() { animateSlide(activeSlide); }, 200);
        heroBanner.addEventListener('slid.bs.carousel', function(e) { animateSlide(e.relatedTarget); });
    }

    /* ── Parallax tilt trên category cards ── */
    document.querySelectorAll('.hp-cat-card').forEach(function(card) {
        card.addEventListener('mousemove', function(e) {
            var r = card.getBoundingClientRect();
            var x = ((e.clientX - r.left) / r.width - 0.5) * 14;
            var y = ((e.clientY - r.top) / r.height - 0.5) * 14;
            card.style.transform = 'perspective(600px) rotateY(' + x + 'deg) rotateX(' + (-y) + 'deg) translateY(-6px)';
        });
        card.addEventListener('mouseleave', function() {
            card.style.transform = '';
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
