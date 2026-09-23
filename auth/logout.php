<?php
/**
 * Đăng xuất - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

// Xóa toàn bộ session
$_SESSION = [];

// Xóa session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy session
session_destroy();

// Bắt đầu session mới để hiển thị flash message
session_start();
setFlashMessage('success', 'Đăng xuất thành công!');
redirect(url('auth/login.php'));
