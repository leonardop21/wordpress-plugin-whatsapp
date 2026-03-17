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
        add_action('rest_api_init', array($this, 'register_notifish_endpoint'));
        add_action('rest_after_insert_post', array($this, 'handle_rest_post_insert'), 10, 3);
        
        // Hook universal para qualquer publicação de post (REST API, XML-RPC, Cron, Admin)
        // Prioridade 20 para executar depois do save_post padrão
        add_action('transition_post_status', array($this, 'handle_scheduled_post_publish'), 20, 3);
        
        // Hook adicional para XML-RPC (app WordPress pode usar)
        add_action('xmlrpc_publish_post', array($this, 'handle_xmlrpc_publish'), 10, 1);
        
        // Activation hook
        register_activation_hook(NOTIFISH_PLUGIN_FILE, array($this, 'activate'));
        
        // AJAX hooks
        add_action('wp_ajax_notifish_get_qrcode', array($this->ajax, 'get_qrcode'));
        add_action('wp_ajax_notifish_get_session_status', array($this->ajax, 'get_session_status'));
        add_action('wp_ajax_notifish_restart_session', array($this->ajax, 'restart_session'));
        add_action('wp_ajax_notifish_logout_session', array($this->ajax, 'logout_session'));
        add_action('wp_ajax_notifish_dismiss_credentials_notice', array($this->ajax, 'dismiss_credentials_notice'));
        
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
        
        // Enqueue config page script and assets
        if ($hook === 'toplevel_page_notifish') {
            wp_enqueue_media();
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script(
                'notifish-config',
                NOTIFISH_PLUGIN_URL . 'assets/js/config-page.js',
                array('jquery', 'wp-color-picker'),
                NOTIFISH_VERSION,
                true
            );
            wp_localize_script(
                'notifish-config',
                'notifishConfig',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('notifish_dismiss_notice'),
                    'logoMaxKb' => 200,
                    'logoAllowedTypes' => array('image/png', 'image/webp')
                )
            );
        }

        // Enqueue QR code page script
        if ($hook === 'notifish_page_notifish_qrcode') {
            $options = get_option('notifish_options');
            $instance_uuid = isset($options['instance_uuid']) ? $options['instance_uuid'] : '';
            
            wp_enqueue_script(
                'notifish-qrcode',
                NOTIFISH_PLUGIN_URL . 'assets/js/qrcode-page.js',
                array('jquery'),
                NOTIFISH_VERSION,
                true
            );
            wp_localize_script(
                'notifish-qrcode',
                'notifishQRCode',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('notifish_ajax_nonce'),
                    'instanceUuid' => esc_js($instance_uuid),
                    'versao' => 'v2'
                )
            );
        }
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
        register_post_meta('post', '_notifish_instagram_key', array(
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
     * Registra endpoint REST para o Notifish acessar dados do post (scrap).
     * URL: GET /wp-json/notifish/v1/post/{id}/{key}
     */
    public function register_notifish_endpoint() {
        register_rest_route('notifish/v1', '/post/(?P<id>\d+)/(?P<key>[a-zA-Z0-9\-_]+)', array(
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => array($this, 'notifish_endpoint_callback'),
            'args'                => array(
                'id'  => array(
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && absint($param) > 0;
                    },
                ),
                'key' => array(
                    'required' => true,
                ),
            ),
        ));
    }

    /**
     * Callback do endpoint GET notifish/v1/post/{id}/{key}
     * Retorna url e featured_image do post para o Notifish fazer scrap.
     */
    public function notifish_endpoint_callback(WP_REST_Request $request) {
        $CACHE_TTL         = 60;
        $RATE_LIMIT_TTL    = 30;
        $RATE_LIMIT_MAX    = 10;
        $ip                = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $rate_key          = 'notifish_rl_' . md5($ip);
        $current_count     = (int) get_transient($rate_key);

        if ($current_count >= $RATE_LIMIT_MAX) {
            return new WP_REST_Response(array('error' => 'Too many requests'), 429);
        }
        set_transient($rate_key, $current_count + 1, $RATE_LIMIT_TTL);

        $key = (string) $request->get_param('key');
        $options = get_option('notifish_options', array());
        $instance_uuid = isset($options['instance_uuid']) ? (string) $options['instance_uuid'] : '';

        if ($instance_uuid === '' || !hash_equals($instance_uuid, $key)) {
            $this->logger->write('Notifish endpoint: Invalid key from IP ' . $ip);
            return new WP_REST_Response(array('error' => 'Invalid API key'), 401);
        }

        $post_id = absint($request->get_param('id'));
        if (!$post_id) {
            return new WP_REST_Response(array('error' => 'Invalid post ID'), 400);
        }

        $cache_key = 'notifish_post_' . $post_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(array('error' => 'Post not found'), 404);
        }

        $thumb_id = get_post_thumbnail_id($post_id);
        $featured_image = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'full') : null;

        $response = array(
            'post_id'        => $post_id,
            'url'            => esc_url_raw(get_permalink($post_id)),
            'featured_image' => $featured_image,
        );

        set_transient($cache_key, $response, $CACHE_TTL);
        return new WP_REST_Response($response, 200);
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
            $options = get_option('notifish_options');
            $existing_meta = get_post_meta($post->ID, '_notifish_meta_value_key', true);
            if (empty($existing_meta)) {
                $default_enabled = isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1';
                if ($default_enabled) {
                    update_post_meta($post->ID, '_notifish_meta_value_key', '1');
                    $this->logger->write("REST API: Valor padrão aplicado (habilitado)", ['post_id' => $post->ID]);
                }
            }
            $existing_instagram = get_post_meta($post->ID, '_notifish_instagram_key', true);
            if (empty($existing_instagram)) {
                $default_instagram = isset($options['default_instagram_enabled']) && $options['default_instagram_enabled'] == '1';
                if ($default_instagram) {
                    update_post_meta($post->ID, '_notifish_instagram_key', '1');
                    $this->logger->write("REST API: Instagram padrão aplicado (habilitado)", ['post_id' => $post->ID]);
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
     * Handle post publish via transition_post_status
     * Dispara mensagem quando um post é publicado por QUALQUER método:
     * - Posts agendados (future -> publish) via WP-Cron
     * - Posts via REST API (app WordPress iOS/Android)
     * - Posts via XML-RPC
     * - Posts via admin
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

        // Só processa quando o novo status é 'publish' e o antigo NÃO era 'publish'
        // Isso captura: future->publish, draft->publish, pending->publish, auto-draft->publish, etc.
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        $this->logger->write("=== TRANSITION: Post publicado ===", [
            'post_id' => $post->ID,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'is_rest' => defined('REST_REQUEST') && REST_REQUEST,
            'is_xmlrpc' => defined('XMLRPC_REQUEST') && XMLRPC_REQUEST,
            'is_cron' => defined('DOING_CRON') && DOING_CRON
        ]);

        // Verifica se já foi processado pelo handle_post_save (admin clássico)
        // Se veio do admin com nonce, o handle_post_save já tratou
        if (isset($_POST['notifish_meta_box_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['notifish_meta_box_nonce']));
            if (wp_verify_nonce($nonce, 'notifish_meta_box')) {
                $this->logger->write("TRANSITION: Ignorando - já processado pelo handle_post_save", ['post_id' => $post->ID]);
                return;
            }
        }

        $notifish_enabled = get_post_meta($post->ID, '_notifish_meta_value_key', true);
        $options = get_option('notifish_options');

        // Se não tem valor definido, verifica o padrão das configurações
        if (empty($notifish_enabled)) {
            $default_enabled = isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1';
            if ($default_enabled) {
                $notifish_enabled = '1';
                update_post_meta($post->ID, '_notifish_meta_value_key', '1');
                $this->logger->write("TRANSITION: Valor padrão aplicado (habilitado)", ['post_id' => $post->ID]);
            }
        }
        $instagram_meta = get_post_meta($post->ID, '_notifish_instagram_key', true);
        if (empty($instagram_meta)) {
            $default_instagram = isset($options['default_instagram_enabled']) && $options['default_instagram_enabled'] == '1';
            if ($default_instagram) {
                update_post_meta($post->ID, '_notifish_instagram_key', '1');
                $this->logger->write("TRANSITION: Instagram padrão aplicado (habilitado)", ['post_id' => $post->ID]);
            }
        }

        if ($notifish_enabled == '1') {
            // Verifica se já foi enviado anteriormente
            if (!$this->database->post_was_sent($post->ID)) {
                $this->logger->write("TRANSITION: Disparando envio de mensagem", ['post_id' => $post->ID]);
                do_action('notifish_send_message', $post->ID);
            } else {
                $this->logger->write("TRANSITION: Post já foi enviado anteriormente, ignorando", ['post_id' => $post->ID]);
            }
        } else {
            $this->logger->write("TRANSITION: Notifish não habilitado para este post", ['post_id' => $post->ID]);
        }
    }

    /**
     * Handle XML-RPC publish
     * Dispara mensagem quando um post é publicado via XML-RPC (app WordPress antigo)
     *
     * @param int $post_id Post ID
     * @return void
     */
    public function handle_xmlrpc_publish($post_id) {
        $this->logger->write("=== XML-RPC: Post publicado ===", ['post_id' => $post_id]);
        
        // O transition_post_status já deve ter tratado, mas garantimos aqui também
        $notifish_enabled = get_post_meta($post_id, '_notifish_meta_value_key', true);
        $options = get_option('notifish_options');

        if (empty($notifish_enabled)) {
            $default_enabled = isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1';
            if ($default_enabled) {
                update_post_meta($post_id, '_notifish_meta_value_key', '1');
                $notifish_enabled = '1';
                $this->logger->write("XML-RPC: Valor padrão aplicado", ['post_id' => $post_id]);
            }
        }
        $instagram_meta = get_post_meta($post_id, '_notifish_instagram_key', true);
        if (empty($instagram_meta)) {
            $default_instagram = isset($options['default_instagram_enabled']) && $options['default_instagram_enabled'] == '1';
            if ($default_instagram) {
                update_post_meta($post_id, '_notifish_instagram_key', '1');
                $this->logger->write("XML-RPC: Instagram padrão aplicado", ['post_id' => $post_id]);
            }
        }

        if ($notifish_enabled == '1' && !$this->database->post_was_sent($post_id)) {
            $this->logger->write("XML-RPC: Disparando envio", ['post_id' => $post_id]);
            do_action('notifish_send_message', $post_id);
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
        $this->api->send_message($post_id);
        $this->logger->write("=== FIM: send_message ===");
    }
}

