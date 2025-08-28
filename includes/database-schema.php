<?php
function rop_create_websocket_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabela konwersacji
    $conversations_table = $wpdb->prefix . 'rop_conversations';
    $conversations_sql = "CREATE TABLE $conversations_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY updated_at (updated_at)
    ) $charset_collate;";
    
    // Tabela uczestników konwersacji
    $participants_table = $wpdb->prefix . 'rop_conversation_participants';
    $participants_sql = "CREATE TABLE $participants_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conversation_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        joined_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_read_at datetime NULL,
        PRIMARY KEY (id),
        UNIQUE KEY conversation_user (conversation_id, user_id),
        KEY user_id (user_id),
        FOREIGN KEY (conversation_id) REFERENCES $conversations_table(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Tabela wiadomości
    $messages_table = $wpdb->prefix . 'rop_messages';
    $messages_sql = "CREATE TABLE $messages_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        conversation_id bigint(20) unsigned NOT NULL,
        sender_id bigint(20) unsigned NOT NULL,
        message text NOT NULL,
        sent_at datetime DEFAULT CURRENT_TIMESTAMP,
        read_at datetime NULL,
        PRIMARY KEY (id),
        KEY conversation_id (conversation_id),
        KEY sender_id (sender_id),
        KEY sent_at (sent_at),
        FOREIGN KEY (conversation_id) REFERENCES $conversations_table(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($conversations_sql);
    dbDelta($participants_sql);
    dbDelta($messages_sql);
}

// Wywołaj przy aktywacji pluginu
register_activation_hook(__FILE__, 'rop_create_websocket_tables');