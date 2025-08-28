<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_UM_Integration {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Sprawdź czy Ultimate Members jest aktywne
        if (!class_exists('UM')) {
            return;
        }
        
        // Dodaj przycisk wiadomości do profilu
        add_action('um_profile_content_main', array($this, 'add_message_button'));
        
        // Dodaj tab wiadomości
        add_filter('um_profile_tabs', array($this, 'add_messages_tab'), 1000);
        
        // Obsłuż content taba wiadomości
        add_action('um_profile_content_messages_default', array($this, 'messages_tab_content'));
        
        // Hook do avatarów UM
        add_filter('rop_user_avatar', array($this, 'get_um_avatar'), 10, 2);
        
        // Hook do nazw użytkowników
        add_filter('rop_user_display_name', array($this, 'get_um_display_name'), 10, 2);
    }
    
    public function add_message_button() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $current_user_id = get_current_user_id();
        $profile_user_id = um_profile_id();
        
        // Nie pokazuj przycisku na własnym profilu
        if ($current_user_id == $profile_user_id) {
            return;
        }
        
        echo '<div class="rop-message-button-wrapper">';
        echo '<button class="rop-start-conversation" data-user-id="' . $profile_user_id . '">';
        echo '<i class="um-icon-android-chat"></i> Wyślij wiadomość';
        echo '</button>';
        echo '</div>';
    }
    
    public function add_messages_tab($tabs) {
        $tabs['messages'] = array(
            'name' => 'Wiadomości',
            'icon' => 'um-icon-android-chat',
            'custom' => true
        );
        return $tabs;
    }
    
    public function messages_tab_content($args) {
        if (!is_user_logged_in()) {
            echo '<p>Musisz być zalogowany, aby przeglądać wiadomości.</p>';
            return;
        }
        
        echo '<div id="rop-messages-tab-content">';
        echo '<div class="rop-loading">Ładowanie wiadomości...</div>';
        echo '</div>';
        
        // Dodaj JavaScript do załadowania panelu w tym miejscu
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicjalizuj panel wiadomości w trybie embedded
            if (typeof RopMessagesPanel !== 'undefined') {
                new RopMessagesPanel({
                    container: '#rop-messages-tab-content',
                    embedded: true
                });
            }
        });
        </script>
        <?php
    }
    
    public function get_um_avatar($avatar_url, $user_id) {
        if (function_exists('um_get_user_avatar_url')) {
            return um_get_user_avatar_url($user_id, 'original');
        }
        return $avatar_url;
    }
    
    public function get_um_display_name($display_name, $user_id) {
        if (function_exists('um_user')) {
            um_fetch_user($user_id);
            $um_name = um_user('display_name');
            if (!empty($um_name)) {
                return $um_name;
            }
        }
        return $display_name;
    }
}

new ROP_UM_Integration();