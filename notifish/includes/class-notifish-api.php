<?php
/**
 * API communication class for Notifish plugin
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Notifish_API {
    private $options;
    private $logger;
    private $database;

    public function __construct($logger, $database) {
        $this->options = get_option('notifish_options');
        $this->logger = $logger;
        $this->database = $database;
    }

    /**
     * Get friendly message based on status code
     *
     * @param int $status_code HTTP status code
     * @param string $response_body Response body
     * @return string
     */
    public function get_friendly_message($status_code, $response_body) {
        switch ($status_code) {
            case 200:
            case 201:
                return 'Mensagem enviada';
            case 401:
                return 'Não autorizado';
            case 403:
                return 'Proibido';
            case 404:
                return 'Não encontrado';
            case 500:
                return 'Erro interno';
            default:
                $data = json_decode($response_body, true);
                if (isset($data['message'])) {
                    return $data['message'];
                }
                return $response_body;
        }
    }

    /**
     * Send message via API v1
     *
     * @param int $post_id Post ID
     * @return void
     */
    public function send_message_v1($post_id) {
        $this->logger->write("=== INÍCIO: send_message_v1 ===", ['post_id' => $post_id]);
        
        error_log("Notifish: Usando API v1 para post ID: " . $post_id);
        
        if (empty($this->options['api_url']) || empty($this->options['api_key']) || empty($this->options['instance_uuid'])) {
            $this->logger->write("ERRO: Configurações não encontradas - ABORTANDO", [
                'api_url' => isset($this->options['api_url']) ? 'OK' : 'FALTANDO',
                'api_key' => isset($this->options['api_key']) ? 'OK' : 'FALTANDO',
                'instance_uuid' => isset($this->options['instance_uuid']) ? 'OK' : 'FALTANDO'
            ]);
            return;
        }
        
        $post_title = sanitize_text_field(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'));
        $post_url = esc_url_raw(get_permalink($post_id) . '?utm_source=whatsapp');
        $instance_uuid = sanitize_text_field($this->options['instance_uuid']);
        $blog_name = sanitize_text_field(get_bloginfo('name'));
        
        $this->logger->write("Dados do post preparados", [
            'post_title' => $post_title,
            'post_url' => $post_url,
            'instance_uuid' => $instance_uuid
        ]);

        $body_data = array(
            'identifier' => absint($post_id) . ' ' . $blog_name . ' - Wordpress',
            'link' => true,
            'typing' => 'composing',
            'delay' => 1200,
            'message' => "*" . $post_title . "* \n\n " . $post_url,
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->options['api_key'],
            'Accept' => 'application/json'
        );

        $api_url_base = rtrim($this->options['api_url'], '/');
        $api_endpoint = $api_url_base . '/' . $instance_uuid . '/whatsapp/message/groups';
        
        $this->logger->write("PREPARANDO ENVIO - v1", [
            'url_montada' => $api_endpoint,
            'headers' => $headers,
            'body' => $body_data
        ]);
        
        $response = wp_remote_post($api_endpoint, array(
            'body' => json_encode($body_data),
            'headers' => $headers
        ));
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $is_wp_error = is_wp_error($response);

        $this->logger->write("RESPOSTA DA API - v1", [
            'status_code' => $status_code,
            'is_wp_error' => $is_wp_error,
            'response_body' => $response_body
        ]);

        $friendly_message = $this->get_friendly_message($status_code, $response_body);
        
        $this->database->save_request(array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'phone_number' => 'Grupo',
            'status_code' => $status_code,
            'user_id' => get_current_user_id(),
            'user_name' => sanitize_text_field(get_the_author_meta('display_name', get_current_user_id())),
            'response' => $friendly_message
        ));
        
        $this->logger->write("=== FIM: send_message_v1 ===");
    }

    /**
     * Send message via API v2
     *
     * @param int $post_id Post ID
     * @return void
     */
    public function send_message_v2($post_id) {
        $this->logger->write("=== INÍCIO: send_message_v2 ===", ['post_id' => $post_id]);
        
        error_log("Notifish: Usando API v2 para post ID: " . $post_id);
        
        $api_url = isset($this->options['api_url']) ? rtrim($this->options['api_url'], '/') : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $instance_uuid = isset($this->options['instance_uuid']) ? $this->options['instance_uuid'] : '';
        
        if (empty($api_url) || empty($api_key) || empty($instance_uuid)) {
            $this->logger->write("ERRO: Configurações não encontradas - ABORTANDO");
            return;
        }
        
        $post_title = sanitize_text_field(html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'));
        $post_url = esc_url_raw(get_permalink($post_id) . '?utm_source=whatsapp');
        $blog_name = sanitize_text_field(get_bloginfo('name'));
        $message_text = "*" . $post_title . "* \n\n " . $post_url;

        $this->logger->write("Dados do post preparados - v2", [
            'post_title' => $post_title,
            'post_url' => $post_url
        ]);

        $api_endpoint = esc_url_raw($api_url . '/' . $instance_uuid . '/whatsapp/message/groups');
        
        $body_data = array(
            'identifier' => absint($post_id) . ' ' . $blog_name . ' - Wordpress',
            'message' => $message_text,
            'linkPreview' => true,
            'delay' => 0
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        );

        $this->logger->write("PREPARANDO ENVIO - v2", [
            'url_montada' => $api_endpoint,
            'headers' => $headers,
            'body' => $body_data
        ]);

        $response = wp_remote_post($api_endpoint, array(
            'body' => json_encode($body_data, JSON_UNESCAPED_UNICODE),
            'headers' => $headers
        ));
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $is_wp_error = is_wp_error($response);

        $this->logger->write("RESPOSTA DA API - v2", [
            'status_code' => $status_code,
            'is_wp_error' => $is_wp_error,
            'response_body' => $response_body
        ]);

        $friendly_message = $this->get_friendly_message($status_code, $response_body);
        
        $this->database->save_request(array(
            'post_id' => $post_id,
            'post_title' => $post_title,
            'phone_number' => 'Grupo',
            'status_code' => $status_code,
            'user_id' => get_current_user_id(),
            'user_name' => sanitize_text_field(get_the_author_meta('display_name', get_current_user_id())),
            'response' => $friendly_message
        ));
        
        $this->logger->write("=== FIM: send_message_v2 ===");
    }

    /**
     * Resend message via API v1
     *
     * @param object $request Request object
     * @return void
     */
    public function resend_message_v1($request) {
        $this->logger->write("=== INÍCIO: resend_message_v1 ===", [
            'request_id' => $request->id,
            'post_id' => $request->post_id
        ]);
        
        if (empty($this->options['api_url']) || empty($this->options['api_key']) || empty($this->options['instance_uuid'])) {
            $this->logger->write("ERRO: Configurações não encontradas - ABORTANDO REENVIO");
            return;
        }

        $post_title = sanitize_text_field(html_entity_decode(get_the_title($request->post_id), ENT_QUOTES, 'UTF-8'));
        $post_url = esc_url_raw(get_permalink($request->post_id) . '?utm_source=whatsapp');
        $blog_name = sanitize_text_field(get_bloginfo('name'));

        $body_data = array(
            'identifier' => absint($request->post_id) . ' ' . $blog_name . ' - Wordpress resend',
            'link' => true,
            'typing' => 'composing',
            'delay' => 1200,
            'message' => "*" . $post_title . "* \n\n " . $post_url,
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->options['api_key'],
            'Accept' => 'application/json'
        );

        $api_url_base = rtrim($this->options['api_url'], '/');
        $api_endpoint = esc_url_raw($api_url_base . '/' . $this->options['instance_uuid'] . '/whatsapp/message/groups');
        
        $this->logger->write("PREPARANDO REENVIO - v1", [
            'url_montada' => $api_endpoint,
            'headers' => $headers,
            'body' => $body_data
        ]);

        $response = wp_remote_post($api_endpoint, array(
            'body' => json_encode($body_data),
            'headers' => $headers
        ));
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $is_wp_error = is_wp_error($response);

        $this->logger->write("RESPOSTA DA API - REENVIO v1", [
            'status_code' => $status_code,
            'is_wp_error' => $is_wp_error,
            'response_body' => $response_body
        ]);

        $friendly_message = $this->get_friendly_message($status_code, $response_body);
        
        $this->database->save_request(array(
            'post_id' => $request->post_id,
            'post_title' => $post_title,
            'phone_number' => 'Grupo',
            'status_code' => $status_code,
            'user_id' => get_current_user_id(),
            'user_name' => sanitize_text_field(get_the_author_meta('display_name', get_current_user_id())),
            'response' => $friendly_message
        ));
        
        $this->logger->write("=== FIM: resend_message_v1 ===");
    }

    /**
     * Resend message via API v2
     *
     * @param object $request Request object
     * @return void
     */
    public function resend_message_v2($request) {
        $this->logger->write("=== INÍCIO: resend_message_v2 ===", [
            'request_id' => $request->id,
            'post_id' => $request->post_id
        ]);
        
        $api_url = isset($this->options['api_url']) ? rtrim($this->options['api_url'], '/') : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $instance_uuid = isset($this->options['instance_uuid']) ? $this->options['instance_uuid'] : '';
        
        if (empty($api_url) || empty($api_key) || empty($instance_uuid)) {
            $this->logger->write("ERRO: Configurações não encontradas - ABORTANDO REENVIO");
            return;
        }

        $post_title = sanitize_text_field(html_entity_decode(get_the_title($request->post_id), ENT_QUOTES, 'UTF-8'));
        $post_url = esc_url_raw(get_permalink($request->post_id) . '?utm_source=whatsapp');
        $blog_name = sanitize_text_field(get_bloginfo('name'));
        $message_text = "*" . $post_title . "* \n\n " . $post_url;

        $api_endpoint = esc_url_raw($api_url . '/' . $instance_uuid . '/whatsapp/message/groups');
        
        $body_data = array(
            'identifier' => absint($request->post_id) . ' ' . $blog_name . ' - Wordpress resend',
            'message' => $message_text,
            'linkPreview' => true,
            'delay' => 0
        );
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json'
        );

        $this->logger->write("PREPARANDO REENVIO - v2", [
            'url_montada' => $api_endpoint,
            'headers' => $headers,
            'body' => $body_data
        ]);

        $response = wp_remote_post($api_endpoint, array(
            'body' => json_encode($body_data, JSON_UNESCAPED_UNICODE),
            'headers' => $headers
        ));
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $is_wp_error = is_wp_error($response);

        $this->logger->write("RESPOSTA DA API - REENVIO v2", [
            'status_code' => $status_code,
            'is_wp_error' => $is_wp_error,
            'response_body' => $response_body
        ]);

        $friendly_message = $this->get_friendly_message($status_code, $response_body);
        
        $this->database->save_request(array(
            'post_id' => $request->post_id,
            'post_title' => $post_title,
            'phone_number' => 'Grupo',
            'status_code' => $status_code,
            'user_id' => get_current_user_id(),
            'user_name' => sanitize_text_field(get_the_author_meta('display_name', get_current_user_id())),
            'response' => $friendly_message
        ));
        
        $this->logger->write("=== FIM: resend_message_v2 ===");
    }
}

