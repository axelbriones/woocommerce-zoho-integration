/**
 * JavaScript for handling Zoho synchronization tasks.
 */
// Contenido para assets/js/sync.js
(function($) {
    'use strict';

    $(function() {
        console.log('WooCommerce Zoho Integration - Sync JS loaded and ready.');

        // Manejador para los botones de sincronización manual
        $(document).on('click', '.wzi-sync-now', function(e) {
            e.preventDefault();
            var $button = $(this);
            var syncType = $button.data('sync-type');
            var originalButtonText = $button.html();

            // Asegurarse de que wzi_admin y sus propiedades necesarias están disponibles
            if (typeof wzi_admin === 'undefined' || typeof wzi_admin.ajax_url === 'undefined' || typeof wzi_admin.nonce === 'undefined') {
                alert('Error: Admin script data (wzi_admin) not found. Cannot proceed.');
                console.error('WZI Sync JS: wzi_admin object or its properties (ajax_url, nonce) are NOT defined!');
                return;
            }

            var $spinner = $('<span class="spinner is-active" style="float: none; margin-left: 5px; vertical-align: middle;"></span>');
            // Intentar encontrar un span de mensaje específico para este grupo de botones o crearlo
            var $buttonContainer = $button.closest('.action-buttons'); // Asumiendo que los botones están en un div con esta clase
            var $messageSpan = $buttonContainer.find('.wzi-sync-message');

            if (!$messageSpan.length) {
                $messageSpan = $('<span class="wzi-sync-message" style="display: block; margin-top: 10px; clear: both;"></span>');
                // Añadir el span después del contenedor de botones o al final del contenedor padre más cercano si es más apropiado
                if ($buttonContainer.length) {
                    $buttonContainer.after($messageSpan);
                } else {
                    $button.parent().append($messageSpan);
                }
            }

            // Usar cadenas traducibles si están disponibles, sino fallbacks
            var confirmSyncStr = (wzi_admin.strings && wzi_admin.strings.confirm_sync) ? wzi_admin.strings.confirm_sync : 'Are you sure you want to start synchronization?';
            var syncingStr = (wzi_admin.strings && wzi_admin.strings.syncing) ? wzi_admin.strings.syncing : 'Syncing...';
            var syncCompleteStr = (wzi_admin.strings && wzi_admin.strings.sync_complete) ? wzi_admin.strings.sync_complete : 'Synchronization process started. Check logs for details.';
            var syncErrorStr = (wzi_admin.strings && wzi_admin.strings.sync_error) ? wzi_admin.strings.sync_error : 'Synchronization error.';

            if (!confirm(confirmSyncStr)) {
                return;
            }

            // Deshabilitar todos los botones de sincronización para evitar múltiples clics
            $('.wzi-sync-now').prop('disabled', true);
            $button.html(syncingStr).append($spinner); // Solo el botón clickeado muestra el spinner
            $messageSpan.text('').removeClass('error success').css('color','');

            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_manual_sync', // Acción PHP
                    nonce: wzi_admin.nonce,    // Nonce de wzi_admin
                    sync_type: syncType
                    // 'direction' no se envía, se tomará de la configuración o será 'both' por defecto en PHP
                },
                success: function(response) {
                    if (response && typeof response.success !== 'undefined') {
                        if (response.success) {
                            $messageSpan.text(response.message || syncCompleteStr).addClass('success').removeClass('error').css('color', 'green');
                        } else {
                            $messageSpan.text(response.message || syncErrorStr).addClass('error').removeClass('success').css('color', 'red');
                        }
                    } else {
                        var rawResponse = typeof response === 'string' ? response.substring(0, 200) + '...' : JSON.stringify(response);
                        $messageSpan.text(syncErrorStr + ' (Respuesta inesperada: ' + rawResponse + ')').addClass('error').removeClass('success').css('color', 'red');
                         console.error('WZI Sync AJAX unexpected response:', response);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    var errorMessage = syncErrorStr;
                     if (jqXHR.responseText) {
                        errorMessage += ' Server response: ' + jqXHR.responseText.substring(0, 200) + '...';
                    } else if (errorThrown) {
                        errorMessage += ': ' + errorThrown;
                    }
                    $messageSpan.text(errorMessage).addClass('error').removeClass('success').css('color', 'red');
                    console.error('WZI Sync AJAX error:', textStatus, errorThrown, jqXHR.responseText);
                },
                complete: function() {
                    // Rehabilitar todos los botones de sincronización y restaurar el texto del botón clickeado
                    $('.wzi-sync-now').prop('disabled', false);
                    $button.html(originalButtonText);
                    $spinner.remove();
                }
            });
        });
    });

})(jQuery);
