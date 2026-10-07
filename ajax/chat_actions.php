<?php
/**
 * Chat AJAX Actions - WinK Shoe Store
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
    
    if ($action === 'init_conversation') {
        // Lấy hoặc tạo conversation cho user
        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE user_id = ?");
        $stmt->execute([$userId]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) {
            $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            $conversationId = $pdo->lastInsertId();
        } else {
            $conversationId = $conversation['id'];
        }
        
        // Đánh dấu đã đọc
        $stmt = $pdo->prepare("UPDATE chat_conversations SET unread_by_user = 0 WHERE id = ?");
        $stmt->execute([$conversationId]);
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversationId
        ]);
    }
    
    if ($action === 'send') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $hasImageUpload = !empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
        
        if ($conversationId <= 0 || ($message === '' && !$hasImageUpload)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }
        
        if (mb_strlen($message) > 2000) {
            echo json_encode(['success' => false, 'message' => 'Tin nhắn quá dài (tối đa 2000 ký tự).']);
            exit;
        }
        
        // Kiểm tra conversation thuộc về user
        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$conversationId, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy cuộc trò chuyện.']);
            exit;
        }
        
        // Upload ảnh (nếu có)
        $upload = processChatImageUpload('image');
        if (!$upload['success']) {
            echo json_encode(['success' => false, 'message' => $upload['error']]);
            exit;
        }
        $imageName = $upload['filename'];
        
        // Lưu tin nhắn
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message, image) 
            VALUES (?, ?, 'user', ?, ?)
        ");
        $stmt->execute([$conversationId, $userId, $message, $imageName]);
        
        // Cập nhật last_message và unread_by_admin
        $stmt = $pdo->prepare("
            UPDATE chat_conversations 
            SET last_message = ?, last_message_at = NOW(), unread_by_admin = unread_by_admin + 1
            WHERE id = ?
        ");
        $stmt->execute([chatPreviewText($message, $imageName !== null), $conversationId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Đã gửi tin nhắn.'
        ]);
    }
}

// GET request để lấy tin nhắn
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'unread_count') {
        echo json_encode(['success' => true, 'unread' => getChatUnreadCount()]);
        exit;
    }
    
    if ($action === 'get_messages') {
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
        
        if ($conversationId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }
        
        // Kiểm tra conversation thuộc về user
        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$conversationId, $userId]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy cuộc trò chuyện.']);
            exit;
        }
        
        // Lấy tin nhắn
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.message, cm.image, cm.created_at, cm.sender_role,
                   DATE_FORMAT(cm.created_at, '%H:%i') as time
            FROM chat_messages cm
            WHERE cm.conversation_id = ?
            ORDER BY cm.created_at ASC, cm.id ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll();
        foreach ($messages as &$m) {
            $m['image'] = chatImageUrl($m['image']);
        }
        unset($m);
        
        // Khách đang xem → đánh dấu tin của admin là đã đọc
        $pdo->prepare("UPDATE chat_conversations SET unread_by_user = 0 WHERE id = ?")->execute([$conversationId]);
        $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_role = 'admin' AND is_read = 0")->execute([$conversationId]);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'user_avatar' => getCurrentUserAvatar()
        ]);
    }
}
