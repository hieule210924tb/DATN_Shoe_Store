<?php
// header admin
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Admin - WinK Shoe Store'); ?></title>
    
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .brand-link { background: #f36811 !important; }
        .brand-link .brand-text { color: #fff !important; font-weight: 700; }
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active { background-color: #f36811 !important; }
        .btn-primary { background-color: #f36811 !important; border-color: #f36811 !important; }
        .btn-primary:hover { background-color: #d95a0a !important; border-color: #d95a0a !important; }
        .small-box { border-radius: 10px; }
        .card { border-radius: 10px; border: none; box-shadow: 0 0 15px rgba(0,0,0,0.05); }
        .card-header { border-bottom: 1px solid #eee; }
        .content-wrapper { background: #f4f6f9; }
        .text-primary { color: #f36811 !important; }
        a.text-primary:hover { color: #d95a0a !important; }
        .page-item.active .page-link { background-color: #f36811; border-color: #f36811; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo url('index.php'); ?>" class="nav-link" target="_blank"><i class="fas fa-external-link-alt me-1"></i> Xem website</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                    <i class="fas fa-user-circle mr-1"></i> <?php echo e(getCurrentUserName()); ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="<?php echo url('auth/logout.php'); ?>"><i class="fas fa-sign-out-alt mr-2"></i>Đăng xuất</a>
                </div>
            </li>
        </ul>
    </nav>
    
    <?php include __DIR__ . '/admin_sidebar.php'; ?>
    
    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Flash Message -->
        <div class="container-fluid pt-2">
            <?php echo displayFlashMessage(); ?>
        </div>
