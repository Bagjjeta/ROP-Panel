<?php
if (!defined('ABSPATH')) {
    exit;
}

error_log('ROP DEBUG: ajax-handlers.php file loaded at ' . current_time('Y-m-d H:i:s'));

add_action('wp_ajax_rop_test', 'rop_test_simple');
add_action('wp_ajax_nopriv_rop_test', 'rop_test_simple');

function rop_test_simple() {
    error_log('ROP DEBUG: rop_test_simple function called by user: ' . (is_user_logged_in() ? wp_get_current_user()->user_login : 'not logged in'));
    wp_send_json_success('Test działa prawidłowo! User: ' . (is_user_logged_in() ? wp_get_current_user()->user_login : 'guest'));
}

add_action('wp_ajax_rop_create_reply', 'rop_create_reply_simple');

function rop_create_reply_simple() {
    error_log('ROP DEBUG: rop_create_reply_simple called by user: ' . (is_user_logged_in() ? wp_get_current_user()->user_login : 'not logged in'));
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
        error_log('ROP DEBUG: Nonce verification failed');
        wp_send_json_error('Błąd bezpieczeństwa - nieprawidłowy nonce');
    }
    
    if (!is_user_logged_in()) {
        error_log('ROP DEBUG: User not logged in');
        wp_send_json_error('Musisz być zalogowany, aby dodać odpowiedź');
    }
    
    $topic_id = isset($_POST['topic_id']) ? intval($_POST['topic_id']) : 0;
    $content = isset($_POST['reply_content']) ? sanitize_textarea_field($_POST['reply_content']) : '';
    
    error_log('ROP DEBUG: Parsed data - Topic ID: ' . $topic_id . ', Content length: ' . strlen($content));
    
    if (!$topic_id || empty(trim($content))) {
        error_log('ROP DEBUG: Missing required data');
        wp_send_json_error('Brak wymaganych danych - ID tematu lub treść odpowiedzi');
    }
    
    if (!function_exists('bbp_get_reply_post_type')) {
        error_log('ROP DEBUG: bbPress functions not available');
        wp_send_json_error('bbPress nie jest dostępny na tej stronie');
    }
    
    $topic = get_post($topic_id);
    if (!$topic) {
        error_log('ROP DEBUG: Topic not found for ID: ' . $topic_id);
        wp_send_json_error('Nie znaleziono tematu o ID: ' . $topic_id);
    }
    
    if ($topic->post_type !== bbp_get_topic_post_type()) {
        error_log('ROP DEBUG: Post is not a bbPress topic');
        wp_send_json_error('To nie jest prawidłowy temat forum');
    }
    
    try {
        error_log('ROP DEBUG: Starting reply creation process');
        
        $forum_id = get_post_meta($topic_id, '_bbp_forum_id', true);
        if (!$forum_id && $topic->post_parent) {
            $forum_id = $topic->post_parent;
        }
        
        $reply_data = array(
            'post_parent' => $topic_id,
            'post_status' => 'publish',
            'post_type' => bbp_get_reply_post_type(),
            'post_author' => get_current_user_id(),
            'post_content' => $content,
            'post_title' => 'Odpowiedź w: ' . $topic->post_title,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        );
        
        $reply_id = wp_insert_post($reply_data);
        
        if (is_wp_error($reply_id)) {
            error_log('ROP DEBUG: wp_insert_post returned WP_Error: ' . $reply_id->get_error_message());
            wp_send_json_error('Błąd tworzenia odpowiedzi: ' . $reply_id->get_error_message());
        }
        
        if (!$reply_id || $reply_id === 0) {
            error_log('ROP DEBUG: wp_insert_post returned 0 or false');
            wp_send_json_error('Nie udało się utworzyć odpowiedzi');
        }
        
        error_log('ROP DEBUG: Reply created successfully with ID: ' . $reply_id);
        
        if ($forum_id) {
            update_post_meta($reply_id, '_bbp_forum_id', $forum_id);
        }
        update_post_meta($reply_id, '_bbp_topic_id', $topic_id);
        update_post_meta($reply_id, '_bbp_reply_id', $reply_id);
        
        $replies_query = new WP_Query(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));

        $reply_count = $replies_query->found_posts;
        error_log('ROP DEBUG: Counted replies for topic after creation: ' . $reply_count);

        update_post_meta($topic_id, '_bbp_reply_count', $reply_count);
        update_post_meta($topic_id, '_bbp_last_reply_id', $reply_id);
        update_post_meta($topic_id, '_bbp_last_active_time', current_time('mysql'));
        
        if ($forum_id) {
            $forum_replies = wp_count_posts(bbp_get_reply_post_type());
            update_post_meta($forum_id, '_bbp_reply_count', $forum_replies->publish);
            update_post_meta($forum_id, '_bbp_last_reply_id', $reply_id);
            update_post_meta($forum_id, '_bbp_last_active_time', current_time('mysql'));
        }
        
        error_log('ROP DEBUG: Reply creation process completed successfully');
        
        wp_send_json_success(array(
            'message' => 'Odpowiedź została dodana pomyślnie!',
            'reply_count' => $reply_count,
            'topic_id' => $topic_id,
            'reply_id' => $reply_id
        ));
        
    } catch (Exception $e) {
        error_log('ROP DEBUG: Exception caught during reply creation: ' . $e->getMessage());
        wp_send_json_error('Wystąpił błąd podczas tworzenia odpowiedzi: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('ROP DEBUG: Throwable caught during reply creation: ' . $e->getMessage());
        wp_send_json_error('Wystąpił krytyczny błąd: ' . $e->getMessage());
    }
}

