<?php
/**
 * QR Code / WhatsApp Status page view
 *
 * @package Notifish
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent direct access
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'notifish'));
}

$api_url = isset($options['api_url']) ? rtrim($options['api_url'], '/') . '/' : '';
$api_key = isset($options['api_key']) ? $options['api_key'] : '';
$instance_uuid = isset($options['instance_uuid']) ? $options['instance_uuid'] : '';
$versao = Notifish::detect_api_version();
?>
<div class="wrap">
    <h1>WhatsApp Status</h1>
    <p id="qrcode-instruction">Escaneie o QR code abaixo com seu WhatsApp para conectar a sessão.</p>
    
    <div id="qrcode-container" style="text-align: center; margin: 30px 0; padding: 30px; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div id="qrcode-loading" style="padding: 40px;">
            <span class="spinner is-active" style="float: none; margin: 0 auto; display: block;"></span>
            <p style="margin-top: 15px; color: #666; font-size: 14px;">Carregando QR Code...</p>
        </div>
        <div id="qrcode-image" style="display: none;">
            <img id="qrcode-img" src="" alt="QR Code" style="max-width: 400px; border: 2px solid #ddd; padding: 15px; background: white; border-radius: 8px;" />
            <p style="margin-top: 15px; color: #666; font-size: 14px;">Escaneie este QR code com seu WhatsApp</p>
        </div>
        <div id="qrcode-error" style="display: none; color: #d63638; padding: 20px; background: #fef7f7; border: 1px solid #f5c2c7; border-radius: 4px;">
            <p id="qrcode-error-message" style="margin: 0;"></p>
        </div>
    </div>
    
    <div id="session-status" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border-left: 4px solid #2271b1; border-radius: 4px;">
        <h3 style="margin-top: 0;">Status da Sessão</h3>
        <p style="font-size: 16px; margin-bottom: 15px;">
            <strong>Status:</strong> 
            <span id="status-text" style="font-weight: 600; font-size: 16px;">Verificando...</span>
        </p>
        <div id="session-info" style="display: none; padding: 15px; background: #fff; border-radius: 4px; margin-top: 15px;">
            <p style="margin: 5px 0;"><strong>Nome da Instância:</strong> <span id="instance-name"></span></p>
            <p style="margin: 5px 0;"><strong>UUID da Instância:</strong> <span id="instance-uuid" style="font-family: monospace; font-size: 12px;"></span></p>
            <p style="margin: 5px 0;"><strong>Versão WhatsApp:</strong> <span id="whatsapp-version"></span></p>
        </div>
    </div>
    
    <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <button type="button" id="refresh-qrcode" class="button button-primary">
            <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
            Atualizar QR Code
        </button>
        <button type="button" id="restart-session" class="button button-secondary">
            <span class="dashicons dashicons-update" style="vertical-align: middle; margin-right: 5px;"></span>
            Reiniciar Sessão
        </button>
        <button type="button" id="logout-session" class="button" style="background: #d63638; color: #fff; border-color: #d63638;">
            <span class="dashicons dashicons-no-alt" style="vertical-align: middle; margin-right: 5px;"></span>
            Desconectar
        </button>
    </div>
    
    <div id="action-message" style="margin-top: 15px; padding: 12px; border-radius: 4px; display: none;"></div>
</div>
