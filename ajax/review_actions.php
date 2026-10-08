<?php
/**
 * Review AJAX Actions - WinK Shoe Store
 * Xử lý gửi đánh giá sản phẩm từ phía người dùng
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để đánh giá.', 'login_required' => true]);
    exit;
}

$pdo    = getDBConnection();
$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// Action: submit_review
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'submit_review') {
    $orderItemId = (int)($_POST['order_item_id'] ?? 0);
    $rating      = (int)($_POST['rating']        ?? 0);
    $content     = trim($_POST['content']        ?? '');

    // --- Validate cơ bản ---
    if ($orderItemId <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        exit;
    }

    // --- Kiểm tra order_item thuộc về user, đơn đã giao, chưa đánh giá ---
    $stmt = $pdo->prepare("
        SELECT oi.id, oi.product_id, oi.is_reviewed, o.status
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE oi.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$orderItemId, $userId]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại trong đơn hàng của bạn.']);
        exit;
    }
    if ($item['status'] !== 'delivered') {
        echo json_encode(['success' => false, 'message' => 'Chỉ có thể đánh giá sản phẩm sau khi đơn hàng được giao thành công.']);
        exit;
    }
    if ($item['is_reviewed']) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã đánh giá sản phẩm này rồi.']);
        exit;
    }

    $productId = $item['product_id'];

    // --- Xử lý upload ảnh (tuỳ chọn) ---
    $imageFilename = null;
    if (!empty($_FILES['review_image']) && $_FILES['review_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = uploadImage($_FILES['review_image'], REVIEW_UPLOAD_PATH);
        if (!$upload['success']) {
            echo json_encode(['success' => false, 'message' => 'Upload ảnh thất bại: ' . $upload['error']]);
            exit;
        }
        $imageFilename = $upload['filename'];
    }

    try {
        $pdo->beginTransaction();

        // Lưu đánh giá
        $ins = $pdo->prepare("
            INSERT INTO product_reviews (user_id, product_id, order_item_id, rating, content, image)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$userId, $productId, $orderItemId, $rating, $content ?: null, $imageFilename]);

        // Đánh dấu order_item đã review
        $pdo->prepare("UPDATE order_items SET is_reviewed = 1 WHERE id = ?")->execute([$orderItemId]);

        // Cập nhật avg_rating & total_reviews trong bảng products
        $pdo->prepare("
            UPDATE products
            SET total_reviews = (SELECT COUNT(*) FROM product_reviews WHERE product_id = ?),
                avg_rating    = (SELECT ROUND(AVG(rating), 1) FROM product_reviews WHERE product_id = ?)
            WHERE id = ?
        ")->execute([$productId, $productId, $productId]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Đánh giá của bạn đã được ghi nhận. Cảm ơn bạn!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
