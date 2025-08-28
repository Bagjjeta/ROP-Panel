class RopMessagesPanel {
    constructor(options = {}) {
        this.ws = null;
        this.currentUserId = null;
        this.activeConversation = null;
        this.container = options.container || 'body';
        this.embedded = options.embedded || false;
        this.init();
    }

    init() {
        this.connectWebSocket();
        if (!this.embedded) {
            this.setupUI();
        } else {
            this.setupEmbeddedUI();
        }
        this.bindEvents();
    }

    setupEmbeddedUI() {
        // Panel już istnieje w kontenerze, tylko podpinamy eventy
        console.log('Embedded messages panel initialized');
    }

    connectWebSocket() {
        this.ws = new WebSocket('ws://localhost:8080');

        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.authenticate();
            this.showConnectionStatus('connected');
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        };

        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            this.showConnectionStatus('disconnected');
            // Ponowne połączenie po 3 sekundach
            setTimeout(() => this.connectWebSocket(), 3000);
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.showConnectionStatus('error');
        };
    }

    showConnectionStatus(status) {
        const statusIndicator = document.querySelector('.rop-connection-status');
        if (statusIndicator) {
            statusIndicator.className = `rop-connection-status ${status}`;
            statusIndicator.textContent = status === 'connected' ? 'Połączono' :
                status === 'error' ? 'Błąd połączenia' : 'Rozłączono';
        }
    }

    bindEvents() {
        // Send message
        const sendBtn = document.getElementById('rop-send-message');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => {
                this.sendMessage();
            });
        }

        // Enter to send
        const messageInput = document.getElementById('rop-message-text');
        if (messageInput) {
            messageInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Typing indicators
            let typingTimer;
            messageInput.addEventListener('input', () => {
                this.sendTypingStart();
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    this.sendTypingStop();
                }, 1000);
            });
        }

        // Search users
        const searchInput = document.getElementById('rop-user-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchUsers(e.target.value);
            });
        }

        // Refresh messages
        const refreshBtn = document.getElementById('rop-refresh-messages');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadConversations();
            });
        }

        // New conversation
        const newConvBtn = document.getElementById('rop-new-conversation');
        if (newConvBtn) {
            newConvBtn.addEventListener('click', () => {
                this.openNewConversationDialog();
            });
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'auth_success':
                this.currentUserId = data.user_id;
                this.loadConversations();
                break;
            case 'new_message':
                this.displayNewMessage(data.message);
                this.updateConversationsList();
                break;
            case 'conversations_list':
                this.displayConversations(data.conversations);
                break;
            case 'messages_history':
                this.displayMessages(data.messages);
                break;
            case 'user_status':
                this.updateUserStatus(data.user_id, data.status);
                break;
            case 'typing_start':
                this.showTypingIndicator(data.user_id);
                break;
            case 'typing_stop':
                this.hideTypingIndicator(data.user_id);
                break;
        }
    }

    displayConversations(conversations) {
        const container = document.getElementById('rop-conversations-list');
        if (!container) return;

        if (conversations.length === 0) {
            container.innerHTML = `
            <div class="rop-no-conversations">
                <i class="dashicons dashicons-format-chat"></i>
                <p>Brak konwersacji</p>
                <button class="rop-btn-primary" id="rop-new-conversation-btn">
                    Rozpocznij nową konwersację
                </button>
            </div>
        `;

            // Binduj event dla nowego przycisku
            const newBtn = document.getElementById('rop-new-conversation-btn');
            if (newBtn) {
                newBtn.addEventListener('click', () => this.openNewConversationDialog());
            }
            return;
        }

        container.innerHTML = conversations.map(conv => `
        <div class="rop-conversation-item" data-conversation-id="${conv.id}">
            <div class="rop-conversation-avatar">
                <img src="${conv.avatar}" alt="${conv.name}" class="rop-avatar-img">
                <span class="rop-status-dot ${conv.online ? 'online' : 'offline'}"></span>
            </div>
            <div class="rop-conversation-info">
                <div class="rop-conversation-header">
                    <span class="rop-conversation-name">${conv.name}</span>
                    <span class="rop-conversation-time">${conv.time}</span>
                </div>
                <div class="rop-conversation-preview">
                    <span class="rop-last-message">${conv.last_message}</span>
                    ${conv.unread ? `<span class="rop-unread-badge">${conv.unread}</span>` : ''}
                </div>
            </div>
        </div>
    `).join('');

        // Binduj eventy dla elementów konwersacji
        container.querySelectorAll('.rop-conversation-item').forEach(item => {
            item.addEventListener('click', () => {
                const convId = item.dataset.conversationId;
                const convName = item.querySelector('.rop-conversation-name').textContent;
                this.openConversation(convId, convName);
            });
        });
    }

    openConversation(conversationId, userName) {
        this.activeConversation = conversationId;

        // Pokaż header chatu
        const chatHeader = document.getElementById('rop-chat-header');
        const messageInputArea = document.getElementById('rop-message-input-area');
        const welcomeMessage = document.querySelector('.rop-welcome-message');

        if (chatHeader) chatHeader.style.display = 'flex';
        if (messageInputArea) messageInputArea.style.display = 'block';
        if (welcomeMessage) welcomeMessage.style.display = 'none';

        // Ustaw informacje użytkownika
        const usernameEl = document.getElementById('rop-chat-username');
        if (usernameEl) usernameEl.textContent = userName;

        // Pobierz historię wiadomości
        this.ws.send(JSON.stringify({
            type: 'get_messages',
            conversation_id: conversationId
        }));

        // Oznacz jako przeczytane
        this.ws.send(JSON.stringify({
            type: 'mark_read',
            conversation_id: conversationId
        }));

        // Oznacz aktywną konwersację
        document.querySelectorAll('.rop-conversation-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');
    }

    sendMessage() {
        const messageText = document.getElementById('rop-message-text').value.trim();
        if (!messageText || !this.activeConversation) return;

        this.ws.send(JSON.stringify({
            type: 'send_message',
            conversation_id: this.activeConversation,
            message: messageText
        }));

        document.getElementById('rop-message-text').value = '';
    }

    authenticate() {
        const token = rop_ajax.nonce;
        this.ws.send(JSON.stringify({
            type: 'auth',
            token: token
        }));
    }

    loadConversations() {
        this.ws.send(JSON.stringify({
            type: 'get_conversations'
        }));
    }
}

// Udostępnij klasę globalnie
window.RopMessagesPanel = RopMessagesPanel;

// Na końcu pliku lub w document.ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof RopMessagesPanel !== 'undefined') {
        window.messagesPanel = new RopMessagesPanel({
            embedded: true
        });
    }
});