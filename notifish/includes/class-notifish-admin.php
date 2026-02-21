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
        if (isset($input['midia_social_enabled'])) {
            $new_input['midia_social_enabled'] = $input['midia_social_enabled'] === '1' ? '1' : '0';
        }
        if (isset($input['midia_social_logo_url'])) {
            $new_input['midia_social_logo_url'] = esc_url_raw($input['midia_social_logo_url']);
        }
        if (isset($input['midia_social_bg_color'])) {
            $color = sanitize_hex_color($input['midia_social_bg_color']);
            $new_input['midia_social_bg_color'] = $color ? $color : '#333333';
        }
        if (isset($input['midia_social_music'])) {
            $new_input['midia_social_music'] = $input['midia_social_music'] === '1' ? '1' : '0';
        }
        if (isset($input['midia_social_music_id'])) {
            $new_input['midia_social_music_id'] = absint($input['midia_social_music_id']);
        }
        if (isset($input['midia_social_publish'])) {
            $new_input['midia_social_publish'] = $input['midia_social_publish'] === '1' ? '1' : '0';
        }

        // Se Mídias sociais estiver habilitado, exige todos os campos preenchidos
        if (!empty($new_input['midia_social_enabled']) && $new_input['midia_social_enabled'] === '1') {
            $logo_url = isset($new_input['midia_social_logo_url']) ? trim($new_input['midia_social_logo_url']) : '';
            $bg_color = isset($new_input['midia_social_bg_color']) ? $new_input['midia_social_bg_color'] : '';
            $music = isset($new_input['midia_social_music']) ? $new_input['midia_social_music'] : '';
            $music_id = isset($new_input['midia_social_music_id']) ? $new_input['midia_social_music_id'] : '';
            $publish = isset($new_input['midia_social_publish']) ? $new_input['midia_social_publish'] : '';

            $missing = array();
            if (empty($logo_url) || !wp_http_validate_url($logo_url)) {
                $missing[] = __('Logo', 'notifish');
            }
            if (empty($bg_color) || sanitize_hex_color($bg_color) === '') {
                $missing[] = __('Cor de fundo', 'notifish');
            }
            if ($music === '') {
                $missing[] = __('Inserir música no vídeo', 'notifish');
            }
            if (!array_key_exists('midia_social_music_id', $new_input)) {
                $missing[] = __('ID da música', 'notifish');
            }
            if ($publish === '') {
                $missing[] = __('Publicar nas mídias sociais', 'notifish');
            }

            if (!empty($missing)) {
                add_settings_error(
                    'notifish_group',
                    'midia_social_required',
                    sprintf(
                        __('Quando "Habilitar Mídias sociais" está em Sim, é obrigatório preencher todos os campos: %s.', 'notifish'),
                        implode(', ', $missing)
                    ),
                    'error'
                );
                $new_input['midia_social_enabled'] = '0';
            }
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
        
        echo '<p style="margin-bottom: 12px;">';
        echo '<label for="notifish_send_notification_whatsapp">';
        echo '<input type="checkbox" id="notifish_send_notification_whatsapp" name="notifish_send_notification_whatsapp" value="1" ' . $checked_attr . ' /> ';
        echo 'Compartilhar no WhatsApp';
        echo '</label>';
        echo '</p>';

        $instagram_value = get_post_meta($post->ID, '_notifish_instagram_key', true);
        $instagram_checked = ($instagram_value === '1' || $instagram_value === 1 || $instagram_value === true);
        $instagram_attr = $instagram_checked ? 'checked="checked"' : '';
        echo '<p style="margin-bottom: 0;">';
        echo '<label for="notifish_send_notification_instagram">';
        echo '<input type="checkbox" id="notifish_send_notification_instagram" name="notifish_send_notification_instagram" value="1" ' . $instagram_attr . ' /> ';
        echo 'Compartilhar no Instagram';
        echo '</label>';
        echo '</p>';
        echo '<p class="description" style="margin-top: 6px;">Se marcado e o post tiver imagem de destaque, será enviado o array para geração de story/reels no Notifish.</p>';
        
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

        $instagram_data = isset($_POST['notifish_send_notification_instagram']) ? '1' : '';
        update_post_meta($post_id, '_notifish_instagram_key', $instagram_data);

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

