<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Messages_Manager
{
    public function __construct()
    {
        add_action('wp_ajax_rop_get_messages', array($this, 'get_messages'));
        add_action('wp_ajax_rop_send_message', array($this, 'send_message'));
        add_action('wp_ajax_rop_get_users_list', array($this, 'get_users_list'));
        add_action('wp_ajax_rop_get_conversation', array($this, 'get_conversation'));
        add_action('wp_ajax_rop_mark_as_read', array($this, 'mark_as_read'));
        add_action('wp_ajax_rop_delete_message', array($this, 'delete_message'));
    }

    public function get_messages()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }

        $messages_html = $this->render_messages_panel();

        wp_send_json_success(array(
            'content' => $messages_html
        ));
    }

    public function send_message()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }

        $recipient_id = intval($_POST['recipient_id']);
        $subject = sanitize_text_field($_POST['subject']);
        $content = wp_kses_post($_POST['content']);
        $is_important = isset($_POST['is_important']) && $_POST['is_important'] === '1';

        $errors = array();

        if (empty($recipient_id)) {
            $errors[] = __('Wybierz odbiorcę wiadomości.', 'rop_panel');
        }

        if (empty($subject)) {
            $errors[] = __('Temat wiadomości jest wymagany.', 'rop_panel');
        }

        if (empty($content)) {
            $errors[] = __('Treść wiadomości jest wymagana.', 'rop_panel');
        }

        if (!empty($errors)) {
            wp_send_json_error(implode('<br>', $errors));
        }

        $current_user_id = get_current_user_id();

        // Sprawdź czy BP Better Messages jest aktywny
        if (function_exists('BP_Better_Messages')) {
            $thread_id = $this->create_bp_message($current_user_id, $recipient_id, $subject, $content, $is_important);
        } else {
            // Fallback - własny system wiadomości
            $thread_id = $this->create_custom_message($current_user_id, $recipient_id, $subject, $content, $is_important);
        }

        if ($thread_id) {
            wp_send_json_success(__('Wiadomość została wysłana pomyślnie!', 'rop_panel'));
        } else {
            wp_send_json_error(__('Wystąpił błąd podczas wysyłania wiadomości.', 'rop_panel'));
        }
    }

    public function get_users_list()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }

        $users = $this->get_available_users();
        wp_send_json_success($users);
    }

    private function get_available_users()
    {
        $current_user_id = get_current_user_id();
        
        $users = get_users(array(
            'exclude' => array($current_user_id),
            'meta_query' => array(
                array(
                    'key' => 'um_account_status',
                    'value' => 'approved',
                    'compare' => '='
                )
            )
        ));

        $users_list = array();
        
        foreach ($users as $user) {
            $full_name = '';
            
            // Próbuj pobrać pełne imię z Ultimate Members
            if (function_exists('um_user')) {
                um_fetch_user($user->ID);
                $first_name = um_user('first_name');
                $last_name = um_user('last_name');
                
                if ($first_name || $last_name) {
                    $full_name = trim($first_name . ' ' . $last_name);
                }
            }
            
            // Fallback na display_name
            if (empty($full_name)) {
                $full_name = $user->display_name;
            }
            
            // Pobierz nazwę firmy
            $company = get_user_meta($user->ID, 'rop_company_name', true);
            if (empty($company)) {
                $company = get_user_meta($user->ID, 'company', true);
            }
            
            $users_list[] = array(
                'id' => $user->ID,
                'name' => $full_name,
                'company' => $company ? $company : '',
                'avatar' => $this->get_user_avatar_or_logo($user->ID)
            );
        }

        return $users_list;
    }

    private function create_bp_message($sender_id, $recipient_id, $subject, $content, $is_important)
    {
        if (!function_exists('messages_new_message')) {
            return false;
        }

        $message_content = $content;
        if ($is_important) {
            $message_content = '[WAŻNE] ' . $content;
        }

        $thread_id = messages_new_message(array(
            'sender_id' => $sender_id,
            'recipients' => array($recipient_id),
            'subject' => $subject,
            'content' => $message_content
        ));

        if ($thread_id && $is_important) {
            // Oznacz jako ważną w meta
            bp_messages_update_meta($thread_id, 'rop_important', '1');
        }

        return $thread_id;
    }

    private function create_custom_message($sender_id, $recipient_id, $subject, $content, $is_important)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rop_messages';
        
        // Utwórz tabelę jeśli nie istnieje
        $this->create_messages_table();

        $result = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $sender_id,
                'recipient_id' => $recipient_id,
                'subject' => $subject,
                'content' => $content,
                'is_important' => $is_important ? 1 : 0,
                'is_read' => 0,
                'date_sent' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    private function create_messages_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'rop_messages';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            recipient_id bigint(20) NOT NULL,
            subject varchar(255) NOT NULL,
            content longtext NOT NULL,
            is_important tinyint(1) DEFAULT 0,
            is_read tinyint(1) DEFAULT 0,
            date_sent datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sender_id (sender_id),
            KEY recipient_id (recipient_id),
            KEY date_sent (date_sent)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function render_messages_panel()
    {
        ob_start();
        ?>
        <div class="rop-messages-container">
            <div class="rop-messages-header">
                <h2>Wiadomości</h2>
                <p class="rop-messages-description">Komunikuj się z innymi członkami platformy</p>
                
                <div class="rop-messages-controls">
                    <button class="rop-btn rop-btn-primary" id="rop-new-message">
                        Nowa wiadomość
                    </button>
                </div>
            </div>

            <div class="rop-messages-content" id="rop-messages-list">
                <?php echo $this->render_messages_list(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_messages_list()
    {
        $current_user_id = get_current_user_id();
        $messages = $this->get_user_messages($current_user_id);

        ob_start();
        
        if (!empty($messages)) {
            foreach ($messages as $message) {
                $this->render_message_item($message);
            }
        } else {
            ?>
            <div class="rop-messages-empty">
                <p>Nie masz jeszcze żadnych wiadomości.</p>
                <button class="rop-btn rop-btn-primary" id="rop-first-message">
                    Wyślij pierwszą wiadomość
                </button>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    private function get_user_messages($user_id)
    {
        // Próbuj pobrać z BP Better Messages
        if (function_exists('BP_Better_Messages')) {
            return $this->get_bp_messages($user_id);
        } else {
            return $this->get_custom_messages($user_id);
        }
    }

    private function get_bp_messages($user_id)
    {
        // Implementacja dla BP Better Messages
        if (!function_exists('messages_get_threads')) {
            return array();
        }

        $threads = messages_get_threads(array(
            'user_id' => $user_id,
            'per_page' => 20
        ));

        $messages = array();
        
        if (!empty($threads['threads'])) {
            foreach ($threads['threads'] as $thread) {
                $last_message = messages_get_last_message($thread->thread_id);
                
                $other_user_id = ($thread->last_sender_id == $user_id) ? 
                    $this->get_other_participant($thread->thread_id, $user_id) : 
                    $thread->last_sender_id;

                $messages[] = array(
                    'id' => $thread->thread_id,
                    'subject' => $thread->last_message_subject,
                    'content' => $last_message->message,
                    'other_user_id' => $other_user_id,
                    'is_read' => !$thread->unread_count,
                    'is_important' => bp_messages_get_meta($thread->thread_id, 'rop_important', true) === '1',
                    'date' => $thread->last_message_date,
                    'type' => 'bp'
                );
            }
        }

        return $messages;
    }

    private function get_custom_messages($user_id)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'rop_messages';
        
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE sender_id = %d OR recipient_id = %d 
            ORDER BY date_sent DESC 
            LIMIT 20
        ", $user_id, $user_id));

        $formatted_messages = array();
        
        foreach ($messages as $message) {
            $other_user_id = ($message->sender_id == $user_id) ? 
                $message->recipient_id : $message->sender_id;

            $formatted_messages[] = array(
                'id' => $message->id,
                'subject' => $message->subject,
                'content' => $message->content,
                'other_user_id' => $other_user_id,
                'is_read' => $message->is_read,
                'is_important' => $message->is_important,
                'date' => $message->date_sent,
                'type' => 'custom'
            );
        }

        return $formatted_messages;
    }

    private function render_message_item($message)
    {
        $other_user = get_userdata($message['other_user_id']);
        if (!$other_user) return;

        $full_name = $this->get_user_full_name($message['other_user_id']);
        $company = get_user_meta($message['other_user_id'], 'rop_company_name', true);
        if (!$company) {
            $company = get_user_meta($message['other_user_id'], 'company', true);
        }

        $avatar_html = $this->get_user_avatar_or_logo($message['other_user_id']);
        $time_diff = human_time_diff(strtotime($message['date']), current_time('timestamp'));
        ?>
        <div class="rop-message-item <?php echo !$message['is_read'] ? 'unread' : ''; ?>" data-message-id="<?php echo $message['id']; ?>">
            <div class="rop-message-avatar">
                <?php echo $avatar_html; ?>
            </div>

            <div class="rop-message-content">
                <div class="rop-message-meta">
                    <span class="rop-message-author"><?php echo esc_html($full_name); ?></span>
                    <?php if ($company): ?>
                        <span class="rop-message-company"><?php echo esc_html($company); ?></span>
                    <?php endif; ?>
                    <span class="rop-message-time"><?php echo $time_diff; ?> temu</span>
                    <?php if ($message['is_important']): ?>
                        <span class="rop-message-important">⭐ WAŻNE</span>
                    <?php endif; ?>
                </div>

                <h4 class="rop-message-subject">
                    <?php echo esc_html($message['subject']); ?>
                </h4>

                <div class="rop-message-excerpt">
                    <?php 
                    $excerpt = wp_trim_words(strip_tags($message['content']), 15);
                    echo esc_html($excerpt);
                    ?>
                </div>

                <?php if (!$message['is_read']): ?>
                    <div class="rop-message-unread-indicator"></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function get_user_full_name($user_id)
    {
        if (function_exists('um_user')) {
            um_fetch_user($user_id);
            $first_name = um_user('first_name');
            $last_name = um_user('last_name');
            
            if ($first_name || $last_name) {
                return trim($first_name . ' ' . $last_name);
            }
        }
        
        $user = get_userdata($user_id);
        return $user ? $user->display_name : 'Nieznany użytkownik';
    }

    private function get_user_avatar_or_logo($user_id)
    {
        $company_logo = get_user_meta($user_id, 'rop_company_logo', true);
        
        if (!empty($company_logo) && file_exists(str_replace(home_url(), ABSPATH, $company_logo))) {
            return '<img src="' . esc_url($company_logo) . '" alt="Logo firmy" class="rop-company-logo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">';
        }
        
        return get_avatar($user_id, 50);
    }

    public function render_new_message_form()
    {
        ob_start();
        ?>
        <div class="rop-new-message-form">
            <form id="rop-new-message-form">
                <div class="rop-form-group rop-form-group-required">
                    <label for="message_recipient" class="rop-form-label">Odbiorca</label>
                    <select id="message_recipient" name="recipient_id" class="rop-form-control" required>
                        <option value="">Wybierz odbiorcę...</option>
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
                    <button type="button" class="rop-btn rop-btn-secondary" id="rop-cancel-new-message">
                        Anuluj
                    </button>
                    <button type="submit" class="rop-btn rop-btn-primary" id="rop-submit-new-message">
                        Wyślij wiadomość
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}