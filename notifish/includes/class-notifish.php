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
        
        // REST API hooks - para compatibilidade com app WordPress iOS/Android
        add_action('init', array($this, 'register_post_meta_for_rest'));
        add_action('rest_after_insert_post', array($this, 'handle_rest_post_insert'), 10, 3);
        
        // Hook para posts agendados - dispara quando post muda de 'future' para 'publish'
        add_action('transition_post_status', array($this, 'handle_scheduled_post_publish'), 10, 3);
        
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
     * Register post meta for REST API
     * Permite que o campo notifish seja acessível via REST API (app WordPress iOS/Android)
     *
     * @return void
     */
    public function register_post_meta_for_rest() {
        register_post_meta('post', '_notifish_meta_value_key', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            },
            'sanitize_callback' => 'sanitize_text_field',
        ));
    }

    /**
     * Handle REST API post insert
     * Aplica valor padrão e dispara mensagem para posts criados via REST API (app WordPress)
     *
     * @param WP_Post         $post     Post object
     * @param WP_REST_Request $request  Request object
     * @param bool            $creating True if creating, false if updating
     * @return void
     */
    public function handle_rest_post_insert($post, $request, $creating) {
        $this->logger->write("=== REST API: handle_rest_post_insert ===", [
            'post_id' => $post->ID,
            'status' => $post->post_status,
            'creating' => $creating
        ]);

        // Se está criando um novo post, aplica o valor padrão das configurações
        if ($creating) {
            $existing_meta = get_post_meta($post->ID, '_notifish_meta_value_key', true);
            
            // Se não foi definido um valor pelo app, usa o padrão das configurações
            if (empty($existing_meta)) {
                $options = get_option('notifish_options');
                $default_enabled = isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1';
                
                if ($default_enabled) {
                    update_post_meta($post->ID, '_notifish_meta_value_key', '1');
                    $this->logger->write("REST API: Valor padrão aplicado (habilitado)", ['post_id' => $post->ID]);
                }
            }
        }

        // Verifica se deve enviar mensagem (post publicado diretamente, não agendado)
        if ($post->post_status === 'publish') {
            $notifish_enabled = get_post_meta($post->ID, '_notifish_meta_value_key', true);
            
            if ($notifish_enabled == '1') {
                // Verifica se já foi enviado anteriormente
                if (!$this->database->post_was_sent($post->ID)) {
                    $this->logger->write("REST API: Disparando envio de mensagem", ['post_id' => $post->ID]);
                    do_action('notifish_send_message', $post->ID);
                } else {
                    $this->logger->write("REST API: Post já foi enviado anteriormente, ignorando", ['post_id' => $post->ID]);
                }
            }
        }
    }

    /**
     * Handle scheduled post publish
     * Dispara mensagem quando um post agendado é publicado automaticamente pelo WP-Cron
     *
     * @param string  $new_status New post status
     * @param string  $old_status Old post status
     * @param WP_Post $post       Post object
     * @return void
     */
    public function handle_scheduled_post_publish($new_status, $old_status, $post) {
        // Só processa posts (não páginas ou outros tipos)
        if ($post->post_type !== 'post') {
            return;
        }

        // Só processa quando muda de 'future' (agendado) para 'publish'
        if ($old_status !== 'future' || $new_status !== 'publish') {
            return;
        }

        $this->logger->write("=== AGENDAMENTO: Post agendado publicado ===", [
            'post_id' => $post->ID,
            'old_status' => $old_status,
            'new_status' => $new_status
        ]);

        $notifish_enabled = get_post_meta($post->ID, '_notifish_meta_value_key', true);
        
        // Se não tem valor definido, verifica o padrão das configurações
        if (empty($notifish_enabled)) {
            $options = get_option('notifish_options');
            $default_enabled = isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1';
            
            if ($default_enabled) {
                $notifish_enabled = '1';
                update_post_meta($post->ID, '_notifish_meta_value_key', '1');
                $this->logger->write("AGENDAMENTO: Valor padrão aplicado (habilitado)", ['post_id' => $post->ID]);
            }
        }

        if ($notifish_enabled == '1') {
            // Verifica se já foi enviado anteriormente
            if (!$this->database->post_was_sent($post->ID)) {
                $this->logger->write("AGENDAMENTO: Disparando envio de mensagem", ['post_id' => $post->ID]);
                do_action('notifish_send_message', $post->ID);
            } else {
                $this->logger->write("AGENDAMENTO: Post já foi enviado anteriormente, ignorando", ['post_id' => $post->ID]);
            }
        } else {
            $this->logger->write("AGENDAMENTO: Notifish não habilitado para este post", ['post_id' => $post->ID]);
        }
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

