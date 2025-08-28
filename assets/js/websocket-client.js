class RopWebSocketClient {
    constructor() {
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectInterval = 3000;
        this.currentConversationId = null;
        this.isTyping = false;
        this.typingTimeout = null;
        
        this.init();
    }

    init() {
        this.connect();
        this.setupEventListeners();
    }

    connect() {
        try {
            this.ws = new WebSocket('ws://localhost:8080');
            
            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.reconnectAttempts = 0;
                this.authenticate();
            };

            this.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            };

            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                this.attemptReconnect();
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };

        } catch (error) {
            console.error('Failed to connect to WebSocket:', error);
            this.attemptReconnect();
        }
    }

    authenticate() {
        const token = this.getAuthToken();
        if (token) {
            this.send({
                type: 'auth',
                token: token
            });
        }
    }

    getAuthToken() {
        // Pobierz token z WordPress (możesz go generować przy logowaniu)
        return window.ropWebSocketToken || null;
    }

    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect... (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
            
            setTimeout(() => {
                this.connect();
            }, this.reconnectInterval);
        } else {
            console.error('Max reconnection attempts reached');
            this.showConnectionError();
        }
    }

    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
        } else {
            console.warn('WebSocket not connected');
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'auth_success':
                console.log('Authenticated successfully');
                this.loadConversations();
                break;
            
            case 'auth_failed':
                console.error('Authentication failed');
                break;
            
            case 'conversations_list':
                this.displayConversations(data.conversations);
                break;
            
            case 'messages_list':
                this.displayMessages(data.messages, data.conversation_id);
                break;
            
            case 'new_message':
                this.handleNewMessage(data);
                break;
            
            case 'user_typing':
                this.showTypingIndicator(data);
                break;
            
            case 'user_stopped_typing':
                this.hideTypingIndicator(data);
                break;
            
            case 'user_status':
                this.updateUserStatus(data);
                break;
        }
    }

    setupEventListeners() {
        // Event listener dla wysyłania wiadomości
        document.addEventListener('click', (e) => {
            if (e.target.id === 'rop-send-message-btn') {
                this.sendMessage();
            }
        });

        // Event listener dla Enter w polu wiadomości
        document.addEventListener('keypress', (e) => {
            if (e.target.id === 'rop-message-input' && e.key === 'Enter') {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Typing indicators
        document.addEventListener('input', (e) => {
            if (e.target.id === 'rop-message-input') {
                this.handleTyping();
            }
        });

        // Kliknięcie na konwersację
        document.addEventListener('click', (e) => {
            if (e.target.closest('.rop-conversation-item')) {
                const conversationId = e.target.closest('.rop-conversation-item').dataset.conversationId;
                this.openConversation(conversationId);
            }
        });
    }

    loadConversations() {
        this.send({
            type: 'get_conversations'
        });
    }

    openConversation(conversationId) {
        this.currentConversationId = conversationId;
        
        this.send({
            type: 'get_messages',
            conversation_id: conversationId,
            page: 1
        });

        // Oznacz jako przeczytane
        this.send({
            type: 'mark_read',
            conversation_id: conversationId
        });
    }

    sendMessage() {
        const messageInput = document.getElementById('rop-message-input');
        const message = messageInput.value.trim();
        
        if (!message || !this.currentConversationId) {
            return;
        }

        this.send({
            type: 'send_message',
            conversation_id: this.currentConversationId,
            message: message
        });

        messageInput.value = '';
        this.stopTyping();
    }

    handleTyping() {
        if (!this.isTyping && this.currentConversationId) {
            this.isTyping = true;
            this.send({
                type: 'typing_start',
                conversation_id: this.currentConversationId
            });
        }

        // Zatrzymaj typing po 3 sekundach braku aktywności
        clearTimeout(this.typingTimeout);
        this.typingTimeout = setTimeout(() => {
            this.stopTyping();
        }, 3000);
    }

    stopTyping() {
        if (this.isTyping && this.currentConversationId) {
            this.isTyping = false;
            this.send({
                type: 'typing_stop',
                conversation_id: this.currentConversationId
            });
        }
    }

    displayConversations(conversations) {
        const container = document.getElementById('rop-conversations-list');
        if (!container) return;

        container.innerHTML = conversations.map(conv => `
            <div class="rop-conversation-item" data-conversation-id="${conv.id}">
                <div class="rop-conversation-avatar">
                    <img src="${conv.other_user_avatar}" alt="${conv.other_user_name}">
                </div>
                <div class="rop-conversation-content">
                    <div class="rop-conversation-name">${conv.other_user_name}</div>
                    <div class="rop-conversation-last-message">${conv.last_message || 'Brak wiadomości'}</div>
                    <div class="rop-conversation-time">${this.formatTime(conv.last_message_time)}</div>
                </div>
            </div>
        `).join('');
    }

    displayMessages(messages, conversationId) {
        if (conversationId !== this.currentConversationId) {
            return;
        }

        const container = document.getElementById('rop-messages-list');
        if (!container) return;

        container.innerHTML = messages.map(msg => `
            <div class="rop-message ${msg.sender_id == window.currentUserId ? 'rop-message-own' : 'rop-message-other'}">
                <div class="rop-message-avatar">
                    <img src="${msg.sender_avatar}" alt="${msg.sender_name}">
                </div>
                <div class="rop-message-content">
                    <div class="rop-message-text">${msg.message}</div>
                    <div class="rop-message-time">${this.formatTime(msg.sent_at)}</div>
                </div>
            </div>
        `).join('');

        this.scrollToBottom();
    }

    handleNewMessage(data) {
        if (data.conversation_id === this.currentConversationId) {
            // Dodaj wiadomość do aktualnej konwersacji
            const container = document.getElementById('rop-messages-list');
            if (container) {
                const messageHtml = `
                    <div class="rop-message ${data.sender_id == window.currentUserId ? 'rop-message-own' : 'rop-message-other'}">
                        <div class="rop-message-avatar">
                            <img src="${data.sender_avatar}" alt="${data.sender_name}">
                        </div>
                        <div class="rop-message-content">
                            <div class="rop-message-text">${data.message}</div>
                            <div class="rop-message-time">${this.formatTime(data.timestamp)}</div>
                        </div>
                    </div>
                `;
                container.insertAdjacentHTML('beforeend', messageHtml);
                this.scrollToBottom();
            }
        }

        // Aktualizuj listę konwersacji
        this.loadConversations();
        
        // Pokaż powiadomienie jeśli nie jest to nasza wiadomość
        if (data.sender_id != window.currentUserId) {
            this.showNotification(data.sender_name, data.message);
        }
    }

    showTypingIndicator(data) {
        const indicator = document.getElementById('rop-typing-indicator');
        if (indicator && data.conversation_id === this.currentConversationId) {
            indicator.textContent = `${data.user_name} pisze...`;
            indicator.style.display = 'block';
        }
    }

    hideTypingIndicator(data) {
        const indicator = document.getElementById('rop-typing-indicator');
        if (indicator && data.conversation_id === this.currentConversationId) {
            indicator.style.display = 'none';
        }
    }

    updateUserStatus(data) {
        // Aktualizuj status użytkownika w UI
        const userElements = document.querySelectorAll(`[data-user-id="${data.user_id}"]`);
        userElements.forEach(el => {
            el.classList.toggle('rop-user-online', data.status === 'online');
            el.classList.toggle('rop-user-offline', data.status === 'offline');
        });
    }

    showNotification(senderName, message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(`Nowa wiadomość od ${senderName}`, {
                body: message,
                icon: '/wp-content/plugins/rop-panel/assets/img/notification-icon.png'
            });
        }
    }

    scrollToBottom() {
        const container = document.getElementById('rop-messages-list');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;
        
        if (diff < 60000) { // mniej niż minuta
            return 'teraz';
        } else if (diff < 3600000) { // mniej niż godzina
            return `${Math.floor(diff / 60000)} min temu`;
        } else if (diff < 86400000) { // mniej niż dzień
            return `${Math.floor(diff / 3600000)} godz temu`;
        } else {
            return date.toLocaleDateString();
        }
    }

    showConnectionError() {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'rop-websocket-error';
        errorDiv.innerHTML = `
            <div class="rop-error-message">
                Utracono połączenie z serwerem wiadomości. 
                <button onclick="window.ropWebSocket.connect()">Spróbuj ponownie</button>
            </div>
        `;
        document.body.appendChild(errorDiv);
    }
}

// Inicjalizacja klienta WebSocket
document.addEventListener('DOMContentLoaded', () => {
    if (window.ropWebSocketEnabled) {
        window.ropWebSocket = new RopWebSocketClient();
        
        // Prośba o pozwolenie na powiadomienia
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }
});