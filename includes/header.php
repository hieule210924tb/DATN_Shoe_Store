<?php

/**
 * Header - WinK Shoe Store
 * Include ở đầu mỗi trang User
 */
if (!defined('BASE_URL')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
$_cacheVersion = '?v=' . time();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    
    <!-- Dark Mode: Apply IMMEDIATELY to prevent flash (before CSS) -->
    <script>
        (function() {
            var t = localStorage.getItem('wink-theme');
            if (t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo e($pageDescription ?? 'WinK Shoe Store - Cửa hàng giày trực tuyến uy tín, đa dạng mẫu mã, giá cả hợp lý.'); ?>">
    <title><?php echo e($pageTitle ?? 'WinK Shoe Store'); ?></title>
    

    <!-- Favicon -->
    <link rel="icon" type="image/x-con" href="<?php echo asset('images/logo/favicon.ico'); ?>">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo asset('css/style.css') . $_cacheVersion; ?>" rel="stylesheet">
    
    <?php if (!empty($extraCSS)): ?>
        <?php foreach ($extraCSS as $css): ?>
            <link href="<?php echo asset('css/' . $css) . $_cacheVersion; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <?php include __DIR__ . '/sidebar_cart.php'; ?>
    
    <!-- Flash Message -->
    <div class="container mt-3">
        <?php echo displayFlashMessage(); ?>
    </div>
    
    <main>
