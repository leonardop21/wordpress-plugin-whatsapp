<?php

/*
Plugin Name: Notifish
Description: Plugin para gerenciar notificações via API do Notifish.
Version: 1.0
Site: https://notifish.com
Author: Notifish
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
} 

class Notifish_Plugin {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'send_message_on_publish'));
        register_activation_hook(__FILE__, array($this, 'create_database'));
    }

    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            'Configurações do Notifish', 
            'Notifish', 
            'manage_options', 
            'notifish', 
            array($this, 'create_admin_page')
        );
    
        // Submenu
        add_submenu_page(
            'notifish', // Slug do menu principal, torna-se pai deste submenu
            'Requests Enviadas', 
            'Notifish Requests', 
            'manage_options', 
            'notifish_requests', 
            array($this, 'requests_page')
        );
    }
    
    public function create_admin_page() {
        $this->options = get_option('notifish_options');
        require_once plugin_dir_path(__FILE__) . 'admin/config-page.php';
    }

    public function register_settings() {
        register_setting('notifish_group', 'notifish_options', array($this, 'sanitize'));
    }

    public function sanitize($input) {
        $new_input = array();
        if (isset($input['api_url'])) {
            $new_input['api_url'] = sanitize_text_field($input['api_url']);
        }
        if (isset($input['api_key'])) {
            $new_input['api_key'] = sanitize_text_field($input['api_key']);
        }
        if (isset($input['instance_uuid'])) {
            $new_input['instance_uuid'] = sanitize_text_field($input['instance_uuid']);
        }
        return $new_input;
    }

    public function add_meta_box() {
        add_meta_box(
            'notifish_meta_box',
            'Notifish',
            array($this, 'meta_box_callback'),
            'post'
        );
    }

   public function meta_box_callback($post) {
        wp_nonce_field('notifish_meta_box', 'notifish_meta_box_nonce');
        $value = get_post_meta($post->ID, '_notifish_meta_value_key', true);
        
        
        echo '<label for="notifish_send_notification_whatsapp">';
        echo 'Deseja compartilhar no WhatsApp?';
        echo '</label> ';
        echo '<input type="checkbox" id="notifish_send_notification_whatsapp" name="notifish_send_notification_whatsapp" value="1" />';
        
        // Exibe a mensagem caso o valor seja '1'
        if ($value === '1' || $value === 1 || $value === true) {
            echo '<p style="color: red;">A matéria já foi compartilhada no WhatsApp.</p>';
        }
    }



    public function send_message_on_publish($post_id) {
        if (!isset($_POST['notifish_meta_box_nonce'])) {
            return;
        }
        if (!wp_verify_nonce($_POST['notifish_meta_box_nonce'], 'notifish_meta_box')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $status = get_post_status($post_id);


        if ($status !== 'publish') {
            return; // Retorna se o status não for "publish"
        }

        $my_data = isset($_POST['notifish_send_notification_whatsapp']) ? sanitize_text_field($_POST['notifish_send_notification_whatsapp']) : '';
        update_post_meta($post_id, '_notifish_meta_value_key', $my_data);

        if ($my_data == '1' && get_post_status($post_id) == 'publish') {
            $this->send_message($post_id);
        }
    }

    private function send_message($post_id) {
        global $wpdb;
        $this->options = get_option('notifish_options');
        
        $post_title = html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8');
        $post_url = get_permalink($post_id) . '?utm_source=whatsapp';
        
        $instance_uuid = $this->options['instance_uuid'];

            $args = array(
                'body' => json_encode(array(
                    'identifier' => $post_id . ' ' . bloginfo('name') . ' - Wordpress',
                    'link' => true,
                    'typing' => 'composing',
                    'delay' => 1200,
                    'message' => "*$post_title* \n\n $post_url",
                )),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->options['api_key'],
                    'Accept' => 'application/json'
                )
            );

            $response = wp_remote_post($this->options['api_url'] . $instance_uuid . '/whatsapp/message/groups', $args);
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // Salvar request no banco de dados
            $wpdb->insert(
                "{$wpdb->prefix}notifish_requests",
                array(
                    'post_id' => $post_id,
                    'post_title' => $post_title,
                    'phone_number' => 'Grupo',
                    'status_code' => $status_code,
                    'user_id' => get_current_user_id(),
                    'user_name' => get_the_author_meta('display_name', get_current_user_id()),
                    'response' => $response_body,
                    'sent_at' => current_time('mysql')
                )
            );
    }

    public function create_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'notifish_requests';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_name varchar(255) NOT NULL,
            post_id bigint(20) NOT NULL,
            post_title text NOT NULL,
            phone_number varchar(255) NOT NULL,
            status_code int(3) NOT NULL,
            response text NOT NULL,
            sent_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            INDEX idx_post_id (post_id),
            INDEX idx_phone_number (phone_number),
            INDEX idx_status_code (status_code),
            INDEX idx_user_id (user_id),
            INDEX idx_sent_at (sent_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function requests_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'notifish_requests';
        
        if (isset($_POST['resend'])) {
            $id = intval($_POST['resend']);
            $request = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            if ($request) {
                $this->resend_message($request);
            }
        }
        
        // Consulta para obter os últimos 20 registros, ordenados por ID de forma decrescente
        $requests = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 20");
        
        echo '<div class="wrap"><h1>Requests Enviadas</h1><h4>Listando às últimas 20</h4>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Post ID</th><th>Título</th><th>Telefone</th><th>Status</th><th>Resposta</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
        foreach ($requests as $request) {
            echo '<tr>';
            echo '<td>' . $request->id . '</td>';
            echo '<td>' . $request->post_id . '</td>';
            echo '<td>' . $request->post_title . '</td>';
            echo '<td>' . $request->phone_number . '</td>';
            echo '<td>' . $request->status_code . '</td>';
            echo '<td>' . $request->response . '</td>';
            echo '<td>' . $request->sent_at . '</td>';
            echo '<td>';
            // if ($request->status_code != 200 && $request->status_code != 201) {
                echo '<form method="post"><button type="submit" name="resend" value="' . $request->id . '">Reenviar</button></form>';
            // }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private function resend_message($request) {
        $this->options = get_option('notifish_options');
        
        if (empty($this->options['api_url']) || empty($this->options['api_key']) || empty($this->options['instance_uuid'])) {
            return;
        }

        $post_title = html_entity_decode(get_the_title($request->post_id), ENT_QUOTES, 'UTF-8');
        $post_url = get_permalink($request->post_id) . '?utm_source=whatsapp';

        $args = array(
            'body' => json_encode(array(
                'identifier' => $request->post_id . ' ' . bloginfo('name') . ' - Wordpress resend',
                'link' => true,
                'typing' => 'composing',
                'delay' => 1200,
                'message' => "*$post_title* \n\n $post_url",
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->options['api_key'],
                'Accept' => 'application/json'
            )
        );

        $response = wp_remote_post($this->options['api_url'] . $this->options['instance_uuid'] . '/whatsapp/message/groups', $args);
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}notifish_requests",
            array(
                'user_id' => get_current_user_id(),
                'user_name' => get_the_author_meta('display_name', get_current_user_id()),
                'status_code' => $status_code,
                'response' => $response_body,
                'sent_at' => current_time('mysql')
            ),
            array('id' => $request->id)
        );
    }
}

if (is_admin()) {
    $notifish_plugin = new Notifish_Plugin();
}