add_action('wp_ajax_rop_delete_reply', 'rop_delete_reply_simple');

function rop_delete_reply_simple() {
    error_log('ROP DEBUG: rop_delete_reply_simple called by user: ' . (is_user_logged_in() ? wp_get_current_user()->user_login : 'not logged in'));
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
        error_log('ROP DEBUG: Nonce verification failed');
        wp_send_json_error('Błąd bezpieczeństwa - nieprawidłowy nonce');
    }
    
    if (!is_user_logged_in()) {
        error_log('ROP DEBUG: User not logged in');
        wp_send_json_error('Musisz być zalogowany, aby usunąć odpowiedź');
    }
    
    $reply_id = isset($_POST['reply_id']) ? intval($_POST['reply_id']) : 0;
    
    if (!$reply_id) {
        error_log('ROP DEBUG: Missing reply ID');
        wp_send_json_error('Brak ID odpowiedzi do usunięcia');
    }
    
    if (!function_exists('bbp_get_reply_post_type')) {
        error_log('ROP DEBUG: bbPress functions not available');
        wp_send_json_error('bbPress nie jest dostępny na tej stronie');
    }
    
    $reply = get_post($reply_id);
    if (!$reply) {
        error_log('ROP DEBUG: Reply not found for ID: ' . $reply_id);
        wp_send_json_error('Nie znaleziono odpowiedzi o ID: ' . $reply_id);
    }
    
    if ($reply->post_type !== bbp_get_reply_post_type()) {
        error_log('ROP DEBUG: Post is not a bbPress reply');
        wp_send_json_error('To nie jest prawidłowa odpowiedź forum');
    }
    
    $current_user_id = get_current_user_id();
    $reply_author_id = $reply->post_author;
    $can_delete = false;
    
    if (current_user_can('administrator')) {
        $can_delete = true;
        error_log('ROP DEBUG: User is administrator - can delete');
    } elseif ($current_user_id == $reply_author_id) {
        $can_delete = true;
        error_log('ROP DEBUG: User is reply author - can delete');
    } elseif (current_user_can('moderate') || current_user_can('edit_others_posts')) {
        $can_delete = true;
        error_log('ROP DEBUG: User has moderate permissions - can delete');
    }
    
    if (!$can_delete) {
        error_log('ROP DEBUG: User does not have permission to delete this reply');
        wp_send_json_error('Nie masz uprawnień do usunięcia tej odpowiedzi');
    }
    
    try {
        error_log('ROP DEBUG: Starting reply deletion process');
        
        $topic_id = $reply->post_parent;
        $forum_id = get_post_meta($reply_id, '_bbp_forum_id', true);
        
        error_log('ROP DEBUG: Reply belongs to topic: ' . $topic_id . ', forum: ' . $forum_id);
        
        $deleted = wp_delete_post($reply_id, true);
        
        if (!$deleted) {
            error_log('ROP DEBUG: wp_delete_post failed');
            wp_send_json_error('Nie udało się usunąć odpowiedzi');
        }
        
        error_log('ROP DEBUG: Reply deleted successfully');
        
        $replies_query = new WP_Query(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $new_reply_count = $replies_query->found_posts;
        error_log('ROP DEBUG: New reply count for topic: ' . $new_reply_count);
        
        update_post_meta($topic_id, '_bbp_reply_count', $new_reply_count);
        
        if ($new_reply_count > 0) {
            $latest_reply_query = new WP_Query(array(
                'post_type' => bbp_get_reply_post_type(),
                'post_parent' => $topic_id,
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'DESC'
            ));
            
            if ($latest_reply_query->have_posts()) {
                $latest_reply_query->the_post();
                $latest_reply_id = get_the_ID();
                $latest_reply_date = get_the_date('Y-m-d H:i:s');
                
                update_post_meta($topic_id, '_bbp_last_reply_id', $latest_reply_id);
                update_post_meta($topic_id, '_bbp_last_active_time', $latest_reply_date);
                
                wp_reset_postdata();
                error_log('ROP DEBUG: Updated last reply to: ' . $latest_reply_id);
            }
        } else {
            delete_post_meta($topic_id, '_bbp_last_reply_id');
            $topic_date = get_post_field('post_date', $topic_id);
            update_post_meta($topic_id, '_bbp_last_active_time', $topic_date);
            error_log('ROP DEBUG: No replies left - reset to topic date');
        }
        
        if ($forum_id) {
            $all_forum_replies = wp_count_posts(bbp_get_reply_post_type());
            if ($all_forum_replies && isset($all_forum_replies->publish)) {
                update_post_meta($forum_id, '_bbp_reply_count', $all_forum_replies->publish);
            }
            error_log('ROP DEBUG: Updated forum reply count');
        }
        
        error_log('ROP DEBUG: Reply deletion process completed successfully');
        
        wp_send_json_success(array(
            'message' => 'Odpowiedź została usunięta pomyślnie!',
            'reply_count' => $new_reply_count,
            'topic_id' => $topic_id,
            'deleted_reply_id' => $reply_id
        ));
        
    } catch (Exception $e) {
        error_log('ROP DEBUG: Exception caught during reply deletion: ' . $e->getMessage());
        wp_send_json_error('Wystąpił błąd podczas usuwania odpowiedzi: ' . $e->getMessage());
    } catch (Throwable $e) {
        error_log('ROP DEBUG: Throwable caught during reply deletion: ' . $e->getMessage());
        wp_send_json_error('Wystąpił krytyczny błąd: ' . $e->getMessage());
    }
}

