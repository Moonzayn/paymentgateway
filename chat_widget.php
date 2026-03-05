<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        .chat-toggle-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #007AFF, #5856D6);
            color: white;
            border: none;
            cursor: move;
            touch-action: none;
            box-shadow: 0 8px 24px rgba(0, 122, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            z-index: 9999;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .chat-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 32px rgba(0, 122, 255, 0.5);
        }

        .chat-toggle-btn .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #FF3B30;
            color: white;
            font-size: 12px;
            font-weight: 600;
            min-width: 22px;
            height: 22px;
            border-radius: 11px;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
            border: 2px solid white;
        }

        .chat-toggle-btn .badge.show {
            display: flex;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .chat-modal {
            position: fixed;
            bottom: 100px;
            right: 24px;
            width: 380px;
            height: 520px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(0, 0, 0, 0.05);
            z-index: 9998;
            display: none;
            flex-direction: column;
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }

        .chat-modal.show {
            display: flex;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .chat-header {
            background: linear-gradient(135deg, #007AFF, #5856D6);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-header .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .chat-header .info h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .chat-header .info p {
            font-size: 12px;
            opacity: 0.8;
        }

        .chat-header .close-btn {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .chat-header .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #F5F5F7;
        }

        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #C7C7CC;
            border-radius: 2px;
        }

        .message {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .message.sent {
            background: #007AFF;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 6px;
        }

        .message.received {
            background: white;
            color: #1C1C1E;
            align-self: flex-start;
            border-bottom-left-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .message .sender {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            opacity: 0.7;
        }

        .message .time {
            font-size: 10px;
            opacity: 0.6;
            text-align: right;
            margin-top: 4px;
        }

        .message.sent .time {
            color: rgba(255, 255, 255, 0.8);
        }

        .chat-input-area {
            padding: 12px 16px;
            background: white;
            border-top: 1px solid #E5E5EA;
            display: flex;
            gap: 10px;
        }

        .chat-input-area input {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: #F5F5F7;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: background 0.2s;
        }

        .chat-input-area input:focus {
            background: #E5E5EA;
        }

        .chat-input-area button {
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

        .chat-input-area button:hover {
            background: #0056CC;
            transform: scale(1.05);
        }

        .chat-input-area button:disabled {
            background: #C7C7CC;
            cursor: not-allowed;
            transform: none;
        }

        .typing-indicator {
            display: none;
            padding: 10px 14px;
            background: white;
            border-radius: 18px;
            border-bottom-left-radius: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            align-self: flex-start;
            margin-bottom: 8px;
        }

        .typing-indicator.show {
            display: flex;
            gap: 4px;
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: #8E8E93;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-6px); }
        }

        .no-messages {
            text-align: center;
            color: #8E8E93;
            padding: 40px 20px;
        }

        .no-messages i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .no-messages p {
            font-size: 14px;
        }

        @media (max-width: 480px) {
            .chat-modal {
                bottom: 0;
                right: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border-radius: 0;
            }
            
            .chat-toggle-btn {
                bottom: 16px;
                right: 16px;
            }
        }
    </style>
</head>
<body>
    <button class="chat-toggle-btn" id="chatToggle">
        <i class="fas fa-comments"></i>
        <span class="badge" id="chatBadge">0</span>
    </button>

    <div class="chat-modal" id="chatModal">
        <div class="chat-header">
            <div class="avatar">
                <i class="fas fa-headset"></i>
            </div>
            <div class="info">
                <h3>Super Admin</h3>
                <p>Siap membantu Anda</p>
            </div>
            <button class="close-btn" id="chatClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="no-messages" id="noMessages">
                <i class="fas fa-comments"></i>
                <p>Ketik pesan untuk memulai percakapan</p>
            </div>
            <div class="typing-indicator" id="typingIndicator">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Ketik pesan..." autocomplete="off">
            <button id="chatSend" disabled>
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <audio id="chatSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleTAKJJfm" type="audio/wav">
    </audio>

    <script>
        const chatToggle = document.getElementById('chatToggle');
        const chatModal = document.getElementById('chatModal');
        const chatClose = document.getElementById('chatClose');
        const chatMessages = document.getElementById('chatMessages');
        const chatInput = document.getElementById('chatInput');
        const chatSend = document.getElementById('chatSend');
        const chatBadge = document.getElementById('chatBadge');
        const noMessages = document.getElementById('noMessages');
        const typingIndicator = document.getElementById('typingIndicator');

        let lastMessageId = 0;
        let isChatOpen = false;
        let pollingInterval = null;
        let unreadCount = 0;

        // Draggable chat button
        (function() {
            let isDragging = false;
            let hasMoved = false;
            let startX, startY, initialX, initialY;
            const DRAG_THRESHOLD = 20; // 20px minimum to count as drag

            function handleStart(e) {
                // Only handle if touching the chat toggle button itself
                if (e.target !== chatToggle && !chatToggle.contains(e.target)) return;

                isDragging = false;
                hasMoved = false;
                const clientX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                const clientY = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;
                startX = clientX;
                startY = clientY;
                initialX = chatToggle.offsetLeft;
                initialY = chatToggle.offsetTop;
                chatToggle.style.transition = 'none';
            }

            function handleMove(e) {
                if (!startX && !startY) return;

                const clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                const clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;
                const deltaX = Math.abs(clientX - startX);
                const deltaY = Math.abs(clientY - startY);

                // Only start dragging after threshold is exceeded
                if (deltaX > DRAG_THRESHOLD || deltaY > DRAG_THRESHOLD) {
                    isDragging = true;
                    hasMoved = true;
                }

                if (isDragging) {
                    e.preventDefault(); // Prevent page scroll while dragging
                    const deltaPosX = clientX - startX;
                    const deltaPosY = clientY - startY;
                    chatToggle.style.left = (initialX + deltaPosX) + 'px';
                    chatToggle.style.top = (initialY + deltaPosY) + 'px';
                    chatToggle.style.right = 'auto';
                    chatToggle.style.bottom = 'auto';
                }
            }

            function handleEnd() {
                if (isDragging) {
                    isDragging = false;
                    chatToggle.style.transition = '';
                }
                // Reset after a short delay to allow click to work
                setTimeout(() => {
                    startX = null;
                    startY = null;
                }, 100);
            }

            chatToggle.addEventListener('mousedown', handleStart);
            chatToggle.addEventListener('touchstart', handleStart, {passive: true});

            document.addEventListener('mousemove', handleMove);
            document.addEventListener('touchmove', handleMove, {passive: false});

            document.addEventListener('mouseup', handleEnd);
            document.addEventListener('touchend', handleEnd);

            // Click handler - only trigger if not dragged
            chatToggle.addEventListener('click', (e) => {
                if (hasMoved) {
                    hasMoved = false;
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            });
        })();

        chatToggle.addEventListener('click', () => {
            isChatOpen = !isChatOpen;
            chatModal.classList.toggle('show', isChatOpen);
            if (isChatOpen) {
                chatInput.focus();
                markAsRead();
                startPolling();
            } else {
                stopPolling();
            }
        });

        chatClose.addEventListener('click', () => {
            isChatOpen = false;
            chatModal.classList.remove('show');
            stopPolling();
        });

        chatInput.addEventListener('input', () => {
            chatSend.disabled = chatInput.value.trim() === '';
        });

        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !chatSend.disabled) {
                sendMessage();
            }
        });

        chatSend.addEventListener('click', sendMessage);

        function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;

            chatSend.disabled = true;
            chatInput.value = '';

            fetch('api/chat_send.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'message=' + encodeURIComponent(message)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fetchMessages();
                }
                chatSend.disabled = false;
                chatInput.focus();
            })
            .catch(() => {
                chatSend.disabled = false;
            });
        }

        function fetchMessages() {
            fetch('api/chat_get.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'last_id=' + lastMessageId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const hadUnread = unreadCount > 0;
                    unreadCount = data.unread_count;
                    
                    if (data.messages.length > 0) {
                        if (!isChatOpen && hadUnread) {
                            playSound();
                        }
                        
                        data.messages.forEach(msg => {
                            addMessage(msg);
                            if (msg.id > lastMessageId) lastMessageId = msg.id;
                        });
                        
                        if (isChatOpen) {
                            markAsRead();
                        }
                    }
                    
                    updateBadge(unreadCount);
                }
            })
            .catch(console.error);
        }

        function addMessage(msg) {
            if (document.getElementById('msg-' + msg.id)) return;
            
            const currentUserId = <?php echo $_SESSION['user_id'] ?? 0; ?>;
            const isSent = msg.sender_id == currentUserId;
            
            noMessages.style.display = 'none';
            
            const div = document.createElement('div');
            div.id = 'msg-' + msg.id;
            div.className = 'message ' + (isSent ? 'sent' : 'received');
            
            const time = new Date(msg.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            
            div.innerHTML = `
                ${!isSent ? '<div class="sender">' + msg.sender_name + '</div>' : ''}
                ${msg.message}
                <div class="time">${time}</div>
            `;
            
            chatMessages.insertBefore(div, typingIndicator);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function markAsRead() {
            fetch('api/chat_mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: ''
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    unreadCount = 0;
                    updateBadge(0);
                }
            })
            .catch(console.error);
        }

        function updateBadge(count) {
            if (count > 0) {
                chatBadge.textContent = count > 99 ? '99+' : count;
                chatBadge.classList.add('show');
            } else {
                chatBadge.classList.remove('show');
            }
        }

        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(fetchMessages, 5000);
        }

        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        function playSound() {
            const audio = document.getElementById('chatSound');
            audio.volume = 0.3;
            audio.play().catch(() => {});
        }

        fetchMessages();
    </script>
</body>
</html>
