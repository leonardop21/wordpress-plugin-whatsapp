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
        const field = document.getElementById(fieldId);
        const button = field.nextElementSibling;
        
        if (field.type === 'password') {
            field.type = 'text';
            button.textContent = '🙈';
        } else {
            field.type = 'password';
            button.textContent = '👁️';
        }
    };
})(jQuery);
