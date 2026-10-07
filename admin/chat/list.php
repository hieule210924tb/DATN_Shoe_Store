<?php
/**
 * Admin - Quản lý chat với khách hàng
 */
$pageTitle = 'Quản lý chat - WinK Admin';
include dirname(__DIR__) . '/includes/admin_header.php';

$initialConversationId = (int)($_GET['id'] ?? 0);
?>

<style>
.chat-admin { display: flex; height: calc(100vh - 190px); min-height: 480px; background: #fff; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,.05); overflow: hidden; }

/* ── Sidebar hội thoại ── */
.chat-list-pane { width: 340px; flex-shrink: 0; border-right: 1px solid #eee; display: flex; flex-direction: column; }
.chat-list-head { padding: 14px; border-bottom: 1px solid #eee; }
.chat-list-head .form-control { border-radius: 20px; }
.chat-tabs { display: flex; gap: 6px; margin-top: 10px; }
.chat-tab { flex: 1; border: 1px solid #e5e7eb; background: #fff; border-radius: 16px; font-size: 13px; padding: 4px 0; cursor: pointer; color: #555; transition: all .2s; }
.chat-tab.active { background: #f36811; border-color: #f36811; color: #fff; }
.chat-list { flex: 1; overflow-y: auto; }
.chat-item { display: flex; gap: 10px; padding: 12px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6; transition: background .15s; }
.chat-item:hover { background: #fafafa; }
.chat-item.active { background: #fff4ec; border-left: 3px solid #f36811; padding-left: 11px; }
.chat-item img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.chat-item-body { flex: 1; min-width: 0; }
.chat-item-top { display: flex; justify-content: space-between; align-items: baseline; gap: 6px; }
.chat-item-name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-item-time { font-size: 11px; color: #9ca3af; flex-shrink: 0; }
.chat-item-bottom { display: flex; justify-content: space-between; align-items: center; gap: 6px; margin-top: 2px; }
.chat-item-last { font-size: 13px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-item.unread .chat-item-last { color: #111827; font-weight: 600; }
.chat-unread-badge { background: #f36811; color: #fff; font-size: 11px; font-weight: 600; min-width: 20px; height: 20px; border-radius: 10px; padding: 0 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.chat-list-empty { text-align: center; color: #9ca3af; padding: 40px 16px; font-size: 14px; }

/* ── Khung chat ── */
.chat-main-pane { flex: 1; display: flex; flex-direction: column; min-width: 0; background: #f8f9fa; }
.chat-main-head { background: #fff; padding: 12px 18px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 12px; }
.chat-main-head img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
.chat-main-head .name { font-weight: 600; }
.chat-main-head .sub { font-size: 12px; color: #6b7280; }
.chat-main-head .btn-back { display: none; }
.chat-messages-admin { flex: 1; overflow-y: auto; padding: 18px; }
.chat-day { text-align: center; margin: 14px 0; }
.chat-day span { background: #e5e7eb; color: #6b7280; font-size: 11px; padding: 3px 10px; border-radius: 10px; }
.chat-row { display: flex; margin-bottom: 10px; }
.chat-row.mine { justify-content: flex-end; }
.chat-bubble { max-width: 68%; padding: 10px 14px; font-size: 14px; line-height: 1.45; word-wrap: break-word; overflow-wrap: anywhere; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
.chat-row.theirs .chat-bubble { background: #fff; border-radius: 16px 16px 16px 4px; color: #1f2937; }
.chat-row.mine .chat-bubble { background: linear-gradient(135deg, #f36811, #ff8a3d); color: #fff; border-radius: 16px 16px 4px 16px; }
.chat-bubble .t { font-size: 11px; opacity: .65; margin-top: 3px; text-align: right; }
.chat-input-admin { background: #fff; border-top: 1px solid #eee; padding: 12px 16px; display: flex; gap: 10px; align-items: flex-end; }
.chat-input-admin textarea { resize: none; border-radius: 20px; padding: 9px 16px; max-height: 120px; }
.chat-input-admin .btn-send { width: 42px; height: 42px; border-radius: 50%; padding: 0; flex-shrink: 0; }
.chat-input-admin .btn-attach { width: 42px; height: 42px; border-radius: 50%; padding: 0; flex-shrink: 0; background: #f3f4f6; color: #f36811; border: none; }
.chat-input-admin .btn-attach:hover { background: #fde7d8; }
.chat-img { display: block; max-width: 100%; max-height: 260px; border-radius: 10px; cursor: zoom-in; }
.chat-img + .chat-text { margin-top: 6px; }
.chat-bubble.has-image { padding: 6px; }
.chat-bubble.has-image .chat-text { padding: 2px 8px 0; }
.chat-bubble.has-image .t { padding: 0 8px 2px; }
.chat-preview-admin { background: #fff; border-top: 1px solid #eee; padding: 10px 16px 0; display: none; }
.chat-preview-admin .box { position: relative; display: inline-block; }
.chat-preview-admin img { height: 72px; max-width: 150px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; }
.chat-preview-admin button { position: absolute; top: -7px; right: -7px; width: 22px; height: 22px; border-radius: 50%; border: none; background: #333; color: #fff; font-size: 11px; cursor: pointer; }
.chat-placeholder { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #9ca3af; }
.chat-placeholder i { font-size: 56px; margin-bottom: 12px; opacity: .5; }

@media (max-width: 767.98px) {
    .chat-list-pane { width: 100%; }
    .chat-main-pane { display: none; }
    .chat-admin.show-main .chat-list-pane { display: none; }
    .chat-admin.show-main .chat-main-pane { display: flex; }
    .chat-main-head .btn-back { display: inline-block; }
}

/* ── Dark mode ── */
body.dark-mode .chat-admin, body.dark-mode .chat-main-head, body.dark-mode .chat-input-admin { background: #1e2235; }
body.dark-mode .chat-list-pane, body.dark-mode .chat-list-head, body.dark-mode .chat-main-head, body.dark-mode .chat-input-admin, body.dark-mode .chat-item { border-color: #2d3148; }
body.dark-mode .chat-main-pane { background: #151826; }
body.dark-mode .chat-item:hover { background: #222540; }
body.dark-mode .chat-item.active { background: #2a2540; }
body.dark-mode .chat-item.unread .chat-item-last, body.dark-mode .chat-main-head .name { color: #f1f5f9; }
body.dark-mode .chat-item-name { color: #e2e8f0; }
body.dark-mode .chat-tab { background: #1e2235; border-color: #2d3148; color: #94a3b8; }
body.dark-mode .chat-tab.active { background: #f36811; color: #fff; }
body.dark-mode .chat-row.theirs .chat-bubble { background: #272b45; color: #e2e8f0; }
body.dark-mode .chat-day span { background: #2d3148; color: #94a3b8; }
body.dark-mode .chat-preview-admin { background: #1e2235; border-color: #2d3148; }
body.dark-mode .chat-input-admin .btn-attach { background: #272b45; }
</style>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Chat khách hàng</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?php echo url('admin/index.php'); ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active">Chat</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="chat-admin" id="chatAdmin">
            <!-- Danh sách hội thoại -->
            <div class="chat-list-pane">
                <div class="chat-list-head">
                    <input type="text" id="chatSearch" class="form-control" placeholder="Tìm tên, email, SĐT..." autocomplete="off">
                    <div class="chat-tabs">
                        <button type="button" class="chat-tab active" data-filter="all">Tất cả</button>
                        <button type="button" class="chat-tab" data-filter="unread">Chưa đọc <span id="tabUnread"></span></button>
                    </div>
                </div>
                <div class="chat-list" id="chatList">
                    <div class="chat-list-empty"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>
                </div>
            </div>

            <!-- Khung hội thoại -->
            <div class="chat-main-pane" id="chatMain">
                <div class="chat-placeholder" id="chatPlaceholder">
                    <i class="fas fa-comments"></i>
                    <div>Chọn một cuộc trò chuyện để bắt đầu</div>
                </div>
                <div id="chatPanel" style="display:none; flex:1; flex-direction:column; min-height:0;">
                    <div class="chat-main-head">
                        <button type="button" class="btn btn-sm btn-light btn-back" id="chatBack"><i class="fas fa-arrow-left"></i></button>
                        <img id="chatCustomerAvatar" src="" alt="">
                        <div>
                            <div class="name" id="chatCustomerName"></div>
                            <div class="sub" id="chatCustomerSub"></div>
                        </div>
                    </div>
                    <div class="chat-messages-admin" id="chatMessagesAdmin"></div>
                    <div class="chat-preview-admin" id="chatPreviewAdmin">
                        <div class="box">
                            <img id="chatPreviewAdminImg" src="" alt="Ảnh xem trước">
                            <button type="button" id="chatPreviewAdminRemove" aria-label="Bỏ ảnh"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <form class="chat-input-admin" id="chatSendForm">
                        <input type="file" id="chatFileInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                        <button type="button" class="btn btn-attach" id="chatAttachBtn" title="Gửi ảnh">
                            <i class="fas fa-image"></i>
                        </button>
                        <textarea id="chatMessageInput" class="form-control" rows="1" maxlength="2000"
                                  placeholder="Nhập tin nhắn... (Enter để gửi, Shift+Enter xuống dòng)"></textarea>
                        <button type="submit" class="btn btn-primary btn-send" id="chatSendBtn" title="Gửi">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const API  = <?php echo json_encode(url('ajax/admin_chat_actions.php')); ?>;
    const CSRF = <?php echo json_encode(generateCsrfToken()); ?>;

    const $ = id => document.getElementById(id);
    const chatAdmin = $('chatAdmin'), listEl = $('chatList'), msgEl = $('chatMessagesAdmin');
    const input = $('chatMessageInput'), sendBtn = $('chatSendBtn');

    let activeId   = <?php echo $initialConversationId; ?>;
    let lastMsgId  = 0;
    let lastDate   = '';
    let filter     = 'all';
    let searchTerm = '';
    let sending    = false;
    let selectedImage = null;
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
    const ALLOWED_TYPES  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    let searchTimer;

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function api(params) {
        return fetch(API + '?' + new URLSearchParams(params)).then(r => r.json());
    }

    /* ───────── Danh sách hội thoại ───────── */
    function loadConversations() {
        api({ action: 'list_conversations', search: searchTerm, filter })
            .then(data => {
                if (!data.success) return;
                renderList(data.conversations);
                updateUnreadBadges(data.total_unread);
            })
            .catch(console.error);
    }

    function renderList(items) {
        if (!items.length) {
            listEl.innerHTML = '<div class="chat-list-empty"><i class="far fa-comment-dots fa-2x mb-2"></i><div>' +
                (searchTerm || filter === 'unread' ? 'Không tìm thấy cuộc trò chuyện' : 'Chưa có cuộc trò chuyện nào') + '</div></div>';
            return;
        }
        listEl.innerHTML = items.map(c => `
            <div class="chat-item ${c.id === activeId ? 'active' : ''} ${c.unread > 0 && c.id !== activeId ? 'unread' : ''}" data-id="${c.id}">
                <img src="${esc(c.avatar)}" alt="">
                <div class="chat-item-body">
                    <div class="chat-item-top">
                        <span class="chat-item-name">${esc(c.full_name)}</span>
                        <span class="chat-item-time">${esc(c.time)}</span>
                    </div>
                    <div class="chat-item-bottom">
                        <span class="chat-item-last">${esc(c.last_message)}</span>
                        ${c.unread > 0 && c.id !== activeId ? `<span class="chat-unread-badge">${c.unread > 99 ? '99+' : c.unread}</span>` : ''}
                    </div>
                </div>
            </div>`).join('');
    }

    function updateUnreadBadges(total) {
        $('tabUnread').textContent = total > 0 ? '(' + total + ')' : '';
        const sb = document.getElementById('sidebarChatBadge');
        if (sb) {
            sb.textContent = total > 99 ? '99+' : total;
            sb.style.display = total > 0 ? '' : 'none';
        }
    }

    listEl.addEventListener('click', e => {
        const item = e.target.closest('.chat-item');
        if (item) openConversation(parseInt(item.dataset.id, 10));
    });

    document.querySelectorAll('.chat-tab').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('.chat-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filter = btn.dataset.filter;
        loadConversations();
    }));

    $('chatSearch').addEventListener('input', e => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { searchTerm = e.target.value.trim(); loadConversations(); }, 300);
    });

    /* ───────── Hội thoại đang mở ───────── */
    function openConversation(id) {
        activeId = id; lastMsgId = 0; lastDate = '';
        setImage(null);
        msgEl.innerHTML = '';
        $('chatPlaceholder').style.display = 'none';
        $('chatPanel').style.display = 'flex';
        chatAdmin.classList.add('show-main');
        history.replaceState(null, '', '?id=' + id);
        loadMessages(true);
        input.focus();
    }

    function loadMessages(initial) {
        if (!activeId) return;
        const reqId = activeId;
        api({ action: 'get_messages', conversation_id: reqId, after_id: lastMsgId })
            .then(data => {
                if (reqId !== activeId) return; // đã chuyển sang hội thoại khác
                if (!data.success) {
                    if (initial) { showError(data.message); }
                    return;
                }
                if (initial) {
                    $('chatCustomerAvatar').src = data.customer.avatar;
                    $('chatCustomerName').textContent = data.customer.full_name;
                    $('chatCustomerSub').textContent = [data.customer.email, data.customer.phone].filter(Boolean).join(' • ');
                }
                if (data.messages.length) appendMessages(data.messages, initial);
                if (initial || data.messages.length) loadConversations();
            })
            .catch(console.error);
    }

    function showError(message) {
        $('chatPanel').style.display = 'none';
        $('chatPlaceholder').style.display = 'flex';
        $('chatPlaceholder').lastElementChild.textContent = message || 'Không thể tải cuộc trò chuyện';
        activeId = 0;
    }

    function appendMessages(messages, forceScroll) {
        const nearBottom = msgEl.scrollHeight - msgEl.scrollTop - msgEl.clientHeight < 120;
        let html = '';
        messages.forEach(m => {
            if (m.date !== lastDate) {
                lastDate = m.date;
                html += `<div class="chat-day"><span>${esc(m.date)}</span></div>`;
            }
            const mine = m.sender_role === 'admin';
            const img  = m.image ? `<img src="${esc(m.image)}" class="chat-img" alt="Ảnh" loading="lazy" onclick="window.open(this.src, '_blank')">` : '';
            const txt  = m.message ? `<div class="chat-text">${esc(m.message).replace(/\n/g, '<br>')}</div>` : '';
            html += `<div class="chat-row ${mine ? 'mine' : 'theirs'}"><div class="chat-bubble${m.image ? ' has-image' : ''}">${img}${txt}<div class="t">${esc(m.time)}</div></div></div>`;
            lastMsgId = Math.max(lastMsgId, parseInt(m.id, 10));
        });
        msgEl.insertAdjacentHTML('beforeend', html);
        if (forceScroll || nearBottom) msgEl.scrollTop = msgEl.scrollHeight;
    }

    /* ───────── Chọn / xem trước ảnh ───────── */
    function setImage(file) {
        const img = $('chatPreviewAdminImg');
        if (img.src && img.src.startsWith('blob:')) URL.revokeObjectURL(img.src);

        selectedImage = file || null;
        if (file) {
            img.src = URL.createObjectURL(file);
            $('chatPreviewAdmin').style.display = 'block';
        } else {
            img.removeAttribute('src');
            $('chatPreviewAdmin').style.display = 'none';
            $('chatFileInput').value = '';
        }
    }

    function pickImage(file) {
        if (!file) return;
        if (!ALLOWED_TYPES.includes(file.type)) { alert('Chỉ hỗ trợ ảnh JPG, PNG, GIF hoặc WebP.'); setImage(null); return; }
        if (file.size > MAX_IMAGE_SIZE) { alert('Ảnh quá lớn. Tối đa 5MB.'); setImage(null); return; }
        setImage(file);
    }

    $('chatAttachBtn').addEventListener('click', () => $('chatFileInput').click());
    $('chatFileInput').addEventListener('change', e => pickImage(e.target.files[0]));
    $('chatPreviewAdminRemove').addEventListener('click', () => setImage(null));

    // Dán ảnh từ clipboard (Ctrl+V) vào ô nhập
    input.addEventListener('paste', e => {
        const item = Array.from(e.clipboardData ? e.clipboardData.items : []).find(i => i.type.startsWith('image/'));
        if (item) { e.preventDefault(); pickImage(item.getAsFile()); }
    });

    /* ───────── Gửi tin ───────── */
    function sendMessage() {
        const message = input.value.trim();
        if ((!message && !selectedImage) || !activeId || sending) return;
        sending = true; sendBtn.disabled = true;

        const body = new FormData();
        body.append('action', 'send');
        body.append('csrf_token', CSRF);
        body.append('conversation_id', activeId);
        body.append('message', message);
        if (selectedImage) body.append('image', selectedImage);

        fetch(API, { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    input.value = ''; autoGrow(); setImage(null);
                    loadMessages(false);
                } else {
                    alert(data.message || 'Không thể gửi tin nhắn.');
                }
            })
            .catch(() => alert('Có lỗi xảy ra. Vui lòng thử lại.'))
            .finally(() => { sending = false; sendBtn.disabled = false; input.focus(); });
    }

    $('chatSendForm').addEventListener('submit', e => { e.preventDefault(); sendMessage(); });
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); sendMessage(); }
    });
    function autoGrow() { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; }
    input.addEventListener('input', autoGrow);

    $('chatBack').addEventListener('click', () => chatAdmin.classList.remove('show-main'));

    /* ───────── Khởi động + polling ───────── */
    loadConversations();
    if (activeId) openConversation(activeId);
    setInterval(() => { if (!document.hidden) { loadMessages(false); loadConversations(); } }, 3000);
})();
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>