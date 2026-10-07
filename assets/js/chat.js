/** Chat Widget JS - WinK Shoe Store */

let chatConversationId = null;
let chatWidgetOpen = false;
let chatSelectedImage = null;
let chatSending = false;

const CHAT_MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB (khớp với MAX_FILE_SIZE phía server)
const CHAT_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

function toggleChatWidget() {
    const chatWindow = document.getElementById('chatWidgetWindow');
    chatWidgetOpen = !chatWidgetOpen;
    chatWindow.classList.toggle('active', chatWidgetOpen);

    if (chatWidgetOpen) {
        setChatUnreadBadge(0); // mở widget = đã xem tin mới
        initChatWidget();
    }
}

function setChatUnreadBadge(count) {
    const badge = document.getElementById('chatUnreadBadge');
    if (!badge) return;
    badge.textContent = count > 99 ? '99+' : count;
    badge.style.display = count > 0 ? '' : 'none';
}

function refreshChatUnreadBadge() {
    if (chatWidgetOpen || document.hidden) return;

    fetch(BASE_URL + '/ajax/chat_actions.php?action=unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) setChatUnreadBadge(data.unread);
        })
        .catch(() => {});
}

function initChatWidget() {
    if (!chatConversationId) {
        const formData = new FormData();
        formData.append('action', 'init_conversation');

        fetch(BASE_URL + '/ajax/chat_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chatConversationId = data.conversation_id;
                loadChatMessages();
            }
        })
        .catch(error => console.error('Lỗi:', error));
    } else {
        loadChatMessages();
    }
}

/** Dựng HTML nội dung 1 bong bóng (chữ và/hoặc ảnh) */
function renderChatBubbleContent(msg) {
    let html = '';
    if (msg.image) {
        html += `<img src="${escapeHtml(msg.image)}" class="chat-widget-img" alt="Ảnh" loading="lazy" onclick="window.open(this.src, '_blank')">`;
    }
    if (msg.message) {
        html += `<div class="chat-widget-text">${escapeHtml(msg.message).replace(/\n/g, '<br>')}</div>`;
    }
    return html;
}

function loadChatMessages() {
    if (!chatConversationId) return;

    const messagesContainer = document.getElementById('chatWidgetMessages');

    fetch(BASE_URL + '/ajax/chat_actions.php?action=get_messages&conversation_id=' + chatConversationId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages) {
                let html = '';
                if (data.messages.length === 0) {
                    html = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p style="font-size: 13px;">Bắt đầu cuộc trò chuyện!</p>
                        </div>
                    `;
                } else {
                    data.messages.forEach(msg => {
                        const imageClass = msg.image ? ' has-image' : '';
                        if (msg.sender_role === 'admin') {
                            html += `
                                <div class="chat-widget-msg chat-widget-msg-admin">
                                    <div class="chat-widget-avatar-small">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                    <div>
                                        <div class="chat-widget-bubble chat-widget-bubble-admin${imageClass}">
                                            ${renderChatBubbleContent(msg)}
                                        </div>
                                        <div class="chat-widget-time">${msg.time}</div>
                                    </div>
                                </div>
                            `;
                        } else {
                            html += `
                                <div class="chat-widget-msg chat-widget-msg-user">
                                    <div>
                                        <div class="chat-widget-bubble chat-widget-bubble-user${imageClass}">
                                            ${renderChatBubbleContent(msg)}
                                        </div>
                                        <div class="chat-widget-time">${msg.time}</div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                }

                // Chỉ render lại khi có thay đổi để ảnh không bị nháy mỗi 5 giây
                if (messagesContainer.dataset.lastHtml !== html) {
                    const nearBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight < 80;
                    messagesContainer.innerHTML = html;
                    messagesContainer.dataset.lastHtml = html;
                    if (nearBottom || !messagesContainer.dataset.scrolled) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        messagesContainer.dataset.scrolled = '1';
                    }
                }
            }
        })
        .catch(error => console.error('Lỗi:', error));
}

/* ───────── Chọn / xem trước / bỏ ảnh ───────── */
function setChatImage(file) {
    const preview = document.getElementById('chatWidgetPreview');
    const previewImg = document.getElementById('chatWidgetPreviewImg');

    if (previewImg.src && previewImg.src.startsWith('blob:')) {
        URL.revokeObjectURL(previewImg.src);
    }

    chatSelectedImage = file || null;

    if (file) {
        previewImg.src = URL.createObjectURL(file);
        preview.style.display = 'inline-block';
    } else {
        previewImg.removeAttribute('src');
        preview.style.display = 'none';
        document.getElementById('chatWidgetFile').value = '';
    }
}

document.getElementById('chatWidgetAttach').addEventListener('click', function() {
    document.getElementById('chatWidgetFile').click();
});

document.getElementById('chatWidgetFile').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    if (!CHAT_ALLOWED_TYPES.includes(file.type)) {
        showToast('error', 'Chỉ hỗ trợ ảnh JPG, PNG, GIF hoặc WebP.');
        setChatImage(null);
        return;
    }
    if (file.size > CHAT_MAX_IMAGE_SIZE) {
        showToast('error', 'Ảnh quá lớn. Tối đa 5MB.');
        setChatImage(null);
        return;
    }
    setChatImage(file);
});

document.getElementById('chatWidgetPreviewRemove').addEventListener('click', function() {
    setChatImage(null);
});

/* ───────── Gửi tin nhắn ───────── */
document.getElementById('chatWidgetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('chatWidgetInput');
    const message = input.value.trim();

    if ((!message && !chatSelectedImage) || !chatConversationId || chatSending) return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('conversation_id', chatConversationId);
    formData.append('message', message);
    if (chatSelectedImage) {
        formData.append('image', chatSelectedImage);
    }

    chatSending = true;
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    fetch(BASE_URL + '/ajax/chat_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            setChatImage(null);
            const box = document.getElementById('chatWidgetMessages');
            box.dataset.scrolled = ''; // gửi xong thì cuộn xuống tin mới nhất
            loadChatMessages();
        } else {
            showToast('error', data.message || 'Không thể gửi tin nhắn.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra.');
    })
    .finally(() => {
        chatSending = false;
        submitBtn.disabled = false;
    });
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto reload messages mỗi 5 giây khi widget mở
setInterval(() => {
    if (chatWidgetOpen && chatConversationId) {
        loadChatMessages();
    }
}, 5000);

// Khi widget đang đóng: kiểm tra tin chưa đọc từ admin mỗi 10 giây
setInterval(refreshChatUnreadBadge, 10000);
refreshChatUnreadBadge();