function load_messages_panel() {
    if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
        wp_send_json_error('Nieprawidłowy token bezpieczeństwa');
    }

    if (!is_user_logged_in()) {
        wp_send_json_error('Musisz być zalogowany');
    }

    // Tylko HTML z shortcoda
    $content = do_shortcode('[better_messages]');

    wp_send_json_success([
        'content' => $content
    ]);
}
add_action('wp_ajax_rop_load_messages_panel', 'load_messages_panel');
add_action('wp_ajax_nopriv_rop_load_messages_panel', 'load_messages_panel');

add_action('wp_ajax_rop_get_company_logo', array($ajax_handlers, 'get_company_logo'));
add_action('wp_ajax_nopriv_rop_get_company_logo', array($ajax_handlers, 'get_company_logo'));

error_log('ROP DEBUG: AJAX functions registered at ' . current_time('Y-m-d H:i:s'));

class ROP_Panel_Ajax {
    
    public function __construct() {
        error_log('ROP DEBUG: ROP_Panel_Ajax constructor called');
        
        add_action('wp_ajax_rop_get_forum_post_by_url', array($this, 'get_forum_post_by_url'));
        add_action('wp_ajax_nopriv_rop_get_forum_post_by_url', array($this, 'get_forum_post_by_url'));
        
        add_action('wp_ajax_rop_get_forum_post_by_id', array($this, 'get_forum_post_by_id'));
        add_action('wp_ajax_nopriv_rop_get_forum_post_by_id', array($this, 'get_forum_post_by_id'));
        
        add_action('wp_ajax_rop_get_topic_replies', array($this, 'get_topic_replies'));
        add_action('wp_ajax_nopriv_rop_get_topic_replies', array($this, 'get_topic_replies'));
        
        error_log('ROP DEBUG: All AJAX actions registered in class');
    }
    
