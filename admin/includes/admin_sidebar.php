<?php
// sidebar admin
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!-- Main Sidebar -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="<?php echo url('admin/index.php'); ?>" class="brand-link text-center">
        <span class="brand-text font-weight-bold">Win<span style="color:rgba(255,255,255,0.8)">K</span> Admin</span>
    </a>
    
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/index.php'); ?>" class="nav-link <?php echo $currentPage === 'index.php' && $currentDir === 'admin' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>
                
                <!-- Sản phẩm -->
                <li class="nav-item <?php echo $currentDir === 'products' ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo $currentDir === 'products' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-shoe-prints"></i>
                        <p>Sản phẩm <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo url('admin/products/list.php'); ?>" class="nav-link <?php echo $currentPage === 'list.php' && $currentDir === 'products' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i><p>Danh sách</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo url('admin/products/add.php'); ?>" class="nav-link <?php echo $currentPage === 'add.php' && $currentDir === 'products' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i><p>Thêm mới</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Danh mục -->
                <li class="nav-item <?php echo $currentDir === 'categories' ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo $currentDir === 'categories' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-folder"></i>
                        <p>Danh mục <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo url('admin/categories/list.php'); ?>" class="nav-link <?php echo $currentPage === 'list.php' && $currentDir === 'categories' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i><p>Danh sách</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo url('admin/categories/add.php'); ?>" class="nav-link <?php echo $currentPage === 'add.php' && $currentDir === 'categories' ? 'active' : ''; ?>">
                                <i class="far fa-circle nav-icon"></i><p>Thêm mới</p>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- Đơn hàng -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/orders/list.php'); ?>" class="nav-link <?php echo $currentDir === 'orders' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>Đơn hàng</p>
                    </a>
                </li>
                
                <!-- Khách hàng -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/customers/list.php'); ?>" class="nav-link <?php echo $currentDir === 'customers' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Khách hàng</p>
                    </a>
                </li>
                
                <!-- Tồn kho -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/inventory/list.php'); ?>" class="nav-link <?php echo $currentDir === 'inventory' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-warehouse"></i>
                        <p>Tồn kho</p>
                    </a>
                </li>
                
                <!-- Voucher -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/vouchers/list.php'); ?>" class="nav-link <?php echo $currentDir === 'vouchers' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tags"></i>
                        <p>Voucher</p>
                    </a>
                </li>
                
                <!-- Đánh giá -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/reviews/list.php'); ?>" class="nav-link <?php echo $currentDir === 'reviews' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-star"></i>
                        <p>Đánh giá</p>
                    </a>
                </li>
                
                <!-- Chat -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/chat/list.php'); ?>" class="nav-link <?php echo $currentDir === 'chat' ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-comments"></i>
                        <p>Chat</p>
                    </a>
                </li>
                
                <!-- Thống kê -->
                <li class="nav-item">
                    <a href="<?php echo url('admin/statistics/index.php'); ?>" class="nav-link <?php echo $currentDir === 'statistics' ? 'active' : ''; ?>">
                        <i class="nav-icon fa-solid fa-chart-simple"></i>
                        <p>Thống kê</p>
                    </a>
                </li>
                
            </ul>
        </nav>
    </div>
</aside>
