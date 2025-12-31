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
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$api_url = isset($options['api_url']) ? rtrim($options['api_url'], '/') . '/' : '';
$api_key = isset($options['api_key']) ? $options['api_key'] : '';
$instance_uuid = isset($options['instance_uuid']) ? $options['instance_uuid'] : '';
$versao = isset($options['versao_notifish']) ? $options['versao_notifish'] : 'v1';
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

<?php 
// Garante que o jQuery está enfileirado
wp_enqueue_script('jquery'); 
// Define ajaxurl se não estiver definido
?>
<script type="text/javascript">
var ajaxurl = typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
var notifish_ajax = typeof notifish_ajax !== 'undefined' ? notifish_ajax : {
    ajaxurl: ajaxurl,
    nonce: '<?php echo wp_create_nonce('notifish_ajax_nonce'); ?>'
};
jQuery(document).ready(function($) {
    let qrcodeInterval;
    let statusInterval;
    
    function loadQRCode() {
        const currentStatus = $('#status-text').text();
        const currentColor = $('#status-text').css('color');
        if (currentStatus.includes('Online') && currentColor === 'rgb(0, 163, 42)') {
            return;
        }
        
        $('#qrcode-loading').show();
        $('#qrcode-image').hide();
        $('#qrcode-error').hide();
        
        $.ajax({
            url: (typeof notifish_ajax !== 'undefined' && notifish_ajax.ajaxurl) ? notifish_ajax.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>'),
            type: 'POST',
            data: {
                action: 'notifish_get_qrcode',
                nonce: (typeof notifish_ajax !== 'undefined' && notifish_ajax.nonce) ? notifish_ajax.nonce : ''
            },
            success: function(response) {
                $('#qrcode-loading').hide();
                
                if (response.success) {
                    // Verifica se já está conectado
                    if (response.data && (response.data.connected || response.data.status === 'WORKING' || (response.data.instance && response.data.instance.state === 'open'))) {
                        $('#qrcode-image').hide();
                        $('#qrcode-error').hide();
                        return;
                    }
                    
                    // Busca o QR code em diferentes formatos possíveis
                    let qrData = null;
                    
                    if (response.data && response.data.data) {
                        qrData = response.data.data;
                    } else if (response.data && typeof response.data === 'string' && response.data.length > 100) {
                        // Se data é uma string longa, provavelmente é o base64 direto
                        qrData = response.data;
                    }
                    
                    if (qrData) {
                        // Remove prefixo data:image se já existir
                        if (qrData.startsWith('data:image')) {
                            $('#qrcode-img').attr('src', qrData);
                        } else {
                            // Adiciona prefixo se não tiver
                            $('#qrcode-img').attr('src', 'data:image/png;base64,' + qrData);
                        }
                        $('#qrcode-image').show();
                        $('#qrcode-error').hide();
                    } else {
                        // Se não encontrou QR code, mostra erro
                        $('#qrcode-image').hide();
                        $('#qrcode-error').show();
                        $('#qrcode-error-message').text(response.data && response.data.error ? response.data.error : 'QR Code não disponível');
                    }
                } else {
                    $('#qrcode-image').hide();
                    $('#qrcode-error').show();
                    $('#qrcode-error-message').text(response.data || 'Erro ao carregar QR Code');
                }
            },
            error: function() {
                $('#qrcode-loading').hide();
                $('#qrcode-error').show();
                $('#qrcode-error-message').text('Erro ao comunicar com o servidor');
            }
        });
    }
    
    function checkSessionStatus() {
                $.ajax({
                    url: (typeof notifish_ajax !== 'undefined' && notifish_ajax.ajaxurl) ? notifish_ajax.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>'),
                    type: 'POST',
                    data: {
                        action: 'notifish_get_session_status',
                        nonce: (typeof notifish_ajax !== 'undefined' && notifish_ajax.nonce) ? notifish_ajax.nonce : ''
                    },
            success: function(response) {
                if (response.success && response.data) {
                    const session = response.data;
                    const state = session.state || session.status;
                    
                    if (state === 'open' || session.status === 'WORKING') {
                        $('#status-text').css({
                            'color': '#00a32a',
                            'font-weight': '600',
                            'font-size': '18px'
                        }).text('● Online');
                        $('#session-info').show();
                        
                        $('#instance-name').text(session.instanceName || 'N/A');
                        $('#instance-uuid').text(session.instance_tenant_uuid || '<?php echo esc_js($instance_uuid); ?>');
                        const versao = '<?php echo esc_js($versao); ?>';
                        $('#whatsapp-version').text(versao.toUpperCase());
                        
                        if (qrcodeInterval) {
                            clearInterval(qrcodeInterval);
                            qrcodeInterval = null;
                        }
                        if (statusInterval) {
                            clearInterval(statusInterval);
                            statusInterval = null;
                        }
                        
                        // Esconde completamente o container do QR code e a instrução quando online
                        $('#qrcode-container').hide();
                        $('#qrcode-instruction').hide();
                        $('#qrcode-image').hide();
                        $('#qrcode-error').hide();
                        $('#qrcode-loading').hide();
                        
                        return;
                    } else if (state === 'close' || session.status === 'SCAN_QR_CODE') {
                        $('#status-text').css({
                            'color': '#d63638',
                            'font-weight': '600'
                        }).text('Aguardando leitura do QR Code');
                        
                        // Mostra o container do QR code e a instrução quando não está online
                        $('#qrcode-container').show();
                        $('#qrcode-instruction').show();
                        
                        if (session.instanceName || session.instance_tenant_uuid) {
                            $('#session-info').show();
                            $('#instance-name').text(session.instanceName || 'N/A');
                            $('#instance-uuid').text(session.instance_tenant_uuid || '<?php echo esc_js($instance_uuid); ?>');
                            const versao = '<?php echo esc_js($versao); ?>';
                            $('#whatsapp-version').text(versao.toUpperCase());
                        } else {
                            $('#session-info').hide();
                        }
                        
                        if (!qrcodeInterval) {
                            qrcodeInterval = setInterval(loadQRCode, 10000);
                        }
                    } else {
                        $('#status-text').css({
                            'color': '#d63638',
                            'font-weight': '600'
                        }).text(session.status || 'Desconhecido');
                        $('#session-info').hide();
                        
                        // Mostra o container do QR code quando status é desconhecido
                        $('#qrcode-container').show();
                        $('#qrcode-instruction').show();
                    }
                }
            },
            error: function() {
                $('#status-text').css('color', 'red').text('Erro ao verificar status');
            }
        });
    }
    
    checkSessionStatus();
    
    setTimeout(function() {
        checkSessionStatus();
        
        const currentStatus = $('#status-text').text();
        const currentColor = $('#status-text').css('color');
        
        if (!currentStatus.includes('Online') || currentColor !== 'rgb(0, 163, 42)') {
            // Mostra o container quando não está online
            $('#qrcode-container').show();
            $('#qrcode-instruction').show();
            loadQRCode();
            qrcodeInterval = setInterval(loadQRCode, 10000);
            statusInterval = setInterval(checkSessionStatus, 5000);
        } else {
            // Esconde o container quando está online
            $('#qrcode-container').hide();
            $('#qrcode-instruction').hide();
        }
    }, 1000);
    
    $('#refresh-qrcode').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>Atualizando...');
        
        if (qrcodeInterval) {
            clearInterval(qrcodeInterval);
            qrcodeInterval = null;
        }
        if (statusInterval) {
            clearInterval(statusInterval);
            statusInterval = null;
        }
        
        checkSessionStatus();
        
        setTimeout(function() {
            const currentStatus = $('#status-text').text();
            const currentColor = $('#status-text').css('color');
            
            if (!currentStatus.includes('Online') || currentColor !== 'rgb(0, 163, 42)') {
                $('#qrcode-container').show();
                $('#qrcode-instruction').show();
                loadQRCode();
                qrcodeInterval = setInterval(loadQRCode, 10000);
                statusInterval = setInterval(checkSessionStatus, 5000);
            } else {
                $('#qrcode-container').hide();
                $('#qrcode-instruction').hide();
            }
            
            $btn.prop('disabled', false).html(originalText);
        }, 1000);
    });
    
    $('#restart-session').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>Reiniciando...');
        
                $.ajax({
                    url: (typeof notifish_ajax !== 'undefined' && notifish_ajax.ajaxurl) ? notifish_ajax.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>'),
                    type: 'POST',
                    data: {
                        action: 'notifish_restart_session',
                        nonce: (typeof notifish_ajax !== 'undefined' && notifish_ajax.nonce) ? notifish_ajax.nonce : ''
                    },
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    showMessage('Sessão reiniciada com sucesso!', 'success');
                    if (qrcodeInterval) {
                        clearInterval(qrcodeInterval);
                        qrcodeInterval = null;
                    }
                    if (statusInterval) {
                        clearInterval(statusInterval);
                        statusInterval = null;
                    }
                    
                    setTimeout(function() {
                        checkSessionStatus();
                        const currentStatus = $('#status-text').text();
                        const currentColor = $('#status-text').css('color');
                        if (!currentStatus.includes('Online') || currentColor !== 'rgb(0, 163, 42)') {
                            $('#qrcode-container').show();
                            $('#qrcode-instruction').show();
                            loadQRCode();
                            qrcodeInterval = setInterval(loadQRCode, 10000);
                            statusInterval = setInterval(checkSessionStatus, 5000);
                        } else {
                            $('#qrcode-container').hide();
                            $('#qrcode-instruction').hide();
                        }
                    }, 2000);
                } else {
                    showMessage('Erro ao reiniciar: ' + (response.data || 'Erro desconhecido'), 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                showMessage('Erro ao comunicar com o servidor', 'error');
            }
        });
    });
    
    $('#logout-session').on('click', function() {
        if (!confirm('Tem certeza que deseja desconectar a sessão do WhatsApp?')) {
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px;"></span>Desconectando...');
        
                $.ajax({
                    url: (typeof notifish_ajax !== 'undefined' && notifish_ajax.ajaxurl) ? notifish_ajax.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>'),
                    type: 'POST',
                    data: {
                        action: 'notifish_logout_session',
                        nonce: (typeof notifish_ajax !== 'undefined' && notifish_ajax.nonce) ? notifish_ajax.nonce : ''
                    },
            success: function(response) {
                $btn.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    showMessage('Sessão desconectada com sucesso!', 'success');
                    if (qrcodeInterval) {
                        clearInterval(qrcodeInterval);
                        qrcodeInterval = null;
                    }
                    if (statusInterval) {
                        clearInterval(statusInterval);
                        statusInterval = null;
                    }
                    
                    setTimeout(function() {
                        checkSessionStatus();
                        const currentStatus = $('#status-text').text();
                        const currentColor = $('#status-text').css('color');
                        if (!currentStatus.includes('Online') || currentColor !== 'rgb(0, 163, 42)') {
                            $('#qrcode-container').show();
                            $('#qrcode-instruction').show();
                            loadQRCode();
                            qrcodeInterval = setInterval(loadQRCode, 10000);
                            statusInterval = setInterval(checkSessionStatus, 5000);
                        } else {
                            $('#qrcode-container').hide();
                            $('#qrcode-instruction').hide();
                        }
                    }, 2000);
                } else {
                    showMessage('Erro ao desconectar: ' + (response.data || 'Erro desconhecido'), 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalText);
                showMessage('Erro ao comunicar com o servidor', 'error');
            }
        });
    });
    
    function showMessage(message, type) {
        const $msg = $('#action-message');
        const bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
        const borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
        const textColor = type === 'success' ? '#155724' : '#721c24';
        
        $msg.css({
            'background': bgColor,
            'border': '1px solid ' + borderColor,
            'color': textColor,
            'padding': '12px',
            'border-radius': '4px'
        }).html(message).fadeIn();
        
        setTimeout(function() {
            $msg.fadeOut();
        }, 5000);
    }
    
    $(window).on('beforeunload', function() {
        if (qrcodeInterval) clearInterval(qrcodeInterval);
        if (statusInterval) clearInterval(statusInterval);
    });
});
</script>