    public function get_topic_replies() {
        error_log('ROP DEBUG: get_topic_replies called');
        
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            error_log('ROP DEBUG: get_topic_replies - nonce failed');
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        $topic_id = intval($_POST['topic_id']);
        $page = intval($_POST['page']) ?: 1;
        $per_page = 5;
        
        if (!$topic_id) {
            wp_send_json_error(__('Nie można określić ID tematu.', 'rop_panel'));
        }
        
        if (!function_exists('bbp_is_topic') || !bbp_is_topic($topic_id)) {
            error_log('ROP DEBUG: get_topic_replies - not a valid bbPress topic');
            wp_send_json_error(__('Nie znaleziono tematu.', 'rop_panel'));
        }
        
        $replies_data = $this->get_topic_replies_data($topic_id, $page, $per_page);
        wp_send_json_success($replies_data);
    }
    
    private function get_topic_replies_data($topic_id, $page = 1, $per_page = 5) {
        $offset = ($page - 1) * $per_page;
        
        $replies_query = new WP_Query(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        $replies = array();
        
        $total_replies_query = new WP_Query(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        $total_replies = $total_replies_query->found_posts;
        $total_pages = ceil($total_replies / $per_page);
        
        if ($replies_query->have_posts()) {
            while ($replies_query->have_posts()) {
                $replies_query->the_post();
                $reply_id = get_the_ID();
                $author_id = get_the_author_meta('ID');
                
                $replies[] = array(
                    'id' => $reply_id,
                    'content' => apply_filters('the_content', get_the_content()),
                    'date' => get_the_date('j F Y, H:i'),
                    'author' => $this->get_author_data($author_id),
                    'can_delete' => $this->can_delete_reply($reply_id)
                );
            }
            wp_reset_postdata();
        }
        
        return array(
            'replies' => $replies,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_replies' => $total_replies,
                'per_page' => $per_page,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            )
        );
    }
    
    public function get_forum_post_by_id() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        $topic_id = intval($_POST['topic_id']);
        
        if (!$topic_id) {
            wp_send_json_error(__('Nie można określić ID tematu.', 'rop_panel'));
        }
        
        if (!function_exists('bbp_is_topic') || !bbp_is_topic($topic_id)) {
            wp_send_json_error(__('Nie znaleziono tematu.', 'rop_panel'));
        }
        
        $post_data = $this->get_topic_data($topic_id);
        
        if (empty($post_data)) {
            wp_send_json_error(__('Nie znaleziono danych tematu.', 'rop_panel'));
        }
        
        wp_send_json_success($post_data);
    }
    
    public function get_forum_post_by_url() {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }
        
        $post_url = sanitize_text_field($_POST['post_url']);
        $post_slug = sanitize_text_field($_POST['post_slug']);
        $post_type = sanitize_text_field($_POST['post_type']);
        $post_id = intval($_POST['post_id']);
        
        if ($post_id) {
            $final_post_id = $post_id;
        } elseif ($post_slug && $post_type) {
            $final_post_id = $this->get_post_id_by_slug_fast($post_slug, $post_type);
        } else {
            $final_post_id = $this->extract_post_id_from_url($post_url);
        }
        
        if (!$final_post_id) {
            wp_send_json_error(__('Nie można określić ID posta.', 'rop_panel'));
        }
        
