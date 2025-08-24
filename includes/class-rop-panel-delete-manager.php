<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Delete_Manager {
    
    public function __construct() {
        add_action('wp_ajax_rop_delete_topic', array($this, 'delete_topic'));
        add_action('wp_ajax_rop_delete_reply', array($this, 'delete_reply'));
    }
    
    // METODA - usuń temat
    public function delete_topic() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        $topic_id = intval($_POST['topic_id']);
        
        if (!$topic_id || !bbp_is_topic($topic_id)) {
            wp_send_json_error(__('Nieprawidłowy temat.', 'rop_panel'));
        }
        
        // Sprawdź uprawnienia
        if (!$this->can_delete_topic($topic_id)) {
            wp_send_json_error(__('Nie masz uprawnień do usunięcia tego tematu.', 'rop_panel'));
        }
        
        // Pobierz dane przed usunięciem
        $topic = get_post($topic_id);
        $forum_id = bbp_get_topic_forum_id($topic_id);
        
        // Usuń wszystkie odpowiedzi do tematu
        $replies = get_posts(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($replies as $reply) {
            wp_delete_post($reply->ID, true);
        }
        
        // Usuń temat
        $deleted = wp_delete_post($topic_id, true);
        
        if (!$deleted) {
            wp_send_json_error(__('Wystąpił błąd podczas usuwania tematu.', 'rop_panel'));
        }
        
        // Zaktualizuj statystyki forum
        if ($forum_id) {
            bbp_update_forum($forum_id);
        }
        
        // Usuń meta polubień
        delete_post_meta($topic_id, '_rop_like_count');
        
        // Usuń z list polubień użytkowników
        $this->remove_from_user_likes($topic_id);
        
        wp_send_json_success(__('Temat został usunięty pomyślnie.', 'rop_panel'));
    }
    
    // METODA - usuń odpowiedź/komentarz
    public function delete_reply() {
        // Sprawdź nonce
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }
        
        $reply_id = intval($_POST['reply_id']);
        
        if (!$reply_id || !bbp_is_reply($reply_id)) {
            wp_send_json_error(__('Nieprawidłowa odpowiedź.', 'rop_panel'));
        }
        
        // Sprawdź uprawnienia
        if (!$this->can_delete_reply($reply_id)) {
            wp_send_json_error(__('Nie masz uprawnień do usunięcia tej odpowiedzi.', 'rop_panel'));
        }
        
        // Pobierz dane przed usunięciem
        $topic_id = bbp_get_reply_topic_id($reply_id);
        $forum_id = bbp_get_reply_forum_id($reply_id);
        
        // Usuń odpowiedź
        $deleted = wp_delete_post($reply_id, true);
        
        if (!$deleted) {
            wp_send_json_error(__('Wystąpił błąd podczas usuwania odpowiedzi.', 'rop_panel'));
        }
        
        // Zaktualizuj statystyki
        if ($topic_id) {
            bbp_update_topic($topic_id);
        }
        if ($forum_id) {
            bbp_update_forum($forum_id);
        }
        
        wp_send_json_success(__('Odpowiedź została usunięta pomyślnie.', 'rop_panel'));
    }
    
    // POMOCNICZA - sprawdź czy można usunąć temat
    private function can_delete_topic($topic_id) {
        $current_user_id = get_current_user_id();
        $topic_author_id = get_post_field('post_author', $topic_id);
        
        // Administrator może usuwać wszystko
        if (current_user_can('administrator')) {
            return true;
        }
        
        // Właściciel może usuwać swój temat
        if ($current_user_id == $topic_author_id) {
            return true;
        }
        
        // Moderator forum może usuwać (jeśli masz taką rolę)
        if (current_user_can('moderate')) {
            return true;
        }
        
        return false;
    }
    
    // POMOCNICZA - sprawdź czy można usunąć odpowiedź
    private function can_delete_reply($reply_id) {
        $current_user_id = get_current_user_id();
        $reply_author_id = get_post_field('post_author', $reply_id);
        
        // Administrator może usuwać wszystko
        if (current_user_can('administrator')) {
            return true;
        }
        
        // Właściciel może usuwać swoją odpowiedź
        if ($current_user_id == $reply_author_id) {
            return true;
        }
        
        // Moderator forum może usuwać
        if (current_user_can('moderate')) {
            return true;
        }
        
        return false;
    }
    
    // POMOCNICZA - usuń z list polubień użytkowników
    private function remove_from_user_likes($topic_id) {
        global $wpdb;
        
        // Znajdź wszystkich użytkowników, którzy polubili ten temat
        $users_with_likes = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'rop_liked_topics' 
            AND meta_value LIKE %s
        ", '%' . $topic_id . '%'));
        
        foreach ($users_with_likes as $user_meta) {
            $liked_topics = maybe_unserialize($user_meta->meta_value);
            if (is_array($liked_topics)) {
                $liked_topics = array_diff($liked_topics, array($topic_id));
                update_user_meta($user_meta->user_id, 'rop_liked_topics', $liked_topics);
            }
        }
    }
}

// Inicjalizuj klasę
new ROP_Panel_Delete_Manager();