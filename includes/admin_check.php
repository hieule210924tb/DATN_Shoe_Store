<?php
/**
 * Kiểm tra quyền Admin - WinK Shoe Store
 * Include file này ở những trang chỉ Admin được truy cập
 */

require_once dirname(__DIR__) . '/config/config.php';

// Kiểm tra đã đăng nhập chưa
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Vui lòng đăng nhập để tiếp tục.');
    redirect(url('auth/login.php'));
}

// Kiểm tra quyền Admin
if (!isAdmin()) {
    setFlashMessage('error', 'Bạn không có quyền truy cập trang này.');
    redirect(url('index.php'));
}

// Kiểm tra tài khoản trong database
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ?");
$stmt->execute([getCurrentUserId()]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'admin' || $user['status'] === 'locked') {
    session_destroy();
    session_start();
    setFlashMessage('error', 'Phiên đăng nhập không hợp lệ.');
    redirect(url('auth/login.php'));
}
