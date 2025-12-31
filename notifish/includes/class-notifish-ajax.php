<?php
/**
 * AJAX handlers class for Notifish plugin
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Notifish_Ajax {
    private $options;
    private $logger;

    public function __construct($logger) {
        $this->logger = $logger;
        $this->options = get_option('notifish_options');
    }

    /**
     * Get QR code via AJAX
     *
     * @return void
     */
    public function get_qrcode() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }
        
        // Verifica nonce (opcional para compatibilidade, mas recomendado)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'notifish_ajax_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        $versao = isset($this->options['versao_notifish']) ? $this->options['versao_notifish'] : 'v1';
        
        if ($versao !== 'v2') {
            wp_send_json_error('API v2 não está habilitada');
            return;
        }
        
        $api_url = isset($this->options['api_url']) ? rtrim($this->options['api_url'], '/') : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $instance_uuid = isset($this->options['instance_uuid']) ? $this->options['instance_uuid'] : '';
        
        $url = $api_url . '/' . $instance_uuid . '/whatsapp/login';
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        );
        
        $response = wp_remote_post($url, $args);
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $data = json_decode($response_body, true);
        
        if ($status_code == 200) {
            // Verifica se já está conectado (formato Laravel: data.data.instance.state)
            if (isset($data['data']['instance']['state']) && $data['data']['instance']['state'] === 'open') {
                wp_send_json_success(array(
                    'status' => 'WORKING',
                    'connected' => true,
                    'state' => 'open',
                    'instance' => $data['data']['instance']
                ));
                return;
            }
            
            // Verifica formato alternativo: data.instance.state
            if (isset($data['instance']['state']) && $data['instance']['state'] === 'open') {
                wp_send_json_success(array(
                    'status' => 'WORKING',
                    'connected' => true,
                    'state' => 'open',
                    'instance' => $data['instance']
                ));
                return;
            }
            
            // Busca o QR code em diferentes formatos possíveis
            $qrcode_base64 = null;
            
            // Formato Laravel: data.data.base64 (com prefixo data:image já incluído)
            if (isset($data['data']['base64'])) {
                $qrcode_base64 = $data['data']['base64'];
            }
            // Formato alternativo: data.base64
            elseif (isset($data['base64'])) {
                $qrcode_base64 = $data['base64'];
            }
            // Formato WAHA direto: data.data (string base64 sem prefixo)
            elseif (isset($data['data']) && is_string($data['data']) && !empty($data['data']) && strlen($data['data']) > 100) {
                $qrcode_base64 = $data['data'];
            }
            // Formato alternativo: data.data.data
            elseif (isset($data['data']['data']) && is_string($data['data']['data'])) {
                $qrcode_base64 = $data['data']['data'];
            }
            // Formato qrcode ou qr_code
            elseif (isset($data['qrcode'])) {
                $qrcode_base64 = $data['qrcode'];
            }
            elseif (isset($data['qr_code'])) {
                $qrcode_base64 = $data['qr_code'];
            }
            
            if ($qrcode_base64) {
                // Remove espaços e quebras de linha
                $qrcode_base64 = trim($qrcode_base64);
                
                // Se não tem prefixo data URI, adiciona
                if (strpos($qrcode_base64, 'data:image') !== 0) {
                    $qrcode_base64 = 'data:image/png;base64,' . $qrcode_base64;
                }
                
                wp_send_json_success(array(
                    'data' => $qrcode_base64,
                    'mimetype' => 'image/png'
                ));
                return;
            }
            
            // Se não encontrou QR code mas status é 200, pode ser que a sessão esteja em estado intermediário
            wp_send_json_error('QR Code não encontrado na resposta da API. Resposta: ' . substr($response_body, 0, 200));
        } else {
            $error_data = json_decode($response_body, true);
            if (isset($error_data['message'])) {
                wp_send_json_error($error_data['message']);
            } else {
                wp_send_json_error('Erro ao obter QR Code (HTTP ' . $status_code . '): ' . substr($response_body, 0, 200));
            }
        }
    }

    /**
     * Get session status via AJAX
     *
     * @return void
     */
    public function get_session_status() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }
        
        // Verifica nonce (opcional para compatibilidade, mas recomendado)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'notifish_ajax_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        $versao = isset($this->options['versao_notifish']) ? $this->options['versao_notifish'] : 'v1';
        
        if ($versao !== 'v2') {
            wp_send_json_error('API v2 não está habilitada');
            return;
        }
        
        $api_url = isset($this->options['api_url']) ? rtrim($this->options['api_url'], '/') : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $instance_uuid = isset($this->options['instance_uuid']) ? $this->options['instance_uuid'] : '';
        
        $url = $api_url . '/' . $instance_uuid . '/whatsapp/status';
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ),
            'timeout' => 15
        );
        
        $response = wp_remote_get($url, $args);
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        if ($status_code == 200) {
            $data = json_decode($response_body, true);
            
            if (isset($data['instance'])) {
                $instance = $data['instance'];
                $state = isset($instance['state']) ? $instance['state'] : 'UNKNOWN';
                
                wp_send_json_success(array(
                    'status' => ($state === 'open') ? 'WORKING' : (($state === 'close') ? 'SCAN_QR_CODE' : 'UNKNOWN'),
                    'state' => $state,
                    'instanceName' => isset($instance['instanceName']) ? $instance['instanceName'] : '',
                    'instance_tenant_uuid' => isset($instance['instance_tenant_uuid']) ? $instance['instance_tenant_uuid'] : ''
                ));
                return;
            }
            
            if (isset($data['data']['instance'])) {
                $instance = $data['data']['instance'];
                $state = isset($instance['state']) ? $instance['state'] : 'UNKNOWN';
                
                wp_send_json_success(array(
                    'status' => ($state === 'open') ? 'WORKING' : (($state === 'close') ? 'SCAN_QR_CODE' : 'UNKNOWN'),
                    'state' => $state,
                    'instanceName' => isset($instance['instanceName']) ? $instance['instanceName'] : '',
                    'instance_tenant_uuid' => isset($instance['instance_tenant_uuid']) ? $instance['instance_tenant_uuid'] : ''
                ));
                return;
            }
            
            wp_send_json_error('Dados da sessão não encontrados');
        } else {
            $error_data = json_decode($response_body, true);
            if (isset($error_data['message'])) {
                wp_send_json_error($error_data['message']);
            } else {
                wp_send_json_error('Erro ao obter status: ' . $response_body);
            }
        }
    }

    /**
     * Restart session via AJAX
     *
     * @return void
     */
    public function restart_session() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }
        
        // Verifica nonce (opcional para compatibilidade, mas recomendado)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'notifish_ajax_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        $versao = isset($this->options['versao_notifish']) ? $this->options['versao_notifish'] : 'v1';
        
        if ($versao !== 'v2') {
            wp_send_json_error('API v2 não está habilitada');
            return;
        }
        
        $api_url = isset($this->options['api_url']) ? rtrim($this->options['api_url'], '/') : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $instance_uuid = isset($this->options['instance_uuid']) ? $this->options['instance_uuid'] : '';
        
        if (empty($api_url) || empty($api_key) || empty($instance_uuid)) {
            wp_send_json_error('Configurações não encontradas');
            return;
        }
        
        $url = $api_url . '/' . $instance_uuid . '/whatsapp/restart';
        
        $args = array(
            'method' => 'GET',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        if ($status_code == 200 || $status_code == 201) {
            $data = json_decode($response_body, true);
            if (isset($data['status']) && $data['status'] === 'success') {
                wp_send_json_success('Sessão reiniciada com sucesso');
            } else {
                wp_send_json_success('Sessão reiniciada');
            }
        } else {
            $error_data = json_decode($response_body, true);
            wp_send_json_error(isset($error_data['message']) ? $error_data['message'] : 'Erro ao reiniciar sessão');
        }
    }

    /**
     * Logout session via AJAX
     *
     * @return void
     */
    public function logout_session() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }
        
        // Verifica nonce (opcional para compatibilidade, mas recomendado)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'notifish_ajax_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }
        
        $versao = isset($this->options['versao_notifish']) ? $this->options['versao_notifish'] : 'v1';
        
        if ($versao !== 'v2') {
            wp_send_json_error('API v2 não está habilitada');
            return;
        }
        
        $api_url = isset($this->options['api_url']) ? rtrim($this->options['api_url'], '/') : '';
        $api_key = isset($this->options['api_key']) ? $this->options['api_key'] : '';
        $instance_uuid = isset($this->options['instance_uuid']) ? $this->options['instance_uuid'] : '';
        
        if (empty($api_url) || empty($api_key) || empty($instance_uuid)) {
            wp_send_json_error('Configurações não encontradas');
            return;
        }
        
        $url = $api_url . '/' . $instance_uuid . '/whatsapp/logout';
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_request($url, $args);
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        if ($status_code == 200 || $status_code == 201) {
            $data = json_decode($response_body, true);
            if (isset($data['status']) && $data['status'] === 'success') {
                wp_send_json_success('Sessão desconectada com sucesso');
            } else {
                wp_send_json_success('Sessão desconectada');
            }
        } else {
            $error_data = json_decode($response_body, true);
            wp_send_json_error(isset($error_data['message']) ? $error_data['message'] : 'Erro ao desconectar sessão');
        }
    }
}

