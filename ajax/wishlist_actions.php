<?php
/**
 * Wishlist AJAX Actions - WinK Shoe Store
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
    $productId = (int)($_POST['product_id'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit;
    }
    
    if ($action === 'toggle') {
        // Kiểm tra sản phẩm tồn tại
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại.']);
            exit;
        }
        
        // Kiểm tra đã có trong wishlist chưa
        $stmt = $pdo->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Xóa khỏi wishlist
            $stmt = $pdo->prepare("DELETE FROM wishlists WHERE id = ?");
            $stmt->execute([$existing['id']]);
            $actionDone = 'removed';
        } else {
            // Thêm vào wishlist
            $stmt = $pdo->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$userId, $productId]);
            $actionDone = 'added';
        }
        
        // Đếm số lượng wishlist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
        $stmt->execute([$userId]);
        $count = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'action' => $actionDone,
            'count' => $count
        ]);
    }
}
