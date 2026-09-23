<?php
/**
 * Chi tiết sản phẩm - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

$pdo = getDBConnection();
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    setFlashMessage('error', 'Sản phẩm không tồn tại.');
    redirect(url('pages/products.php'));
}

// Lấy thông tin sản phẩm
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug, b.name as brand_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE p.slug = ? AND p.status = 'active'
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    setFlashMessage('error', 'Sản phẩm không tồn tại.');
    redirect(url('pages/products.php'));
}

// Lấy hình ảnh sản phẩm từ thumbnail và images (JSON)
$images = [];
if (!empty($product['thumbnail'])) {
    $images[] = ['image_path' => $product['thumbnail'], 'is_primary' => 1];
}
if (!empty($product['images'])) {
    $extraImages = json_decode($product['images'], true);
    if (is_array($extraImages)) {
        foreach ($extraImages as $img) {
            $images[] = ['image_path' => $img, 'is_primary' => 0];
        }
    }
}

// Lấy biến thể (size + color)
$stmtVariants = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY color, CAST(size AS UNSIGNED)");
$stmtVariants->execute([$product['id']]);
$variants = $stmtVariants->fetchAll();

// Nhóm biến thể theo màu và size
$colors = [];
$allSizes = [];
foreach ($variants as $v) {
    $colors[$v['color']][] = $v;
    $allSizes[$v['size']] = true;
}
$allSizes = array_keys($allSizes);
sort($allSizes, SORT_NUMERIC);

// Lấy đánh giá
$stmtReviews = $pdo->prepare("
    SELECT pr.*, u.full_name, u.avatar
    FROM product_reviews pr
    INNER JOIN users u ON pr.user_id = u.id
    WHERE pr.product_id = ?
    ORDER BY pr.created_at DESC
    LIMIT 10
");
$stmtReviews->execute([$product['id']]);
$reviews = $stmtReviews->fetchAll();

// Sản phẩm liên quan (cùng danh mục)
$stmtRelated = $pdo->prepare("
    SELECT p.*, p.thumbnail as primary_image, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER BY RAND()
    LIMIT 4
");
$stmtRelated->execute([$product['category_id'], $product['id']]);
$relatedProducts = $stmtRelated->fetchAll();

// Giá hiển thị
$displayPrice = $product['sale_price'] ?: $product['price'];
$hasDiscount = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
$discountPercent = $hasDiscount ? round(100 - ($product['sale_price'] / $product['price'] * 100)) : 0;

// Kiểm tra wishlist
$isInWishlist = false;
if (isLoggedIn()) {
    $stmtWl = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmtWl->execute([getCurrentUserId(), $product['id']]);
    $isInWishlist = (bool)$stmtWl->fetch();
}

$pageTitle = e($product['name']) . ' - WinK Shoe Store';
$pageDescription = mb_substr(strip_tags($product['description'] ?? ''), 0, 160);
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div class="wink-breadcrumb">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="<?php echo url('pages/products.php'); ?>">Sản phẩm</a></li>
                <?php if ($product['category_name']): ?>
                    <li class="breadcrumb-item"><a href="<?php echo url('pages/products.php?category=' . e($product['category_slug'])); ?>"><?php echo e($product['category_name']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo e($product['name']); ?></li>
            </ol>
        </nav>
    </div>
</div>

<section class="product-detail-section">
    <div class="container">
        <div class="row g-4">
            <!-- Gallery -->
            <div class="col-lg-6">
                <div class="product-gallery">
                    <div class="product-main-image">
                        <img id="mainImage" 
                             src="<?php echo !empty($images) ? PRODUCT_UPLOAD_URL . '/' . $images[0]['image_path'] : asset('images/default/no-product.png'); ?>" 
                             alt="<?php echo e($product['name']); ?>">
                    </div>
                    <?php if (count($images) > 1): ?>
                    <div class="product-thumbnails">
                        <?php foreach ($images as $index => $img): ?>
                        <div class="product-thumb <?php echo $index === 0 ? 'active' : ''; ?>" 
                             onclick="changeMainImage(this, '<?php echo PRODUCT_UPLOAD_URL . '/' . e($img['image_path']); ?>')">
                            <img src="<?php echo PRODUCT_UPLOAD_URL . '/' . e($img['image_path']); ?>" alt="Ảnh <?php echo $index + 1; ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="product-info">
                    <h1 class="product-title"><?php echo e($product['name']); ?></h1>
                    
                    <!-- Meta -->
                    <div class="product-meta">
                        <?php if ($product['brand_name']): ?>
                            <span><i class="fas fa-tag me-1"></i> <?php echo e($product['brand_name']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-folder me-1"></i> <?php echo e($product['category_name']); ?></span>
                        <?php if ($product['total_reviews'] > 0): ?>
                            <span class="d-flex align-items-center gap-1">
                                <i class="fas fa-star" style="color:#ffc107;"></i>
                                <?php echo $product['avg_rating']; ?>/5 
                                (<?php echo $product['total_reviews']; ?> đánh giá)
                            </span>
                        <?php endif; ?>
                        <span><i class="fas fa-shopping-cart me-1"></i> Đã bán <?php echo $product['total_sold']; ?></span>
                    </div>
                    
                    <!-- Price -->
                    <div class="product-price-box">
                        <span class="price-current"><?php echo formatPrice($displayPrice); ?></span>
                        <?php if ($hasDiscount): ?>
                            <span class="price-original"><?php echo formatPrice($product['price']); ?></span>
                            <span class="price-discount">-<?php echo $discountPercent; ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Color Selector -->
                    <?php if (!empty($colors)): ?>
                    <div class="variant-selector">
                        <div class="variant-label">Màu sắc: <span id="selectedColorName"><?php echo e(array_key_first($colors)); ?></span></div>
                        <div class="color-options">
                            <?php $firstColor = true; foreach ($colors as $colorName => $colorVariants): ?>
                            <label class="color-option">
                                <input type="radio" name="color" value="<?php echo e($colorName); ?>" 
                                       <?php echo $firstColor ? 'checked' : ''; ?>
                                       onchange="selectColor('<?php echo e($colorName); ?>')">
                                <span><?php echo e($colorName); ?></span>
                            </label>
                            <?php $firstColor = false; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Size Selector -->
                    <?php if (!empty($allSizes)): ?>
                    <div class="variant-selector">
                        <div class="variant-label d-flex justify-content-between">
                            <span>Size: <span id="selectedSizeName">Chọn size</span></span>
                            <a href="#sizeGuide" data-bs-toggle="modal" style="font-size:13px;"><i class="fas fa-ruler me-1"></i>Hướng dẫn chọn size</a>
                        </div>
                        <div class="size-options" id="sizeOptions">
                            <!-- Sizes sẽ được cập nhật bằng JS khi chọn màu -->
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quantity -->
                    <div class="variant-selector">
                        <div class="variant-label">Số lượng: <span class="text-muted" id="stockInfo" style="font-size:13px;"></span></div>
                        <div class="qty-selector">
                            <button type="button" onclick="changeQty(-1)">−</button>
                            <input type="number" id="qtyInput" value="1" min="1" max="1" readonly>
                            <button type="button" onclick="changeQty(1)">+</button>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="product-actions-detail">
                        <button class="btn-wink" onclick="addProductToCart()" id="btnAddToCart">
                            <i class="fas fa-shopping-cart"></i> Thêm vào giỏ
                        </button>
                        <button class="btn-wink-outline <?php echo $isInWishlist ? 'wishlist-active-btn' : ''; ?>" 
                                onclick="toggleWishlist(<?php echo $product['id']; ?>, this)" id="btnWishlist"
                                style="flex: 0 0 auto; padding: 13px 18px;">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    
                    <!-- Description -->
                    <?php if (!empty($product['description'])): ?>
                    <div class="mt-4 pt-3" style="border-top: 1px solid var(--gray-200);">
                        <h6 class="fw-bold mb-2">Mô tả sản phẩm</h6>
                        <div style="font-size:14px;line-height:1.7;color:var(--gray-700);">
                            <?php echo nl2br(e($product['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Reviews Section -->
        <div class="mt-5">
            <div class="bg-white rounded-3 p-4 shadow-sm">
                <h4 class="fw-bold mb-4">
                    <i class="fas fa-star me-2" style="color:#ffc107;"></i>
                    Đánh giá sản phẩm (<?php echo $product['total_reviews']; ?>)
                </h4>
                
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="d-flex align-items-start gap-3">
                            <img class="review-avatar" 
                                 src="<?php echo !empty($review['avatar']) ? AVATAR_UPLOAD_URL . '/' . e($review['avatar']) : asset('images/default/default-avatar.png'); ?>" 
                                 alt="Avatar">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="review-user"><?php echo e($review['full_name']); ?></span>
                                    <span class="review-date"><?php echo formatDate($review['created_at']); ?></span>
                                </div>
                                <div class="star-rating mt-1">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'empty'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if (!empty($review['content'])): ?>
                                    <div class="review-content"><?php echo nl2br(e($review['content'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($review['image'])): ?>
                                    <img class="review-image" src="<?php echo REVIEW_UPLOAD_URL . '/' . e($review['image']); ?>" alt="Ảnh đánh giá">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-4">Chưa có đánh giá nào cho sản phẩm này.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="related-products">
            <div class="section-heading">
                <div class="section-line"></div>
                <h2>Sản Phẩm Liên Quan</h2>
            </div>
            <div class="row g-4">
            <?php $originalProduct = $product; ?>
            <?php foreach ($relatedProducts as $product): ?>
                <?php include dirname(__DIR__) . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
            <?php $product = $originalProduct; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Size Guide Modal -->
<div class="modal fade" id="sizeGuide" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ruler me-2"></i>Hướng Dẫn Chọn Size</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="size-guide-table table table-bordered">
                    <thead>
                        <tr><th>Size VN</th><th>Size US</th><th>Size EU</th><th>Chiều dài (cm)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>36</td><td>4</td><td>36</td><td>22.5</td></tr>
                        <tr><td>37</td><td>5</td><td>37</td><td>23.0</td></tr>
                        <tr><td>38</td><td>5.5</td><td>38</td><td>23.5</td></tr>
                        <tr><td>39</td><td>6.5</td><td>39</td><td>24.5</td></tr>
                        <tr><td>40</td><td>7</td><td>40</td><td>25.0</td></tr>
                        <tr><td>41</td><td>8</td><td>41</td><td>25.5</td></tr>
                        <tr><td>42</td><td>9</td><td>42</td><td>26.5</td></tr>
                        <tr><td>43</td><td>10</td><td>43</td><td>27.0</td></tr>
                        <tr><td>44</td><td>10.5</td><td>44</td><td>27.5</td></tr>
                    </tbody>
                </table>
                <p class="text-muted mt-2" style="font-size:13px;">
                    <i class="fas fa-info-circle me-1"></i> 
                    Đo chiều dài bàn chân và so sánh với bảng size để chọn size phù hợp nhất.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Dữ liệu biến thể từ PHP
const variants = <?php echo json_encode($variants); ?>;
let selectedColor = '<?php echo e(array_key_first($colors)); ?>';
let selectedVariantId = null;

// Khởi tạo
document.addEventListener('DOMContentLoaded', function() {
    selectColor(selectedColor);
});

function changeMainImage(thumb, src) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.product-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

function selectColor(color) {
    selectedColor = color;
    document.getElementById('selectedColorName').textContent = color;
    selectedVariantId = null;
    document.getElementById('selectedSizeName').textContent = 'Chọn size';
    document.getElementById('stockInfo').textContent = '';
    document.getElementById('qtyInput').value = 1;
    document.getElementById('qtyInput').max = 1;
    
    // Cập nhật danh sách size cho màu đã chọn
    const sizeContainer = document.getElementById('sizeOptions');
    const colorVariants = variants.filter(v => v.color === color);
    
    let html = '';
    colorVariants.forEach(v => {
        const outOfStock = v.stock_quantity <= 0;
        html += `
            <label class="size-opt ${outOfStock ? 'out-of-stock' : ''}">
                <input type="radio" name="size" value="${v.size}" data-variant-id="${v.id}" 
                       data-stock="${v.stock_quantity}" 
                       ${outOfStock ? 'disabled' : ''}
                       onchange="selectSize(this)">
                <span>${v.size}</span>
            </label>
        `;
    });
    sizeContainer.innerHTML = html;
}

function selectSize(input) {
    selectedVariantId = input.dataset.variantId;
    const stock = parseInt(input.dataset.stock);
    document.getElementById('selectedSizeName').textContent = input.value;
    document.getElementById('stockInfo').textContent = `(Còn ${stock} sản phẩm)`;
    document.getElementById('qtyInput').max = stock;
    document.getElementById('qtyInput').value = 1;
}

function changeQty(delta) {
    const input = document.getElementById('qtyInput');
    let val = parseInt(input.value) + delta;
    const max = parseInt(input.max);
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

function addProductToCart() {
    if (!selectedVariantId) {
        showToast('warning', 'Vui lòng chọn size trước khi thêm vào giỏ.');
        return;
    }
    const qty = parseInt(document.getElementById('qtyInput').value);
    addToCart(<?php echo $product['id']; ?>, parseInt(selectedVariantId), qty);
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
