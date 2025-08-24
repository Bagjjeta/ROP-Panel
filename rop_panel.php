<?php
/**
 * Plugin Name: ROP Panel
 * Plugin URI: https://yourwebsite.com
 * Description: Kompletny panel członka z funkcjonalnościami forum popup i edycji profilu firmowego. Kompatybilny z bbPress i Ultimate Members.
 * Version: 2.1.0
 * Author: git
 * Text Domain: rop_panel
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// DODAJ DEBUGGING NA POCZĄTKU
error_log('ROP DEBUG: Plugin file loaded');

// Definiowanie stałych
define('ROP_PANEL_VERSION', '2.1.0');
define('ROP_PANEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ROP_PANEL_PLUGIN_URL', plugin_dir_url(__FILE__));

error_log('ROP DEBUG: Constants defined');

// Główna klasa wtyczki
class ROP_Panel_Main {
    
    public function __construct() {
        error_log('ROP DEBUG: ROP_Panel_Main constructor');
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        error_log('ROP DEBUG: init() called');
        // Załaduj pliki
        $this->load_dependencies();
        
        // Inicjalizuj komponenty tylko jeśli pliki zostały załadowane
        if (class_exists('ROP_Panel_Core')) {
            new ROP_Panel_Core();
            error_log('ROP DEBUG: ROP_Panel_Core initialized');
        }
        
        if (class_exists('ROP_Panel_Profile_Editor')) {
            new ROP_Panel_Profile_Editor();
            error_log('ROP DEBUG: ROP_Panel_Profile_Editor initialized');
        }
        
        if (class_exists('ROP_Panel_Forum_Manager')) {
            new ROP_Panel_Forum_Manager();
            error_log('ROP DEBUG: ROP_Panel_Forum_Manager initialized');
        }
        
        if (class_exists('ROP_Panel_Delete_Manager')) {
            new ROP_Panel_Delete_Manager();
            error_log('ROP DEBUG: ROP_Panel_Delete_Manager initialized');
        }
        
        // Forum popup tylko jeśli bbPress jest aktywny
        if (function_exists('bbp_get_version') && class_exists('ROP_Panel_Forum_Popup')) {
            new ROP_Panel_Forum_Popup();
            error_log('ROP DEBUG: ROP_Panel_Forum_Popup initialized');
        }
        
        // Inicjalizacja komponentu wiadomości
        if (class_exists('ROP_Panel_Messages')) {
            new ROP_Panel_Messages();
            error_log('ROP DEBUG: ROP_Panel_Messages initialized');
        }
    }
    
    public function load_dependencies() {
        error_log('ROP DEBUG: load_dependencies() called');
        
        // Lista plików do załadowania
        $files = array(
            'includes/class-rop-panel-core.php',
            'includes/class-rop-panel-profile-editor.php',
            'includes/class-rop-panel-forum-popup.php',
            'includes/class-rop-panel-forum-manager.php',
            'includes/class-rop-panel-delete-manager.php',
            'includes/class-rop-panel-messages.php', // Dodany nowy plik dla funkcjonalności wiadomości
            'includes/ajax-handlers.php'
        );
        
        foreach ($files as $file) {
            $file_path = ROP_PANEL_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                error_log('ROP DEBUG: Successfully loaded: ' . $file);
            } else {
                error_log('ROP ERROR: Cannot load file: ' . $file_path);
            }
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('rop_panel', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function activate() {
        error_log('ROP DEBUG: Plugin activated');
        // Sprawdź czy folder includes istnieje
        $includes_dir = ROP_PANEL_PLUGIN_DIR . 'includes';
        if (!file_exists($includes_dir)) {
            wp_mkdir_p($includes_dir);
        }
        
        // Sprawdź czy folder assets istnieje
        $assets_dir = ROP_PANEL_PLUGIN_DIR . 'assets';
        if (!file_exists($assets_dir)) {
            wp_mkdir_p($assets_dir);
            wp_mkdir_p($assets_dir . '/css');
            wp_mkdir_p($assets_dir . '/js');
        }
        
        // Sprawdź czy folder templates istnieje
        $templates_dir = ROP_PANEL_PLUGIN_DIR . 'templates';
        if (!file_exists($templates_dir)) {
            wp_mkdir_p($templates_dir);
        }
        
        // Utwórz folder dla uploadu logo firm
        $upload_dir = wp_upload_dir();
        $rop_upload_dir = $upload_dir['basedir'] . '/rop_panel/company_logos';
        
        if (!file_exists($rop_upload_dir)) {
            wp_mkdir_p($rop_upload_dir);
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        error_log('ROP DEBUG: Plugin deactivated');
        flush_rewrite_rules();
    }
}

// Filtr do dodawania zmiennych JavaScript do skryptów wtyczki
function rop_panel_add_script_vars($script_vars = array()) {
    $script_vars['ajax_url'] = admin_url('admin-ajax.php');
    $script_vars['nonce'] = wp_create_nonce('rop_panel_nonce');
    return $script_vars;
}
add_filter('rop_panel_script_vars', 'rop_panel_add_script_vars');

// Uruchom wtyczkę
error_log('ROP DEBUG: About to initialize ROP_Panel_Main');
new ROP_Panel_Main();
error_log('ROP DEBUG: ROP_Panel_Main initialized');