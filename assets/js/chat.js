/** Chat Widget JS - WinK Shoe Store */

let chatConversationId = null;
let chatWidgetOpen = false;

function toggleChatWidget() {
    const window = document.getElementById('chatWidgetWindow');
    chatWidgetOpen = !chatWidgetOpen;
    window.classList.toggle('active', chatWidgetOpen);

    if (chatWidgetOpen) {
        initChatWidget();
    }
}

function initChatWidget() {
    if (!chatConversationId) {
        fetch(BASE_URL + '/ajax/chat_actions.php?action=init_conversation', {
            method: 'POST'
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
                        if (msg.sender_role === 'admin') {
                            html += `
                                <div class="chat-widget-msg chat-widget-msg-admin">
                                    <div class="chat-widget-avatar-small">
                                        <i class="fas fa-headset"></i>
                                    </div>
                                    <div>
                                        <div class="chat-widget-bubble chat-widget-bubble-admin">
                                            ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
                                        </div>
                                        <div class="chat-widget-time">${msg.time}</div>
                                    </div>
                                </div>
                            `;
                        } else {
                            html += `
                                <div class="chat-widget-msg chat-widget-msg-user">
                                    <div>
                                        <div class="chat-widget-bubble chat-widget-bubble-user">
                                            ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
                                        </div>
                                        <div class="chat-widget-time">${msg.time}</div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                }
                messagesContainer.innerHTML = html;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        })
        .catch(error => console.error('Lỗi:', error));
}

document.getElementById('chatWidgetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const input = document.getElementById('chatWidgetInput');
    const message = input.value.trim();

    if (!message || !chatConversationId) return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('conversation_id', chatConversationId);
    formData.append('message', message);

    fetch(BASE_URL + '/ajax/chat_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadChatMessages();
        } else {
            showToast('error', data.message || 'Không thể gửi tin nhắn.');
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        showToast('error', 'Có lỗi xảy ra.');
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
