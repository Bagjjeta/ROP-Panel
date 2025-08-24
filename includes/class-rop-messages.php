<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Messages {
    
    public function __construct() {
        add_action('wp_ajax_rop_get_messages', array($this, 'get_messages'));
        add_action('wp_ajax_rop_send_message', array($this, 'send_message'));
        add_action('wp_ajax_rop_get_users_list', array($this, 'get_users_list'));
        add_action('wp_ajax_rop_mark_message_read', array($this, 'mark_message_read'));
        add_action('wp_ajax_rop_get_unread_count', array($this, 'get_unread_count'));
    }
    
    public function get_messages() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Musisz być zalogowany');
        }
        
        // Podstawowa implementacja - zwróć pustą tablicę na razie
        wp_send_json_success(array());
    }
    
    public function send_message() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
        }
        
        wp_send_json_success(array('message' => 'Funkcja w budowie'));
    }
    
    public function get_users_list() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
        }
        
        wp_send_json_success(array());
    }
    
    public function mark_message_read() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
        }
        
        wp_send_json_success('OK');
    }
    
    public function get_unread_count() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
        }
        
        wp_send_json_success(array('count' => 0));
    }
}