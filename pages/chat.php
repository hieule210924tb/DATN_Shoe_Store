<?php
/** Trang chat hỗ trợ - WinK Shoe Store */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth_check.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();

// Lấy hoặc tạo conversation cho user
$stmt = $pdo->prepare('SELECT id FROM chat_conversations WHERE user_id = ?');
$stmt->execute([$userId]);
$conversation = $stmt->fetch();

if (!$conversation) {
    $stmt = $pdo->prepare('INSERT INTO chat_conversations (user_id) VALUES (?)');
    $stmt->execute([$userId]);
    $conversationId = $pdo->lastInsertId();
} else {
    $conversationId = $conversation['id'];
}

// Đánh dấu đã đọc
$stmt = $pdo->prepare('UPDATE chat_conversations SET unread_by_user = 0 WHERE id = ?');
$stmt->execute([$conversationId]);

// Lấy tin nhắn
$stmt = $pdo->prepare('
    SELECT cm.*, u.full_name, u.avatar, u.role
    FROM chat_messages cm
    INNER JOIN users u ON cm.sender_id = u.id
    WHERE cm.conversation_id = ?
    ORDER BY cm.created_at ASC
');
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll();

$pageTitle = 'Hỗ trợ trực tuyến - WinK Shoe Store';
$extraCSS = ['product.css'];

include dirname(__DIR__) . '/includes/header.php';
?>

<section class="section-padding" style="padding-top: 30px;">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="bg-white rounded-3 shadow-sm overflow-hidden" style="height: 600px; display: flex; flex-direction: column;">
                    <!-- Chat Header -->
                    <div class="chat-header p-3 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="chat-avatar">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold">Hỗ trợ WinK</h6>
                                <small class="text-success">
                                    <i class="fas fa-circle me-1" style="font-size: 8px;"></i>Online
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="chat-messages flex-grow-1 p-3 overflow-auto" id="chatMessages">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3"></i>
                                <p>Bắt đầu cuộc trò chuyện với chúng tôi!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php if ($msg['sender_role'] === 'admin'): ?>
                                    <!-- Admin message -->
                                    <div class="chat-message chat-message-admin mb-3">
                                        <div class="d-flex gap-2">
                                            <div class="chat-msg-avatar chat-admin-avatar">
                                                <i class="fas fa-headset"></i>
                                            </div>
                                            <div class="chat-msg-bubble chat-msg-bubble-admin">
                                                <?php echo nl2br(e($msg['message'])); ?>
                                                <div class="chat-msg-time"><?php echo formatDate($msg['created_at'], 'H:i'); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- User message -->
                                    <div class="chat-message chat-message-user mb-3">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <div class="chat-msg-bubble chat-msg-bubble-user">
                                                <?php echo nl2br(e($msg['message'])); ?>
                                                <div class="chat-msg-time"><?php echo formatDate($msg['created_at'], 'H:i'); ?></div>
                                            </div>
                                            <img src="<?php echo getCurrentUserAvatar(); ?>" 
                                                 class="chat-msg-avatar" alt="Bạn">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Chat Input -->
                    <div class="chat-input p-3 border-top">
                        <form id="chatForm" class="d-flex gap-2">
                            <input type="text" id="messageInput" class="form-control" 
                                   placeholder="Nhập tin nhắn..." autocomplete="off" required>
                            <button type="submit" class="btn-wink">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="mt-3 text-center text-muted">
                    <small><i class="fas fa-info-circle me-1"></i>Thời gian phản hồi: Thường trong vòng 5-10 phút</small>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.chat-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.chat-messages {
    background: #f8f9fa;
    min-height: 400px;
}

.chat-message-admin .chat-msg-bubble {
    background: white;
    border-radius: 18px 18px 18px 4px;
    max-width: 70%;
}

.chat-message-user .chat-msg-bubble {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 18px 18px 4px 18px;
    max-width: 70%;
}

.chat-msg-bubble {
    padding: 12px 16px;
    position: relative;
}

.chat-msg-time {
    font-size: 11px;
    opacity: 0.7;
    margin-top: 4px;
}

.chat-msg-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
}

.chat-admin-avatar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.chat-input input {
    border-radius: 25px;
    padding: 12px 20px;
}

.chat-input button {
    border-radius: 50%;
    width: 45px;
    height: 45px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<script>
const conversationId = <?php echo $conversationId; ?>;

document.getElementById('chatForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('conversation_id', conversationId);
    formData.append('message', message);
    
    fetch(BASE_URL + '/ajax/chat_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            loadMessages();
        } else {
            showToast('error', data.message || 'Không thể gửi tin nhắn.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra. Vui lòng thử lại.');
    });
});

function loadMessages() {
    const chatMessages = document.getElementById('chatMessages');
    
    fetch(BASE_URL + '/ajax/chat_actions.php?action=get_messages&conversation_id=' + conversationId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages) {
                let html = '';
                if (data.messages.length === 0) {
                    html = `
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-comments fa-3x mb-3"></i>
                            <p>Bắt đầu cuộc trò chuyện với chúng tôi!</p>
                        </div>
                    `;
                } else {
                    data.messages.forEach(msg => {
                        if (msg.sender_role === 'admin') {
                            html += `
                                <div class="chat-message chat-message-admin mb-3">
                                    <div class="d-flex gap-2">
                                        <div class="chat-msg-avatar chat-admin-avatar">
                                            <i class="fas fa-headset"></i>
                                        </div>
                                        <div class="chat-msg-bubble chat-msg-bubble-admin">
                                            ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
                                            <div class="chat-msg-time">${msg.time}</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            html += `
                                <div class="chat-message chat-message-user mb-3">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <div class="chat-msg-bubble chat-msg-bubble-user">
                                            ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
                                            <div class="chat-msg-time">${msg.time}</div>
                                        </div>
                                        <img src="${data.user_avatar}" 
                                             class="chat-msg-avatar" alt="Bạn">
                                    </div>
                                </div>
                            `;
                        }
                    });
                }
                chatMessages.innerHTML = html;
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        })
        .catch(error => console.error('Lỗi:', error));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Tự động load tin nhắn mới mỗi 5 giây
setInterval(loadMessages, 5000);

// Load tin nhắn khi trang mở
document.addEventListener('DOMContentLoaded', loadMessages);
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
