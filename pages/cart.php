<?php
/** Trang giỏ hàng - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Lấy giỏ hàng
$stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = ?');
$stmt->execute([$userId]);
$cart = $stmt->fetch();

$cartItems = [];
$totalAmount = 0;

if ($cart) {
    $stmt = $pdo->prepare('
        SELECT ci.id, ci.quantity, ci.price,
               p.id as product_id, p.name, p.slug,
               pv.size, pv.color, pv.stock_quantity,
               p.thumbnail as image_path
        FROM cart_items ci
        INNER JOIN products p ON ci.product_id = p.id
        INNER JOIN product_variants pv ON ci.variant_id = pv.id
        WHERE ci.cart_id = ?
        ORDER BY ci.created_at DESC
    ');
    $stmt->execute([$cart['id']]);
    $cartItems = $stmt->fetchAll();

    foreach ($cartItems as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }
}

$pageTitle = 'Giỏ hàng - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>
<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <h4 class="fw-bold mb-4"><i class="fas fa-shopping-cart me-2"></i>Giỏ hàng (<?php echo count($cartItems); ?> sản phẩm)</h4>
        
        <?php if (!empty($cartItems)): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="bg-white rounded-3 shadow-sm overflow-hidden">
                    <table class="table table-hover mb-0">
                        <thead style="background:var(--gray-100);">
                            <tr>
                                <th colspan="2">Sản phẩm</th>
                                <th>Đơn giá</th>
                                <th>Số lượng</th>
                                <th>Thành tiền</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                            <tr id="cartRow-<?php echo $item['id']; ?>">
                                <td style="width:80px;">
                                    <img src="<?php echo !empty($item['image_path']) ? PRODUCT_UPLOAD_URL . '/' . e($item['image_path']) : asset('images/default/no-product.png'); ?>" 
                                         style="width:65px;height:65px;object-fit:cover;border-radius:8px;">
                                </td>
                                <td>
                                    <a href="<?php echo url('pages/product_detail.php?slug=' . e($item['slug'])); ?>" class="fw-bold" style="color:var(--dark);font-size:14px;"><?php echo e($item['name']); ?></a>
                                    <br><small class="text-muted">Size: <?php echo e($item['size']); ?> | Màu: <?php echo e($item['color']); ?></small>
                                </td>
                                <td class="fw-bold" style="color:var(--primary);"><?php echo formatPrice($item['price']); ?></td>
                                <td>
                                    <div class="cart-item-qty">
                                        <button onclick="updateCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)">−</button>
                                        <input type="number" value="<?php echo $item['quantity']; ?>" readonly style="width:36px;height:28px;">
                                        <button onclick="updateCartQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">+</button>
                                    </div>
                                </td>
                                <td class="fw-bold"><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                                <td>
                                    <button onclick="if(confirm('Xóa sản phẩm này?')) removeCartItem(<?php echo $item['id']; ?>)" class="btn btn-sm text-danger" title="Xóa">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="bg-white rounded-3 shadow-sm p-4" style="position:sticky;top:90px;">
                    <h5 class="fw-bold mb-3">Tóm tắt đơn hàng</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính:</span>
                        <span class="fw-bold"><?php echo formatPrice($totalAmount); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển:</span>
                        <span class="text-muted">Tính khi thanh toán</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Tổng cộng:</strong>
                        <strong style="color:var(--primary);font-size:20px;"><?php echo formatPrice($totalAmount); ?></strong>
                    </div>
                    <a href="<?php echo url('pages/checkout.php'); ?>" class="btn-wink w-100 justify-content-center mb-2">
                        <i class="fas fa-credit-card me-1"></i> Tiến hành thanh toán
                    </a>
                    <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink-outline w-100 justify-content-center">
                        <i class="fas fa-arrow-left me-1"></i> Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                <h5>Giỏ hàng trống</h5>
                <p class="text-muted">Hãy thêm sản phẩm yêu thích vào giỏ hàng!</p>
                <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink mt-2">Mua sắm ngay</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Override to reload page when cart changes
const origUpdateCart = window.updateCartQuantity;
window.updateCartQuantity = function(id, qty) {
    if (qty < 1) { if(confirm('Xóa sản phẩm này?')) removeCartItemAndReload(id); return; }
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('cart_item_id', id);
    formData.append('quantity', qty);
    fetch(BASE_URL + '/ajax/cart_actions.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(d => { if(d.success) location.reload(); else showToast('error', d.message); });
};

function removeCartItemAndReload(id) {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_item_id', id);
    fetch(BASE_URL + '/ajax/cart_actions.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(d => { location.reload(); });
}
// Also override removeCartItem for the delete button
window.removeCartItem = function(id) { removeCartItemAndReload(id); };
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
