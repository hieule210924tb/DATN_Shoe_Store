<?php
/**
 * Cart Drawer (Sidebar) - WinK Shoe Store
 * Ngăn kéo giỏ hàng bên phải
 */
?>

<!-- Cart Drawer Overlay -->
<div class="cart-drawer-overlay" id="cartDrawerOverlay" onclick="closeCartDrawer()"></div>

<!-- Cart Drawer -->
<div class="cart-drawer" id="cartDrawer">
    <div class="cart-drawer-header">
        <h5><i class="fas fa-shopping-bag me-2"></i>Giỏ hàng (<span id="cartDrawerCount">0</span>)</h5>
        <button class="cart-drawer-close" onclick="closeCartDrawer()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="cart-drawer-body" id="cartDrawerBody">
        <!-- Cart items sẽ được load bằng AJAX -->
        <div class="cart-drawer-empty">
            <i class="fas fa-shopping-bag"></i>
            <p>Giỏ hàng trống</p>
            <a href="<?php echo url('pages/products.php'); ?>" class="btn-wink btn-wink-sm mt-2">
                Mua sắm ngay
            </a>
        </div>
    </div>
    
    <div class="cart-drawer-footer" id="cartDrawerFooter" style="display: none;">
        <div class="cart-drawer-total">
            <span>Tạm tính:</span>
            <span id="cartDrawerTotal">0đ</span>
        </div>
        <a href="<?php echo url('pages/cart.php'); ?>" class="btn-wink-outline w-100 justify-content-center mb-2">
            <i class="fas fa-shopping-cart"></i> Xem giỏ hàng
        </a>
        <a href="<?php echo url('pages/checkout.php'); ?>" class="btn-wink w-100 justify-content-center">
            <i class="fas fa-credit-card"></i> Thanh toán
        </a>
    </div>
</div>
