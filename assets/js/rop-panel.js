(function ($) {
    'use strict';

    var RopPanel = {
        init: function () {
            this.initProfileEditor();
            this.initForumManager();
            this.initForumPopup();
            this.initNewTopicPopup();
            this.initReplyPopup();
            this.initMessagesPanel();
            console.log('ROP Panel initialized');
            this.autoLoadProfile();
        },

        autoLoadProfile: function () {
            if ($('#panel-container').length > 0) {
                console.log('Auto-loading profile...');
                this.loadProfileTab();
            }
        },

        loadProfileTab: function () {
            var self = this;
            this.setActiveTab('profile');

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_auto_load_profile',
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#panel-container').html(response.data.content);
                    } else {
                        self.showContainerError('Błąd podczas ładowania profilu');
                    }
                },
                error: function () {
                    self.showContainerError('Błąd podczas ładowania profilu');
                }
            });
        },

        loadForumTab: function () {
            var self = this;
            this.setActiveTab('forum');

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_get_forum_topics',
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#panel-container').html(response.data.content);
                    } else {
                        self.showContainerError('Błąd podczas ładowania forum');
                    }
                },
                error: function () {
                    self.showContainerError('Błąd podczas ładowania forum');
                }
            });
        },

        loadMessagesTab: function () {
            var self = this;
            this.setActiveTab('messages');

            // Załaduj customowy panel wiadomości w #panel-container
            $('#panel-container').show();
            $('#messages-rop').hide();

            console.log('Przełączono na customowy panel wiadomości');
            self.loadCustomMessagesPanel();
        },

        loadCustomMessagesPanel: function () {
            const panelContainer = $('#panel-container');

            // Wyczyść kontener
            panelContainer.empty();

            // Dodaj HTML panelu wiadomości
            const messagesHTML = `
        <div id="rop-custom-messages-panel" class="rop-custom-messages-wrapper">
            <div class="rop-messages-header">
                <h3>Wiadomości</h3>
                <div class="rop-header-controls">
                    <button id="rop-refresh-messages" class="rop-btn-secondary">
                        <i class="dashicons dashicons-update"></i> Odśwież
                    </button>
                    <div class="rop-connection-status">Łączenie...</div>
                </div>
            </div>
            <div class="rop-messages-layout">
                <div class="rop-conversations-sidebar">
                    <div class="rop-search-section">
                        <input type="text" id="rop-user-search" placeholder="Szukaj użytkowników..." class="rop-search-input">
                        <button id="rop-new-conversation" class="rop-btn-primary">
                            <i class="dashicons dashicons-plus"></i> Nowa konwersacja
                        </button>
                    </div>
                    <div id="rop-conversations-list" class="rop-conversations-container">
                        <div class="rop-loading-conversations">Ładowanie konwersacji...</div>
                    </div>
                </div>
                <div class="rop-chat-main">
                    <div id="rop-chat-header" class="rop-chat-header" style="display: none;">
                        <div class="rop-chat-user-info">
                            <img id="rop-chat-avatar" src="" alt="" class="rop-chat-user-avatar">
                            <div class="rop-chat-user-details">
                                <span id="rop-chat-username" class="rop-chat-user-name"></span>
                                <span id="rop-user-status" class="rop-user-status-indicator"></span>
                            </div>
                        </div>
                    </div>
                    <div id="rop-messages-container" class="rop-messages-area">
                        <div class="rop-welcome-message">
                            <i class="dashicons dashicons-format-chat"></i>
                            <h4>Wybierz konwersację</h4>
                            <p>Kliknij na konwersację z lewej strony, aby rozpocząć czat</p>
                        </div>
                    </div>
                    <div id="rop-typing-indicator" class="rop-typing-indicator" style="display: none;"></div>
                    <div id="rop-message-input-area" class="rop-message-input-section" style="display: none;">
                        <div class="rop-message-input-wrapper">
                            <textarea id="rop-message-text" placeholder="Napisz wiadomość..." class="rop-message-textarea"></textarea>
                            <div class="rop-message-actions">
                                <button id="rop-send-message" class="rop-btn-send">
                                    <i class="dashicons dashicons-paperplane"></i> Wyślij
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

            panelContainer.html(messagesHTML);

            // Inicjalizuj WebSocket bezpośrednio tutaj
            this.initWebSocketForMessages();
        },

        initWebSocketForMessages: function () {
            // Inicjalizuj WebSocket bezpośrednio bez ładowania dodatkowego pliku
            this.messagesWS = new WebSocket('ws://localhost:8080');

            this.messagesWS.onopen = () => {
                console.log('Messages WebSocket connected');
                this.updateConnectionStatus('connected');
                this.authenticateMessages();
            };

            this.messagesWS.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleMessageData(data);
            };

            this.messagesWS.onclose = () => {
                console.log('Messages WebSocket disconnected');
                this.updateConnectionStatus('disconnected');
                setTimeout(() => this.initWebSocketForMessages(), 3000);
            };

            this.messagesWS.onerror = (error) => {
                console.error('Messages WebSocket error:', error);
                this.updateConnectionStatus('error');
            };

            // Bind events dla panelu wiadomości
            this.bindMessagesEvents();
        },

        updateConnectionStatus: function (status) {
            const statusEl = document.querySelector('.rop-connection-status');
            if (statusEl) {
                statusEl.className = `rop-connection-status ${status}`;
                statusEl.textContent = status === 'connected' ? 'Połączono' :
                    status === 'error' ? 'Błąd połączenia' : 'Rozłączono';
            }
        },

        authenticateMessages: function () {
            if (this.messagesWS && this.messagesWS.readyState === WebSocket.OPEN) {
                this.messagesWS.send(JSON.stringify({
                    type: 'auth',
                    token: rop_panel_ajax.nonce
                }));
            }
        },

        bindMessagesEvents: function () {
            const self = this;

            // Send message
            $(document).on('click', '#rop-send-message', function () {
                self.sendMessage();
            });

            // Enter to send
            $(document).on('keypress', '#rop-message-text', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Search users
            $(document).on('input', '#rop-user-search', function () {
                self.searchUsers($(this).val());
            });

            // Refresh
            $(document).on('click', '#rop-refresh-messages', function () {
                self.loadConversations();
            });
        },

        handleMessageData: function (data) {
            console.log('Received WebSocket message:', data);
            switch (data.type) {
                case 'auth_success':
                    console.log('Auth success, loading conversations...');
                    this.currentUserId = data.user_id;
                    this.loadConversations();
                    break;
                case 'conversations_list':
                    console.log('Conversations received:', data.conversations);
                    this.displayConversations(data.conversations);
                    break;
                case 'messages_history':
                    this.displayMessages(data.messages);
                    break;
                case 'new_message':
                    this.handleNewMessage(data);
                    break;
            }
        },

        sendMessage: function () {
            const messageText = $('#rop-message-text').val().trim();
            if (!messageText || !this.activeConversation) return;

            if (this.messagesWS && this.messagesWS.readyState === WebSocket.OPEN) {
                this.messagesWS.send(JSON.stringify({
                    type: 'send_message',
                    conversation_id: this.activeConversation,
                    message: messageText
                }));

                $('#rop-message-text').val('');
            }
        },

        loadConversations() {
            console.log('📤 Requesting conversations...');
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({
                    type: 'get_conversations'
                }));
            } else {
                console.error('❌ WebSocket not connected');
            }
        },

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

                // Poprawne bindowanie eventu
                const newBtn = document.getElementById('rop-new-conversation-btn');
                if (newBtn) {
                    newBtn.addEventListener('click', () => this.openNewConversationDialog());
                }
                return;
            }

            container.innerHTML = conversations.map(conv => `
        <div class="rop-conversation-item" data-conversation-id="${conv.id}">
            <div class="rop-conversation-avatar">
                <img src="${conv.avatar || 'default-avatar.png'}" alt="${conv.name}" class="rop-avatar-img">
                <span class="rop-status-dot ${conv.online ? 'online' : 'offline'}"></span>
            </div>
            <div class="rop-conversation-info">
                <div class="rop-conversation-header">
                    <span class="rop-conversation-name">${conv.name}</span>
                    <span class="rop-conversation-time">${conv.time || ''}</span>
                </div>
                <div class="rop-conversation-preview">
                    <span class="rop-last-message">${conv.last_message || 'Brak wiadomości'}</span>
                    ${conv.unread ? `<span class="rop-unread-badge">${conv.unread}</span>` : ''}
                </div>
            </div>
        </div>
    `).join('');

            // Poprawne bindowanie eventów dla każdej konwersacji
            container.querySelectorAll('.rop-conversation-item').forEach(item => {
                item.addEventListener('click', () => {
                    const convId = item.dataset.conversationId;
                    const convName = item.querySelector('.rop-conversation-name').textContent;
                    this.openConversation(convId, convName);
                });
            });
        },

        openNewConversationDialog() {
            console.log('Opening new conversation dialog');

        },

        openConversation: function (conversationId, userName) {
            this.activeConversation = conversationId;

            // Pokaż header chatu i input
            $('#rop-chat-header').show();
            $('#rop-message-input-area').show();
            $('.rop-welcome-message').hide();

            // Ustaw nazwę użytkownika
            $('#rop-chat-username').text(userName);

            // Oznacz aktywną konwersację
            $('.rop-conversation-item').removeClass('active');
            $(`.rop-conversation-item[data-conversation-id="${conversationId}"]`).addClass('active');

            // Pobierz wiadomości
            if (this.messagesWS && this.messagesWS.readyState === WebSocket.OPEN) {
                this.messagesWS.send(JSON.stringify({
                    type: 'get_messages',
                    conversation_id: conversationId
                }));
            }
        },

        // Dodaj te funkcje do obiektu RopPanel

        searchUsers: function (searchTerm) {
            if (!searchTerm || searchTerm.length < 2) {
                // Jeśli brak wyszukiwanego terminu, pokaż wszystkie konwersacje
                this.loadConversations();
                return;
            }

            // Wyślij zapytanie o wyszukanie użytkowników
            if (this.messagesWS && this.messagesWS.readyState === WebSocket.OPEN) {
                this.messagesWS.send(JSON.stringify({
                    type: 'search_users',
                    query: searchTerm
                }));
            }
        },

        displayMessages: function (messages) {
            const container = $('#rop-messages-container');

            if (!messages || messages.length === 0) {
                container.html(`
            <div class="rop-no-messages">
                <i class="dashicons dashicons-format-chat"></i>
                <p>Brak wiadomości w tej konwersacji</p>
            </div>
        `);
                return;
            }

            const messagesHTML = messages.map(msg => `
        <div class="rop-message ${msg.sender_id == this.currentUserId ? 'rop-message-own' : 'rop-message-other'}">
            <div class="rop-message-avatar">
                <img src="${msg.sender_avatar}" alt="${msg.sender_name}" class="rop-msg-avatar">
            </div>
            <div class="rop-message-content">
                <div class="rop-message-header">
                    <span class="rop-message-sender">${msg.sender_name}</span>
                    <span class="rop-message-time">${this.formatMessageTime(msg.sent_at)}</span>
                </div>
                <div class="rop-message-text">${this.formatMessageText(msg.message)}</div>
            </div>
        </div>
    `).join('');

            container.html(messagesHTML);
            this.scrollToBottom();
        },

        handleNewMessage: function (data) {
            // Jeśli wiadomość jest z aktualnej konwersacji, dodaj ją do widoku
            if (data.conversation_id === this.activeConversation) {
                const container = $('#rop-messages-container');
                const messageHTML = `
            <div class="rop-message ${data.sender_id == this.currentUserId ? 'rop-message-own' : 'rop-message-other'}">
                <div class="rop-message-avatar">
                    <img src="${data.sender_avatar}" alt="${data.sender_name}" class="rop-msg-avatar">
                </div>
                <div class="rop-message-content">
                    <div class="rop-message-header">
                        <span class="rop-message-sender">${data.sender_name}</span>
                        <span class="rop-message-time">${this.formatMessageTime(data.timestamp)}</span>
                    </div>
                    <div class="rop-message-text">${this.formatMessageText(data.message)}</div>
                </div>
            </div>
        `;

                // Usuń "brak wiadomości" jeśli istnieje
                container.find('.rop-no-messages').remove();
                container.append(messageHTML);
                this.scrollToBottom();
            }

            // Aktualizuj listę konwersacji (pokaż nową wiadomość)
            this.loadConversations();

            // Pokaż powiadomienie jeśli nie jest to nasza wiadomość i nie jest z aktualnej konwersacji
            if (data.sender_id != this.currentUserId && data.conversation_id !== this.activeConversation) {
                this.showNotification(data.sender_name, data.message);
            }
        },

        formatMessageTime: function (timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) { // mniej niż minuta
                return 'teraz';
            } else if (diff < 3600000) { // mniej niż godzina
                return `${Math.floor(diff / 60000)} min temu`;
            } else if (diff < 86400000) { // mniej niż dzień
                const hours = Math.floor(diff / 3600000);
                return `${hours} godz temu`;
            } else {
                return date.toLocaleDateString('pl-PL', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        },

        formatMessageText: function (text) {
            // Podstawowe formatowanie tekstu - escape HTML i zamiana nowych linii
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/\n/g, '<br>');
        },

        scrollToBottom: function () {
            const container = $('#rop-messages-container');
            if (container.length) {
                container.scrollTop(container[0].scrollHeight);
            }
        },

        showNotification: function (senderName, message) {
            // Sprawdź czy przeglądarki obsługuje powiadomienia
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(`Nowa wiadomość od ${senderName}`, {
                    body: message.length > 50 ? message.substring(0, 50) + '...' : message,
                    icon: '/wp-content/plugins/rop-panel/assets/img/notification-icon.png'
                });
            } else if ('Notification' in window && Notification.permission !== 'denied') {
                // Poproś o pozwolenie
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        this.showNotification(senderName, message);
                    }
                });
            }

            // Pokaż również powiadomienie w UI
            this.showUINotification(`Nowa wiadomość od ${senderName}`);
        },

        showUINotification: function (message) {
            // Utwórz powiadomienie w UI
            const notification = $(`
        <div class="rop-ui-notification">
            <i class="dashicons dashicons-format-chat"></i>
            <span>${message}</span>
            <button class="rop-notification-close">&times;</button>
        </div>
    `);

            $('body').append(notification);

            // Auto hide po 5 sekundach
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 5000);

            // Bind close button
            notification.find('.rop-notification-close').on('click', function () {
                notification.fadeOut(() => notification.remove());
            });
        },

        // Zaktualizuj również handleMessageData żeby obsługiwać wyszukiwanie
        handleMessageData: function (data) {
            console.log('🔵 WebSocket message received:', data); // Debug

            switch (data.type) {
                case 'auth_success':
                    console.log('✅ Authentication successful, user ID:', data.user_id);
                    this.currentUserId = data.user_id;
                    this.loadConversations();
                    break;
                case 'conversations_list':
                    console.log('📋 Conversations received:', data.conversations);
                    if (data.conversations && data.conversations.length > 0) {
                        this.displayConversations(data.conversations);
                    } else {
                        console.log('⚠️ No conversations found');
                        this.displayConversations([]);
                    }
                    break;
                case 'messages_history':
                case 'messages_list':
                    console.log('💬 Messages received:', data.messages);
                    this.displayMessages(data.messages);
                    break;
                case 'new_message':
                    this.handleNewMessage(data);
                    break;
                case 'search_results':
                    this.displaySearchResults(data.users);
                    break;
                case 'typing_start':
                    this.showTypingIndicator(data);
                    break;
                case 'typing_stop':
                    this.hideTypingIndicator(data);
                    break;
                case 'user_status':
                    this.updateUserStatus(data);
                    break;
                default:
                    console.log('Unhandled message type:', data.type);
            }
        },

        displaySearchResults: function (users) {
            const container = $('#rop-conversations-list');

            if (!users || users.length === 0) {
                container.html(`
            <div class="rop-no-results">
                <i class="dashicons dashicons-search"></i>
                <p>Brak wyników wyszukiwania</p>
            </div>
        `);
                return;
            }

            const usersHTML = users.map(user => `
        <div class="rop-user-search-item" data-user-id="${user.id}">
            <div class="rop-conversation-avatar">
                <img src="${user.avatar}" alt="${user.name}" class="rop-avatar-img">
                <span class="rop-status-dot ${user.online ? 'online' : 'offline'}"></span>
            </div>
            <div class="rop-conversation-info">
                <div class="rop-conversation-header">
                    <span class="rop-conversation-name">${user.name}</span>
                </div>
                <div class="rop-conversation-preview">
                    <span class="rop-last-message">Kliknij aby rozpocząć konwersację</span>
                </div>
            </div>
        </div>
    `).join('');

            container.html(usersHTML);

            // Bind click events dla wyszukanych użytkowników
            const self = this;
            $('.rop-user-search-item').on('click', function () {
                const userId = $(this).data('user-id');
                const userName = $(this).find('.rop-conversation-name').text();
                self.startNewConversation(userId, userName);
            });
        },

        startNewConversation: function (userId, userName) {
            // Rozpocznij nową konwersację z użytkownikiem
            if (this.messagesWS && this.messagesWS.readyState === WebSocket.OPEN) {
                this.messagesWS.send(JSON.stringify({
                    type: 'start_conversation',
                    recipient_id: userId
                }));
            }

            // Tymczasowo otwórz konwersację (może być potrzebne ID z serwera)
            this.openConversation(null, userName);
            this.tempRecipientId = userId; // Tymczasowe rozwiązanie
        },

        showTypingIndicator: function (data) {
            if (data.conversation_id === this.activeConversation && data.user_id !== this.currentUserId) {
                const indicator = $('#rop-typing-indicator');
                indicator.text(`${data.user_name} pisze...`).show();
            }
        },

        hideTypingIndicator: function (data) {
            if (data.conversation_id === this.activeConversation) {
                $('#rop-typing-indicator').hide();
            }
        },

        updateUserStatus: function (data) {
            // Aktualizuj status użytkownika w liście konwersacji
            $(`.rop-conversation-item[data-user-id="${data.user_id}"] .rop-status-dot`)
                .removeClass('online offline')
                .addClass(data.status);
        },

        setActiveTab: function (tabName) {
            $('.rop-tab').removeClass('active');
            if (tabName === 'profile') {
                $('#company-profile').addClass('active');
                $('#panel-container').show();
                $('#messages-rop').hide();
            } else if (tabName === 'forum') {
                $('#rop-forum').addClass('active');
                $('#panel-container').show();
                $('#messages-rop').hide();
            } else if (tabName === 'messages') {
                $('#rop-messages').addClass('active');
                $('#panel-container').show();
                //                 $('#messages-rop').show();
            }
        },

        initMessagesPanel: function () {
            var self = this;

            $(document).on('click', '#rop-messages', function (e) {
                e.preventDefault();
                console.log('Messages tab clicked');
                self.loadMessagesTab();
            });
        },

        initProfileEditor: function () {
            var self = this;

            $(document).on('click', '#company-profile', function (e) {
                e.preventDefault();
                console.log('Profile tab clicked');
                self.loadProfileTab();
            });

            $(document).on('submit', '#rop-company-profile-form', function (e) {
                e.preventDefault();
                self.saveCompanyProfile();
            });

            $(document).on('click', '#rop-logo-upload-btn', function () {
                $('#rop-logo-input').click();
            });

            $(document).on('change', '#rop-logo-input', function () {
                if (this.files && this.files[0]) {
                    self.uploadLogo(this.files[0]);
                }
            });

            $(document).on('click', '#rop-logo-delete-btn', function () {
                self.deleteLogo();
            });
        },

        initForumManager: function () {
            var self = this;

            $(document).on('click', '#rop-forum', function (e) {
                e.preventDefault();
                console.log('Forum tab clicked');
                self.loadForumTab();
            });

            $(document).on('change', '#rop-forum-category, #rop-forum-sort', function () {
                console.log('Filter changed');
                self.filterForumTopics();
            });

            $(document).on('click', '#rop-new-topic', function () {
                console.log('New topic clicked');
                self.openNewTopicPopup();
            });

            $(document).on('click', '#rop-first-topic', function () {
                console.log('First topic clicked');
                var forumId = $(this).data('forum-id') || 0;
                self.openNewTopicPopup(forumId);
            });

            $(document).on('click', '#rop-load-more-topics', function () {
                self.loadMoreTopics();
            });

            $(document).on('click', '.rop-like-btn', function (e) {
                e.stopPropagation();
                var topicId = $(this).data('topic-id');
                console.log('Like button clicked for topic:', topicId);
                self.toggleTopicLike(topicId, $(this));
            });

            $(document).on('click', '.rop-topic-item', function (e) {
                if ($(e.target).closest('.rop-like-btn').length > 0) {
                    return;
                }

                var topicId = $(this).data('topic-id');
                console.log('Topic container clicked, opening popup for topic ID:', topicId);
                self.openForumPopupById(topicId);
            });
        },

        initNewTopicPopup: function () {
            var self = this;

            $(document).on('click', '.rop-new-topic-close, .rop-popup-close', function () {
                if ($(this).closest('#rop-new-topic-popup-overlay').length > 0) {
                    self.closeNewTopicPopup();
                } else if ($(this).closest('#rop-forum-popup-overlay').length > 0) {
                    self.closeForumPopup();
                }
            });

            $(document).on('click', '#rop-new-topic-popup-overlay', function (e) {
                if (e.target === this) {
                    self.closeNewTopicPopup();
                }
            });

            $(document).on('keydown', function (e) {
                if (e.keyCode === 27) {
                    if ($('#rop-new-topic-popup-overlay').is(':visible')) {
                        self.closeNewTopicPopup();
                    } else if ($('#rop-forum-popup-overlay').is(':visible')) {
                        self.closeForumPopup();
                    }
                }
            });

            $(document).on('submit', '#rop-new-topic-form', function (e) {
                e.preventDefault();
                self.submitNewTopic();
            });

            $(document).on('click', '#rop-cancel-new-topic', function () {
                self.closeNewTopicPopup();
            });
        },

        openNewTopicPopup: function (selectedForum) {
            var self = this;

            $('#rop-new-topic-popup-overlay').fadeIn(300);
            $('#rop-new-topic-content').html('<div class="rop-loading" style="text-align: center; padding: 40px;">Ładowanie formularza...</div>');

            var ajaxData = {
                action: 'rop_get_new_topic_form',
                nonce: rop_panel_ajax.nonce
            };

            if (selectedForum && selectedForum > 0) {
                ajaxData.selected_forum = selectedForum;
            }

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function (response) {
                    if (response.success) {
                        $('#rop-new-topic-content').html(response.data.content);
                    } else {
                        self.showNewTopicError(response.data || 'Błąd podczas ładowania formularza');
                    }
                },
                error: function () {
                    self.showNewTopicError('Wystąpił błąd podczas ładowania formularza');
                }
            });
        },

        submitNewTopic: function () {
            var self = this;
            var $form = $('#rop-new-topic-form');
            var $submitBtn = $('#rop-submit-new-topic');

            $submitBtn.prop('disabled', true).text('Publikowanie...');
            $('.rop-error, .rop-success').remove();

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=rop_create_new_topic&nonce=' + rop_panel_ajax.nonce,
                success: function (response) {
                    if (response.success) {
                        self.showNewTopicSuccess(response.data);
                        setTimeout(function () {
                            self.closeNewTopicPopup();
                            if ($('#rop-forum').hasClass('active')) {
                                self.loadForumTab();
                            }
                        }, 2000);
                    } else {
                        self.showNewTopicError(response.data);
                    }
                },
                error: function () {
                    self.showNewTopicError('Wystąpił błąd podczas tworzenia tematu');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text('Opublikuj post');
                }
            });
        },

        closeNewTopicPopup: function () {
            $('#rop-new-topic-popup-overlay').fadeOut(300);
        },

        showNewTopicError: function (message) {
            var errorHtml = '<div class="rop-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' + message + '</div>';
            $('.rop-error, .rop-success').remove();
            $('#rop-new-topic-content').prepend(errorHtml);
        },

        showNewTopicSuccess: function (message) {
            var successHtml = '<div class="rop-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' + message + '</div>';
            $('.rop-error, .rop-success').remove();
            $('#rop-new-topic-content').prepend(successHtml);
        },

        filterForumTopics: function () {
            var self = this;
            var forumId = $('#rop-forum-category').val();
            var sortBy = $('#rop-forum-sort').val();

            console.log('Filtering topics - Forum:', forumId, 'Sort:', sortBy);

            $('#rop-forum-topics-list').html('<div class="rop-loading" style="text-align: center; padding: 40px;">Filtrowanie...</div>');

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_filter_forum_topics',
                    forum_id: forumId,
                    sort_by: sortBy,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#rop-forum-topics-list').html(response.data.topics_html);
                    } else {
                        self.showContainerError('Błąd podczas filtrowania');
                    }
                },
                error: function () {
                    self.showContainerError('Błąd podczas filtrowania');
                }
            });
        },

        loadMoreTopics: function () {
            console.log('Loading more topics...');
        },

        initForumPopup: function () {
            var self = this;

            $(document).on('click', '#rop-forum-popup-overlay', function (e) {
                if (e.target === this) {
                    self.closeForumPopup();
                }
            });

            $(document).on('click', '#rop-popup-reply', function () {
                var topicId = $('#rop-popup-like-btn').data('topic-id');
                if (topicId) {
                    self.openReplyPopup(topicId);
                }
            });

            $(document).on('click', '#rop-popup-like-btn', function () {
                var topicId = $(this).data('topic-id');
                self.togglePopupLike(topicId);
            });

            $(document).on('click', '#rop-popup-comment-btn', function () {
                var topicId = $(this).data('topic-id');
                self.showTopicReplies(topicId);
            });

            $(document).on('click', '#rop-back-to-post', function () {
                self.showPostContent();
            });

            $(document).on('click', '.rop-replies-page-btn', function () {
                var topicId = $(this).data('topic-id');
                var page = $(this).data('page');
                self.loadRepliesPage(topicId, page);
            });

            $(document).on('click', '.rop-delete-reply-btn', function (e) {
                e.stopPropagation();
                var replyId = $(this).data('reply-id');
                console.log('Delete reply clicked for:', replyId);
                self.deleteReply(replyId, $(this));
            });

            $(document).on('click', '#rop-popup-delete-btn', function () {
                var topicId = $(this).data('topic-id');
                console.log('Delete topic from popup clicked for:', topicId);
                self.deleteTopic(topicId, $(this));
            });
        },

        openForumPopupById: function (topicId) {
            var self = this;

            if (!topicId) {
                console.error('No topic ID provided');
                return;
            }

            $('#rop-forum-popup-overlay').fadeIn(300);
            $('.rop-popup-body').html('<div class="rop-loading" style="text-align: center; padding: 40px;">Ładowanie...</div>');

            $('#rop-popup-title').text('');
            $('.rop-author-avatar').empty();
            $('.rop-author-name').text('');
            $('.rop-author-company').text('');
            $('.rop-popup-category').text('');
            $('.rop-popup-date').text('');
            $('#rop-popup-reply').hide();

            $('#rop-popup-replies').hide();
            $('.rop-popup-body').show();

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_get_forum_post_by_id',
                    topic_id: topicId,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    console.log('Popup response:', response);
                    if (response.success) {
                        self.populateForumPopup(response.data);
                    } else {
                        self.showForumError(response.data || 'Błąd podczas ładowania tematu');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Popup AJAX error:', xhr, status, error);
                    self.showForumError('Wystąpił błąd podczas ładowania tematu');
                }
            });
        },

        deleteReply: function (replyId, $button) {
            var self = this;

            if (!replyId) {
                console.error('No reply ID provided for delete');
                return;
            }

            if (!confirm('Czy na pewno chcesz usunąć tę odpowiedź? Ta operacja jest nieodwracalna.')) {
                return;
            }

            $button.prop('disabled', true);
            $('.rop-reply-item[data-reply-id="' + replyId + '"]').addClass('deleting');

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_delete_reply',
                    reply_id: replyId,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    console.log('Delete reply response:', response);
                    if (response.success) {
                        $('.rop-reply-item[data-reply-id="' + replyId + '"]').fadeOut(300, function () {
                            $(this).remove();

                            if ($('.rop-reply-item').length === 0) {
                                $('#rop-replies-content').html('<div class="rop-no-replies">Brak komentarzy do tego posta.</div>');
                            }
                        });

                        if (response.data && typeof response.data === 'object') {
                            if (response.data.reply_count !== undefined) {
                                var newCount = response.data.reply_count;
                                $('#rop-popup-comment-count').text(newCount);

                                if (response.data.topic_id) {
                                    $('.rop-topic-item[data-topic-id="' + response.data.topic_id + '"] .rop-comment-count').text(newCount);
                                }
                                console.log('Updated comment counts to:', newCount);
                            }
                        } else {
                            var $currentPopupCount = $('#rop-popup-comment-count');
                            if ($currentPopupCount.length > 0) {
                                var currentCount = parseInt($currentPopupCount.text()) || 0;
                                var newCount = Math.max(0, currentCount - 1);
                                $currentPopupCount.text(newCount);

                                var topicId = $('#rop-popup-like-btn').data('topic-id');
                                if (topicId) {
                                    $('.rop-topic-item[data-topic-id="' + topicId + '"] .rop-comment-count').text(newCount);
                                }
                                console.log('Manually decremented count to:', newCount);
                            }
                        }

                        console.log('Reply deleted successfully');
                    } else {
                        console.error('Delete reply failed:', response.data);
                        $('.rop-reply-item[data-reply-id="' + replyId + '"]').removeClass('deleting');
                        alert('Błąd: ' + (response.data || 'Nie udało się usunąć odpowiedzi'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Delete reply AJAX error:', xhr.responseText);
                    $('.rop-reply-item[data-reply-id="' + replyId + '"]').removeClass('deleting');
                    alert('Wystąpił błąd podczas usuwania odpowiedzi');
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        populateForumPopup: function (data) {
            console.log('Populating forum popup with data:', data);

            $('#rop-popup-title').text(data.title);

            if (data.author.avatar) {
                $('.rop-author-avatar').html('<img src="' + data.author.avatar + '" alt="' + data.author.name + '">');
            }
            $('.rop-author-name').text(data.author.name);
            $('.rop-author-company').text(data.author.company);

            $('.rop-popup-category').text(data.category);
            $('.rop-popup-date').text(data.date);

            $('.rop-popup-body').html(data.content);

            if (data.stats) {
                $('#rop-popup-like-btn').data('topic-id', data.id);
                $('#rop-popup-like-count').text(data.stats.likes);
                if (data.stats.is_liked) {
                    $('#rop-popup-like-btn').addClass('liked');
                } else {
                    $('#rop-popup-like-btn').removeClass('liked');
                }

                $('#rop-popup-comment-btn').data('topic-id', data.id);
                $('#rop-popup-comment-count').text(data.stats.replies);

                console.log('Set comment count to:', data.stats.replies);
            }

            if (data.can_delete) {
                if ($('#rop-popup-delete-btn').length === 0) {
                    $('.rop-popup-footer').prepend(
                        '<button class="rop-btn rop-btn-danger" id="rop-popup-delete-btn" data-topic-id="' + data.id + '">' +
                        '🗑️ Usuń temat' +
                        '</button>'
                    );
                } else {
                    $('#rop-popup-delete-btn').data('topic-id', data.id).show();
                }
            } else {
                $('#rop-popup-delete-btn').hide();
            }

            if (data.can_reply) {
                $('#rop-popup-reply').show();
            } else {
                $('#rop-popup-reply').hide();
            }
        },

        togglePopupLike: function (topicId) {
            var self = this;

            if (!topicId) {
                console.error('No topic ID provided for popup like');
                return;
            }

            var $button = $('#rop-popup-like-btn');
            $button.prop('disabled', true);

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_toggle_topic_like',
                    topic_id: topicId,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;

                        if (data.is_liked) {
                            $button.addClass('liked');
                        } else {
                            $button.removeClass('liked');
                        }

                        $('#rop-popup-like-count').text(data.likes_count);

                        $('.rop-topic-item[data-topic-id="' + topicId + '"] .rop-like-count').text(data.likes_count);
                        if (data.is_liked) {
                            $('.rop-topic-item[data-topic-id="' + topicId + '"] .rop-like-btn').addClass('liked');
                        } else {
                            $('.rop-topic-item[data-topic-id="' + topicId + '"] .rop-like-btn').removeClass('liked');
                        }

                        console.log('Popup like status:', data.action, 'New count:', data.likes_count);
                    } else {
                        console.error('Popup like toggle failed:', response.data);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Popup like toggle AJAX error:', xhr, status, error);
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        showTopicReplies: function (topicId) {
            var self = this;

            if (!topicId) {
                console.error('No topic ID provided for replies');
                return;
            }

            $('.rop-popup-body').hide();
            $('#rop-popup-replies').show();

            this.loadRepliesPage(topicId, 1);
        },

        loadRepliesPage: function (topicId, page) {
            var self = this;

            $('#rop-replies-content').html('<div class="rop-loading" style="text-align: center; padding: 40px;">Ładowanie komentarzy...</div>');
            $('#rop-replies-pagination').empty();

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_get_topic_replies',
                    topic_id: topicId,
                    page: page,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    console.log('Replies response:', response);
                    if (response.success) {
                        self.populateReplies(response.data, topicId);
                    } else {
                        self.showRepliesError(response.data || 'Błąd podczas ładowania komentarzy');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Replies AJAX error:', xhr, status, error);
                    self.showRepliesError('Wystąpił błąd podczas ładowania komentarzy');
                }
            });
        },

        populateReplies: function (data, topicId) {
            var repliesHtml = '';

            if (data.replies && data.replies.length > 0) {
                data.replies.forEach(function (reply) {
                    repliesHtml += '<div class="rop-reply-item" data-reply-id="' + reply.id + '">';
                    repliesHtml += '<div class="rop-reply-author">';
                    repliesHtml += '<div class="rop-reply-avatar">';
                    if (reply.author.avatar) {
                        repliesHtml += '<img src="' + reply.author.avatar + '" alt="' + reply.author.name + '">';
                    }
                    repliesHtml += '</div>';
                    repliesHtml += '<div class="rop-reply-author-info">';
                    repliesHtml += '<div class="rop-reply-author-name">' + reply.author.name + '</div>';
                    if (reply.author.company) {
                        repliesHtml += '<div class="rop-reply-author-company">' + reply.author.company + '</div>';
                    }
                    repliesHtml += '</div>';
                    repliesHtml += '<div class="rop-reply-date">' + reply.date + '</div>';
                    repliesHtml += '</div>';

                    repliesHtml += '<div class="rop-reply-content">' + reply.content + '</div>';

                    if (reply.can_delete) {
                        repliesHtml += '<div class="rop-reply-actions">';
                        repliesHtml += '<button class="rop-delete-reply-btn" data-reply-id="' + reply.id + '" title="Usuń odpowiedź">';
                        repliesHtml += '<svg class="rop-delete-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                        repliesHtml += '<polyline points="3,6 5,6 21,6"></polyline>';
                        repliesHtml += '<path d="m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"></path>';
                        repliesHtml += '<line x1="10" y1="11" x2="10" y2="17"></line>';
                        repliesHtml += '<line x1="14" y1="11" x2="14" y2="17"></line>';
                        repliesHtml += '</svg>';
                        repliesHtml += '</button>';
                        repliesHtml += '</div>';
                    }

                    repliesHtml += '</div>';
                });
            } else {
                repliesHtml = '<div class="rop-no-replies">Brak komentarzy do tego posta.</div>';
            }

            $('#rop-replies-content').html(repliesHtml);

            if (data.pagination && data.pagination.total_pages > 1) {
                this.renderRepliesPagination(data.pagination, topicId);
            }
        },

        renderRepliesPagination: function (pagination, topicId) {
            var paginationHtml = '<div class="rop-pagination">';

            if (pagination.has_prev) {
                paginationHtml += '<button class="rop-btn rop-btn-secondary rop-replies-page-btn" data-topic-id="' + topicId + '" data-page="' + (pagination.current_page - 1) + '">← Poprzednia</button>';
            }

            paginationHtml += '<span class="rop-pagination-info">Strona ' + pagination.current_page + ' z ' + pagination.total_pages + '</span>';

            if (pagination.has_next) {
                paginationHtml += '<button class="rop-btn rop-btn-secondary rop-replies-page-btn" data-topic-id="' + topicId + '" data-page="' + (pagination.current_page + 1) + '">Następna →</button>';
            }

            paginationHtml += '</div>';

            $('#rop-replies-pagination').html(paginationHtml);
        },

        showPostContent: function () {
            $('#rop-popup-replies').hide();
            $('.rop-popup-body').show();
        },

        showRepliesError: function (message) {
            $('#rop-replies-content').html('<div class="rop-error" style="text-align: center; color: #dc3545; padding: 40px 20px;">' + message + '</div>');
        },

        showForumError: function (message) {
            $('.rop-popup-body').html('<div class="rop-error" style="text-align: center; color: #dc3545; padding: 40px 20px;">' + message + '</div>');
        },

        closeForumPopup: function () {
            $('#rop-forum-popup-overlay').fadeOut(300);
        },

        saveCompanyProfile: function () {
            var self = this;
            var $form = $('#rop-company-profile-form');
            var $submitBtn = $('#rop-save-profile');

            $submitBtn.prop('disabled', true).text('Zapisywanie...');
            $('.rop-error, .rop-success').remove();

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: $form.serialize() + '&action=rop_save_company_profile&nonce=' + rop_panel_ajax.nonce,
                success: function (response) {
                    if (response.success) {
                        self.showContainerSuccess(response.data);
                    } else {
                        self.showContainerError(response.data);
                    }
                },
                error: function () {
                    self.showContainerError('Wystąpił błąd podczas zapisywania');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).html('<i class="rop-icon-save"></i> Zapisz zmiany');
                }
            });
        },

        uploadLogo: function (file) {
            var self = this;

            if (!file) return;

            var allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            var maxSize = 2 * 1024 * 1024;

            if (!allowedTypes.includes(file.type)) {
                this.showContainerError('Nieprawidłowy typ pliku. Dozwolone są tylko PNG i JPG.');
                return;
            }

            if (file.size > maxSize) {
                this.showContainerError('Plik jest zbyt duży. Maksymalny rozmiar to 2MB.');
                return;
            }

            var formData = new FormData();
            formData.append('logo', file);
            formData.append('action', 'rop_upload_company_logo');
            formData.append('nonce', rop_panel_ajax.nonce);

            var $uploadBtn = $('#rop-logo-upload-btn');
            $uploadBtn.prop('disabled', true).text('Przesyłanie...');

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        $('#rop-logo-placeholder').hide();
                        $('#rop-logo-image').remove();
                        $('.rop-logo-overlay').remove();
                        $('.rop-logo-preview').append(
                            '<img src="' + response.data.logo_url + '" alt="Logo firmy" id="rop-logo-image">' +
                            '<div class="rop-logo-overlay">' +
                            '<button type="button" class="rop-logo-delete" id="rop-logo-delete-btn" title="Usuń logo">' +
                            '<span>&times;</span>' +
                            '</button>' +
                            '</div>'
                        );
                        self.showContainerSuccess(response.data.message);
                    } else {
                        self.showContainerError(response.data);
                    }
                },
                error: function () {
                    self.showContainerError('Błąd podczas przesyłania pliku');
                },
                complete: function () {
                    $uploadBtn.prop('disabled', false).text('Prześlij logo');
                }
            });
        },

        deleteTopic: function (topicId, $button) {
            var self = this;

            if (!topicId) {
                console.error('No topic ID provided for delete');
                return;
            }

            if (!confirm('Czy na pewno chcesz usunąć ten temat? Ta operacja jest nieodwracalna.')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_delete_topic',
                    topic_id: topicId,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    console.log('Delete topic response:', response);
                    if (response.success) {
                        self.closeForumPopup();

                        if ($('#rop-forum').hasClass('active')) {
                            self.loadForumTab();
                        }

                        self.showContainerSuccess(response.data);
                    } else {
                        console.error('Delete topic failed:', response.data);
                        if ($('#rop-forum-popup-overlay').is(':visible')) {
                            self.showForumError(response.data || 'Błąd podczas usuwania tematu');
                        } else {
                            self.showContainerError(response.data || 'Błąd podczas usuwania tematu');
                        }
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Delete topic AJAX error:', xhr, status, error);
                    if ($('#rop-forum-popup-overlay').is(':visible')) {
                        self.showForumError('Wystąpił błąd podczas usuwania tematu');
                    } else {
                        self.showContainerError('Wystąpił błąd podczas usuwania tematu');
                    }
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        deleteLogo: function () {
            var self = this;

            if (!confirm('Czy na pewno chcesz usunąć logo firmy?')) {
                return;
            }

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_delete_company_logo',
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $('#rop-logo-image').remove();
                        $('.rop-logo-overlay').remove();
                        $('.rop-logo-preview').append('<div class="rop-logo-placeholder" id="rop-logo-placeholder"><i class="rop-icon-image"></i></div>');
                        $('#rop-logo-placeholder').show();

                        self.showContainerSuccess(response.data);
                    } else {
                        self.showContainerError(response.data);
                    }
                },
                error: function () {
                    self.showContainerError('Błąd podczas usuwania logo');
                }
            });
        },

        initReplyPopup: function () {
            var self = this;

            $(document).on('click', '.rop-reply-close', function () {
                self.closeReplyPopup();
            });

            $(document).on('click', '#rop-reply-popup-overlay', function (e) {
                if (e.target === this) {
                    self.closeReplyPopup();
                }
            });

            $(document).on('keydown', function (e) {
                if (e.keyCode === 27 && $('#rop-reply-popup-overlay').is(':visible')) {
                    self.closeReplyPopup();
                }
            });

            $(document).on('submit', '#rop-reply-form', function (e) {
                e.preventDefault();
                self.submitReply();
            });

            $(document).on('click', '#rop-cancel-reply', function () {
                self.closeReplyPopup();
            });
        },

        openReplyPopup: function (topicId) {
            var self = this;

            if (!topicId) {
                console.error('No topic ID provided for reply');
                return;
            }

            $('#rop-reply-form').data('topic-id', topicId);

            $('#reply_content').val('');
            $('.rop-error, .rop-success').remove();

            $('#rop-reply-popup-overlay').fadeIn(300);

            setTimeout(function () {
                $('#reply_content').focus();
            }, 400);
        },

        submitReply: function () {
            var self = this;
            var $form = $('#rop-reply-form');
            var $submitBtn = $('#rop-submit-reply');
            var topicId = $form.data('topic-id');
            var content = $('#reply_content').val().trim();

            console.log('Submitting reply:', {
                topicId: topicId,
                content: content
            });

            if (!topicId || !content) {
                self.showReplyError('Brak wymaganych danych');
                return;
            }

            $submitBtn.prop('disabled', true).text('Dodawanie...');
            $('.rop-error, .rop-success').remove();

            $.ajax({
                url: rop_panel_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'rop_create_reply',
                    topic_id: topicId,
                    reply_content: content,
                    nonce: rop_panel_ajax.nonce
                },
                success: function (response) {
                    console.log('Create reply response:', response);
                    if (response.success) {
                        self.showReplySuccess(response.data.message || 'Odpowiedź została dodana pomyślnie!');

                        if (response.data && typeof response.data === 'object' && response.data.reply_count !== undefined) {
                            var newCount = response.data.reply_count;
                            $('#rop-popup-comment-count').text(newCount);
                            $('.rop-topic-item[data-topic-id="' + topicId + '"] .rop-comment-count').text(newCount);
                            console.log('Updated comment counts to:', newCount);
                        } else {
                            var $currentPopupCount = $('#rop-popup-comment-count');
                            if ($currentPopupCount.length > 0) {
                                var currentCount = parseInt($currentPopupCount.text()) || 0;
                                var newCount = currentCount + 1;
                                $currentPopupCount.text(newCount);
                                $('.rop-topic-item[data-topic-id="' + topicId + '"] .rop-comment-count').text(newCount);
                                console.log('Manually incremented count to:', newCount);
                            }
                        }

                        $('#reply_content').val('');

                        setTimeout(function () {
                            self.closeReplyPopup();

                            if ($('#rop-popup-replies').is(':visible')) {
                                self.showTopicReplies(topicId);
                            }
                        }, 2000);
                    } else {
                        self.showReplyError(response.data || 'Błąd podczas dodawania odpowiedzi');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr.responseText);
                    self.showReplyError('Wystąpił błąd połączenia');
                },
                complete: function () {
                    $submitBtn.prop('disabled', false).text('Dodaj odpowiedź');
                }
            });
        },

        closeReplyPopup: function () {
            $('#rop-reply-popup-overlay').fadeOut(300);
        },

        showReplyError: function (message) {
            var errorHtml = '<div class="rop-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' + message + '</div>';
            $('.rop-error, .rop-success').remove();
            $('#rop-reply-content').prepend(errorHtml);
        },

        showReplySuccess: function (message) {
            var successHtml = '<div class="rop-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' + message + '</div>';
            $('.rop-error, .rop-success').remove();
            $('#rop-reply-content').prepend(successHtml);
        },

        showContainerError: function (message) {
            var errorHtml = '<div class="rop-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' + message + '</div>';
            $('.rop-error, .rop-success').remove();
            $('#panel-container').prepend(errorHtml);
        },

        showContainerSuccess: function (message) {
            var successHtml = '<div class="rop-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' + message + '</div>';
            $('.rop-error, .rop-success').remove();
            $('#panel-container').prepend(successHtml);
        }
    };

    $(document).ready(function () {
        RopPanel.init();

        if ($('#company-profile').length) {
            console.log('Element #company-profile found');
        }

        if ($('#rop-forum').length) {
            console.log('Element #rop-forum found');
        }

        if ($('#rop-messages').length) {
            console.log('Element #rop-messages found');
        }

        if ($('#panel-container').length) {
            console.log('Element #panel-container found on page');
        }
    });

})(jQuery);