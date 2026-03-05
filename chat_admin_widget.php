<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .chat-icon-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.2s;
        }

        .chat-icon-btn:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .chat-icon-btn .badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #FF3B30;
            color: white;
            font-size: 10px;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            border: 2px solid white;
        }

        .chat-icon-btn .badge.show {
            display: flex;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .chat-panel-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 9990;
            display: none;
            animation: fadeIn 0.2s ease;
        }

        .chat-panel-overlay.show {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .chat-panel {
            position: fixed;
            top: 0;
            right: 0;
            width: 400px;
            max-width: 100%;
            height: 100vh;
            background: white;
            box-shadow: -8px 0 32px rgba(0, 0, 0, 0.15);
            z-index: 9991;
            display: flex;
            flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .chat-panel.show {
            transform: translateX(0);
        }

        .chat-panel-header {
            background: linear-gradient(135deg, #007AFF, #5856D6);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .chat-panel-header .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .chat-panel-header .info {
            flex: 1;
        }

        .chat-panel-header .info h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .chat-panel-header .info p {
            font-size: 13px;
            opacity: 0.8;
        }

        .chat-panel-header .close-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background 0.2s;
        }

        .chat-panel-header .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .chat-conversation-list {
            flex: 1;
            overflow-y: auto;
            border-bottom: 1px solid var(--border);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid var(--border);
        }

        .conversation-item:hover {
            background: var(--bg);
        }

        .conversation-item.active {
            background: var(--primary-50);
        }

        .conversation-item .store-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            flex-shrink: 0;
        }

        .conversation-item .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-item .store-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-item .last-message {
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-item .unread-badge {
            background: #FF3B30;
            color: white;
            font-size: 11px;
            font-weight: 600;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .conversation-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .conversation-empty i {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .chat-view {
            flex: 1;
            display: none;
            flex-direction: column;
        }

        .chat-view.show {
            display: flex;
        }

        .chat-view-header {
            padding: 12px 16px;
            background: var(--bg);
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border);
        }

        .chat-view-header .back-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text);
            font-size: 14px;
        }

        .chat-view-header .store-info {
            flex: 1;
        }

        .chat-view-header .store-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .chat-view-header .store-status {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .chat-messages-view {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #F5F5F7;
        }

        .chat-messages-view::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages-view::-webkit-scrollbar-thumb {
            background: #C7C7CC;
            border-radius: 2px;
        }

        .chat-messages-view .message {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .chat-messages-view .message.sent {
            background: #007AFF;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 6px;
        }

        .chat-messages-view .message.received {
            background: white;
            color: #1C1C1E;
            align-self: flex-start;
            border-bottom-left-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .chat-messages-view .message .sender {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            opacity: 0.7;
        }

        .chat-messages-view .message .time {
            font-size: 10px;
            opacity: 0.6;
            text-align: right;
            margin-top: 4px;
        }

        .chat-messages-view .message.sent .time {
            color: rgba(255, 255, 255, 0.8);
        }

        .chat-input-view {
            padding: 12px 16px;
            background: white;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }

        .chat-input-view input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: #F5F5F7;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: background 0.2s;
        }

        .chat-input-view input:focus {
            background: #E5E5EA;
        }

        .chat-input-view button {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #007AFF;
            color: white;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, background 0.2s;
        }

        .chat-input-view button:hover {
            background: #0056CC;
            transform: scale(1.05);
        }

        .chat-input-view button:disabled {
            background: #C7C7CC;
            cursor: not-allowed;
            transform: none;
        }

        .no-messages-view {
            text-align: center;
            color: #8E8E93;
            padding: 40px 20px;
        }

        .no-messages-view i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 480px) {
            .chat-panel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-panel-overlay" id="chatPanelOverlay"></div>

    <div class="chat-panel" id="chatPanel">
        <div class="chat-panel-header">
            <div class="avatar">
                <i class="fas fa-headset"></i>
            </div>
            <div class="info">
                <h3>Live Chat</h3>
                <p><span id="totalUnread">0</span> pesan belum terbaca</p>
            </div>
            <button class="close-btn" id="chatPanelClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-conversation-list" id="conversationList">
            <div class="conversation-empty" id="conversationEmpty">
                <i class="fas fa-comments"></i>
                <p>Belum ada percakapan</p>
            </div>
        </div>

        <div class="chat-view" id="chatView">
            <div class="chat-view-header">
                <button class="back-btn" id="chatViewBack">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="store-avatar" id="chatStoreAvatar" style="width: 36px; height: 36px; font-size: 14px;">T</div>
                <div class="store-info">
                    <div class="store-name" id="chatStoreName">Toko</div>
                    <div class="store-status">Online</div>
                </div>
            </div>
            <div class="chat-messages-view" id="chatMessagesView">
                <div class="no-messages-view" id="noMessagesView">
                    <i class="fas fa-comments"></i>
                    <p>Ketik pesan untuk memulai percakapan</p>
                </div>
            </div>
            <div class="chat-input-view">
                <input type="text" id="chatInputView" placeholder="Ketik pesan..." autocomplete="off">
                <button id="chatSendView" disabled>
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        const adminChatToggle = document.getElementById('adminChatToggle');
        const chatPanelOverlay = document.getElementById('chatPanelOverlay');
        const chatPanel = document.getElementById('chatPanel');
        const chatPanelClose = document.getElementById('chatPanelClose');
        const conversationList = document.getElementById('conversationList');
        const conversationEmpty = document.getElementById('conversationEmpty');
        const chatView = document.getElementById('chatView');
        const chatViewBack = document.getElementById('chatViewBack');
        const chatStoreAvatar = document.getElementById('chatStoreAvatar');
        const chatStoreName = document.getElementById('chatStoreName');
        const chatMessagesView = document.getElementById('chatMessagesView');
        const chatInputView = document.getElementById('chatInputView');
        const chatSendView = document.getElementById('chatSendView');
        const adminChatBadge = document.getElementById('adminChatBadge');
        const totalUnread = document.getElementById('totalUnread');

        let currentStoreId = null;
        let lastMessageId = 0;
        let pollingInterval = null;
        let conversationsPollingInterval = null;

        function openChatPanel() {
            chatPanelOverlay.classList.add('show');
            chatPanel.classList.add('show');
            loadConversations();
            startConversationsPolling();
        }

        function closeChatPanel() {
            chatPanelOverlay.classList.remove('show');
            chatPanel.classList.remove('show');
            chatView.classList.remove('show');
            currentStoreId = null;
            stopConversationsPolling();
            stopPolling();
        }

        adminChatToggle.addEventListener('click', openChatPanel);
        chatPanelOverlay.addEventListener('click', closeChatPanel);
        chatPanelClose.addEventListener('click', closeChatPanel);

        chatViewBack.addEventListener('click', () => {
            chatView.classList.remove('show');
            currentStoreId = null;
            stopPolling();
            loadConversations();
            startConversationsPolling();
        });

        chatInputView.addEventListener('input', () => {
            chatSendView.disabled = chatInputView.value.trim() === '';
        });

        chatInputView.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !chatSendView.disabled) {
                sendMessageView();
            }
        });

        chatSendView.addEventListener('click', sendMessageView);

        function loadConversations() {
            fetch('api/chat_get_all.php')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderConversations(data.conversations, data.total_unread);
                    }
                })
                .catch(console.error);
        }

        function renderConversations(conversations, total) {
            totalUnread.textContent = total;
            
            if (total > 0) {
                adminChatBadge.textContent = total > 99 ? '99+' : total;
                adminChatBadge.classList.add('show');
            } else {
                adminChatBadge.classList.remove('show');
            }

            if (conversations.length === 0) {
                conversationEmpty.style.display = 'block';
                conversationList.innerHTML = '';
                conversationList.appendChild(conversationEmpty);
                return;
            }

            conversationEmpty.style.display = 'none';
            conversationList.innerHTML = '';

            conversations.forEach(conv => {
                const item = document.createElement('div');
                item.className = 'conversation-item';
                item.onclick = () => openConversation(conv.store_id, conv.nama_toko);

                const initial = conv.nama_toko ? conv.nama_toko.charAt(0).toUpperCase() : 'T';
                const lastMsg = conv.last_message || 'Belum ada pesan';
                const time = conv.last_time ? formatTime(conv.last_time) : '';

                item.innerHTML = `
                    <div class="store-avatar">${initial}</div>
                    <div class="conversation-info">
                        <div class="store-name">${conv.nama_toko || 'Toko'}</div>
                        <div class="last-message">${lastMsg}</div>
                    </div>
                    <div style="text-align: right;">
                        ${time ? `<div style="font-size: 11px; color: var(--text-secondary); margin-bottom: 4px;">${time}</div>` : ''}
                        ${conv.unread > 0 ? `<div class="unread-badge">${conv.unread}</div>` : ''}
                    </div>
                `;

                conversationList.appendChild(item);
            });
        }

        function formatTime(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Baru';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'j';
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        }

        function openConversation(storeId, storeName) {
            currentStoreId = storeId;
            chatStoreAvatar.textContent = storeName.charAt(0).toUpperCase();
            chatStoreName.textContent = storeName;

            chatView.classList.add('show');
            lastMessageId = 0;

            loadMessages();
            markAsRead(storeId);
            startPolling();

            stopConversationsPolling();
        }

        function loadMessages() {
            // Allow loading for null/0 store_id (users without store)
            if (currentStoreId === undefined || currentStoreId === null) {
                currentStoreId = '';
            }

            fetch('api/chat_get.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'store_id=' + (currentStoreId || '') + '&last_id=' + lastMessageId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        addMessageView(msg);
                        if (msg.id > lastMessageId) lastMessageId = msg.id;
                    });
                }
            })
            .catch(console.error);
        }

        function addMessageView(msg) {
            if (document.getElementById('msg-' + msg.id)) return;
            
            const currentUserId = <?php echo $_SESSION['user_id'] ?? 0; ?>;
            const isSent = msg.sender_id == currentUserId;
            
            const noMessages = document.getElementById('noMessagesView');
            if (noMessages) noMessages.style.display = 'none';
            
            const div = document.createElement('div');
            div.id = 'msg-' + msg.id;
            div.className = 'message ' + (isSent ? 'sent' : 'received');
            
            const time = new Date(msg.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            
            div.innerHTML = `
                ${!isSent ? '<div class="sender">' + msg.sender_name + '</div>' : ''}
                ${msg.message}
                <div class="time">${time}</div>
            `;
            
            chatMessagesView.appendChild(div);
            chatMessagesView.scrollTop = chatMessagesView.scrollHeight;
        }

        function sendMessageView() {
            const message = chatInputView.value.trim();
            if (!message || !currentStoreId) return;

            chatSendView.disabled = true;
            chatInputView.value = '';

            fetch('api/chat_send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'message=' + encodeURIComponent(message) + '&store_id=' + currentStoreId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadMessages();
                }
                chatSendView.disabled = false;
                chatInputView.focus();
            })
            .catch(() => {
                chatSendView.disabled = false;
            });
        }

        function markAsRead(storeId) {
            // Handle null/0 store_id
            const storeIdParam = (storeId === null || storeId === undefined || storeId === 0) ? '' : storeId;

            fetch('api/chat_mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'store_id=' + storeIdParam
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadConversations();
                }
            })
            .catch(console.error);
        }

        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(loadMessages, 5000);
        }

        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        function startConversationsPolling() {
            if (conversationsPollingInterval) clearInterval(conversationsPollingInterval);
            loadConversations();
            conversationsPollingInterval = setInterval(loadConversations, 5000);
        }

        function stopConversationsPolling() {
            if (conversationsPollingInterval) {
                clearInterval(conversationsPollingInterval);
                conversationsPollingInterval = null;
            }
        }

        loadConversations();
    </script>
</body>
</html>
