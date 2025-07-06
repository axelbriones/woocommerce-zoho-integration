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
                    action: 'wzi_test_api_connection', // La acción registrada en PHP
                    nonce: wzi_admin.nonce,
                    service: service
                },
                success: function(response) {
                    if (response.success) {
                        var message = successStr;
                        if (response.data && response.data.message) {
                            message = response.data.message; // Usar el mensaje específico del backend
                        }
                        $messageSpan.text(message).addClass('success').removeClass('error').css('color', 'green');
                    } else {
                        var message = failedStr;
                        if (response.data && response.data.message) {
                            message = response.data.message; // Usar el mensaje específico del backend
                        }
                        $messageSpan.text(message).addClass('error').removeClass('success').css('color', 'red');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var errorMessage = failedStr;
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                        errorMessage = jqXHR.responseJSON.data.message; // Usar mensaje específico del backend
                    } else if (jqXHR.responseJSON && jqXHR.responseJSON.message) { // A veces el mensaje está directamente en .message
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

        // Ocultar Client Secret si se intenta cambiar el tipo de campo
        // Esto se aplica en la pestaña de Configuración -> API
        var clientSecretInput = $('input[name="wzi_api_settings[client_secret]"]');
        if (clientSecretInput.length > 0) {
            // Guardar el valor original para evitar que se borre si el usuario juega con el tipo
            var originalSecretValue = clientSecretInput.val();

            if (originalSecretValue && originalSecretValue !== '') {
                 // Si hay un valor, no queremos que se revele fácilmente.
                 // Podríamos poner un placeholder o simplemente asegurarnos de que siempre sea password.
                 // Por ahora, nos enfocaremos en revertir el cambio de tipo.
            }

            // Usar MutationObserver para detectar cambios en el atributo 'type'
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'type') {
                        if (clientSecretInput.attr('type') === 'text' && clientSecretInput.val() !== '') {
                            // Si se cambia a texto y hay un valor (podría ser el original o uno nuevo)
                            // lo revertimos a password.
                            // Esto es una medida disuasoria, no seguridad infalible del lado del cliente.
                            setTimeout(function() { // setTimeout para permitir que el navegador procese el cambio momentáneamente si es necesario
                                clientSecretInput.attr('type', 'password');
                                // Opcionalmente, restaurar el valor original si se considera que el cambio a text lo pudo haber blanqueado
                                // clientSecretInput.val(originalSecretValue);
                                console.log('Client Secret field type change to "text" reverted to "password".');
                            }, 0);
                        }
                    }
                });
            });

            observer.observe(clientSecretInput[0], {
                attributes: true // Observar cambios en atributos
            });

            // También, un event listener para 'input' o 'change' podría ser útil si el valor se borra
            // al cambiar el tipo en algunos navegadores, aunque el MutationObserver debería ser suficiente para el tipo.
            clientSecretInput.on('focus', function() {
                // Quizás mostrar temporalmente los últimos 4 caracteres si se desea, pero es más complejo.
                // Por ahora, mantenerlo simple.
            });
            clientSecretInput.on('blur', function() {
                // Asegurar que si el campo está vacío y pierde foco, sigue siendo password
                if (clientSecretInput.val() === '') {
                    clientSecretInput.attr('type', 'password');
                }
            });
        }

    });
})(jQuery);