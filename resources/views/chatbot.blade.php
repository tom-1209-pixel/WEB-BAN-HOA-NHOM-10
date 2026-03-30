<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot AI - Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           DESIGN SYSTEM
           ============================================================ */
        :root {
            --bg-primary: #0f0f23;
            --bg-secondary: #1a1a2e;
            --bg-chat: #16213e;
            --bg-input: #1a1a3e;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --accent-glow: rgba(99, 102, 241, 0.3);
            --user-bubble: #312e81;
            --bot-bubble: #1e293b;
            --border: #2d2d5e;
            --danger: #ef4444;
            --success: #22c55e;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
        }

        /* ============================================================
           LAYOUT
           ============================================================ */
        .app {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 900px;
            margin: 0 auto;
        }

        /* --- Header --- */
        .header {
            padding: 16px 24px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .header-title {
            font-size: 18px;
            font-weight: 700;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-subtitle {
            font-size: 12px;
            color: var(--text-muted);
        }

        .btn-new-chat {
            padding: 8px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .btn-new-chat:hover {
            background: var(--accent-hover);
            box-shadow: 0 0 20px var(--accent-glow);
            transform: translateY(-1px);
        }

        /* --- Status Bar --- */
        .status-bar {
            padding: 8px 24px;
            background: var(--bg-chat);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }

        .status-dot.offline { background: var(--danger); animation: none; }
        .status-dot.loading { background: #f59e0b; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* --- Chat Messages Area --- */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }

        /* --- Welcome Screen --- */
        .welcome {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            text-align: center;
            padding: 40px;
        }

        .welcome-icon {
            font-size: 64px;
            margin-bottom: 16px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .welcome h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome p {
            color: var(--text-secondary);
            margin-bottom: 24px;
            max-width: 400px;
        }

        .suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            max-width: 500px;
        }

        .suggestion-chip {
            padding: 8px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 20px;
            color: var(--text-secondary);
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .suggestion-chip:hover {
            border-color: var(--accent);
            color: var(--accent-hover);
            background: rgba(99, 102, 241, 0.1);
        }

        /* --- Message Bubbles --- */
        .message {
            display: flex;
            gap: 12px;
            max-width: 85%;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .message.bot .message-avatar {
            background: linear-gradient(135deg, var(--accent), #a855f7);
        }

        .message.user .message-avatar {
            background: var(--user-bubble);
        }

        .message-content {
            padding: 12px 16px;
            border-radius: var(--radius);
            line-height: 1.6;
            font-size: 14px;
            word-wrap: break-word;
        }

        .message.bot .message-content {
            background: var(--bot-bubble);
            border: 1px solid var(--border);
            border-top-left-radius: 4px;
        }

        .message.user .message-content {
            background: var(--user-bubble);
            border-top-right-radius: 4px;
        }

        /* Markdown-like styling within bot messages */
        .message.bot .message-content strong { color: #a5b4fc; }
        .message.bot .message-content code {
            background: rgba(99, 102, 241, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
        }

        /* Typing indicator */
        .typing-indicator {
            display: flex;
            gap: 4px;
            padding: 4px 0;
        }

        .typing-indicator span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--text-muted);
            animation: bounce 1.4s infinite;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes bounce {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-8px); }
        }

        /* --- Input Area --- */
        .input-area {
            padding: 16px 24px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
        }

        .input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .input-field {
            flex: 1;
            padding: 12px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            resize: none;
            outline: none;
            transition: border-color 0.2s ease;
            max-height: 120px;
            min-height: 44px;
        }

        .input-field:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-glow);
        }

        .input-field::placeholder {
            color: var(--text-muted);
        }

        .btn-send {
            padding: 12px 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
        }

        .btn-send:hover:not(:disabled) {
            background: var(--accent-hover);
            box-shadow: 0 0 20px var(--accent-glow);
        }

        .btn-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .input-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 8px;
            text-align: center;
        }

        /* --- Error Toast --- */
        .error-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border-radius: 8px;
            font-size: 13px;
            backdrop-filter: blur(8px);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        /* --- Responsive --- */
        @media (max-width: 640px) {
            .header { padding: 12px 16px; }
            .chat-messages { padding: 16px; }
            .input-area { padding: 12px 16px; }
            .message { max-width: 95%; }
            .suggestions { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <div class="app" id="app">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="header-logo">🤖</div>
                <div>
                    <div class="header-title">Trợ Lý Bán Hàng AI</div>
                    <div class="header-subtitle">Powered by Google Gemini</div>
                </div>
            </div>
            <button class="btn-new-chat" onclick="startNewChat()">
                ✨ Chat mới
            </button>
        </div>

        <!-- Status Bar -->
        <div class="status-bar">
            <div class="status-dot" id="statusDot"></div>
            <span id="statusText">Sẵn sàng trò chuyện</span>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <!-- Welcome Screen (hiện khi chưa có tin nhắn) -->
            <div class="welcome" id="welcomeScreen">
                <div class="welcome-icon">🛍️</div>
                <h2>Xin chào! Tôi là trợ lý AI</h2>
                <p>Tôi có thể giúp bạn tìm kiếm sản phẩm, tư vấn mua sắm, và trả lời mọi câu hỏi. Hãy thử hỏi tôi nhé!</p>
                <div class="suggestions">
                    <button class="suggestion-chip" onclick="sendSuggestion(this)">📱 Có điện thoại nào đang bán?</button>
                    <button class="suggestion-chip" onclick="sendSuggestion(this)">💰 Sản phẩm dưới 10 triệu</button>
                    <button class="suggestion-chip" onclick="sendSuggestion(this)">🔥 Sản phẩm nổi bật</button>
                    <button class="suggestion-chip" onclick="sendSuggestion(this)">📦 Có những danh mục nào?</button>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="input-area">
            <div class="input-wrapper">
                <textarea
                    class="input-field"
                    id="messageInput"
                    placeholder="Nhập tin nhắn... (Enter để gửi, Shift+Enter xuống dòng)"
                    rows="1"
                    onkeydown="handleKeyDown(event)"
                    oninput="autoResize(this)"
                ></textarea>
                <button class="btn-send" id="btnSend" onclick="sendMessage()">
                    ➤
                </button>
            </div>
            <div class="input-hint">
                Chatbot sử dụng Gemini AI · Dữ liệu sản phẩm thực từ database
            </div>
        </div>
    </div>

    <script>
    // ================================================================
    // STATE MANAGEMENT
    // ================================================================

    /** Base URL cho API — đổi nếu port khác */
    const API_BASE = '/api/chat';

    /** ID cuộc hội thoại hiện tại */
    let currentConversationId = null;

    /** Trạng thái đang chờ response */
    let isWaiting = false;

    // ================================================================
    // DOM REFERENCES
    // ================================================================

    const chatMessages = document.getElementById('chatMessages');
    const messageInput = document.getElementById('messageInput');
    const btnSend      = document.getElementById('btnSend');
    const statusDot    = document.getElementById('statusDot');
    const statusText   = document.getElementById('statusText');
    const welcomeScreen = document.getElementById('welcomeScreen');

    // ================================================================
    // CORE FUNCTIONS
    // ================================================================

    /**
     * Tạo cuộc hội thoại mới.
     * Gọi API: POST /api/chat/conversations
     */
    async function startNewChat() {
        try {
            const response = await fetch(`${API_BASE}/conversations`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Không thể tạo cuộc hội thoại');
            }

            currentConversationId = data.data.id;

            // Reset UI
            clearMessages();
            welcomeScreen.style.display = 'flex';
            setStatus('online', 'Sẵn sàng trò chuyện');

        } catch (error) {
            showError(error.message);
        }
    }

    /**
     * Gửi tin nhắn — gọi API, nhận JSON response.
     *
     * Flow:
     * 1. Tạo conversation nếu chưa có
     * 2. Hiện tin nhắn user trên UI
     * 3. Gọi API (POST JSON)
     * 4. Hiện phản hồi từ AI
     */
    async function sendMessage() {
        const text = messageInput.value.trim();
        if (!text || isWaiting) return;

        // Auto-tạo conversation nếu chưa có
        if (!currentConversationId) {
            await startNewChat();
            if (!currentConversationId) return;
        }

        // Ẩn welcome screen
        welcomeScreen.style.display = 'none';

        // Hiện tin nhắn user
        appendMessage('user', text);
        messageInput.value = '';
        autoResize(messageInput);

        // Hiện typing indicator
        const typingEl = showTypingIndicator();
        setWaiting(true);

        try {
            const response = await fetch(
                `${API_BASE}/conversations/${currentConversationId}/messages`,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text }),
                }
            );

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `Lỗi ${response.status}`);
            }

            // Xóa typing indicator, hiện phản hồi
            typingEl.remove();
            appendMessage('bot', data.data.response || 'Không nhận được phản hồi');

        } catch (error) {
            typingEl?.remove();
            showError(error.message);
            appendMessage('bot', '❌ Xin lỗi, đã xảy ra lỗi: ' + error.message);
        } finally {
            setWaiting(false);
        }
    }

    // ================================================================
    // UI HELPERS
    // ================================================================

    /** Thêm tin nhắn vào chat area */
    function appendMessage(role, text) {
        const messageEl = document.createElement('div');
        messageEl.className = `message ${role}`;

        const avatar = role === 'bot' ? '🤖' : '👤';

        messageEl.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-content">${role === 'bot' ? formatMarkdown(text) : escapeHtml(text)}</div>
        `;

        chatMessages.appendChild(messageEl);
        scrollToBottom();

        return messageEl;
    }

    /** Hiện typing indicator */
    function showTypingIndicator() {
        const el = document.createElement('div');
        el.className = 'message bot';
        el.id = 'typingIndicator';
        el.innerHTML = `
            <div class="message-avatar">🤖</div>
            <div class="message-content">
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        chatMessages.appendChild(el);
        scrollToBottom();
        return el;
    }

    /** Xóa tất cả tin nhắn */
    function clearMessages() {
        const messages = chatMessages.querySelectorAll('.message');
        messages.forEach(m => m.remove());
    }

    /** Scroll xuống cuối chat */
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    /** Set trạng thái chờ */
    function setWaiting(waiting) {
        isWaiting = waiting;
        btnSend.disabled = waiting;
        messageInput.disabled = waiting;

        if (waiting) {
            setStatus('loading', 'Đang suy nghĩ...');
        } else {
            setStatus('online', 'Sẵn sàng trò chuyện');
            messageInput.focus();
        }
    }

    /** Cập nhật status bar */
    function setStatus(state, text) {
        statusDot.className = `status-dot ${state === 'online' ? '' : state}`;
        statusText.textContent = text;
    }

    /** Hiện thông báo lỗi dạng toast */
    function showError(message) {
        const toast = document.createElement('div');
        toast.className = 'error-toast';
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 5000);
    }

    /** Gửi tin nhắn từ suggestion chip */
    function sendSuggestion(chipEl) {
        messageInput.value = chipEl.textContent.replace(/^[^\s]+\s/, '');
        sendMessage();
    }

    /** Auto-resize textarea */
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    /** Xử lý phím Enter */
    function handleKeyDown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }

    // ================================================================
    // TEXT FORMATTING
    // ================================================================

    /** Escape HTML để tránh XSS */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format markdown đơn giản cho tin nhắn bot.
     * Hỗ trợ: **bold**, *italic*, `code`, bullet points, line breaks.
     */
    function formatMarkdown(text) {
        if (!text) return '';

        return escapeHtml(text)
            // Bold: **text**
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            // Italic: *text*
            .replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em>$1</em>')
            // Inline code: `text`
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            // Bullet points: - text or * text
            .replace(/^[\-\*]\s(.+)$/gm, '• $1')
            // Line breaks
            .replace(/\n/g, '<br>');
    }

    // ================================================================
    // INITIALIZATION
    // ================================================================

    // Focus input field khi trang load
    messageInput.focus();
    </script>
</body>
</html>
