<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Forum_Manager
{

    public function __construct()
    {
        add_action('wp_ajax_rop_get_forum_topics', array($this, 'get_forum_topics'));
        add_action('wp_ajax_nopriv_rop_get_forum_topics', array($this, 'get_forum_topics'));
        add_action('wp_ajax_rop_filter_forum_topics', array($this, 'filter_forum_topics'));
        add_action('wp_ajax_nopriv_rop_filter_forum_topics', array($this, 'filter_forum_topics'));

        add_action('wp_ajax_rop_get_new_topic_form', array($this, 'get_new_topic_form'));
        add_action('wp_ajax_rop_create_new_topic', array($this, 'create_new_topic'));

        add_action('wp_ajax_rop_toggle_topic_like', array($this, 'toggle_topic_like'));
        add_action('wp_ajax_nopriv_rop_toggle_topic_like', array($this, 'toggle_topic_like'));
    }

    public function toggle_topic_like()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany, aby polubić post.', 'rop_panel'));
        }

        $topic_id = intval($_POST['topic_id']);
        $user_id = get_current_user_id();

        if (!$topic_id || !bbp_is_topic($topic_id)) {
            wp_send_json_error(__('Nieprawidłowy temat.', 'rop_panel'));
        }

        $user_likes = get_user_meta($user_id, 'rop_liked_topics', true);
        if (!is_array($user_likes)) {
            $user_likes = array();
        }

        $is_liked = in_array($topic_id, $user_likes);

        $current_likes = get_post_meta($topic_id, '_rop_like_count', true);
        $current_likes = $current_likes ? intval($current_likes) : 0;

        if ($is_liked) {
            $user_likes = array_diff($user_likes, array($topic_id));
            $new_likes = max(0, $current_likes - 1);
            $action = 'unliked';
        } else {
            $user_likes[] = $topic_id;
            $new_likes = $current_likes + 1;
            $action = 'liked';
        }

        update_user_meta($user_id, 'rop_liked_topics', array_values(array_unique($user_likes)));
        update_post_meta($topic_id, '_rop_like_count', $new_likes);

        wp_send_json_success(array(
            'action' => $action,
            'likes_count' => $new_likes,
            'is_liked' => !$is_liked
        ));
    }

    public function get_new_topic_form()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany, aby utworzyć nowy temat.', 'rop_panel'));
        }

        if (!function_exists('bbp_get_version')) {
            wp_send_json_error(__('bbPress nie jest aktywny.', 'rop_panel'));
        }

        $selected_forum = isset($_POST['selected_forum']) ? intval($_POST['selected_forum']) : 0;

        $form_html = $this->render_new_topic_form($selected_forum);

        wp_send_json_success(array(
            'content' => $form_html
        ));
    }

    public function create_new_topic()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(__('Musisz być zalogowany.', 'rop_panel'));
        }

        $title = sanitize_text_field($_POST['topic_title']);
        $content = wp_kses_post($_POST['topic_content']);
        $forum_id = intval($_POST['forum_id']);

        $errors = array();

        if (empty($title)) {
            $errors[] = __('Tytuł posta jest wymagany.', 'rop_panel');
        }

        if (empty($content)) {
            $errors[] = __('Treść posta jest wymagana.', 'rop_panel');
        }

        if (empty($forum_id)) {
            $errors[] = __('Kategoria jest wymagana.', 'rop_panel');
        }

        if (!empty($errors)) {
            wp_send_json_error(implode('<br>', $errors));
        }

        if (!bbp_is_forum($forum_id)) {
            wp_send_json_error(__('Wybrana kategoria nie istnieje.', 'rop_panel'));
        }

        $topic_data = array(
            'post_parent' => $forum_id,
            'post_status' => bbp_get_public_status_id(),
            'post_type' => bbp_get_topic_post_type(),
            'post_author' => get_current_user_id(),
            'post_password' => '',
            'post_content' => $content,
            'post_title' => $title,
            'comment_status' => 'closed',
            'menu_order' => 0,
        );

        $topic_id = wp_insert_post($topic_data);

        if (is_wp_error($topic_id) || !$topic_id) {
            wp_send_json_error(__('Wystąpił błąd podczas tworzenia tematu.', 'rop_panel'));
        }

        bbp_update_topic_forum_id($topic_id, $forum_id);
        bbp_update_topic_topic_id($topic_id, $topic_id);

        update_post_meta($topic_id, '_rop_like_count', 0);
        update_post_meta($topic_id, '_bbp_reply_count', 0);

        bbp_update_forum($forum_id);

        wp_send_json_success(__('Temat został utworzony pomyślnie!', 'rop_panel'));
    }

    private function render_new_topic_form($selected_forum = 0)
    {
        $forums = get_posts(array(
            'post_type' => bbp_get_forum_post_type(),
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ob_start();
?>
        <div class="rop-new-topic-form">
            <form id="rop-new-topic-form">

                <div class="rop-form-group rop-form-group-required">
                    <label for="topic_title" class="rop-form-label"><?php _e('Tytuł posta', 'rop_panel'); ?></label>
                    <input type="text" id="topic_title" name="topic_title" class="rop-form-control"
                        placeholder="<?php _e('Wprowadź tytuł posta...', 'rop_panel'); ?>" required>
                </div>

                <div class="rop-form-group rop-form-group-required">
                    <label for="forum_id" class="rop-form-label"><?php _e('Kategoria', 'rop_panel'); ?></label>
                    <select id="forum_id" name="forum_id" class="rop-form-control" required>
                        <option value=""><?php _e('Wybierz kategorię', 'rop_panel'); ?></option>
                        <?php foreach ($forums as $forum): ?>
                            <option value="<?php echo $forum->ID; ?>" <?php selected($selected_forum, $forum->ID); ?>>
                                <?php echo esc_html($forum->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="rop-form-group rop-form-group-required">
                    <label for="topic_content" class="rop-form-label"><?php _e('Treść posta', 'rop_panel'); ?></label>
                    <textarea id="topic_content" name="topic_content" class="rop-form-control" rows="8"
                        placeholder="<?php _e('Napisz treść swojego posta...', 'rop_panel'); ?>" required></textarea>
                </div>

                <div class="rop-form-footer">
                    <button type="button" class="rop-btn rop-btn-secondary" id="rop-cancel-new-topic">
                        <?php _e('Anuluj', 'rop_panel'); ?>
                    </button>
                    <button type="submit" class="rop-btn rop-btn-primary" id="rop-submit-new-topic">
                        <?php _e('Opublikuj post', 'rop_panel'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_forum_topics()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!function_exists('bbp_get_version')) {
            wp_send_json_error(__('bbPress nie jest aktywny.', 'rop_panel'));
        }

        $forum_html = $this->render_forum_topics();

        wp_send_json_success(array(
            'content' => $forum_html
        ));
    }

    public function filter_forum_topics()
    {
        if (!wp_verify_nonce($_POST['nonce'], 'rop_panel_nonce')) {
            wp_die(__('Błąd bezpieczeństwa.', 'rop_panel'));
        }

        if (!function_exists('bbp_get_version')) {
            wp_send_json_error(__('bbPress nie jest aktywny.', 'rop_panel'));
        }

        $forum_id = sanitize_text_field($_POST['forum_id']);
        $sort_by = sanitize_text_field($_POST['sort_by']);

        $topics_html = $this->get_filtered_topics($forum_id, $sort_by);

        wp_send_json_success(array(
            'topics_html' => $topics_html
        ));
    }

    private function get_filtered_topics($forum_id = '', $sort_by = 'date_desc')
    {
        if ($sort_by === 'replies_desc') {
            $all_topics = $this->get_all_topics_for_sorting($forum_id);
            
            ob_start();
            if (!empty($all_topics)) {
                foreach ($all_topics as $topic_id) {
                    $this->render_topic_item($topic_id);
                }
            } else {
        ?>
                <div class="rop-forum-empty">
                    <p>Brak tematów do wyświetlenia dla wybranych kryteriów.</p>
                    <button class="rop-btn rop-btn-primary" id="rop-first-topic" data-forum-id="<?php echo esc_attr($forum_id); ?>">
                        Utwórz pierwszy temat
                    </button>
                </div>
            <?php
            }

            return ob_get_clean();
        }

        $query_args = array(
            'post_type' => bbp_get_topic_post_type(),
            'post_status' => 'publish',
            'posts_per_page' => 20,
        );

        if (!empty($forum_id)) {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_bbp_forum_id',
                    'value' => $forum_id,
                    'compare' => '='
                )
            );
        } else {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_bbp_forum_id',
                    'compare' => 'EXISTS'
                )
            );
        }

        switch ($sort_by) {
            case 'date_asc':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'ASC';
                break;
            case 'date_desc':
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
                break;
            case 'title_asc':
                $query_args['orderby'] = 'title';
                $query_args['order'] = 'ASC';
                break;
            default:
                $query_args['orderby'] = 'date';
                $query_args['order'] = 'DESC';
        }

        $topics_query = new WP_Query($query_args);

        ob_start();
        if ($topics_query->have_posts()):
            while ($topics_query->have_posts()): $topics_query->the_post();
                $this->render_topic_item(get_the_ID());
            endwhile;
            wp_reset_postdata();
        else: ?>
            <div class="rop-forum-empty">
                <p>Brak tematów do wyświetlenia dla wybranych kryteriów.</p>
                <button class="rop-btn rop-btn-primary" id="rop-first-topic" data-forum-id="<?php echo esc_attr($forum_id); ?>">
                    Utwórz pierwszy temat
                </button>
            </div>
        <?php endif;

        return ob_get_clean();
    }

    private function get_all_topics_for_sorting($forum_id = '') {
        $query_args = array(
            'post_type' => bbp_get_topic_post_type(),
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'fields' => 'ids'
        );

        if (!empty($forum_id)) {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_bbp_forum_id',
                    'value' => $forum_id,
                    'compare' => '='
                )
            );
        } else {
            $query_args['meta_query'] = array(
                array(
                    'key' => '_bbp_forum_id',
                    'compare' => 'EXISTS'
                )
            );
        }

        $topics_query = new WP_Query($query_args);
        $topic_ids = $topics_query->posts;

        $topics_with_counts = array();
        foreach ($topic_ids as $topic_id) {
            $reply_count = $this->count_topic_replies($topic_id);
            $topics_with_counts[] = array(
                'id' => $topic_id,
                'replies' => $reply_count
            );
        }

        usort($topics_with_counts, function($a, $b) {
            if ($a['replies'] == $b['replies']) {
                return 0;
            }
            return ($a['replies'] > $b['replies']) ? -1 : 1;
        });

        return array_column($topics_with_counts, 'id');
    }

    private function render_forum_topics()
    {
        $topics_query = new WP_Query(array(
            'post_type' => bbp_get_topic_post_type(),
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_bbp_forum_id',
                    'compare' => 'EXISTS'
                )
            )
        ));

        ob_start();
        ?>
        <div class="rop-forum-container">
            <div class="rop-forum-header">
                <h2>Forum</h2>
                <p class="rop-forum-description">Dyskutuj, dziel się wiedzą i nawiązuj kontakty biznesowe</p>

                <div class="rop-forum-controls">
                    <div class="rop-forum-filters">
                        <select id="rop-forum-category" class="rop-form-control">
                            <option value="">Wszystkie kategorie</option>
                            <?php
                            $forums = get_posts(array(
                                'post_type' => bbp_get_forum_post_type(),
                                'post_status' => 'publish',
                                'numberposts' => -1,
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ));
                            foreach ($forums as $forum): ?>
                                <option value="<?php echo $forum->ID; ?>"><?php echo esc_html($forum->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select id="rop-forum-sort" class="rop-form-control">
                            <option value="date_desc">Najnowsze</option>
                            <option value="date_asc">Najstarsze</option>
                            <option value="replies_desc">Najwięcej odpowiedzi</option>
                            <option value="title_asc">Alfabetycznie (A-Z)</option>
                        </select>
                    </div>

                    <button class="rop-btn rop-btn-primary" id="rop-new-topic">
                        Nowy post
                    </button>
                </div>
            </div>

            <div class="rop-forum-topics" id="rop-forum-topics-list">
                <?php if ($topics_query->have_posts()): ?>
                    <?php while ($topics_query->have_posts()): $topics_query->the_post(); ?>
                        <?php $this->render_topic_item(get_the_ID()); ?>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else: ?>
                    <div class="rop-forum-empty">
                        <p>Brak tematów do wyświetlenia.</p>
                        <button class="rop-btn rop-btn-primary" id="rop-first-topic">
                            Utwórz pierwszy temat
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($topics_query->found_posts > 20): ?>
                <div class="rop-forum-pagination">
                    <button class="rop-btn rop-btn-secondary" id="rop-load-more-topics">
                        Załaduj więcej
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    private function render_topic_item($topic_id) {
        $author_id = bbp_get_topic_author_id($topic_id);
        $author_data = get_userdata($author_id);
        $company = get_user_meta($author_id, 'rop_company_name', true);
        if (!$company) {
            $company = get_user_meta($author_id, 'company', true);
        }

        $forum_id = bbp_get_topic_forum_id($topic_id);
        $forum_title = bbp_get_forum_title($forum_id);

        $reply_count = $this->count_topic_replies($topic_id);

        $like_count = get_post_meta($topic_id, '_rop_like_count', true);
        $like_count = $like_count ? intval($like_count) : 0;

        $current_user_id = get_current_user_id();
        $user_likes = get_user_meta($current_user_id, 'rop_liked_topics', true);
        $is_liked = is_array($user_likes) && in_array($topic_id, $user_likes);

        $topic_tags = wp_get_post_terms($topic_id, bbp_get_topic_tag_tax_id());

        $time_diff = human_time_diff(get_post_time('U', false, $topic_id), current_time('timestamp'));

        $avatar_html = $this->get_user_avatar_or_logo($author_id);
        ?>
        <div class="rop-topic-item" data-topic-id="<?php echo $topic_id; ?>">
            <div class="rop-topic-avatar">
                <?php echo $avatar_html; ?>
            </div>

            <div class="rop-topic-content">
                <div class="rop-topic-meta">
                    <span class="rop-topic-author"><?php echo esc_html($author_data->display_name); ?></span>
                    <?php if ($company): ?>
                        <span class="rop-topic-company"><?php echo esc_html($company); ?></span>
                    <?php endif; ?>
                    <span class="rop-topic-time"><?php echo $time_diff; ?> temu</span>
                </div>

                <h3 class="rop-topic-title">
                    <?php echo esc_html(bbp_get_topic_title($topic_id)); ?>
                </h3>

                <?php if ($forum_title): ?>
                    <div class="rop-topic-category">
                        <span class="rop-category-badge"><?php echo esc_html($forum_title); ?></span>
                    </div>
                <?php endif; ?>

                <div class="rop-topic-excerpt">
                    <?php 
                    $content = get_post_field('post_content', $topic_id);
                    $excerpt = wp_trim_words(strip_tags($content), 30);
                    echo esc_html($excerpt);
                    ?>
                </div>

                <?php if (!empty($topic_tags)): ?>
                    <div class="rop-topic-tags">
                        <?php foreach (array_slice($topic_tags, 0, 3) as $tag): ?>
                            <span class="rop-tag">#<?php echo esc_html($tag->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="rop-topic-stats">
                    <span class="rop-stat rop-like-container">
                        <button class="rop-like-btn <?php echo $is_liked ? 'liked' : ''; ?>" 
                                data-topic-id="<?php echo $topic_id; ?>" 
                                title="<?php echo $is_liked ? 'Usuń polubienie' : 'Polub'; ?>">
                            <span class="rop-heart-icon">♥</span>
                        </button>
                        <span class="rop-like-count"><?php echo $like_count; ?></span>
                    </span>

                    <span class="rop-stat rop-comment-container">
                        <span class="rop-comment-icon">💬</span>
                        <span class="rop-comment-count"><?php echo $reply_count; ?></span>
                    </span>
                </div>
            </div>
        </div>
<?php
    }

    private function get_user_avatar_or_logo($user_id) {
        $company_logo = get_user_meta($user_id, 'rop_company_logo', true);
        
        if (!empty($company_logo) && file_exists(str_replace(home_url(), ABSPATH, $company_logo))) {
            return '<img src="' . esc_url($company_logo) . '" alt="Logo firmy" class="rop-company-logo" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">';
        }
        
        return get_avatar($user_id, 60);
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
}