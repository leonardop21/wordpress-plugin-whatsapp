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

        $api_endpoint = esc_url_raw($api_url . '/' . $instance_uuid . '/whatsapp/message/send-link-custom-preview/groups');
        
        $link_scraping = sprintf(
            '%s/wp-json/wp-api/v2/notifish/%d/%s',
            get_site_url(),
            $post_id,
            'k4fmowksmfwekfmwkomfeowfmweoimfweiofmwem'
        );


        // 1️ imagem destacada do post
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!empty($thumb_id)) {
            $image_url = wp_get_attachment_image_url($thumb_id, 'full');
        }

        // 2️ logo do site
        if (empty($image_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if (!empty($custom_logo_id)) {
                $image_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }

        // 3️ ícone do site (512px)
        if (empty($image_url)) {
            $image_url = get_site_icon_url(512);
        }


        $body_data = array(
            'recipient_phone' => '',
            'identifier' => absint($post_id) . ' ' . $blog_name . ' - Wordpress',
            'link' => esc_url_raw(get_permalink($post_id)),
            'message'    => $message_text,
            'title' => $post_title,
            'description' => $this->get_post_description($post_id),
            'scrapping' => true,
            'image_url' => $image_url,
            "link_scraping" => $link_scraping,
            "scraping" => true,
            "wp_post_id" => $post_id
        );

        // só envia image_url se existir
        if (!empty($image_url)) {
            $body_data['image_url'] = esc_url_raw($image_url);
        }
        
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

        $post_id = $request->post_id;
        
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

        $api_endpoint = esc_url_raw($api_url . '/' . $instance_uuid . '/whatsapp/message/send-link-custom-preview/groups');
        
        // 1️ imagem destacada do post
        $thumb_id = get_post_thumbnail_id($post_id);
        if (!empty($thumb_id)) {
            $image_url = wp_get_attachment_image_url($thumb_id, 'full');
        }

        // 2️ logo do site
        if (empty($image_url)) {
            $custom_logo_id = get_theme_mod('custom_logo');
            if (!empty($custom_logo_id)) {
                $image_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            }
        }

        // 3️ ícone do site (512px)
        if (empty($image_url)) {
            $image_url = get_site_icon_url(512);
        }


        $body_data = array(
            'recipient_phone' => '',
            'identifier' => absint($post_id) . ' ' . $blog_name . ' - Wordpress resend v2',
            'link' => esc_url_raw(get_permalink($post_id)),
            'message'    => $message_text,
            'title' => $post_title,
            'description' => $this->get_post_description($post_id),
            'scrapping' => false,
            'image_url' => $image_url,
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

    /**
     * Obtém a URL da imagem para o post (destaque, conteúdo ou logo).
     * Usa transient quando o thumbnail foi enviado na mesma requisição e ainda não está em meta.
     *
     * @param int $post_id ID do post
     * @return string URL da imagem ou string vazia
     */
    public function get_post_image_url($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        // Thumbnail definido na mesma requisição (admin/REST) antes do meta ser salvo
        $transient_key = 'notifish_thumbnail_id_' . $post_id;
        $attachment_id = get_transient($transient_key);
        if ($attachment_id && (int) $attachment_id > 0) {
            $url = wp_get_attachment_image_url((int) $attachment_id, 'full');
            delete_transient($transient_key);
            if ($url) {
                return $url;
            }
        }

        // Imagem de destaque do post (já salva em meta)
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
        if ($image_url) {
            return $image_url;
        }

        // Fallback: _thumbnail_id em meta (útil quando get_the_post_thumbnail_url falha por cache)
        $thumb_id = get_post_meta($post_id, '_thumbnail_id', true);
        if ($thumb_id && (int) $thumb_id > 0) {
            $url = wp_get_attachment_image_url((int) $thumb_id, 'full');
            if ($url) {
                return $url;
            }
        }

        // Fallback: primeira imagem no conteúdo do post
        $content = get_post_field('post_content', $post_id);
        if ($content && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m)) {
            $url = esc_url_raw($m[1]);
            if ($url) {
                return $url;
            }
        }

        // Fallback: scrape da URL do post (og:image ou imagem de destaque na página)
        $url = $this->get_post_image_url_by_scrape($post_id);
        if ($url) {
            return $url;
        }

        // Fallback: logo do site
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $url = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($url) {
                return $url;
            }
        }

        return '';
    }

    /**
     * Obtém a URL da imagem de destaque fazendo request na URL do post e extraindo do HTML (og:image ou thumbnail).
     *
     * @param int $post_id ID do post
     * @return string URL da imagem ou string vazia
     */
    private function get_post_image_url_by_scrape($post_id) {
        $post_url = get_permalink($post_id);
        if (!$post_url || !wp_http_validate_url($post_url)) {
            return '';
        }

        $response = wp_remote_get($post_url, array(
            'timeout'     => 10,
            'redirection' => 3,
            'user-agent'  => 'Notifish-WordPress-Plugin/1.0',
            'sslverify'   => true,
        ));

        if (is_wp_error($response)) {
            $this->logger->write('Scrape: falha ao acessar URL do post', [
                'post_id' => $post_id,
                'url' => $post_url,
                'error' => $response->get_error_message(),
            ]);
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return '';
        }

        $make_absolute = function ($url) {
            $url = trim($url);
            if (empty($url)) {
                return '';
            }
            if (wp_http_validate_url($url)) {
                return esc_url_raw($url);
            }
            $url = esc_url_raw($url);
            if (strpos($url, '/') === 0) {
                return rtrim(home_url(), '/') . $url;
            }
            return $url;
        };

        // 1) og:image (Open Graph) - a maioria dos temas e plugins de SEO preenchem
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/', $body, $m)) {
            $url = $make_absolute($m[1]);
            if ($url) {
                $this->logger->write('Scrape: imagem obtida via og:image', ['post_id' => $post_id, 'url' => $url]);
                return $url;
            }
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/', $body, $m)) {
            $url = $make_absolute($m[1]);
            if ($url) {
                $this->logger->write('Scrape: imagem obtida via og:image', ['post_id' => $post_id, 'url' => $url]);
                return $url;
            }
        }

        // 2) twitter:image
        if (preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/', $body, $m)) {
            $url = $make_absolute($m[1]);
            if ($url) {
                return $url;
            }
        }
        if (preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']/', $body, $m)) {
            $url = $make_absolute($m[1]);
            if ($url) {
                return $url;
            }
        }

        // 3) Classe comum de thumbnail do post (post-thumbnail, wp-post-image, attachment-post-thumbnail)
        if (preg_match('/<img[^>]+class=["\'][^"\']*(?:post-thumbnail|wp-post-image|attachment-post-thumbnail)[^"\']*["\'][^>]+src=["\']([^"\']+)["\']/', $body, $m)) {
            $url = $make_absolute($m[1]);
            if ($url) {
                $this->logger->write('Scrape: imagem obtida via classe thumbnail', ['post_id' => $post_id, 'url' => $url]);
                return $url;
            }
        }
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*(?:post-thumbnail|wp-post-image|attachment-post-thumbnail)/', $body, $m)) {
            $url = $make_absolute($m[1]);
            if ($url) {
                return $url;
            }
        }

        return '';
    }

    public function get_post_description($post_id, $max = 160)
    {
        $candidates = [
            get_post_field('post_excerpt', $post_id),
            wp_strip_all_tags(strip_shortcodes(get_post_field('post_content', $post_id))),
            get_bloginfo('description'),
            get_bloginfo('name'),
            $this->get_site_domain_name(),
        ];

        foreach ($candidates as $text) {
            if (!empty(trim($text))) {
                return mb_substr(trim($text), 0, $max);
            }
        }

        return 'NF';
    }


    private function get_site_domain_name()
    {
        $host = parse_url(get_bloginfo('url'), PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);

        return explode('.', $host)[0];
    }
    

    public function register_notifish_endpoint()
    {
        register_rest_route(
            'wp-api/v2',
            '/notifish/(?P<id>\d+)/(?P<key>[a-zA-Z0-9]{32,64})',
            [
                'methods'  => 'GET',
                'permission_callback' => '__return_true',
                'callback' => function (WP_REST_Request $request) {

                    /* =====================================================
                    * CONFIGURAÇÃO
                    * ===================================================== */
                    $VALID_KEY = 'k4fmowksmfwekfmwkomfeowfmweoimfweiofmwem';
                    $CACHE_TTL = 60; // segundos
                    $RATE_LIMIT_TTL = 30; // segundos (por IP)

                    /* =====================================================
                    * RATE LIMIT SIMPLES (por IP)
                    * ===================================================== */
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $rate_key = 'notifish_rl_' . md5($ip);

                    if (get_transient($rate_key)) {
                        return new WP_REST_Response([
                            'error' => 'Too many requests'
                        ], 429);
                    }

                    set_transient($rate_key, 1, $RATE_LIMIT_TTL);

                    /* =====================================================
                    * VALIDAÇÃO DA API KEY
                    * ===================================================== */
                    $key = (string) $request->get_param('key');

                    if (!hash_equals($VALID_KEY, $key)) {
                        error_log('Notifish: Invalid API key from IP ' . $ip);

                        return new WP_REST_Response([
                            'error' => 'Invalid API key'
                        ], 401);
                    }

                    /* =====================================================
                    * POST ID
                    * ===================================================== */
                    $post_id = absint($request->get_param('id'));
                    if (!$post_id) {
                        return new WP_REST_Response([
                            'error' => 'Invalid post ID'
                        ], 400);
                    }

                    /* =====================================================
                    * CACHE (ANTI FLOOD / PERFORMANCE)
                    * ===================================================== */
                    $cache_key = 'notifish_post_' . $post_id;
                    $cached = get_transient($cache_key);

                    if ($cached !== false) {
                        return new WP_REST_Response($cached, 200);
                    }

                    /* =====================================================
                    * POST
                    * ===================================================== */
                    $post = get_post($post_id);
                    if (!$post || $post->post_status !== 'publish') {
                        return new WP_REST_Response([
                            'error' => 'Post not found'
                        ], 404);
                    }

                    /* =====================================================
                    * FEATURED IMAGE
                    * ===================================================== */
                    $thumb_id = get_post_thumbnail_id($post_id);
                    $featured_image = $thumb_id
                        ? wp_get_attachment_image_url($thumb_id, 'full')
                        : null;

                    /* =====================================================
                    * RESPONSE
                    * ===================================================== */
                    $response = [
                        'post_id'        => $post_id,
                        'url'            => esc_url_raw(get_permalink($post_id)),
                        'featured_image' => $featured_image,
                    ];

                    set_transient($cache_key, $response, $CACHE_TTL);

                    return new WP_REST_Response($response, 200);
                }
            ]
        );
    }

}