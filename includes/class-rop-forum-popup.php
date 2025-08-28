<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Forum_Popup {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'popup_html'));
        add_filter('bbp_get_topic_permalink', array($this, 'modify_topic_links'), 10, 2);
        add_filter('bbp_get_reply_url', array($this, 'modify_reply_links'), 10, 2);
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'rop-forum-popup-css',
            ROP_FORUM_POPUP_PLUGIN_URL . 'assets/css/popup-styles.css',
            array(),
            ROP_FORUM_POPUP_VERSION
        );
        
        wp_enqueue_script(
            'rop-forum-popup-js',
            ROP_FORUM_POPUP_PLUGIN_URL . 'assets/js/popup-script.js',
            array('jquery'),
            ROP_FORUM_POPUP_VERSION,
            true
        );

        wp_localize_script('rop-forum-popup-js', 'rop_forum_popup_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rop_forum_popup_nonce'),
            'loading_text' => __('Ładowanie...', 'rop_forum_popup'),
            'error_text' => __('Wystąpił błąd podczas ładowania.', 'rop_forum_popup')
        ));
    }
    
    public function modify_topic_links($permalink, $topic_id) {
        if (is_main_query() && !is_admin()) {
            return add_query_arg('popup', '1', $permalink);
        }
        return $permalink;
    }
    
    public function modify_reply_links($permalink, $reply_id) {
        if (is_main_query() && !is_admin()) {
            return add_query_arg('popup', '1', $permalink);
        }
        return $permalink;
    }
    
    public function popup_html() {
        ?>
        <div id="rop-forum-popup-overlay" class="rop-popup-overlay" style="display: none;">
            <div class="rop-popup-container">
                <div class="rop-popup-header">
                    <h2 id="rop-popup-title"></h2>
                    <button class="rop-popup-close" aria-label="<?php _e('Zamknij', 'rop_forum_popup'); ?>">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="rop-popup-content">
                    <div class="rop-popup-author">
                        <div class="rop-author-avatar"></div>
                        <div class="rop-author-info">
                            <div class="rop-author-name"></div>
                            <div class="rop-author-company"></div>
                        </div>
                    </div>
                    <div class="rop-popup-meta">
                        <span class="rop-popup-category"></span>
                        <span class="rop-popup-date"></span>
                    </div>
                    <div class="rop-popup-body">
                        <div class="rop-loading">
                            <?php _e('Ładowanie...', 'rop_forum_popup'); ?>
                        </div>
                    </div>
                </div>
                <div class="rop-popup-footer">
                    <button class="rop-btn rop-btn-secondary rop-popup-close">
                        <?php _e('Zamknij', 'rop_forum_popup'); ?>
                    </button>
                    <button class="rop-btn rop-btn-primary" id="rop-popup-reply">
                        <?php _e('Odpowiedz', 'rop_forum_popup'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}