(function($) {
    'use strict';
    $(document).ready(function() {
        if (typeof wzi_admin === 'undefined' || typeof wzi_admin.ajax_url === 'undefined' || typeof wzi_admin.nonce === 'undefined' || typeof wzi_admin.strings === 'undefined') {
            console.error('WZI Admin JS: wzi_admin object or its properties (ajax_url, nonce, strings) are NOT defined!');
            // No continuar si wzi_admin o sus propiedades esenciales no están definidos
            return;
        } else {
            console.log('WZI Admin JS loaded successfully with all required properties.');
        }

        // Manejador para el botón "Probar Conexión"
        $(document).on('click', '.wzi-test-connection', function(e) {
            e.preventDefault();
            var $button = $(this);
            var service = $button.data('service');
            var $spinner = $('<span class="spinner is-active" style="float: none; margin-left: 5px; vertical-align: middle;"></span>');
            var $messageSpan = $button.next('.wzi-connection-status-message');

            // Crear el span para mensajes si no existe
            if (!$messageSpan.length) {
                $messageSpan = $('<span class="wzi-connection-status-message" style="margin-left: 10px;"></span>');
                $button.after($messageSpan);
            }

            // Traducibles
            var testingStr = wzi_admin.strings.testing_connection || 'Testing connection...';
            var successStr = wzi_admin.strings.connection_success || 'Connection successful';
            var failedStr = wzi_admin.strings.connection_failed || 'Connection failed';

            // Mostrar mensaje de "Probando..." y spinner
            $messageSpan.text(testingStr).removeClass('success error').css('color', ''); // Reset color
            $button.prop('disabled', true);
            $spinner.insertAfter($button);

            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_test_connection', // La acción registrada en PHP
                    nonce: wzi_admin.nonce,
                    service: service
                },
                success: function(response) {
                    var message = '';
                    if (response.success) {
                        message = (response.data && response.data.message) ? response.data.message : successStr;
                        $messageSpan.text(message).addClass('success').removeClass('error').css('color', 'green');
                    } else {
                        message = (response.data && response.data.message) ? response.data.message : failedStr;
                        $messageSpan.text(message).addClass('error').removeClass('success').css('color', 'red');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var errorMessage = failedStr;
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                        errorMessage = jqXHR.responseJSON.data.message;
                    } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMessage = jqXHR.responseJSON.message;
                    } else if (errorThrown) {
                        errorMessage += ': ' + errorThrown;
                    }
                    $messageSpan.text(errorMessage).addClass('error').removeClass('success').css('color', 'red');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $spinner.remove();
                }
            });
        });

    });
})(jQuery);