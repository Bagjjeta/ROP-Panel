<?php
if (!defined('ABSPATH')) {
    exit;
}

class ROP_Panel_Core {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_popups'));
    }
    
    public function enqueue_scripts() {
        // CSS - sprawdź czy plik istnieje
        $css_file = ROP_PANEL_PLUGIN_URL . 'assets/css/rop-panel.css';
        if (file_exists(ROP_PANEL_PLUGIN_DIR . 'assets/css/rop-panel.css')) {
            wp_enqueue_style(
                'rop-panel-css',
                $css_file,
                array(),
                ROP_PANEL_VERSION
            );
        }
        
        // JavaScript - sprawdź czy plik istnieje
        $js_file = ROP_PANEL_PLUGIN_URL . 'assets/js/rop-panel.js';
        if (file_exists(ROP_PANEL_PLUGIN_DIR . 'assets/js/rop-panel.js')) {
            wp_enqueue_script(
                'rop-panel-js',
                $js_file,
                array('jquery'),
                ROP_PANEL_VERSION,
                true
            );
            
            // Lokalizacja dla AJAX
            wp_localize_script('rop-panel-js', 'rop_panel_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rop_panel_nonce'),
                'loading_text' => __('Ładowanie...', 'rop_panel'),
                'error_text' => __('Wystąpił błąd podczas ładowania.', 'rop_panel'),
                'success_text' => __('Dane zostały zapisane pomyślnie.', 'rop_panel'),
                'upload_error' => __('Błąd podczas przesyłania pliku.', 'rop_panel'),
                'file_too_large' => __('Plik jest zbyt duży. Maksymalny rozmiar to 2MB.', 'rop_panel'),
                'invalid_file_type' => __('Nieprawidłowy typ pliku. Dozwolone są tylko PNG i JPG.', 'rop_panel')
            ));
        }
    }
    
    // ZAKTUALIZOWANY - dodany popup odpowiedzi
    public function render_popups() {
        ?>
        <!-- Forum Popup - dla postów forum -->
        <div id="rop-forum-popup-overlay" class="rop-popup-overlay" style="display: none;">
            <div class="rop-popup-container">
                <div class="rop-popup-header">
                    <h2 id="rop-popup-title"></h2>
                    <button class="rop-popup-close" aria-label="<?php _e('Zamknij', 'rop_panel'); ?>">
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
                    
                    <!-- STATYSTYKI W POPUP -->
                    <div class="rop-popup-stats">
                        <div class="rop-popup-stat rop-popup-likes">
                            <button class="rop-popup-like-btn" id="rop-popup-like-btn">
                                <span class="rop-heart-icon">♥</span>
                            </button>
                            <span class="rop-popup-like-count" id="rop-popup-like-count">0</span>
                        </div>
                        <div class="rop-popup-stat rop-popup-comments">
                            <button class="rop-popup-comment-btn" id="rop-popup-comment-btn">
                                <span class="rop-comment-icon">💬</span>
                            </button>
                            <span class="rop-popup-comment-count" id="rop-popup-comment-count">0</span>
                        </div>
                    </div>
                    
                    <div class="rop-popup-body">
                        <div class="rop-loading">
                            <?php _e('Ładowanie...', 'rop_panel'); ?>
                        </div>
                    </div>
                    
                    <!-- KONTENER NA KOMENTARZE -->
                    <div class="rop-popup-replies" id="rop-popup-replies" style="display: none;">
                        <div class="rop-replies-header">
                            <h3>Komentarze</h3>
                            <button class="rop-back-to-post" id="rop-back-to-post">← Powrót do posta</button>
                        </div>
                        <div class="rop-replies-content" id="rop-replies-content">
                            <!-- Komentarze będą tutaj -->
                        </div>
                        <div class="rop-replies-pagination" id="rop-replies-pagination">
                            <!-- Paginacja będzie tutaj -->
                        </div>
                    </div>
                </div>
                <div class="rop-popup-footer">
                    <button class="rop-btn rop-btn-primary" id="rop-popup-reply">
                        <?php _e('Odpowiedz', 'rop_panel'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- NEW TOPIC POPUP -->
        <div id="rop-new-topic-popup-overlay" class="rop-popup-overlay" style="display: none;">
            <div class="rop-popup-container">
                <div class="rop-popup-header">
                    <h2 id="rop-new-topic-title"><?php _e('Nowy post na forum', 'rop_panel'); ?></h2>
                    <button class="rop-popup-close rop-new-topic-close" aria-label="<?php _e('Zamknij', 'rop_panel'); ?>">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="rop-popup-content" id="rop-new-topic-content">
                    <div class="rop-loading">
                        <?php _e('Ładowanie...', 'rop_panel'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOWY - REPLY POPUP -->
        <div id="rop-reply-popup-overlay" class="rop-popup-overlay" style="display: none;">
            <div class="rop-popup-container">
                <div class="rop-popup-header">
                    <h2 id="rop-reply-popup-title"><?php _e('Dodaj odpowiedź', 'rop_panel'); ?></h2>
                    <button class="rop-popup-close rop-reply-close" aria-label="<?php _e('Zamknij', 'rop_panel'); ?>">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="rop-popup-content" id="rop-reply-content">
                    <div class="rop-reply-form">
                        <form id="rop-reply-form">
                            <div class="rop-form-group rop-form-group-required">
                                <label for="reply_content" class="rop-form-label"><?php _e('Twoja odpowiedź', 'rop_panel'); ?></label>
                                <textarea id="reply_content" name="reply_content" class="rop-form-control" rows="6" 
                                          placeholder="<?php _e('Napisz swoją odpowiedź...', 'rop_panel'); ?>" required></textarea>
                            </div>

                            <div class="rop-form-footer">
                                <button type="button" class="rop-btn rop-btn-secondary" id="rop-cancel-reply">
                                    <?php _e('Anuluj', 'rop_panel'); ?>
                                </button>
                                <button type="submit" class="rop-btn rop-btn-primary" id="rop-submit-reply">
                                    <?php _e('Dodaj odpowiedź', 'rop_panel'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}