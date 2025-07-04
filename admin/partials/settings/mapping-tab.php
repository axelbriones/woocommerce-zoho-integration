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
    ?>

</div>
