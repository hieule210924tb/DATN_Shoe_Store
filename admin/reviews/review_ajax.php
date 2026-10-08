<?php
/**
 * Admin - Review AJAX Handler
 * WinK Shoe Store
 */
// admin/reviews/review_ajax.php  →  ../../config/config.php
require_once dirname(dirname(__DIR__)) . '/config/config.php';
// admin_check.php nằm ở ../../includes/ (tức project root/includes)
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// Action: delete_review
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_review') {
    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($reviewId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']);
        exit;
    }

    // Lấy thông tin đánh giá
    $stmt = $pdo->prepare("SELECT id, product_id, order_item_id, image FROM product_reviews WHERE id = ?");
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch();

    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Đánh giá không tồn tại.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Xoá file ảnh (nếu có)
        if (!empty($review['image'])) {
            deleteImage($review['image'], REVIEW_UPLOAD_PATH);
        }

        // Đặt lại is_reviewed = 0 trên order_item tương ứng
        $pdo->prepare("UPDATE order_items SET is_reviewed = 0 WHERE id = ?")->execute([$review['order_item_id']]);

        // Xoá đánh giá
        $pdo->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$reviewId]);

        // Cập nhật lại avg_rating & total_reviews của sản phẩm
        $pdo->prepare("
            UPDATE products
            SET total_reviews = (SELECT COUNT(*) FROM product_reviews WHERE product_id = ?),
                avg_rating    = COALESCE((SELECT ROUND(AVG(rating), 1) FROM product_reviews WHERE product_id = ?), 0.0)
            WHERE id = ?
        ")->execute([$review['product_id'], $review['product_id'], $review['product_id']]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Đã xoá đánh giá thành công.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống. Vui lòng thử lại.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
