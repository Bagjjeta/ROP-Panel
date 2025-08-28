class RopMessagesPanel {
    constructor() {
        this.ws = null;
        this.currentUserId = null;
        this.activeConversation = null;
        this.init();
    }

    init() {
        this.connectWebSocket();
        this.setupUI();
        this.bindEvents();
    }

    connectWebSocket() {
        this.ws = new WebSocket('ws://localhost:8080');
        
        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.authenticate();
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        };

        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            // Ponowne połączenie po 3 sekundach
            setTimeout(() => this.connectWebSocket(), 3000);
        };
    }

    authenticate() {
        // Pobierz token z WordPress
        const token = rop_ajax.nonce; // lub inny token
        this.ws.send(JSON.stringify({
            type: 'auth',
            token: token
        }));
    }

    setupUI() {
        const panelHTML = `
            <div id="rop-messages-panel" class="rop-messages-panel">
                <div class="rop-messages-header">
                    <h3>Wiadomości</h3>
                    <button id="rop-toggle-panel" class="rop-toggle-btn">−</button>
                </div>
                <div class="rop-messages-content">
                    <div class="rop-conversations-list">
                        <div class="rop-search-box">
                            <input type="text" placeholder="Szukaj użytkowników..." id="rop-user-search">
                        </div>
                        <div id="rop-conversations"></div>
                    </div>
                    <div class="rop-chat-area">
                        <div class="rop-chat-header">
                            <span id="rop-chat-username">Wybierz konwersację</span>
                            <span id="rop-user-status"></span>
                        </div>
                        <div id="rop-messages-container"></div>
                        <div class="rop-typing-indicator" id="rop-typing"></div>
                        <div class="rop-message-input">
                            <textarea id="rop-message-text" placeholder="Napisz wiadomość..."></textarea>
                            <button id="rop-send-message">Wyślij</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', panelHTML);
    }

    bindEvents() {
        // Toggle panel
        document.getElementById('rop-toggle-panel').addEventListener('click', () => {
            this.togglePanel();
        });

        // Send message
        document.getElementById('rop-send-message').addEventListener('click', () => {
            this.sendMessage();
        });

        // Enter to send
        document.getElementById('rop-message-text').addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Typing indicators
        let typingTimer;
        document.getElementById('rop-message-text').addEventListener('input', () => {
            this.sendTypingStart();
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                this.sendTypingStop();
            }, 1000);
        });

        // Search users
        document.getElementById('rop-user-search').addEventListener('input', (e) => {
            this.searchUsers(e.target.value);
        });
    }

    handleMessage(data) {
        switch(data.type) {
            case 'auth_success':
                this.currentUserId = data.user_id;
                this.loadConversations();
                break;
            case 'new_message':
                this.displayMessage(data.message);
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

    loadConversations() {
        this.ws.send(JSON.stringify({
            type: 'get_conversations'
        }));
    }

    displayConversations(conversations) {
        const container = document.getElementById('rop-conversations');
        container.innerHTML = '';

        conversations.forEach(conv => {
            const convElement = document.createElement('div');
            convElement.className = 'rop-conversation-item';
            convElement.innerHTML = `
                <div class="rop-avatar">
                    <img src="${conv.avatar}" alt="${conv.name}">
                    <span class="rop-status ${conv.online ? 'online' : 'offline'}"></span>
                </div>
                <div class="rop-conv-info">
                    <div class="rop-conv-name">${conv.name}</div>
                    <div class="rop-last-message">${conv.last_message}</div>
                </div>
                <div class="rop-conv-meta">
                    <span class="rop-time">${conv.time}</span>
                    ${conv.unread ? `<span class="rop-unread">${conv.unread}</span>` : ''}
                </div>
            `;

            convElement.addEventListener('click', () => {
                this.openConversation(conv.id, conv.name);
            });

            container.appendChild(convElement);
        });
    }

    openConversation(conversationId, userName) {
        this.activeConversation = conversationId;
        document.getElementById('rop-chat-username').textContent = userName;

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
    }
}

// Inicjalizacja panelu po załadowaniu strony
document.addEventListener('DOMContentLoaded', () => {
    if (typeof rop_ajax !== 'undefined') {
        new RopMessagesPanel();
    }
});