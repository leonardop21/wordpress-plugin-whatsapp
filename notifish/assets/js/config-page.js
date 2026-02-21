/**
 * Config page JavaScript
 *
 * @package Notifish
 * @since 2.0.0
 */

(function($) {
    'use strict';

    // Dismiss credentials notice
    $('.notifish-credentials-notice').on('click', '.notice-dismiss', function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'notifish_dismiss_credentials_notice',
                nonce: notifishConfig.nonce
            }
        });
    });

    // Toggle password visibility
    window.togglePassword = function(fieldId) {
        var field = document.getElementById(fieldId);
        var button = field.nextElementSibling;
        if (field.type === 'password') {
            field.type = 'text';
            button.textContent = '🙈';
        } else {
            field.type = 'password';
            button.textContent = '👁️';
        }
    };

    // Color picker
    if ($('.notifish-color-picker').length) {
        $('.notifish-color-picker').wpColorPicker();
    }

    // Logo upload (max 200kb, PNG or WebP)
    var logoFrame;
    $('#notifish-select-logo').on('click', function(e) {
        e.preventDefault();
        if (logoFrame) {
            logoFrame.open();
            return;
        }
        logoFrame = wp.media({
            title: 'Selecionar logo',
            button: { text: 'Usar esta imagem' },
            multiple: false,
            library: { type: 'image' }
        });
        logoFrame.on('select', function() {
            var attachment = logoFrame.state().get('selection').first().toJSON();
            var maxBytes = (notifishConfig.logoMaxKb || 200) * 1024;
            var allowed = notifishConfig.logoAllowedTypes || ['image/png', 'image/webp'];
            if (allowed.indexOf(attachment.mime) === -1) {
                alert('Use apenas PNG ou WebP.');
                return;
            }
            if (attachment.filesizeInBytes && attachment.filesizeInBytes > maxBytes) {
                alert('A imagem deve ter no máximo ' + (maxBytes / 1024) + ' KB.');
                return;
            }
            $('#midia_social_logo_url').val(attachment.url);
            $('#notifish-logo-preview').html('<img src="' + attachment.url + '" alt="Logo" style="max-width:120px;max-height:60px;border:1px solid #ccc;" />');
            $('#notifish-remove-logo').show();
        });
        logoFrame.open();
    });
    $('#notifish-remove-logo').on('click', function() {
        $('#midia_social_logo_url').val('');
        $('#notifish-logo-preview').empty();
        $(this).hide();
    });
})(jQuery);
