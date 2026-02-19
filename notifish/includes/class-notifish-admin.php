<?php
/**
 * Admin interface class for Notifish plugin
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Notifish_Admin {
    private $options;
    private $database;
    private $api;
    private $logger;

    public function __construct($database, $api, $logger) {
        $this->database = $database;
        $this->api = $api;
        $this->logger = $logger;
        $this->options = get_option('notifish_options');
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Notifish Settings', 'notifish'),
            __('Notifish', 'notifish'),
            'manage_options', 
            'notifish', 
            array($this, 'render_config_page')
        );
    
        add_submenu_page(
            'notifish',
            __('Notifish Logs', 'notifish'),
            __('Notifish Logs', 'notifish'),
            'manage_options', 
            'notifish_requests', 
            array($this, 'render_requests_page')
        );
        
        add_submenu_page(
            'notifish',
            __('WhatsApp Status', 'notifish'),
            __('WhatsApp Status', 'notifish'),
            'manage_options',
            'notifish_qrcode',
            array($this, 'render_qrcode_page')
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings() {
        register_setting('notifish_group', 'notifish_options', array($this, 'sanitize'));
    }

    /**
     * Sanitize settings input
     *
     * @param array $input Input data
     * @return array
     */
    public function sanitize($input) {
        $current_options = get_option('notifish_options', array());
        
        $new_input = array();
        if (isset($input['api_url'])) {
            $new_input['api_url'] = sanitize_text_field($input['api_url']);
        }
        
        if (isset($input['api_key'])) {
            $api_key_value = sanitize_text_field($input['api_key']);
            if (empty($api_key_value) || preg_match('/^[*]+$/', $api_key_value)) {
                if (isset($current_options['api_key']) && !empty($current_options['api_key'])) {
                    $new_input['api_key'] = $current_options['api_key'];
                }
            } else {
                $new_input['api_key'] = $api_key_value;
            }
        } else {
            if (isset($current_options['api_key']) && !empty($current_options['api_key'])) {
                $new_input['api_key'] = $current_options['api_key'];
            }
        }
        
        if (isset($input['instance_uuid'])) {
            $new_input['instance_uuid'] = sanitize_text_field($input['instance_uuid']);
        }
        if (isset($input['default_whatsapp_enabled'])) {
            $new_input['default_whatsapp_enabled'] = sanitize_text_field($input['default_whatsapp_enabled']);
        }
        if (isset($input['enable_logging'])) {
            $new_input['enable_logging'] = sanitize_text_field($input['enable_logging']);
        }
        if (isset($input['remove_data_on_uninstall'])) {
            $new_input['remove_data_on_uninstall'] = sanitize_text_field($input['remove_data_on_uninstall']);
        }
        if (isset($input['language'])) {
            $new_input['language'] = sanitize_text_field($input['language']);
        }
        return $new_input;
    }

    /**
     * Add meta box to posts
     *
     * @return void
     */
    public function add_meta_box() {
        add_meta_box(
            'notifish_meta_box',
            'Notifish',
            array($this, 'render_meta_box'),
            'post'
        );
    }

    /**
     * Render meta box
     *
     * @param WP_Post $post Post object
     * @return void
     */
    public function render_meta_box($post) {
        wp_nonce_field('notifish_meta_box', 'notifish_meta_box_nonce');
        $value = get_post_meta($post->ID, '_notifish_meta_value_key', true);
        
        $already_sent = false;
        if (!empty($post->ID)) {
            $already_sent = $this->database->post_was_sent($post->ID);
        }
        
        $is_new_post = empty($post->ID) || $post->post_status === 'auto-draft';
        $has_no_value = empty($value);
        
        $default_enabled = false;
        if ($is_new_post || $has_no_value) {
            $options = get_option('notifish_options');
            $default_enabled = isset($options['default_whatsapp_enabled']) && $options['default_whatsapp_enabled'] == '1';
        }
        
        $is_checked = $already_sent ? false : ($default_enabled || ($value === '1' || $value === 1 || $value === true));
        $checked_attr = $is_checked ? 'checked="checked"' : '';
        
        echo '<label for="notifish_send_notification_whatsapp">';
        echo 'Deseja compartilhar no WhatsApp?';
        echo '</label> ';
        echo '<input type="checkbox" id="notifish_send_notification_whatsapp" name="notifish_send_notification_whatsapp" value="1" ' . $checked_attr . ' />';
        
        if ($already_sent) {
            echo '<p style="color: red;">A matéria já foi compartilhada no WhatsApp. Para reenviar, use o menu "Notifish Logs".</p>';
        }
    }

    /**
     * Handle post save (editor clássico/Gutenberg via admin)
     * 
     * NOTA: Este método só processa salvamentos via admin (com nonce).
     * Posts via REST API (app WordPress) são processados em handle_rest_post_insert().
     * Posts agendados são processados em handle_scheduled_post_publish().
     *
     * @param int $post_id Post ID
     * @return void
     */
    public function handle_post_save($post_id) {
        // Ignora se é uma requisição REST API (será tratada por handle_rest_post_insert)
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        if (!isset($_POST['notifish_meta_box_nonce'])) {
            return;
        }
        
        $nonce = sanitize_text_field(wp_unslash($_POST['notifish_meta_box_nonce']));
        if (!wp_verify_nonce($nonce, 'notifish_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $my_data = isset($_POST['notifish_send_notification_whatsapp']) ? sanitize_text_field(wp_unslash($_POST['notifish_send_notification_whatsapp'])) : '';
        update_post_meta($post_id, '_notifish_meta_value_key', $my_data);

        $status = get_post_status($post_id);
        
        // Verifica se o post já foi enviado antes de disparar o envio
        // Se já foi enviado, não reenvia automaticamente (só pelo menu Notifish Logs)
        // NOTA: Posts agendados (status 'future') serão processados quando publicados via transition_post_status
        if ($status === 'publish' && $my_data == '1') {
            // Verifica se o post já foi enviado anteriormente
            if (!$this->database->post_was_sent($post_id)) {
                do_action('notifish_send_message', $post_id);
            }
            // Se já foi enviado, não faz nada (o usuário deve usar o menu Notifish Logs para reenviar)
        }
    }

    /**
     * Render config page
     *
     * @return void
     */
    public function render_config_page() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'notifish'));
        }
        
        $this->options = get_option('notifish_options');
        $options = $this->options;
        require_once NOTIFISH_PLUGIN_DIR . 'admin/config-page.php';
    }

    /**
     * Render requests page
     *
     * @return void
     */
    public function render_requests_page() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'notifish'));
        }
        
        require_once NOTIFISH_PLUGIN_DIR . 'admin/views/requests-page.php';
    }

    /**
     * Render QR code page
     *
     * @return void
     */
    public function render_qrcode_page() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'notifish'));
        }
        
        $this->options = get_option('notifish_options');
        $options = $this->options;
        require_once NOTIFISH_PLUGIN_DIR . 'admin/views/qrcode-page.php';
    }

    /**
     * Handle resend message
     *
     * @param object $request Request object
     * @return void
     */
    public function handle_resend_message($request) {
        $this->api->resend_message($request);
    }
}