        if (!$post_type) {
            $wp_post = get_post($final_post_id);
            if ($wp_post) {
                if ($wp_post->post_type === bbp_get_topic_post_type()) {
                    $post_type = 'topic';
                } elseif ($wp_post->post_type === bbp_get_reply_post_type()) {
                    $post_type = 'reply';
                }
            }
        }
        
        $post_data = array();
        
        if ($post_type === 'topic') {
            $post_data = $this->get_topic_data($final_post_id);
        } elseif ($post_type === 'reply') {
            $post_data = $this->get_reply_data($final_post_id);
        }
        
        if (empty($post_data)) {
            wp_send_json_error(__('Nie znaleziono posta.', 'rop_panel'));
        }
        
        wp_send_json_success($post_data);
    }
    
    private function get_post_id_by_slug_fast($slug, $type) {
        global $wpdb;
        
        $wp_post_type = '';
        if ($type === 'topic') {
            $wp_post_type = bbp_get_topic_post_type();
        } elseif ($type === 'reply') {
            $wp_post_type = bbp_get_reply_post_type();
        }
        
        if (!$wp_post_type) {
            return false;
        }
        
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status = 'publish' LIMIT 1",
            $slug,
            $wp_post_type
        ));
        
        return $post_id ? intval($post_id) : false;
    }
    
    private function extract_post_id_from_url($url) {
        $parsed_url = parse_url($url);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        
        $patterns = array(
            '/\/topic\/([^\/]+)\/?$/',
            '/\/forums\/topic\/([^\/]+)\/?$/',
            '/\/reply\/([^\/]+)\/?$/',
            '/\/forums\/reply\/([^\/]+)\/?$/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches)) {
                $slug = $matches[1];
                
                $topic_id = $this->get_post_id_by_slug_fast($slug, 'topic');
                if ($topic_id) {
                    return $topic_id;
                }
                
                $reply_id = $this->get_post_id_by_slug_fast($slug, 'reply');
                if ($reply_id) {
                    return $reply_id;
                }
            }
        }
        
        return false;
    }
    
    private function get_topic_data($topic_id) {
        if (!function_exists('bbp_is_topic') || !bbp_is_topic($topic_id)) {
            return false;
        }
        
        $topic = get_post($topic_id);
        if (!$topic) {
            return false;
        }
        
        $author_id = $topic->post_author;
        
        $reply_count = $this->count_topic_replies($topic_id);
        $like_count = get_post_meta($topic_id, '_rop_like_count', true);
        $like_count = $like_count ? intval($like_count) : 0;
        
        $current_user_id = get_current_user_id();
        $user_likes = get_user_meta($current_user_id, 'rop_liked_topics', true);
        $is_liked = is_array($user_likes) && in_array($topic_id, $user_likes);
        
        return array(
            'id' => $topic_id,
            'title' => get_the_title($topic_id),
            'content' => apply_filters('the_content', $topic->post_content),
            'author' => $this->get_author_data($author_id),
            'date' => get_the_date('j F Y, H:i', $topic_id),
            'category' => $this->get_topic_forum_title($topic_id),
            'permalink' => get_permalink($topic_id),
            'type' => 'topic',
            'stats' => array(
                'replies' => $reply_count,
                'likes' => $like_count,
                'is_liked' => $is_liked
            ),
            'can_delete' => $this->can_delete_topic($topic_id),
            'can_reply' => $this->can_reply_to_topic($topic_id)
        );
    }
    
    private function get_reply_data($reply_id) {
        if (!function_exists('bbp_is_reply') || !bbp_is_reply($reply_id)) {
            return false;
        }
        
        $reply = get_post($reply_id);
        if (!$reply) {
            return false;
        }
        
        $author_id = $reply->post_author;
        $topic_id = $reply->post_parent;
        
        return array(
            'title' => 'Odpowiedź w: ' . get_the_title($topic_id),
            'content' => apply_filters('the_content', $reply->post_content),
            'author' => $this->get_author_data($author_id),
            'date' => get_the_date('j F Y, H:i', $reply_id),
            'category' => $this->get_topic_forum_title($topic_id),
            'permalink' => get_permalink($reply_id),
            'type' => 'reply',
            'can_delete' => $this->can_delete_reply($reply_id)
        );
    }
    
    private function get_author_data($user_id) {
        static $author_cache = array();
        
        if (isset($author_cache[$user_id])) {
            return $author_cache[$user_id];
        }
        
        $user_data = get_userdata($user_id);
        
        if (!$user_data) {
            $result = array(
                'name' => __('Nieznany użytkownik', 'rop_panel'),
                'avatar' => get_avatar_url($user_id, array('size' => 60)),
                'company' => ''
            );
            $author_cache[$user_id] = $result;
            return $result;
        }
        
        $company = get_user_meta($user_id, 'rop_company_name', true);
        
        if (empty($company) && function_exists('um_user')) {
            um_fetch_user($user_id);
            $company = um_user('company');
            if (empty($company)) {
                $company = um_user('organization');
            }
        }
        
        if (empty($company)) {
            $company = get_user_meta($user_id, 'company', true);
            if (empty($company)) {
                $company = get_user_meta($user_id, 'organization', true);
            }
        }

        $avatar = $this->get_user_avatar_or_logo($user_id);
        
        $result = array(
            'name' => $user_data->display_name,
            'avatar' => $avatar,
            'company' => $company ? $company : ''
        );
        
        $author_cache[$user_id] = $result;
        return $result;
    }

    private function get_user_avatar_or_logo($user_id) {
        $company_logo = get_user_meta($user_id, 'rop_company_logo', true);
        
        if (!empty($company_logo) && file_exists(str_replace(home_url(), ABSPATH, $company_logo))) {
            return $company_logo;
        }
        
        return get_avatar_url($user_id, array('size' => 60));
    }
    
    private function count_topic_replies($topic_id) {
        $replies_query = new WP_Query(array(
            'post_type' => bbp_get_reply_post_type(),
            'post_parent' => $topic_id,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        return $replies_query->found_posts;
    }
    
    private function get_topic_forum_title($topic_id) {
        $forum_id = get_post_meta($topic_id, '_bbp_forum_id', true);
        if ($forum_id) {
            return get_the_title($forum_id);
        }
        return '';
    }
    
    private function can_delete_topic($topic_id) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return false;
        }
        
        $topic_author_id = get_post_field('post_author', $topic_id);
        
        if (current_user_can('administrator')) {
            return true;
        }
        
        if ($current_user_id == $topic_author_id) {
            return true;
        }
        
        if (current_user_can('moderate') || current_user_can('edit_others_posts')) {
            return true;
        }
        
        return false;
    }
    
    private function can_delete_reply($reply_id) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return false;
        }
        
        $reply_author_id = get_post_field('post_author', $reply_id);
        
        if (current_user_can('administrator')) {
            return true;
        }
        
        if ($current_user_id == $reply_author_id) {
            return true;
        }
        
        if (current_user_can('moderate') || current_user_can('edit_others_posts')) {
            return true;
        }
        
        return false;
    }
    
    private function can_reply_to_topic($topic_id) {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            return false;
        }
        
        $topic = get_post($topic_id);
        if (!$topic || $topic->post_status !== 'publish') {
            return false;
        }
        
        if (function_exists('bbp_is_topic_closed') && bbp_is_topic_closed($topic_id)) {
            return false;
        }
        
        return true;
    }

    /**
 * Pobiera logo firmy dla użytkownika (AJAX)
 */
public function get_company_logo() {
    if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
        wp_send_json_error('Błąd bezpieczeństwa');
    }
    
    $user_id = intval($_POST['user_id']);
    if (!$user_id) {
        wp_send_json_error('Nieprawidłowy ID użytkownika');
    }
    
    $company_logo = get_user_meta($user_id, 'rop_company_logo', true);
    
    if (!empty($company_logo) && file_exists(str_replace(home_url(), ABSPATH, $company_logo))) {
        wp_send_json_success(array(
            'logo_url' => $company_logo,
            'user_id' => $user_id
        ));
    } else {
        wp_send_json_error('Brak logo firmy');
    }
}
}

new ROP_Panel_Ajax();
error_log('ROP DEBUG: ROP_Panel_Ajax class initialized at ' . current_time('Y-m-d H:i:s'));