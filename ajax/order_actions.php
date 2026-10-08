<?php
/**
 * Order AJAX Actions - WinK Shoe Store
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.', 'login_required' => true]);
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        
        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }
        
        // Kiểm tra đơn hàng thuộc về user và có thể hủy
        $stmt = $pdo->prepare("
            SELECT id, status 
            FROM orders 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại.']);
            exit;
        }
        
        if ($order['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'Đơn hàng này không thể hủy.']);
            exit;
        }
        
        // Cập nhật trạng thái đơn hàng
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Đã hủy đơn hàng thành công.'
        ]);
    }
}
