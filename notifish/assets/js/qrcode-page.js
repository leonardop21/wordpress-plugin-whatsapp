/**
 * QR Code page JavaScript
 *
 * @package Notifish
 * @since 2.0.0
 */

(function($) {
    'use strict';

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
            url: notifishQRCode.ajaxurl,
            type: 'POST',
            data: {
                action: 'notifish_get_qrcode',
                nonce: notifishQRCode.nonce
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
            url: notifishQRCode.ajaxurl,
            type: 'POST',
            data: {
                action: 'notifish_get_session_status',
                nonce: notifishQRCode.nonce
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
                        $('#instance-uuid').text(session.instance_tenant_uuid || notifishQRCode.instanceUuid);
                        $('#whatsapp-version').text(notifishQRCode.versao.toUpperCase());
                        
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
                            $('#instance-uuid').text(session.instance_tenant_uuid || notifishQRCode.instanceUuid);
                            $('#whatsapp-version').text(notifishQRCode.versao.toUpperCase());
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
            url: notifishQRCode.ajaxurl,
            type: 'POST',
            data: {
                action: 'notifish_restart_session',
                nonce: notifishQRCode.nonce
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
            url: notifishQRCode.ajaxurl,
            type: 'POST',
            data: {
                action: 'notifish_logout_session',
                nonce: notifishQRCode.nonce
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
})(jQuery);
