<?php
/**
 * Vista de la pestaña de Mapeo de Campos
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/admin/partials/settings
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wzi-mapping-settings">
    <h2><?php esc_html_e('Mapeo de Campos WooCommerce - Zoho', 'woocommerce-zoho-integration'); ?></h2>
    <p><?php esc_html_e('Configure cómo se mapean los campos de WooCommerce a los campos correspondientes en sus módulos de Zoho. Los cambios se guardan por módulo.', 'woocommerce-zoho-integration'); ?></p>

    <div id="wzi-mapping-ui-container">
        <div class="wzi-mapping-selectors">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wzi_wc_module_select"><?php esc_html_e('Módulo de WooCommerce:', 'woocommerce-zoho-integration'); ?></label>
                    </th>
                    <td>
                        <select id="wzi_wc_module_select" name="wzi_wc_module">
                            <option value=""><?php esc_html_e('-- Seleccionar Módulo WC --', 'woocommerce-zoho-integration'); ?></option>
                            <option value="customer"><?php esc_html_e('Clientes', 'woocommerce-zoho-integration'); ?></option>
                            <option value="product"><?php esc_html_e('Productos', 'woocommerce-zoho-integration'); ?></option>
                            <option value="order"><?php esc_html_e('Pedidos', 'woocommerce-zoho-integration'); ?></option>
                             <?php
                            // TODO: Se podrían añadir más módulos si es necesario, ej. Invoices, Coupons
                            // Considerar obtenerlos dinámicamente de una lista en WZI_Sync_Manager o similar
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wzi_zoho_service_select"><?php esc_html_e('Servicio de Zoho:', 'woocommerce-zoho-integration'); ?></label>
                    </th>
                    <td>
                        <select id="wzi_zoho_service_select" name="wzi_zoho_service">
                            <option value=""><?php esc_html_e('-- Seleccionar Servicio Zoho --', 'woocommerce-zoho-integration'); ?></option>
                            <option value="crm"><?php esc_html_e('Zoho CRM', 'woocommerce-zoho-integration'); ?></option>
                            <option value="inventory"><?php esc_html_e('Zoho Inventory', 'woocommerce-zoho-integration'); ?></option>
                            <option value="books"><?php esc_html_e('Zoho Books', 'woocommerce-zoho-integration'); ?></option>
                            <?php // Campaigns podría no tener "módulos" de la misma manera para mapeo de campos. ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wzi_zoho_module_select"><?php esc_html_e('Módulo de Zoho:', 'woocommerce-zoho-integration'); ?></label>
                    </th>
                    <td>
                        <select id="wzi_zoho_module_select" name="wzi_zoho_module" disabled>
                            <option value=""><?php esc_html_e('-- Seleccionar Módulo Zoho --', 'woocommerce-zoho-integration'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Seleccione primero el Servicio de Zoho.', 'woocommerce-zoho-integration'); ?></p>
                    </td>
                </tr>
            </table>
            <button type="button" id="wzi_load_mapping_button" class="button button-secondary" disabled><?php esc_html_e('Cargar Campos para Mapeo', 'woocommerce-zoho-integration'); ?></button>
        </div>

        <hr/>

        <div id="wzi_field_mapping_table_container">
            <p class="description wzi-no-mapping-loaded"><?php esc_html_e('Seleccione los módulos de WooCommerce y Zoho para ver y configurar el mapeo de campos.', 'woocommerce-zoho-integration'); ?></p>

            <div id="wzi-mapping-table-render-area" style="display:none;">
                <table class="wp-list-table widefat fixed striped wzi-mapping-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;"><?php esc_html_e('Campo WooCommerce', 'woocommerce-zoho-integration'); ?></th>
                            <th style="width: 30%;"><?php esc_html_e('Campo Zoho', 'woocommerce-zoho-integration'); ?></th>
                            <th style="width: 20%;"><?php esc_html_e('Dirección Sinc.', 'woocommerce-zoho-integration'); ?></th>
                            <th><?php esc_html_e('Acciones', 'woocommerce-zoho-integration'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wzi-field-mapping-rows">
                        <!-- Las filas de mapeo se añadirán aquí por JavaScript -->
                    </tbody>
                </table>
                <button type="button" id="wzi_add_mapping_row_button" class="button" style="margin-top:10px;"><?php esc_html_e('Añadir Fila de Mapeo', 'woocommerce-zoho-integration'); ?></button>
            </div>
        </div>

        <div id="wzi-mapping-feedback" style="margin-top: 20px;"></div>

        <p class="submit wzi-save-mapping-controls" style="display:none;">
            <button type="button" id="wzi_save_mapping_button" class="button button-primary"><?php esc_html_e('Guardar Mapeos para este Módulo', 'woocommerce-zoho-integration'); ?></button>
            <span class="spinner"></span>
        </p>
    </div>

    <script type="text/template" id="wzi-mapping-row-template">
        <tr>
            <td>
                <select class="wzi-wc-field-select" name="mappings[<%= index %>][wc_field]">
                    <option value=""><?php echo esc_js(__('Seleccionar campo WC', 'woocommerce-zoho-integration')); ?></option>
                    <% wcFields.forEach(function(field) { %>
                        <option value="<%= field.id %>" data-type="<%= field.type %>"><%= field.name %></option>
                    <% }); %>
                    <option value="_custom_meta_"><?php echo esc_js(__('Campo Meta Personalizado', 'woocommerce-zoho-integration')); ?></option>
                </select>
                <input type="text" class="wzi-wc-custom-meta-field" name="mappings[<%= index %>][wc_custom_meta]" placeholder="<?php echo esc_js(__('Nombre del Meta Key', 'woocommerce-zoho-integration')); ?>" style="display:none; margin-top:5px;" />
            </td>
            <td>
                <select class="wzi-zoho-field-select" name="mappings[<%= index %>][zoho_field]">
                    <option value=""><?php echo esc_js(__('Seleccionar campo Zoho', 'woocommerce-zoho-integration')); ?></option>
                    <% zohoFields.forEach(function(field) { %>
                        <option value="<%= field.api_name %>" data-type="<%= field.data_type %>"><%= field.field_label %> (<%= field.api_name %>)</option>
                    <% }); %>
                </select>
            </td>
            <td>
                <select name="mappings[<%= index %>][direction]">
                    <option value="wc_to_zoho"><?php echo esc_js(__('WC → Zoho', 'woocommerce-zoho-integration')); ?></option>
                    <option value="zoho_to_wc"><?php echo esc_js(__('Zoho → WC', 'woocommerce-zoho-integration')); ?></option>
                    <option value="both"><?php echo esc_js(__('Ambos (Bidireccional)', 'woocommerce-zoho-integration')); ?></option>
                </select>
            </td>
            <td>
                <button type="button" class="button wzi-remove-mapping-row">
                    <span class="dashicons dashicons-trash"></span> <?php echo esc_js(__('Quitar', 'woocommerce-zoho-integration')); ?>
                </button>
            </td>
        </tr>
    </script>

    <?php
        // TODO: Mover este JS a un archivo admin.js y localizar los datos y nonces necesarios.
        // Por ahora, lo dejo aquí para ilustrar la funcionalidad esperada.
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var wcFieldsCache = {}; // Cache para campos de WC
            var zohoFieldsCache = {}; // Cache para campos de Zoho
            var currentMappings = []; // Mapeos cargados

            var zohoModulesByService = {
                crm: [
                    { value: 'Contacts', text: '<?php echo esc_js(__('Contacts', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Leads', text: '<?php echo esc_js(__('Leads', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Deals', text: '<?php echo esc_js(__('Deals', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Sales_Orders', text: '<?php echo esc_js(__('Sales Orders (CRM)', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Products', text: '<?php echo esc_js(__('Products (CRM)', 'woocommerce-zoho-integration')); ?>' }
                ],
                inventory: [
                    { value: 'Items', text: '<?php echo esc_js(__('Items (Inventory)', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'SalesOrders', text: '<?php echo esc_js(__('Sales Orders (Inventory)', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Contacts', text: '<?php echo esc_js(__('Contacts (Inventory)', 'woocommerce-zoho-integration')); ?>' }
                ],
                books: [
                    { value: 'Invoices', text: '<?php echo esc_js(__('Invoices', 'woocommerce-zoho-integration')); ?>' },
                    // { value: 'Estimates', text: '<?php echo esc_js(__('Estimates', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Customers', text: '<?php echo esc_js(__('Customers (Books)', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Items', text: '<?php echo esc_js(__('Items (Books)', 'woocommerce-zoho-integration')); ?>' }
                ]
            };

            $('#wzi_zoho_service_select').on('change', function() {
                var service = $(this).val();
                var $zohoModuleSelect = $('#wzi_zoho_module_select');
                $zohoModuleSelect.html('<option value=""><?php esc_html_e('-- Seleccionar Módulo Zoho --', 'woocommerce-zoho-integration'); ?></option>');

                if (service && zohoModulesByService[service]) {
                    zohoModulesByService[service].forEach(function(module) {
                        $zohoModuleSelect.append($('<option>', { value: module.value, text: module.text }));
                    });
                    $zohoModuleSelect.prop('disabled', false);
                } else {
                    $zohoModuleSelect.prop('disabled', true);
                }
                $('#wzi_load_mapping_button').prop('disabled', true);
                $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').hide();
                $('.wzi-no-mapping-loaded').show();
            }).trigger('change');

            $('#wzi_wc_module_select, #wzi_zoho_module_select').on('change', function() {
                if ($('#wzi_wc_module_select').val() && $('#wzi_zoho_module_select').val()) {
                    $('#wzi_load_mapping_button').prop('disabled', false);
                } else {
                    $('#wzi_load_mapping_button').prop('disabled', true);
                }
                 $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').hide();
                 $('.wzi-no-mapping-loaded').show();
            });

            $('#wzi_load_mapping_button').on('click', function() {
                var wcModule = $('#wzi_wc_module_select').val();
                var zohoService = $('#wzi_zoho_service_select').val();
                var zohoModule = $('#wzi_zoho_module_select').val();
                var $button = $(this);
                var $spinner = $button.siblings('.spinner'); // Asumiendo que hay un spinner cerca
                var $container = $('#wzi_field_mapping_table_container');
                var $feedback = $('#wzi-mapping-feedback');

                if (!wcModule || !zohoModule || !zohoService) {
                    alert('<?php echo esc_js(__("Por favor, seleccione todos los módulos.", "woocommerce-zoho-integration")); ?>');
                    return;
                }

                $button.prop('disabled', true);
                if($spinner.length) $spinner.addClass('is-active');
                $container.html('<p><i><?php echo esc_js(__("Cargando campos y mapeos...", "woocommerce-zoho-integration")); ?></i></p>');
                $feedback.html('').removeClass('error success notice notice-error notice-success');

                // 1. Cargar campos de WC (simulado, debería ser AJAX o localizado)
                // 2. Cargar campos de Zoho (AJAX a una acción que llame a get_available_fields_for_module)
                // 3. Cargar mapeos existentes (AJAX)
                // Por ahora, simulación y placeholder:

                // Simular carga de campos y mapeos
                $.when(
                    loadWcFields(wcModule), // Debería ser una llamada AJAX
                    loadZohoFields(zohoService, zohoModule) // Debería ser una llamada AJAX
                ).done(function(wcFieldsResult, zohoFieldsResult) {
                    var wcFields = wcFieldsCache[wcModule]; // wcFieldsResult[0] si AJAX
                    var zohoFields = zohoFieldsCache[zohoService + '_' + zohoModule]; // zohoFieldsResult[0] si AJAX

                    if (!wcFields || !zohoFields) {
                         $container.html('<p class="notice notice-error"><?php echo esc_js(__("Error al cargar la estructura de campos.", "woocommerce-zoho-integration")); ?></p>');
                         return;
                    }

                    // TODO: Cargar mapeos existentes para wcModule y zohoModule vía AJAX
                    // Por ahora, currentMappings será un array vacío o con datos de ejemplo.
                    currentMappings = [ /* { wc_field: 'billing_email', zoho_field: 'Email', direction: 'both' }, ... */ ];

                    renderMappingTable(wcFields, zohoFields, currentMappings);
                    $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').show();
                    $('.wzi-no-mapping-loaded').hide();

                }).fail(function() {
                    $container.html('<p class="notice notice-error"><?php echo esc_js(__("Error al cargar la estructura de campos.", "woocommerce-zoho-integration")); ?></p>');
                }).always(function() {
                    $button.prop('disabled', false);
                    if($spinner.length) $spinner.removeClass('is-active');
                });
            });

            // Funciones de simulación (reemplazar con AJAX real)
            function loadWcFields(module) {
                var dfd = $.Deferred();
                if (wcFieldsCache[module]) {
                    return dfd.resolve(wcFieldsCache[module]).promise();
                }
                // Simulación, estos campos deberían venir de una llamada AJAX o datos localizados
                var fields = [];
                if (module === 'customer') {
                    fields = [
                        { id: 'user_email', name: '<?php echo esc_js(__("Email de Usuario", "woocommerce-zoho-integration")); ?>', type: 'email' },
                        { id: 'first_name', name: '<?php echo esc_js(__("Nombre", "woocommerce-zoho-integration")); ?>', type: 'string' },
                        { id: 'last_name', name: '<?php echo esc_js(__("Apellido", "woocommerce-zoho-integration")); ?>', type: 'string' },
                        { id: 'billing_phone', name: '<?php echo esc_js(__("Teléfono de Facturación", "woocommerce-zoho-integration")); ?>', type: 'phone' }
                    ];
                } else if (module === 'product') {
                     fields = [
                        { id: 'name', name: '<?php echo esc_js(__("Nombre del Producto", "woocommerce-zoho-integration")); ?>', type: 'string' },
                        { id: 'sku', name: '<?php echo esc_js(__("SKU", "woocommerce-zoho-integration")); ?>', type: 'string' },
                        { id: 'regular_price', name: '<?php echo esc_js(__("Precio Regular", "woocommerce-zoho-integration")); ?>', type: 'decimal' }
                    ];
                } else if (module === 'order') {
                     fields = [
                        { id: 'order_number', name: '<?php echo esc_js(__("Número de Pedido", "woocommerce-zoho-integration")); ?>', type: 'string' },
                        { id: 'total', name: '<?php echo esc_js(__("Total del Pedido", "woocommerce-zoho-integration")); ?>', type: 'decimal' },
                        { id: 'status', name: '<?php echo esc_js(__("Estado del Pedido", "woocommerce-zoho-integration")); ?>', type: 'string' }
                    ];
                }
                wcFieldsCache[module] = fields;
                dfd.resolve(fields);
                return dfd.promise();
            }

            function loadZohoFields(service, module) {
                 var dfd = $.Deferred();
                 var cacheKey = service + '_' + module;
                 if (zohoFieldsCache[cacheKey]) {
                    return dfd.resolve(zohoFieldsCache[cacheKey]).promise();
                 }
                // Esto debería ser una llamada AJAX a una acción que use WZI_Zoho_CRM::get_available_fields_for_module() etc.
                // Ejemplo: action: 'wzi_get_zoho_module_fields', service: service, module: module
                // Por ahora, simulación:
                var fields = [];
                 if (service === 'crm' && module === 'Contacts') {
                    fields = [
                        { api_name: 'Email', field_label: '<?php echo esc_js(__("Email", "woocommerce-zoho-integration")); ?>', data_type: 'email'},
                        { api_name: 'First_Name', field_label: '<?php echo esc_js(__("First Name", "woocommerce-zoho-integration")); ?>', data_type: 'string'},
                        { api_name: 'Last_Name', field_label: '<?php echo esc_js(__("Last Name", "woocommerce-zoho-integration")); ?>', data_type: 'string'},
                        { api_name: 'Phone', field_label: '<?php echo esc_js(__("Phone", "woocommerce-zoho-integration")); ?>', data_type: 'phone'}
                    ];
                 } else if (service === 'inventory' && module === 'Items') {
                     fields = [
                        { api_name: 'name', field_label: '<?php echo esc_js(__("Item Name", "woocommerce-zoho-integration")); ?>', data_type: 'string'},
                        { api_name: 'sku', field_label: '<?php echo esc_js(__("SKU", "woocommerce-zoho-integration")); ?>', data_type: 'string'},
                        { api_name: 'rate', field_label: '<?php echo esc_js(__("Rate", "woocommerce-zoho-integration")); ?>', data_type: 'decimal'}
                    ];
                 }
                 zohoFieldsCache[cacheKey] = fields;
                 dfd.resolve(fields);
                 return dfd.promise();
            }

            // Renderizar tabla de mapeo
            var rowTemplate = _.template($('#wzi-mapping-row-template').html());
            function renderMappingTable(wcFields, zohoFields, mappings) {
                var $tbody = $('#wzi-field-mapping-rows');
                $tbody.empty();
                if (mappings.length === 0) { // Añadir una fila vacía si no hay mapeos
                    mappings.push({ wc_field: '', zoho_field: '', direction: 'wc_to_zoho' });
                }
                mappings.forEach(function(mapping, index) {
                    var rowHtml = rowTemplate({ index: index, wcFields: wcFields, zohoFields: zohoFields });
                    var $row = $(rowHtml);
                    $row.find('select[name="mappings['+index+'][wc_field]"]').val(mapping.wc_field);
                    $row.find('select[name="mappings['+index+'][zoho_field]"]').val(mapping.zoho_field);
                    $row.find('select[name="mappings['+index+'][direction]"]').val(mapping.direction);
                    if (mapping.wc_field === '_custom_meta_') {
                        $row.find('.wzi-wc-custom-meta-field').show().val(mapping.wc_custom_meta || '');
                    }
                    $tbody.append($row);
                });
            }

            $('#wzi_add_mapping_row_button').on('click', function() {
                var wcModule = $('#wzi_wc_module_select').val();
                var zohoService = $('#wzi_zoho_service_select').val();
                var zohoModule = $('#wzi_zoho_module_select').val();
                if (!wcFieldsCache[wcModule] || !zohoFieldsCache[zohoService + '_' + zohoModule]) {
                    alert('<?php echo esc_js(__("Por favor, cargue los campos primero.", "woocommerce-zoho-integration")); ?>');
                    return;
                }
                var newIndex = $('#wzi-field-mapping-rows tr').length;
                var rowHtml = rowTemplate({ index: newIndex, wcFields: wcFieldsCache[wcModule], zohoFields: zohoFieldsCache[zohoService + '_' + zohoModule] });
                $('#wzi-field-mapping-rows').append(rowHtml);
            });

            $('#wzi_field_mapping_table_container').on('click', '.wzi-remove-mapping-row', function() {
                $(this).closest('tr').remove();
            });

            $('#wzi_field_mapping_table_container').on('change', '.wzi-wc-field-select', function() {
                var $select = $(this);
                var $customMetaInput = $select.closest('td').find('.wzi-wc-custom-meta-field');
                if ($select.val() === '_custom_meta_') {
                    $customMetaInput.show();
                } else {
                    $customMetaInput.hide().val('');
                }
            });

            $('#wzi_save_mapping_button').on('click', function() {
                var $button = $(this);
                var $spinner = $button.siblings('.spinner'); // Asumiendo spinner junto al botón
                var $feedback = $('#wzi-mapping-feedback');

                var mappingsData = [];
                $('#wzi-field-mapping-rows tr').each(function(index) {
                    var $row = $(this);
                    var wcField = $row.find('select[name="mappings['+index+'][wc_field]"]').val();
                    var wcCustomMeta = '';
                    if (wcField === '_custom_meta_') {
                        wcCustomMeta = $row.find('input[name="mappings['+index+'][wc_custom_meta]"]').val();
                        if(!wcCustomMeta) {
                            // Podrías añadir una validación o alerta aquí
                            // Por ahora, se permite si el usuario quiere mapear un meta vacío (aunque no tenga sentido)
                        }
                    }
                    mappingsData.push({
                        wc_field: wcField,
                        wc_custom_meta: wcCustomMeta,
                        zoho_field: $row.find('select[name="mappings['+index+'][zoho_field]"]').val(),
                        direction: $row.find('select[name="mappings['+index+'][direction]"]').val()
                        // transform_function: $row.find('input[name="mappings['+index+'][transform_function]"]').val() // Si añades este campo
                    });
                });

                $button.prop('disabled', true);
                if($spinner.length) $spinner.addClass('is-active');
                $feedback.html('').removeClass('error success notice notice-error notice-success');

                $.ajax({
                    url: wzi_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wzi_save_field_mapping',
                        nonce: wzi_admin.nonce,
                        wc_module: $('#wzi_wc_module_select').val(),
                        zoho_service: $('#wzi_zoho_service_select').val(),
                        zoho_module: $('#wzi_zoho_module_select').val(),
                        mappings: mappingsData
                    },
                    success: function(response) {
                        if (response.success) {
                            $feedback.html('<p>' + response.data.message + '</p>').addClass('notice notice-success is-dismissible');
                        } else {
                            var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__("Ocurrió un error desconocido.", "woocommerce-zoho-integration")); ?>';
                            $feedback.html('<p>' + errorMsg + '</p>').addClass('notice notice-error is-dismissible');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $feedback.html('<p><?php echo esc_js(__("Error AJAX al guardar el mapeo: ", "woocommerce-zoho-integration")); ?>' + textStatus + ' - ' + errorThrown + '</p>').addClass('notice notice-error is-dismissible');
                    },
                    complete: function() {
                        if($spinner.length) $spinner.removeClass('is-active');
                        $button.prop('disabled', false);
                    }
                });
            });
             // Inicializar Underscore.js para plantillas si no está ya configurado
            if (typeof _ === 'undefined' && typeof wp !== 'undefined' && wp.template) {
                 _ = { template: wp.template };
            } else if (typeof _ === 'undefined') {
                console.error("Underscore.js (o wp.template) no está disponible para las plantillas de mapeo.");
            }
        });
    </script>
</div>
