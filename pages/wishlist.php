<?php
/**
 * Trang danh sách yêu thích - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Lấy danh sách wishlist
$stmt = $pdo->prepare("
    SELECT w.id as wishlist_id, w.created_at,
           p.id, p.name, p.slug, p.price, p.sale_price, p.thumbnail as primary_image,
           p.is_new, p.is_featured, p.avg_rating, p.total_reviews,
           c.name as category_name
    FROM wishlists w
    INNER JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ? AND p.status = 'active'
    ORDER BY w.created_at DESC
");
$stmt->execute([$userId]);
$wishlistItems = $stmt->fetchAll();

$pageTitle = 'Danh sách yêu thích - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>

<div class="wink-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Trang chủ</a></li>
                <li class="breadcrumb-item active">Danh sách yêu thích</li>
            </ol>
        </nav>
    </div>
</div>

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0"><i class="fas fa-heart me-2"></i>Danh sách yêu thích (<?php echo count($wishlistItems); ?> sản phẩm)</h4>
            <?php if (!empty($wishlistItems)): ?>
                <button class="btn btn-sm btn-outline-danger" onclick="clearWishlist()">
                    <i class="fas fa-trash-alt me-1"></i>Xóa tất cả
                </button>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($wishlistItems)): ?>
        <div class="row g-4">
            <?php foreach ($wishlistItems as $product): ?>
                <?php 
                // Tính giá hiển thị
                $displayPrice = $product['sale_price'] ?: $product['price'];
                $hasDiscount = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
                $discountPercent = $hasDiscount ? round(100 - ($product['sale_price'] / $product['price'] * 100)) : 0;
                
                // Ảnh sản phẩm
                $productImage = !empty($product['primary_image']) 
                    ? PRODUCT_UPLOAD_URL . '/' . $product['primary_image'] 
                    : asset('images/default/no-product.png');
                
                // URL chi tiết
                $productUrl = url('pages/product_detail.php?slug=' . e($product['slug']));
                ?>
                <div class="col-6 col-sm-6 col-md-4 col-lg-3">
                    <div class="product-card">
                        <!-- Product Image -->
                        <div class="product-card-img">
                            <a href="<?php echo $productUrl; ?>">
                                <img src="<?php echo $productImage; ?>" alt="<?php echo e($product['name']); ?>" loading="lazy">
                            </a>
                            
                            <!-- Badges -->
                            <div class="product-badge">
                                <?php if ($product['is_new']): ?>
                                    <span class="badge-new">Mới</span>
                                <?php endif; ?>
                                <?php if ($hasDiscount): ?>
                                    <span class="badge-sale">-<?php echo $discountPercent; ?>%</span>
                                <?php endif; ?>
                                <?php if ($product['is_featured']): ?>
                                    <span class="badge-hot">Hot</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Actions on Hover -->
                            <div class="product-actions">
                                <button class="product-action-btn wishlist-active" 
                                        onclick="removeFromWishlist(<?php echo $product['wishlist_id']; ?>, this)" 
                                        title="Xóa khỏi yêu thích">
                                    <i class="fas fa-heart"></i>
                                </button>
                                <a href="<?php echo $productUrl; ?>" class="product-action-btn" title="Xem chi tiết">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo $productUrl; ?>" class="product-action-btn" title="Thêm vào giỏ">
                                    <i class="fas fa-shopping-cart"></i>
                                </a>
                            </div>
                        </div>
                        
                        <!-- Product Info -->
                        <div class="product-card-body">
                            <div class="product-card-category"><?php echo e($product['category_name'] ?? ''); ?></div>
                            <h3 class="product-card-title">
                                <a href="<?php echo $productUrl; ?>"><?php echo e($product['name']); ?></a>
                            </h3>
                            
                            <!-- Rating -->
                            <?php if ($product['total_reviews'] > 0): ?>
                            <div class="product-card-rating">
                                <div class="stars">
                                    <?php 
                                    $rating = $product['avg_rating'];
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= floor($rating)):
                                    ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i - 0.5 <= $rating): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star empty"></i>
                                    <?php 
                                        endif;
                                    endfor; 
                                    ?>
                                </div>
                                <span class="count">(<?php echo $product['total_reviews']; ?>)</span>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Price -->
                            <div class="product-card-price">
                                <span class="current-price"><?php echo formatPrice($displayPrice); ?></span>
                                <?php if ($hasDiscount): ?>
                                    <span class="original-price"><?php echo formatPrice($product['price']); ?></span>
                                    <span class="discount-percent">-<?php echo $discountPercent; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-heart fa-4x text-muted mb-3"></i>
                <h5>Danh sách yêu thích trống</h5>
                <p class="text-muted">Hãy thêm sản phẩm bạn thích vào danh sách yêu thích!</p>
                <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink mt-2">Khám phá sản phẩm</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
function removeFromWishlist(wishlistId, btn) {
    if (!confirm('Xóa sản phẩm này khỏi danh sách yêu thích?')) return;
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('wishlist_id', wishlistId);
    
    fetch(BASE_URL + '/ajax/wishlist_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Đã xóa khỏi danh sách yêu thích.');
            // Xóa card sản phẩm khỏi DOM
            const card = btn.closest('.col-6, .col-sm-6, .col-md-4, .col-lg-3');
            card.style.opacity = '0';
            setTimeout(() => {
                card.remove();
                // Cập nhật số lượng hiển thị
                const countEl = document.querySelector('.fw-bold');
                const currentCount = parseInt(countEl.textContent.match(/\d+/)[0]);
                countEl.innerHTML = '<i class="fas fa-heart me-2"></i>Danh sách yêu thích (' + (currentCount - 1) + ' sản phẩm)';
                
                // Nếu không còn sản phẩm, reload trang
                if (document.querySelectorAll('.product-card').length === 0) {
                    location.reload();
                }
            }, 300);
            
            // Cập nhật badge trên navbar
            const wishlistBadge = document.querySelector('#navWishlist .badge');
            if (wishlistBadge) {
                wishlistBadge.textContent = data.count;
                wishlistBadge.style.display = data.count > 0 ? 'flex' : 'none';
            }
        } else {
            showToast('error', data.message || 'Có lỗi xảy ra.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
    });
}

function clearWishlist() {
    if (!confirm('Bạn có chắc chắn muốn xóa tất cả sản phẩm khỏi danh sách yêu thích?')) return;
    
    const formData = new FormData();
    formData.append('action', 'clear');
    
    fetch(BASE_URL + '/ajax/wishlist_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', 'Đã xóa tất cả khỏi danh sách yêu thích.');
            location.reload();
        } else {
            showToast('error', data.message || 'Có lỗi xảy ra.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
    });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
