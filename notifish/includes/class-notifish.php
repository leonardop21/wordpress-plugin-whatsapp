<?php
/**
 * Main plugin class
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Notifish {
    private $logger;
    private $database;
    private $api;
    private $admin;
    private $ajax;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'class-notifish-logger.php';
        require_once plugin_dir_path(__FILE__) . 'class-notifish-database.php';
        require_once plugin_dir_path(__FILE__) . 'class-notifish-api.php';
        require_once plugin_dir_path(__FILE__) . 'class-notifish-admin.php';
        require_once plugin_dir_path(__FILE__) . 'class-notifish-ajax.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->logger = new Notifish_Logger();
        $this->database = new Notifish_Database($this->logger);
        $this->api = new Notifish_API($this->logger, $this->database);
        $this->admin = new Notifish_Admin($this->database, $this->api, $this->logger);
        $this->ajax = new Notifish_Ajax($this->logger);
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this->admin, 'add_admin_menu'));
        add_action('admin_init', array($this->admin, 'register_settings'));
        add_action('add_meta_boxes', array($this->admin, 'add_meta_box'));
        add_action('save_post', array($this->admin, 'handle_post_save'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Activation hook
        register_activation_hook(NOTIFISH_PLUGIN_FILE, array($this, 'activate'));
        
        // AJAX hooks
        add_action('wp_ajax_notifish_get_qrcode', array($this->ajax, 'get_qrcode'));
        add_action('wp_ajax_notifish_get_session_status', array($this->ajax, 'get_session_status'));
        add_action('wp_ajax_notifish_restart_session', array($this->ajax, 'restart_session'));
        add_action('wp_ajax_notifish_logout_session', array($this->ajax, 'logout_session'));
        
        // Custom action hooks
        add_action('notifish_send_message', array($this, 'send_message'), 10, 1);
        add_action('notifish_resend_message', array($this->admin, 'handle_resend_message'), 10, 1);
    }

    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        $this->database->create_table();
    }

    /**
     * Enqueue admin scripts with security nonce
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on plugin admin pages
        if (strpos($hook, 'notifish') === false) {
            return;
        }
        
        // Enqueue jQuery if not already enqueued
        wp_enqueue_script('jquery');
        
        // Localize script with nonce for AJAX security
        wp_localize_script('jquery', 'notifish_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('notifish_ajax_nonce')
        ));
    }

    /**
     * Send message handler
     *
     * @param int $post_id Post ID
     * @return void
     */
    public function send_message($post_id) {
        $this->logger->write("=== INÍCIO: send_message ===", ['post_id' => $post_id]);
        
        $options = get_option('notifish_options');
        $versao = isset($options['versao_notifish']) ? $options['versao_notifish'] : 'v1';
        
        if ($versao === 'v2') {
            $this->api->send_message_v2($post_id);
        } else {
            $this->api->send_message_v1($post_id);
        }
        
        $this->logger->write("=== FIM: send_message ===");
    }
}

