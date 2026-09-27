<?php
/**
 * Navbar - WinK Shoe Store
 * Thanh điều hướng chính
 */
$cartCount = isLoggedIn() ? getCartItemCount() : 0;
$wishlistCount = isLoggedIn() ? getWishlistCount() : 0;
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Top Bar -->
<div class="wink-topbar d-none d-md-block">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span><i class="fas fa-phone-alt me-1"></i> <?php echo STORE_PHONE; ?></span>
                <span><i class="fas fa-envelope me-1"></i> <?php echo STORE_EMAIL; ?></span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="<?php echo url('pages/orders.php'); ?>"><i class="fas fa-truck me-1"></i> Tra cứu đơn hàng</a>
                <a href="<?php echo url('pages/chat.php'); ?>"><i class="fas fa-headset me-1"></i> Hỗ trợ</a>
            </div>
        </div>
    </div>
</div>

<!-- Main Header -->
<header class="wink-header sticky-top">
    <div class="wink-navbar py-2">
        <div class="container">
            <div class="d-flex align-items-center justify-content-between gap-3">
                
                <!-- Left: Logo & Nav Links -->
                <div class="d-flex align-items-center gap-3 gap-lg-4">
                    <!-- Logo -->
                    <a href="<?php echo url('index.php'); ?>" class="wink-logo text-decoration-none">
                        Win<span>K</span>
                    </a>

                    <!-- Nav Links for Desktop -->
                    <nav class="wink-nav-menu d-none d-md-flex align-items-center gap-1 gap-lg-2">
                        <a href="<?php echo url('index.php'); ?>" class="nav-item-link <?php echo ($currentPage == 'index.php' && !isset($_GET['page'])) ? 'active' : ''; ?>">
                            <i class="fas fa-home me-1"></i>Trang chủ
                        </a>
                        <a href="<?php echo url('pages/products.php'); ?>" class="nav-item-link <?php echo ($currentPage == 'products.php') ? 'active' : ''; ?>">
                            <i class="fas fa-shoe-prints me-1"></i>Sản phẩm
                        </a>
                        <a href="<?php echo url('pages/orders.php'); ?>" class="nav-item-link <?php echo ($currentPage == 'orders.php') ? 'active' : ''; ?>">
                            <i class="fas fa-box me-1"></i>Đơn hàng
                        </a>
                        <a href="<?php echo url('pages/vouchers.php'); ?>" class="nav-item-link <?php echo ($currentPage == 'vouchers.php') ? 'active' : ''; ?>">
                            <i class="fas fa-tags me-1"></i>Khuyến mãi
                        </a>
                    </nav>
                </div>
                
                <!-- Center: Search Bar -->
                <form action="<?php echo url('pages/search.php'); ?>" method="GET" class="wink-search flex-grow-1 mx-2 d-none d-lg-block" style="max-width: 360px;">
                    <input type="text" name="q" placeholder="Tìm kiếm giày, thương hiệu..." 
                           value="<?php echo e($_GET['q'] ?? ''); ?>" autocomplete="off">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                
                <!-- Right: Nav Icons & User -->
                <div class="wink-nav-icons d-flex align-items-center gap-2">
                    <!-- Mobile Search Toggle -->
                    <button class="wink-nav-icon d-lg-none" data-bs-toggle="collapse" data-bs-target="#mobileSearch" aria-label="Tìm kiếm">
                        <i class="fas fa-search"></i>
                    </button>
                    
                    <!-- Dark Mode Toggle -->
                    <button class="dark-mode-toggle" id="darkModeToggle" title="Chế độ tối/sáng" aria-label="Chuyển đổi chế độ tối/sáng">
                        <i class="fas fa-moon icon-moon"></i>
                        <i class="fas fa-sun icon-sun"></i>
                    </button>
                    
                    <!-- Wishlist -->
                    <a href="<?php echo url('pages/wishlist.php'); ?>" class="wink-nav-icon" title="Yêu thích" id="navWishlist">
                        <i class="fas fa-heart"></i>
                        <?php if ($wishlistCount > 0): ?>
                            <span class="badge"><?php echo $wishlistCount; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Cart -->
                    <button class="wink-nav-icon" onclick="openCartDrawer()" title="Giỏ hàng" id="navCartBtn">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="badge cart-count-badge" style="<?php echo $cartCount > 0 ? '' : 'display:none'; ?>">
                            <?php echo $cartCount; ?>
                        </span>
                    </button>
                    
                    <!-- User Dropdown -->
                    <?php if (isLoggedIn()): ?>
                        <div class="dropdown">
                            <button class="wink-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="userDropdown">
                                <img src="<?php echo getCurrentUserAvatar(); ?>" alt="Avatar">
                                <span class="d-none d-xl-inline"><?php echo e(getCurrentUserName()); ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                <?php if (isAdmin()): ?>
                                    <li><a class="dropdown-item py-2" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-tachometer-alt me-2 text-primary"></i>Quản trị</a></li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item py-2" href="<?php echo url('pages/profile.php'); ?>"><i class="fas fa-user me-2 text-muted"></i>Tài khoản</a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo url('pages/orders.php'); ?>"><i class="fas fa-box me-2 text-muted"></i>Đơn hàng</a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo url('pages/wishlist.php'); ?>"><i class="fas fa-heart me-2 text-muted"></i>Yêu thích</a></li>
                                <li><a class="dropdown-item py-2" href="<?php echo url('pages/change_password.php'); ?>"><i class="fas fa-key me-2 text-muted"></i>Đổi mật khẩu</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?php echo url('auth/logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Đăng xuất</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo url('auth/login.php'); ?>" class="btn-wink btn-wink-sm">
                            <i class="fas fa-sign-in-alt"></i>
                            <span class="d-none d-md-inline">Đăng nhập</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Mobile Search Collapse -->
            <div class="collapse d-lg-none mt-2" id="mobileSearch">
                <form action="<?php echo url('pages/search.php'); ?>" method="GET" class="wink-search w-100">
                    <input type="text" name="q" placeholder="Tìm kiếm giày, thương hiệu..." 
                           value="<?php echo e($_GET['q'] ?? ''); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>
</header>

<!-- Mobile Bottom Navigation -->
<nav class="wink-mobile-nav d-md-none">
    <a href="<?php echo url('index.php'); ?>" class="<?php echo ($currentPage == 'index.php') ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Trang chủ</span>
    </a>
    <a href="<?php echo url('pages/products.php'); ?>" class="<?php echo ($currentPage == 'products.php') ? 'active' : ''; ?>">
        <i class="fas fa-th-large"></i>
        <span>Sản phẩm</span>
    </a>
    <button onclick="openCartDrawer()">
        <i class="fas fa-shopping-bag"></i>
        <span>Giỏ hàng</span>
        <?php if ($cartCount > 0): ?>
            <span class="mobile-cart-badge cart-count-badge"><?php echo $cartCount; ?></span>
        <?php endif; ?>
    </button>
    <a href="<?php echo url('pages/wishlist.php'); ?>" class="<?php echo ($currentPage == 'wishlist.php') ? 'active' : ''; ?>">
        <i class="fas fa-heart"></i>
        <span>Yêu thích</span>
        <?php if ($wishlistCount > 0): ?>
            <span class="mobile-cart-badge"><?php echo $wishlistCount; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo isLoggedIn() ? url('pages/profile.php') : url('auth/login.php'); ?>">
        <i class="fas fa-user"></i>
        <span><?php echo isLoggedIn() ? 'Tài khoản' : 'Đăng nhập'; ?></span>
    </a>
</nav>

<style>
/* Inline Header Navigation Styling */
.wink-nav-menu .nav-item-link {
    color: var(--gray-700);
    font-weight: 500;
    font-size: 14px;
    padding: 8px 14px;
    border-radius: 20px;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.wink-nav-menu .nav-item-link:hover {
    color: var(--primary);
    background-color: var(--primary-bg);
}

.wink-nav-menu .nav-item-link.active {
    color: var(--primary);
    font-weight: 600;
    background-color: var(--primary-bg);
}

/* Mobile Bottom Navigation */
.wink-mobile-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--white);
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-around;
    padding: 6px 0;
    z-index: 1020;
}

.wink-mobile-nav a,
.wink-mobile-nav button {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    font-size: 10px;
    color: var(--gray-600);
    background: none;
    border: none;
    padding: 4px 8px;
    cursor: pointer;
    text-decoration: none;
    position: relative;
}

.wink-mobile-nav a i,
.wink-mobile-nav button i {
    font-size: 18px;
}

.wink-mobile-nav a.active,
.wink-mobile-nav a:hover {
    color: var(--primary);
}

.mobile-cart-badge {
    position: absolute;
    top: 0;
    right: 0;
    font-size: 9px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

@media (min-width: 768px) {
    .wink-mobile-nav {
        display: none !important;
    }
}

@media (max-width: 767.98px) {
    body {
        padding-bottom: 60px;
    }
}
</style>

