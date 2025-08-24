<?php
/**
 * Klasa obsługująca panel wiadomości
 *
 * @package ROP-Panel
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Messages {
    
    /**
     * Inicjalizacja klasy
     */
    public function __construct() {
        // Dodaj akcje AJAX
        add_action('wp_ajax_rop_load_messages_panel', array($this, 'load_messages_panel'));
        
        // Dołącz skrypty i style
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Dodaj element menu w panelu
        add_filter('rop_panel_menu_items', array($this, 'add_messages_menu_item'));
        
        error_log('ROP DEBUG: ROP_Panel_Messages constructed');
    }
    
    /**
     * Dołącza skrypty i style
     */
    public function enqueue_scripts() {
        // Dołącz tylko na stronach panelu
        if (!$this->is_panel_page()) {
            return;
        }
        
        wp_enqueue_script(
            'rop-messages-js',
            ROP_PANEL_PLUGIN_URL . 'assets/js/rop-messages.js',
            array('jquery'),
            ROP_PANEL_VERSION,
            true
        );
        
        wp_localize_script('rop-messages-js', 'rop_panel_vars', apply_filters('rop_panel_script_vars', array()));
        
        wp_enqueue_style(
            'rop-messages-css',
            ROP_PANEL_PLUGIN_URL . 'assets/css/rop-messages.css',
            array(),
            ROP_PANEL_VERSION
        );
        
        error_log('ROP DEBUG: Messages scripts and styles enqueued');
    }
    
    /**
     * Sprawdza czy aktualna strona to strona panelu
     */
    private function is_panel_page() {
        // Tutaj logika wykrywania strony panelu
        // Przykład:
        global $post;
        if (!$post) return false;
        
        return has_shortcode($post->post_content, 'rop_panel');
    }
    
    /**
     * Dodaje element menu wiadomości do panelu
     */
    public function add_messages_menu_item($menu_items) {
        // Dodaj pozycję menu wiadomości
        $menu_items['messages'] = array(
            'id' => 'rop-messages',
            'title' => 'Wiadomości',
            'icon' => 'message',
            'position' => 30, // Pozycja w menu
        );
        
        error_log('ROP DEBUG: Messages menu item added');
        return $menu_items;
    }
    
    /**
     * Ładuje panel wiadomości poprzez AJAX
     */
    public function load_messages_panel() {
        // Sprawdź nonce dla bezpieczeństwa
        check_ajax_referer('rop_panel_nonce', 'security');
        
        // Sprawdź uprawnienia
        if (!is_user_logged_in()) {
            wp_die('Nie masz uprawnień do wykonania tej operacji.');
        }
        
        // Załaduj szablon
        ob_start();
        include ROP_PANEL_PLUGIN_DIR . 'templates/messages-panel.php';
        $content = ob_get_clean();
        
        echo $content;
        wp_die();
    }
}