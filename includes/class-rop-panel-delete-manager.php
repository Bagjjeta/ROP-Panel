<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Delete_Manager {
    
    public function __construct() {
        add_action('wp_ajax_rop_delete_topic', array($this, 'delete_topic'));
        add_action('wp_ajax_rop_delete_reply', array($this, 'delete_reply'));
    }

    public function delete_topic() {
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

        if (!$this->can_delete_topic($topic_id)) {
            wp_send_json_error(__('Nie masz uprawnień do usunięcia tego tematu.', 'rop_panel'));
        }

        $topic = get_post($topic_id);
        $forum_id = bbp_get_topic_forum_id($topic_id);

        $replies = get_posts(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($replies as $reply) {
            wp_delete_post($reply->ID, true);
        }

        $deleted = wp_delete_post($topic_id, true);
        
        if (!$deleted) {
            wp_send_json_error(__('Wystąpił błąd podczas usuwania tematu.', 'rop_panel'));
        }

        if ($forum_id) {
            bbp_update_forum($forum_id);
        }

        delete_post_meta($topic_id, '_rop_like_count');

        $this->remove_from_user_likes($topic_id);
        
        wp_send_json_success(__('Temat został usunięty pomyślnie.', 'rop_panel'));
    }

    public function delete_reply() {
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

        if (!$this->can_delete_reply($reply_id)) {
            wp_send_json_error(__('Nie masz uprawnień do usunięcia tej odpowiedzi.', 'rop_panel'));
        }

        $topic_id = bbp_get_reply_topic_id($reply_id);
        $forum_id = bbp_get_reply_forum_id($reply_id);

        $deleted = wp_delete_post($reply_id, true);
        
        if (!$deleted) {
            wp_send_json_error(__('Wystąpił błąd podczas usuwania odpowiedzi.', 'rop_panel'));
        }

        if ($topic_id) {
            bbp_update_topic($topic_id);
        }
        if ($forum_id) {
            bbp_update_forum($forum_id);
        }
        
        wp_send_json_success(__('Odpowiedź została usunięta pomyślnie.', 'rop_panel'));
    }

    private function can_delete_topic($topic_id) {
        $current_user_id = get_current_user_id();
        $topic_author_id = get_post_field('post_author', $topic_id);

        if (current_user_can('administrator')) {
            return true;
        }

        if ($current_user_id == $topic_author_id) {
            return true;
        }

        if (current_user_can('moderate')) {
            return true;
        }
        
        return false;
    }

    private function can_delete_reply($reply_id) {
        $current_user_id = get_current_user_id();
        $reply_author_id = get_post_field('post_author', $reply_id);

        if (current_user_can('administrator')) {
            return true;
        }

        if ($current_user_id == $reply_author_id) {
            return true;
        }

        if (current_user_can('moderate')) {
            return true;
        }
        
        return false;
    }

    private function remove_from_user_likes($topic_id) {
        global $wpdb;

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

new ROP_Panel_Delete_Manager();