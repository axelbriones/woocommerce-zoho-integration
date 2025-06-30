/**
 * JavaScript del área de administración del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/assets/js
 */

(function($) {
    'use strict';

    /**
     * Objeto principal del plugin
     */
    var WZI_Admin = {
        
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Sincronización manual
            $(document).on('click', '.wzi-sync-now', this.handleManualSync);
            
            // Probar conexión
            $(document).on('click', '.wzi-test-connection', this.handleTestConnection);
            
            // Guardar mapeo de campos
            $(document).on('click', '.wzi-save-mapping', this.handleSaveMapping);
            
            // Añadir nuevo mapeo
            $(document).on('click', '.wzi-add-mapping', this.handleAddMapping);
            
            // Eliminar mapeo
            $(document).on('click', '.wzi-remove-mapping', this.handleRemoveMapping);
            
            // Limpiar logs
            $(document).on('click', '.wzi-clear-logs', this.handleClearLogs);
            
            // Exportar logs
            $(document).on('click', '.wzi-export-logs', this.handleExportLogs);
            
            // Generar webhook secret
            $(document).on('click', '.wzi-generate-secret', this.handleGenerateSecret);
            
            // Copiar al portapapeles
            $(document).on('click', '.wzi-copy-clipboard', this.handleCopyToClipboard);
            
            // Actualizar estado de sincronización
            if ($('.wzi-sync-status').length) {
                setInterval(this.updateSyncStatus, 5000);
            }
            
            // Tabs
            $(document).on('click', '.nav-tab', this.handleTabClick);
            
            // Formularios AJAX
            $(document).on('submit', '.wzi-ajax-form', this.handleAjaxForm);
        },
        
        /**
         * Inicializar componentes
         */
        initComponents: function() {
            // Tooltips
            this.initTooltips();
            
            // Color picker
            if ($.fn.wpColorPicker) {
                $('.wzi-color-picker').wpColorPicker();
            }
            
            // Select2
            if ($.fn.select2) {
                $('.wzi-select2').select2({
                    width: '100%'
                });
            }
            
            // Datepicker
            if ($.fn.datepicker) {
                $('.wzi-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
            
            // Gráficos
            this.initCharts();
        },
        
        /**
         * Manejar sincronización manual
         */
        handleManualSync: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var syncType = $button.data('sync-type') || 'all';
            var direction = $button.data('direction') || 'both';
            
            if (!confirm(wzi_admin.strings.confirm_sync)) {
                return;
            }
            
            $button.prop('disabled', true)
                   .addClass('wzi-loading')
                   .text(wzi_admin.strings.syncing);
            
            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_manual_sync',
                    sync_type: syncType,
                    direction: direction,
                    nonce: wzi_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WZI_Admin.showNotice(wzi_admin.strings.sync_complete, 'success');
                        
                        // Actualizar estado
                        if (response.data && response.data.status) {
                            WZI_Admin.updateSyncStatusDisplay(response.data.status);
                        }
                    } else {
                        WZI_Admin.showNotice(response.data.message || wzi_admin.strings.sync_error, 'error');
                    }
                },
                error: function() {
                    WZI_Admin.showNotice(wzi_admin.strings.sync_error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .removeClass('wzi-loading')
                           .text($button.data('original-text') || 'Sincronizar');
                }
            });
        },
        
        /**
         * Manejar prueba de conexión
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var service = $button.data('service') || 'crm';
            var originalText = $button.text();
            
            $button.prop('disabled', true)
                   .text(wzi_admin.strings.testing_connection);
            
            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_test_connection',
                    service: service,
                    nonce: wzi_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.text(wzi_admin.strings.connection_success)
                               .addClass('button-primary');
                        WZI_Admin.showNotice(response.data.message, 'success');
                    } else {
                        $button.text(wzi_admin.strings.connection_failed);
                        WZI_Admin.showNotice(response.data.message || wzi_admin.strings.connection_failed, 'error');
                    }
                },
                error: function() {
                    $button.text(wzi_admin.strings.connection_failed);
                    WZI_Admin.showNotice(wzi_admin.strings.connection_failed, 'error');
                },
                complete: function() {
                    setTimeout(function() {
                        $button.prop('disabled', false)
                               .text(originalText)
                               .removeClass('button-primary');
                    }, 3000);
                }
            });
        },
        
        /**
         * Manejar guardado de mapeo de campos
         */
        handleSaveMapping: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('form');
            var entityType = $form.data('entity-type');
            var mappings = [];
            
            // Recopilar mapeos
            $form.find('.field-mapping-row').each(function() {
                var $row = $(this);
                var mapping = {
                    woo_field: $row.find('.woo-field').val(),
                    zoho_field: $row.find('.zoho-field').val(),
                    sync_direction: $row.find('.sync-direction').val(),
                    transform_function: $row.find('.transform-function').val()
                };
                
                if (mapping.woo_field && mapping.zoho_field) {
                    mappings.push(mapping);
                }
            });
            
            $button.prop('disabled', true)
                   .addClass('wzi-loading');
            
            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_save_field_mapping',
                    entity_type: entityType,
                    mappings: mappings,
                    nonce: wzi_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WZI_Admin.showNotice(response.data, 'success');
                    } else {
                        WZI_Admin.showNotice(response.data || 'Error al guardar', 'error');
                    }
                },
                error: function() {
                    WZI_Admin.showNotice('Error al guardar el mapeo', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .removeClass('wzi-loading');
                }
            });
        },
        
        /**
         * Manejar añadir nuevo mapeo
         */
        handleAddMapping: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $container = $button.closest('.wzi-field-mapping').find('.field-mapping-rows');
            var $template = $('#field-mapping-template').html();
            
            $container.append($template);
            
            // Inicializar select2 si está disponible
            if ($.fn.select2) {
                $container.find('.wzi-select2').select2({
                    width: '100%'
                });
            }
        },
        
        /**
         * Manejar eliminar mapeo
         */
        handleRemoveMapping: function(e) {
            e.preventDefault();
            
            if (confirm('¿Estás seguro de eliminar este mapeo?')) {
                $(this).closest('.field-mapping-row').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        },
        
        /**
         * Manejar limpiar logs
         */
        handleClearLogs: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var days = $button.data('days') || 0;
            
            if (!confirm(wzi_admin.strings.confirm_clear_logs)) {
                return;
            }
            
            $button.prop('disabled', true)
                   .addClass('wzi-loading');
            
            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_clear_logs',
                    days: days,
                    nonce: wzi_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        WZI_Admin.showNotice(response.data, 'success');
                        
                        // Recargar tabla de logs si existe
                        if (typeof WZI_Admin.logsTable !== 'undefined') {
                            WZI_Admin.logsTable.ajax.reload();
                        }
                    } else {
                        WZI_Admin.showNotice(response.data || 'Error al limpiar logs', 'error');
                    }
                },
                error: function() {
                    WZI_Admin.showNotice('Error al limpiar logs', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .removeClass('wzi-loading');
                }
            });
        },
        
        /**
         * Manejar exportar logs
         */
        handleExportLogs: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var format = $button.data('format') || 'csv';
            
            // Construir URL con parámetros
            var url = wzi_admin.ajax_url + '?action=wzi_export_logs&format=' + format + '&nonce=' + wzi_admin.nonce;
            
            // Abrir en nueva ventana para descargar
            window.open(url, '_blank');
        },
        
        /**
         * Manejar generar webhook secret
         */
        handleGenerateSecret: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $('#webhook_secret');
            
            // Generar secret aleatorio
            var secret = WZI_Admin.generateRandomString(32);
            
            $input.val(secret);
            $button.text('¡Generado!');
            
            setTimeout(function() {
                $button.text('Generar Nuevo');
            }, 2000);
        },
        
        /**
         * Manejar copiar al portapapeles
         */
        handleCopyToClipboard: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var target = $button.data('target');
            var $element = $(target);
            
            if ($element.length) {
                var text = $element.is('input') ? $element.val() : $element.text();
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        WZI_Admin.showNotice('Copiado al portapapeles', 'success');
                    });
                } else {
                    // Fallback para navegadores antiguos
                    var $temp = $('<textarea>');
                    $('body').append($temp);
                    $temp.val(text).select();
                    document.execCommand('copy');
                    $temp.remove();
                    WZI_Admin.showNotice('Copiado al portapapeles', 'success');
                }
            }
        },
        
        /**
         * Actualizar estado de sincronización
         */
        updateSyncStatus: function() {
            $.ajax({
                url: wzi_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wzi_get_sync_status',
                    nonce: wzi_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        WZI_Admin.updateSyncStatusDisplay(response.data);
                    }
                }
            });
        },
        
        /**
         * Actualizar visualización del estado
         */
        updateSyncStatusDisplay: function(status) {
            var $container = $('.wzi-sync-status');
            
            if (status.is_running) {
                $container.removeClass('idle error').addClass('running');
                $container.find('.status-text').text('Sincronizando ' + status.current_type + '...');
                
                // Actualizar barra de progreso
                var $progress = $container.find('.wzi-progress-fill');
                if ($progress.length) {
                    $progress.css('width', status.progress + '%');
                }
            } else {
                $container.removeClass('running error').addClass('idle');
                $container.find('.status-text').text('Sin sincronizaciones activas');
            }
            
            // Actualizar contadores
            if (status.queue) {
                $('.queue-pending').text(status.queue.pending);
                $('.queue-failed').text(status.queue.failed);
            }
        },
        
        /**
         * Manejar click en tabs
         */
        handleTabClick: function(e) {
            // WordPress ya maneja esto, pero podemos añadir funcionalidad extra
            var $tab = $(this);
            var target = $tab.attr('href').split('tab=')[1];
            
            // Guardar tab activa en localStorage
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('wzi_active_tab', target);
            }
        },
        
        /**
         * Manejar formularios AJAX
         */
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submit = $form.find('[type="submit"]');
            var formData = new FormData(this);
            
            formData.append('nonce', wzi_admin.nonce);
            
            $submit.prop('disabled', true)
                   .addClass('wzi-loading');
            
            $.ajax({
                url: $form.attr('action') || wzi_admin.ajax_url,
                type: $form.attr('method') || 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WZI_Admin.showNotice(response.data.message || 'Operación exitosa', 'success');
                        
                        // Callback personalizado
                        if ($form.data('success-callback')) {
                            window[$form.data('success-callback')](response);
                        }
                    } else {
                        WZI_Admin.showNotice(response.data.message || 'Error en la operación', 'error');
                    }
                },
                error: function() {
                    WZI_Admin.showNotice('Error al procesar la solicitud', 'error');
                },
                complete: function() {
                    $submit.prop('disabled', false)
                           .removeClass('wzi-loading');
                }
            });
        },
        
        /**
         * Inicializar tooltips
         */
        initTooltips: function() {
            $('.wzi-tooltip').on('mouseenter', function() {
                var $this = $(this);
                var text = $this.data('tooltip');
                
                if (text && !$this.find('.wzi-tooltiptext').length) {
                    $this.append('<span class="wzi-tooltiptext">' + text + '</span>');
                }
            });
        },
        
        /**
         * Inicializar gráficos
         */
        initCharts: function() {
            // Gráfico de sincronización
            var $syncChart = $('#wzi-sync-chart');
            if ($syncChart.length && typeof Chart !== 'undefined') {
                var ctx = $syncChart[0].getContext('2d');
                var chartData = $syncChart.data('chart-data');
                
                new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Gráfico de estado
            var $statusChart = $('#wzi-status-chart');
            if ($statusChart.length && typeof Chart !== 'undefined') {
                var ctx = $statusChart[0].getContext('2d');
                var chartData = $statusChart.data('chart-data');
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }
        },
        
        /**
         * Mostrar notificación
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible wzi-notice"><p>' + message + '</p></div>');
            
            $('.wzi-notices-container').length 
                ? $('.wzi-notices-container').append($notice)
                : $('.wrap h1').after($notice);
            
            // Auto ocultar después de 5 segundos
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Hacer dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },
        
        /**
         * Generar string aleatorio
         */
        generateRandomString: function(length) {
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var result = '';
            
            for (var i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            return result;
        },
        
        /**
         * Formatear número
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        },
        
        /**
         * Debounce function
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    timeout = null;
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    /**
     * Inicializar cuando el DOM esté listo
     */
    $(document).ready(function() {
        WZI_Admin.init();
    });
    
    /**
     * Exportar para uso global
     */
    window.WZI_Admin = WZI_Admin;

})(jQuery);