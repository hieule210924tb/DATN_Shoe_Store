<?php
/**
 * Kiểm tra đăng nhập - WinK Shoe Store
 * Include file này ở những trang yêu cầu đăng nhập
 */

require_once dirname(__DIR__) . '/config/config.php';

// Kiểm tra đã đăng nhập chưa
if (!isLoggedIn()) {
    setFlashMessage('warning', 'Vui lòng đăng nhập để tiếp tục.');
    redirect(url('auth/login.php'));
}

// Kiểm tra tài khoản bị khóa
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([getCurrentUserId()]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'locked') {
    // Xóa session và chuyển về trang đăng nhập
    session_destroy();
    session_start();
    setFlashMessage('error', 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.');
    redirect(url('auth/login.php'));
}
