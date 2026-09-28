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

        /* ── Dark Mode Toggle Button ── */
        #dm-toggle {
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 50%;
            background: #e9ecef;
            color: #000000ff;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .25s, color .25s, transform .2s, box-shadow .25s;
            outline: none;
        }
        #dm-toggle:hover {
            background: #dee2e6;
            color: #000000ff;
            transform: scale(1.12) rotate(15deg);
            box-shadow: 0 3px 10px rgba(0,0,0,.15);
        }
        body.dark-mode #dm-toggle {
            background: #f36811;
            color: #fff;
            box-shadow: 0 2px 8px rgba(243,104,17,.5);
        }
        body.dark-mode #dm-toggle:hover {
            background: #d95a0a;
            transform: scale(1.12) rotate(-15deg);
            box-shadow: 0 4px 14px rgba(243,104,17,.6);
        }
    </style>

    <!-- Dark Mode CSS -->
    <link rel="stylesheet" href="<?php echo asset('assets/css/admin-dark.css'); ?>">

    <!-- Anti-flash: áp dụng dark mode trước khi render -->
    <script>
        if (localStorage.getItem('adminDarkMode') === '1') {
            document.documentElement.classList.add('dm-preload');
        }
    </script>
    <style>
        .dm-preload body { background:#000!important; }

        /* ── Ép ghi đè inline style="background:..." trên thead/th ── */
        body.dark-mode thead[style],
        body.dark-mode thead tr[style],
        body.dark-mode th[style] {
            background-color: #1e2235 !important;
            background:       #1e2235 !important;
            color:            #94a3b8 !important;
        }
        body.dark-mode thead,
        body.dark-mode thead tr {
            background-color: #1e2235 !important;
            background:       #1e2235 !important;
        }
        body.dark-mode th {
            background-color: #1e2235 !important;
            background:       #1e2235 !important;
            color:            #94a3b8 !important;
            border-bottom:    2px solid #2d3148 !important;
            border-color:     #2d3148 !important;
        }
        body.dark-mode td {
            color:        #cbd5e1 !important;
            border-color: #2d3148 !important;
        }
        body.dark-mode .table-hover tbody tr:hover {
            background-color: #222540 !important;
        }
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
        <ul class="navbar-nav ml-auto align-items-center">
            <!-- Dark Mode Toggle -->
            <li class="nav-item mr-2">
                <button id="dm-toggle" onclick="toggleDarkMode()" title="Chế độ tối / sáng" aria-label="Toggle dark mode">
                    <i id="dm-icon" class="fas fa-moon"></i>
                </button>
            </li>
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

<script>
(function() {
    if (localStorage.getItem('adminDarkMode') === '1') {
        document.body.classList.add('dark-mode');
    }
})();

function toggleDarkMode() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('adminDarkMode', isDark ? '1' : '0');
    document.getElementById('dm-icon').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
}

document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.body.classList.contains('dark-mode');
    const icon = document.getElementById('dm-icon');
    if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
});
</script>
