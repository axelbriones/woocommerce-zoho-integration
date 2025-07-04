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
            <button type="button" id="wzi_load_mapping_button" class="button button-secondary" disabled>
                <?php esc_html_e('Cargar Campos para Mapeo', 'woocommerce-zoho-integration'); ?>
                <span class="spinner" style="float: none; vertical-align: middle; margin-left: 5px;"></span>
            </button>
        </div>

        <hr/>

        <div id="wzi_field_mapping_table_container">
            <p class="description wzi-no-mapping-loaded"><?php esc_html_e('Seleccione los módulos de WooCommerce y Zoho y luego haga clic en "Cargar Campos para Mapeo" para configurar las asignaciones de campos.', 'woocommerce-zoho-integration'); ?></p>

            <div id="wzi-mapping-table-render-area" style="display:none;">
                <table class="wp-list-table widefat fixed striped wzi-mapping-table">
                    <thead>
                        <tr>
                            <th style="width: 35%;"><?php esc_html_e('Campo WooCommerce', 'woocommerce-zoho-integration'); ?></th>
                            <th style="width: 35%;"><?php esc_html_e('Campo Zoho', 'woocommerce-zoho-integration'); ?></th>
                            <th style="width: 20%;"><?php esc_html_e('Dirección Sinc.', 'woocommerce-zoho-integration'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Acciones', 'woocommerce-zoho-integration'); ?></th>
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
            <button type="button" id="wzi_save_mapping_button" class="button button-primary">
                <?php esc_html_e('Guardar Mapeos para este Módulo', 'woocommerce-zoho-integration'); ?>
                <span class="spinner" style="float: none; vertical-align: middle; margin-left: 5px;"></span>
            </button>
        </p>
    </div>

    <script type="text/template" id="wzi-mapping-row-template">
        <tr class="wzi-mapping-row">
            <td>
                <select class="wzi-wc-field-select" name="mappings[<%= index %>][wc_field]">
                    <option value=""><?php echo esc_js(__('Seleccionar campo WooCommerce', 'woocommerce-zoho-integration')); ?></option>
                    <% wcFields.forEach(function(group) { %>
                        <optgroup label="<%= group.group_name %>">
                            <% group.fields.forEach(function(field) { %>
                                <option value="<%= field.id %>" data-type="<%= field.type %>" <%= (typeof wc_field !== 'undefined' && wc_field === field.id) ? 'selected' : '' %>>
                                    <%= field.name %>
                                </option>
                            <% }); %>
                        </optgroup>
                    <% }); %>
                    <option value="_custom_meta_" <%= (typeof wc_field !== 'undefined' && wc_field === '_custom_meta_') ? 'selected' : '' %>>
                        <?php echo esc_js(__('Campo Meta Personalizado', 'woocommerce-zoho-integration')); ?>
                    </option>
                </select>
                <input type="text" class="wzi-wc-custom-meta-field regular-text" name="mappings[<%= index %>][wc_custom_meta]" value="<%= typeof wc_custom_meta !== 'undefined' ? wc_custom_meta : '' %>" placeholder="<?php echo esc_js(__('Nombre del Meta Key', 'woocommerce-zoho-integration')); ?>" style="display:<%= (typeof wc_field !== 'undefined' && wc_field === '_custom_meta_') ? 'block' : 'none' %>; margin-top:5px;" />
            </td>
            <td>
                <select class="wzi-zoho-field-select" name="mappings[<%= index %>][zoho_field]">
                    <option value=""><?php echo esc_js(__('Seleccionar campo Zoho', 'woocommerce-zoho-integration')); ?></option>
                    <% zohoFields.forEach(function(field) { %>
                        <option value="<%= field.api_name %>" data-type="<%= field.data_type %>" <%= (typeof zoho_field !== 'undefined' && zoho_field === field.api_name) ? 'selected' : '' %>>
                            <%= field.field_label %> (<%= field.api_name %>)
                        </option>
                    <% }); %>
                </select>
            </td>
            <td>
                <select name="mappings[<%= index %>][direction]">
                    <option value="wc_to_zoho" <%= (typeof direction !== 'undefined' && direction === 'wc_to_zoho') ? 'selected' : '' %>><?php echo esc_js(__('WC → Zoho', 'woocommerce-zoho-integration')); ?></option>
                    <option value="zoho_to_wc" <%= (typeof direction !== 'undefined' && direction === 'zoho_to_wc') ? 'selected' : '' %>><?php echo esc_js(__('Zoho → WC', 'woocommerce-zoho-integration')); ?></option>
                    <option value="both" <%= (typeof direction !== 'undefined' && direction === 'both') ? 'selected' : '' %>><?php echo esc_js(__('Ambos (Bidireccional)', 'woocommerce-zoho-integration')); ?></option>
                </select>
            </td>
            <td>
                <button type="button" class="button button-link-delete wzi-remove-mapping-row" title="<?php echo esc_attr__('Quitar este mapeo', 'woocommerce-zoho-integration'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </td>
        </tr>
    </script>

    <?php
        // TODO: Mover este JS a un archivo admin.js y localizar los datos y nonces necesarios.
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var wcFieldsCache = {};
            var zohoFieldsCache = {};
            var currentMappings = [];

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
                    { value: 'Customers', text: '<?php echo esc_js(__('Customers (Books)', 'woocommerce-zoho-integration')); ?>' },
                    { value: 'Items', text: '<?php echo esc_js(__('Items (Books)', 'woocommerce-zoho-integration')); ?>' }
                ]
            };

            function updateLoadButtonState() {
                if ($('#wzi_wc_module_select').val() && $('#wzi_zoho_module_select').val() && $('#wzi_zoho_service_select').val()) {
                    $('#wzi_load_mapping_button').prop('disabled', false);
                } else {
                    $('#wzi_load_mapping_button').prop('disabled', true);
                }
            }

            $('#wzi_zoho_service_select').on('change', function() {
                var service = $(this).val();
                var $zohoModuleSelect = $('#wzi_zoho_module_select');
                var currentZohoModuleVal = $zohoModuleSelect.val(); // Guardar valor actual si existe
                $zohoModuleSelect.html('<option value=""><?php esc_html_e('-- Seleccionar Módulo Zoho --', 'woocommerce-zoho-integration'); ?></option>');

                if (service && zohoModulesByService[service]) {
                    zohoModulesByService[service].forEach(function(module) {
                        $zohoModuleSelect.append($('<option>', { value: module.value, text: module.text }));
                    });
                    $zohoModuleSelect.val(currentZohoModuleVal); // Intentar restaurar valor
                    $zohoModuleSelect.prop('disabled', false);
                } else {
                    $zohoModuleSelect.prop('disabled', true);
                }
                updateLoadButtonState();
                $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').hide();
                $('.wzi-no-mapping-loaded').show();
            }).trigger('change');

            $('#wzi_wc_module_select, #wzi_zoho_module_select').on('change', function() {
                updateLoadButtonState();
                 $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').hide();
                 $('.wzi-no-mapping-loaded').show();
            });

            $('#wzi_load_mapping_button').on('click', function() {
                var wcModule = $('#wzi_wc_module_select').val();
                var zohoService = $('#wzi_zoho_service_select').val();
                var zohoModule = $('#wzi_zoho_module_select').val();
                var $button = $(this);
                var $spinner = $button.find('.spinner');
                var $container = $('#wzi_field_mapping_table_container');
                var $feedback = $('#wzi-mapping-feedback');

                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $('.wzi-no-mapping-loaded').hide();
                $('#wzi-mapping-table-render-area').hide();
                $container.find('.notice').remove(); // Limpiar errores previos en el contenedor
                $container.append('<p class="wzi-loading-fields"><i><?php echo esc_js(__("Cargando campos y mapeos...", "woocommerce-zoho-integration")); ?></i></p>');
                $feedback.html('').removeClass('error success notice notice-error notice-success is-dismissible');

                // Simulación de llamadas AJAX
                var ajaxWcFields = $.ajax({
                    url: wzi_admin.ajax_url,
                    type: 'POST',
                    data: { action: 'wzi_get_wc_module_fields', nonce: wzi_admin.nonce, wc_module: wcModule }
                });
                var ajaxZohoFields = $.ajax({
                    url: wzi_admin.ajax_url,
                    type: 'POST',
                    data: { action: 'wzi_get_zoho_module_fields', nonce: wzi_admin.nonce, zoho_service: zohoService, zoho_module: zohoModule }
                });
                var ajaxLoadMappings = $.ajax({
                    url: wzi_admin.ajax_url,
                    type: 'POST',
                    data: { action: 'wzi_load_field_mappings', nonce: wzi_admin.nonce, wc_module: wcModule, zoho_service: zohoService, zoho_module: zohoModule }
                });

                $.when(ajaxWcFields, ajaxZohoFields, ajaxLoadMappings).done(function(wcRes, zohoRes, mappingsRes) {
                    var wcFields = (wcRes[0].success && wcRes[0].data) ? wcRes[0].data : null;
                    var zohoFields = (zohoRes[0].success && zohoRes[0].data) ? zohoRes[0].data : null;
                    currentMappings = (mappingsRes[0].success && mappingsRes[0].data) ? mappingsRes[0].data : [];

                    wcFieldsCache[wcModule] = wcFields; // Cachear
                    zohoFieldsCache[zohoService + '_' + zohoModule] = zohoFields; // Cachear

                    if (!wcFields || wcFields.length === 0) {
                         $container.append('<p class="notice notice-warning"><?php echo esc_js(__("No se pudieron cargar los campos de WooCommerce o no hay campos definidos.", "woocommerce-zoho-integration")); ?></p>');
                    }
                    if (!zohoFields || zohoFields.length === 0) {
                         $container.append('<p class="notice notice-warning"><?php echo esc_js(__("No se pudieron cargar los campos de Zoho o no hay campos definidos.", "woocommerce-zoho-integration")); ?></p>');
                    }

                    if (wcFields && zohoFields && wcFields.length > 0 && zohoFields.length > 0) {
                        renderMappingTable(wcFields, zohoFields, currentMappings);
                        $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').show();
                    } else {
                        $('.wzi-no-mapping-loaded').show();
                         $('#wzi-mapping-table-render-area, .wzi-save-mapping-controls').hide();
                    }

                }).fail(function(jqXHR, textStatus, errorThrown) {
                    var errorDetail = jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? jqXHR.responseJSON.data.message : errorThrown;
                    $container.append('<p class="notice notice-error"><?php echo esc_js(__("Error AJAX al cargar la estructura de campos: ", "woocommerce-zoho-integration")); ?>' + errorDetail + '</p>');
                     $('.wzi-no-mapping-loaded').show();
                }).always(function() {
                    $button.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    $container.find('.wzi-loading-fields').remove();
                });
            });

            var rowTemplate = _.template($('#wzi-mapping-row-template').html());
            function renderMappingTable(wcFields, zohoFields, mappings) {
                var $tbody = $('#wzi-field-mapping-rows');
                $tbody.empty();

                var effectiveMappings = mappings.length > 0 ? mappings : [{ wc_field: '', wc_custom_meta: '', zoho_field: '', direction: 'wc_to_zoho' }];

                effectiveMappings.forEach(function(mapping, index) {
                    var rowHtml = rowTemplate({
                        index: index,
                        wcFields: wcFields,
                        zohoFields: zohoFields,
                        wc_field: mapping.wc_field,
                        wc_custom_meta: mapping.wc_custom_meta,
                        zoho_field: mapping.zoho_field,
                        direction: mapping.direction
                    });
                    var $row = $(rowHtml);
                    $tbody.append($row);
                    // Trigger change para que se muestre/oculte el campo de meta personalizado si es necesario
                    $row.find('.wzi-wc-field-select').trigger('change');
                });
            }

            $('#wzi_add_mapping_row_button').on('click', function() {
                var wcModule = $('#wzi_wc_module_select').val();
                var zohoService = $('#wzi_zoho_service_select').val();
                var zohoModule = $('#wzi_zoho_module_select').val();

                var wcFields = wcFieldsCache[wcModule];
                var zohoFields = zohoFieldsCache[zohoService + '_' + zohoModule];

                if (!wcFields || !zohoFields) {
                    alert('<?php echo esc_js(__("Por favor, cargue los campos primero.", "woocommerce-zoho-integration")); ?>');
                    return;
                }
                var newIndex = $('#wzi-field-mapping-rows tr').length;
                var rowHtml = rowTemplate({ index: newIndex, wcFields: wcFields, zohoFields: zohoFields, wc_field: '', wc_custom_meta: '', zoho_field: '', direction: 'wc_to_zoho' });
                $('#wzi-field-mapping-rows').append(rowHtml);
            });

            $('#wzi_field_mapping_table_container').on('click', '.wzi-remove-mapping-row', function() {
                $(this).closest('tr.wzi-mapping-row').remove();
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
                var $spinner = $button.find('.spinner');
                var $feedback = $('#wzi-mapping-feedback');

                var mappingsData = [];
                $('#wzi-field-mapping-rows tr').each(function(index) {
                    var $row = $(this);
                    var wcField = $row.find('.wzi-wc-field-select').val();
                    var wcCustomMeta = '';
                    if (wcField === '_custom_meta_') {
                        wcCustomMeta = $row.find('.wzi-wc-custom-meta-field').val();
                        if(!wcCustomMeta) {
                            // Considerar validación: si es _custom_meta_ pero el input está vacío
                            // $feedback.html('<p class="notice notice-error"><?php echo esc_js(__("Por favor, especifique el nombre del Meta Key para los campos personalizados.", "woocommerce-zoho-integration")); ?></p>').addClass('is-dismissible');
                            // return false; // Detener el guardado
                        }
                    }
                    var zohoField = $row.find('.wzi-zoho-field-select').val();
                    if (!wcField || !zohoField) { // No guardar filas incompletas (a menos que se permita explícitamente)
                        return true; // Continuar al siguiente .each()
                    }

                    mappingsData.push({
                        wc_field: wcField,
                        wc_custom_meta: wcCustomMeta,
                        zoho_field: zohoField,
                        direction: $row.find('select[name*="[direction]"]').val()
                    });
                });

                if (mappingsData.length === 0) {
                    if (!confirm('<?php echo esc_js(__("No hay mapeos definidos. ¿Desea guardar una configuración de mapeo vacía para estos módulos (esto eliminará los mapeos existentes)?", "woocommerce-zoho-integration")); ?>')) {
                        return;
                    }
                }

                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $feedback.html('').removeClass('error success notice notice-error notice-success is-dismissible');

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
                        $spinner.removeClass('is-active');
                        $button.prop('disabled', false);
                    }
                });
            });

            if (typeof _ === 'undefined' && typeof wp !== 'undefined' && wp.template) {
                 _ = { template: wp.template };
            } else if (typeof _ === 'undefined') {
                console.error("Underscore.js (o wp.template) no está disponible para las plantillas de mapeo.");
            }
        });
    </script>
</div>
