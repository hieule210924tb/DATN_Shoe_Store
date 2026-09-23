<?php
/**
 * Product Card Component - WinK Shoe Store
 * Sử dụng biến $product từ trang gọi include
 */

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

// Kiểm tra trong wishlist
$isInWishlist = false;
if (isLoggedIn()) {
    static $wishlistProducts = null;
    if ($wishlistProducts === null) {
        $stmtWl = getDBConnection()->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
        $stmtWl->execute([getCurrentUserId()]);
        $wishlistProducts = $stmtWl->fetchAll(PDO::FETCH_COLUMN);
    }
    $isInWishlist = in_array($product['id'], $wishlistProducts);
}
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
                <button class="product-action-btn <?php echo $isInWishlist ? 'wishlist-active' : ''; ?>" 
                        onclick="toggleWishlist(<?php echo $product['id']; ?>, this)" 
                        title="<?php echo $isInWishlist ? 'Bỏ yêu thích' : 'Yêu thích'; ?>">
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
