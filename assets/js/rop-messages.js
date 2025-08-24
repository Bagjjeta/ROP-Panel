(function($) {
    'use strict';

    let messagesManager = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Kliknięcie w kontener wiadomości
            $(document).on('click', '#rop-messages', this.loadMessages.bind(this));
            
            // Nowa wiadomość
            $(document).on('click', '#rop-new-message, #rop-first-message', this.showNewMessageForm.bind(this));
            
            // Wysyłanie wiadomości
            $(document).on('submit', '#rop-new-message-form', this.sendMessage.bind(this));
            
            // Anulowanie nowej wiadomości
            $(document).on('click', '#rop-cancel-new-message', this.hideNewMessageForm.bind(this));
            
            // Kliknięcie w wiadomość
            $(document).on('click', '.rop-message-item', this.openMessage.bind(this));
        },

        loadMessages: function(e) {
            e.preventDefault();
            
            if ($('#rop-messages').hasClass('active')) {
                return;
            }

            this.showLoading();

            $.ajax({
                url: ropPanel.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rop_get_messages',
                    nonce: ropPanel.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayMessages(response.data.content);
                    } else {
                        this.showError(response.data || 'Wystąpił błąd podczas ładowania wiadomości.');
                    }
                },
                error: () => {
                    this.showError('Wystąpił błąd połączenia.');
                }
            });
        },

        showNewMessageForm: function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Pobierz listę użytkowników
            $.ajax({
                url: ropPanel.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'rop_get_users_list',
                    nonce: ropPanel.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayNewMessageForm(response.data);
                    } else {
                        this.showError('Nie można pobrać listy użytkowników.');
                    }
                }
            });
        },

        displayNewMessageForm: function(users) {
            const popupHTML = `
                <div class="rop-popup-overlay rop-message-popup-overlay">
                    <div class="rop-popup-container">
                        <div class="rop-popup-header">
                            <h2>Nowa wiadomość</h2>
                            <button class="rop-popup-close rop-message-close">&times;</button>
                        </div>
                        <div class="rop-popup-content">
                            <div class="rop-new-message-form">
                                <form id="rop-new-message-form">
                                    <div class="rop-form-group rop-form-group-required">
                                        <label for="message_recipient" class="rop-form-label">Odbiorca</label>
                                        <select id="message_recipient" name="recipient_id" class="rop-form-control" required>
                                            <option value="">Wybierz odbiorcę...</option>
                                            ${users.map(user => `
                                                <option value="${user.id}">
                                                    ${user.name}${user.company ? ` (${user.company})` : ''}
                                                </option>
                                            `).join('')}
                                        </select>
                                    </div>

                                    <div class="rop-form-group rop-form-group-required">
                                        <label for="message_subject" class="rop-form-label">Temat</label>
                                        <input type="text" id="message_subject" name="subject" class="rop-form-control"
                                            placeholder="Wprowadź temat wiadomości..." required>
                                    </div>

                                    <div class="rop-form-group rop-form-group-required">
                                        <label for="message_content" class="rop-form-label">Treść wiadomości</label>
                                        <textarea id="message_content" name="content" class="rop-form-control" rows="6"
                                            placeholder="Napisz treść swojej wiadomości..." required></textarea>
                                    </div>

                                    <div class="rop-form-group">
                                        <label class="rop-checkbox-label">
                                            <input type="checkbox" id="message_important" name="is_important" value="1">
                                            <span class="rop-checkbox-custom"></span>
                                            Oznacz jako ważną wiadomość
                                        </label>
                                    </div>

                                    <div class="rop-form-footer">
                                        <button type="button" class="rop-btn rop-btn-secondary rop-message-close">
                                            Anuluj
                                        </button>
                                        <button type="submit" class="rop-btn rop-btn-primary">
                                            Wyślij wiadomość
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(popupHTML);
            
            // Bind close events
            $(document).on('click', '.rop-message-close, .rop-message-popup-overlay', (e) => {
                if (e.target === e.currentTarget) {
                    this.hideNewMessageForm();
                }
            });
        },

        sendMessage: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $submitBtn = $form.find('button[type="submit"]');
            
            $submitBtn.prop('disabled', true).text('Wysyłanie...');

            const formData = {
                action: 'rop_send_message',
                nonce: ropPanel.nonce,
                recipient_id: $('#message_recipient').val(),
                subject: $('#message_subject').val(),
                content: $('#message_content').val(),
                is_important: $('#message_important').is(':checked') ? '1' : '0'
            };

            $.ajax({
                url: ropPanel.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data);
                        this.hideNewMessageForm();
                        this.loadMessages({ preventDefault: () => {} });
                    } else {
                        this.showError(response.data);
                    }
                },
                error: () => {
                    this.showError('Wystąpił błąd podczas wysyłania wiadomości.');
                },
                complete: () => {
                    $submitBtn.prop('disabled', false).text('Wyślij wiadomość');
                }
            });
        },

        hideNewMessageForm: function() {
            $('.rop-message-popup-overlay').remove();
            $(document).off('click', '.rop-message-close, .rop-message-popup-overlay');
        },

        displayMessages: function(content) {
            $('.rop-content').html(content);
            $('#rop-messages').addClass('active');
            $('.rop-tab').removeClass('active');
        },

        openMessage: function(e) {
            const messageId = $(e.currentTarget).data('message-id');
            // Implementacja otwierania szczegółów wiadomości
            console.log('Opening message:', messageId);
        },

        showLoading: function() {
            $('.rop-content').html('<div class="rop-loading">Ładowanie wiadomości...</div>');
        },

        showError: function(message) {
            if ($('.rop-error').length === 0) {
                $('.rop-content').prepend(`<div class="rop-error">${message}</div>`);
            }
        },

        showSuccess: function(message) {
            if ($('.rop-success').length === 0) {
                $('.rop-content').prepend(`<div class="rop-success">${message}</div>`);
            }
            
            setTimeout(() => {
                $('.rop-success').fadeOut();
            }, 3000);
        }
    };

    $(document).ready(() => {
        messagesManager.init();
    });

})(jQuery);