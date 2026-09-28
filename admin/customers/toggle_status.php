<?php
/**
 * Admin - Khoá / Mở khoá tài khoản khách hàng (AJAX)
 * WinK Shoe Store
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$pdo        = getDBConnection();
$customerId = (int)($_POST['id'] ?? 0);
$action     = $_POST['action'] ?? '';

if ($customerId <= 0 || !in_array($action, ['lock', 'unlock'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

// Kiểm tra tồn tại & không phải admin
$stmt = $pdo->prepare("SELECT id, full_name, status FROM users WHERE id = ? AND role = 'user'");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

if (!$customer) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng.']);
    exit;
}

$newStatus = ($action === 'lock') ? 'locked' : 'active';

$upd = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
$upd->execute([$newStatus, $customerId]);

$msg = $action === 'lock'
    ? 'Đã khoá tài khoản "' . $customer['full_name'] . '".'
    : 'Đã mở khoá tài khoản "' . $customer['full_name'] . '".';


echo json_encode(['success' => true, 'message' => $msg, 'new_status' => $newStatus]);
