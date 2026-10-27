<?php
/**
 * Admin - Review AJAX Handler
 * WinK Shoe Store
 */
require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once dirname(dirname(__DIR__)) . '/includes/admin_check.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// Action: reply_review
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'reply_review') {
    $reviewId     = (int)($_POST['review_id'] ?? 0);
    $replyContent = trim($_POST['reply_content'] ?? '');

    if ($reviewId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']);
        exit;
    }
    if ($replyContent === '') {
        echo json_encode(['success' => false, 'message' => 'Nội dung phản hồi không được để trống.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM product_reviews WHERE id = ?");
    $stmt->execute([$reviewId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Đánh giá không tồn tại.']);
        exit;
    }

    $adminId = getCurrentUserId();
    $pdo->prepare("UPDATE product_reviews SET admin_reply = ?, reply_at = NOW(), admin_id = ? WHERE id = ?")
        ->execute([$replyContent, $adminId, $reviewId]);

    echo json_encode(['success' => true, 'message' => 'Phản hồi đã được lưu thành công.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Action: delete_reply
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_reply') {
    $reviewId = (int)($_POST['review_id'] ?? 0);

    if ($reviewId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID không hợp lệ.']);
        exit;
    }

    $pdo->prepare("UPDATE product_reviews SET admin_reply = NULL, reply_at = NULL, admin_id = NULL WHERE id = ?")
        ->execute([$reviewId]);

    echo json_encode(['success' => true, 'message' => 'Đã xoá phản hồi.']);
    exit;
}

// Fallback
echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
