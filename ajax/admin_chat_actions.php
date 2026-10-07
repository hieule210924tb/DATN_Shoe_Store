<?php
/**
 * Admin Chat AJAX Actions - WinK Shoe Store
 * Dành riêng cho admin trả lời tin nhắn của khách hàng
 */
require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json; charset=utf-8');

function chatJson(array $data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Chỉ admin mới được dùng
if (!isLoggedIn()) {
    chatJson(['success' => false, 'message' => 'Vui lòng đăng nhập.', 'login_required' => true], 401);
}
if (!isAdmin()) {
    chatJson(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
}

const CHAT_MAX_LENGTH = 2000;

$pdo     = getDBConnection();
$adminId = getCurrentUserId();

/**
 * Trả về URL avatar của user (hoặc ảnh mặc định)
 */
function chatAvatarUrl($avatar) {
    return !empty($avatar)
        ? UPLOAD_URL . '/avatars/' . $avatar
        : asset('images/default/default-avatar.png');
}

/**
 * Định dạng thời gian ngắn gọn cho danh sách hội thoại
 */
function chatShortTime($datetime) {
    if (empty($datetime)) return '';
    $ts = strtotime($datetime);
    if (date('Ymd', $ts) === date('Ymd')) return date('H:i', $ts);
    if (date('Y', $ts) === date('Y')) return date('d/m', $ts);
    return date('d/m/Y', $ts);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

// ---------------------------------------------------------------
// POST: gửi tin nhắn
// ---------------------------------------------------------------
if ($method === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        chatJson(['success' => false, 'message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'], 419);
    }

    if ($action === 'send') {
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $message        = trim($_POST['message'] ?? '');
        $hasImageUpload = !empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

        if ($conversationId <= 0 || ($message === '' && !$hasImageUpload)) {
            chatJson(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        }
        if (mb_strlen($message) > CHAT_MAX_LENGTH) {
            chatJson(['success' => false, 'message' => 'Tin nhắn quá dài (tối đa ' . CHAT_MAX_LENGTH . ' ký tự).']);
        }

        $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        if (!$stmt->fetch()) {
            chatJson(['success' => false, 'message' => 'Không tìm thấy cuộc trò chuyện.']);
        }

        // Upload ảnh (nếu có)
        $upload = processChatImageUpload('image');
        if (!$upload['success']) {
            chatJson(['success' => false, 'message' => $upload['error']]);
        }
        $imageName = $upload['filename'];

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message, image)
                VALUES (?, ?, 'admin', ?, ?)
            ");
            $stmt->execute([$conversationId, $adminId, $message, $imageName]);
            $messageId = (int)$pdo->lastInsertId();

            // Admin đã trả lời → coi như đã đọc hết tin của khách; khách có thêm 1 tin chưa đọc
            $stmt = $pdo->prepare("
                UPDATE chat_conversations
                SET last_message = ?, last_message_at = NOW(),
                    unread_by_user = unread_by_user + 1, unread_by_admin = 0
                WHERE id = ?
            ");
            $stmt->execute([chatPreviewText($message, $imageName !== null), $conversationId]);

            $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_role = 'user'");
            $stmt->execute([$conversationId]);

            $pdo->commit();
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            chatJson(['success' => false, 'message' => 'Không thể gửi tin nhắn. Vui lòng thử lại.'], 500);
        }

        chatJson(['success' => true, 'message_id' => $messageId]);
    }

    chatJson(['success' => false, 'message' => 'Hành động không hợp lệ.']);
}

// ---------------------------------------------------------------
// GET: danh sách hội thoại
// ---------------------------------------------------------------
if ($action === 'list_conversations') {
    $search = trim($_GET['search'] ?? '');
    $filter = $_GET['filter'] ?? 'all';

    $where  = ['cc.last_message_at IS NOT NULL']; // bỏ qua hội thoại rỗng
    $params = [];

    if ($search !== '') {
        $where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $like     = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if ($filter === 'unread') {
        $where[] = 'cc.unread_by_admin > 0';
    }

    $stmt = $pdo->prepare("
        SELECT cc.id, cc.user_id, cc.last_message, cc.last_message_at, cc.unread_by_admin,
               u.full_name, u.email, u.avatar
        FROM chat_conversations cc
        INNER JOIN users u ON u.id = cc.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY cc.last_message_at DESC, cc.id DESC
        LIMIT 100
    ");
    $stmt->execute($params);

    $conversations = [];
    foreach ($stmt->fetchAll() as $row) {
        $conversations[] = [
            'id'           => (int)$row['id'],
            'user_id'      => (int)$row['user_id'],
            'full_name'    => $row['full_name'],
            'email'        => $row['email'],
            'avatar'       => chatAvatarUrl($row['avatar']),
            'last_message' => mb_strimwidth($row['last_message'] ?? '', 0, 80, '…', 'UTF-8'),
            'time'         => chatShortTime($row['last_message_at']),
            'unread'       => (int)$row['unread_by_admin'],
        ];
    }

    $totalUnread = (int)$pdo->query("SELECT COALESCE(SUM(unread_by_admin), 0) FROM chat_conversations")->fetchColumn();

    chatJson(['success' => true, 'conversations' => $conversations, 'total_unread' => $totalUnread]);
}

// ---------------------------------------------------------------
// GET: lấy tin nhắn của 1 hội thoại (hỗ trợ lấy tin mới theo after_id)
// ---------------------------------------------------------------
if ($action === 'get_messages') {
    $conversationId = (int)($_GET['conversation_id'] ?? 0);
    $afterId        = max(0, (int)($_GET['after_id'] ?? 0));

    if ($conversationId <= 0) {
        chatJson(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    }

    $stmt = $pdo->prepare("
        SELECT cc.id, cc.user_id, u.full_name, u.email, u.phone, u.avatar
        FROM chat_conversations cc
        INNER JOIN users u ON u.id = cc.user_id
        WHERE cc.id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        chatJson(['success' => false, 'message' => 'Không tìm thấy cuộc trò chuyện.']);
    }

    $stmt = $pdo->prepare("
        SELECT id, sender_role, message, image,
               DATE_FORMAT(created_at, '%H:%i') AS time,
               DATE_FORMAT(created_at, '%d/%m/%Y') AS date
        FROM chat_messages
        WHERE conversation_id = ? AND id > ?
        ORDER BY id ASC
    ");
    $stmt->execute([$conversationId, $afterId]);
    $messages = $stmt->fetchAll();
    foreach ($messages as &$m) {
        $m['image'] = chatImageUrl($m['image']);
    }
    unset($m);

    // Admin đang mở hội thoại → đánh dấu đã đọc
    $pdo->prepare("UPDATE chat_conversations SET unread_by_admin = 0 WHERE id = ?")->execute([$conversationId]);
    $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE conversation_id = ? AND sender_role = 'user' AND is_read = 0")
        ->execute([$conversationId]);

    chatJson([
        'success'      => true,
        'customer'     => [
            'id'        => (int)$conversation['user_id'],
            'full_name' => $conversation['full_name'],
            'email'     => $conversation['email'],
            'phone'     => $conversation['phone'],
            'avatar'    => chatAvatarUrl($conversation['avatar']),
        ],
        'messages'     => $messages,
    ]);
}

// ---------------------------------------------------------------
// GET: tổng số tin chưa đọc (cho badge)
// ---------------------------------------------------------------
if ($action === 'unread_count') {
    $total = (int)$pdo->query("SELECT COALESCE(SUM(unread_by_admin), 0) FROM chat_conversations")->fetchColumn();
    chatJson(['success' => true, 'total_unread' => $total]);
}

chatJson(['success' => false, 'message' => 'Hành động không hợp lệ.']);
