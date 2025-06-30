<?php
/**
 * Integración con Zoho CRM
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 */

/**
 * Integración con Zoho CRM.
 *
 * Esta clase maneja todas las operaciones con la API de Zoho CRM
 * incluyendo contactos, leads, deals y pedidos.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Zoho_CRM extends WZI_API_Handler {

    /**
     * Módulos disponibles en CRM.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $modules    Módulos de CRM.
     */
    private $modules = array(
        'Contacts',
        'Leads',
        'Accounts',
        'Deals',
        'Sales_Orders',
        'Products',
        'Price_Books',
        'Quotes',
        'Invoices',
    );

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        parent::__construct('crm');
    }

    /**
     * Probar conexión con Zoho CRM.
     *
     * @since    1.0.0
     * @return   bool    Si la conexión es exitosa.
     */
    public function test_connection() {
        $response = $this->get('users?type=CurrentUser');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return isset($response['data']['users']) && !empty($response['data']['users']);
    }

    /**
     * Obtener información de la organización.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Información de la organización.
     */
    public function get_organization_info() {
        $cached = $this->cache('organization_info');
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->get('org');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['org'])) {
            return $this->cache('organization_info', $response['data']['org'][0], 3600);
        }
        
        return new WP_Error('no_org_info', __('No se pudo obtener información de la organización', 'woocommerce-zoho-integration'));
    }

    /**
     * Obtener campos de un módulo.
     *
     * @since    1.0.0
     * @param    string    $module    Nombre del módulo.
     * @return   array|WP_Error       Campos del módulo.
     */
    public function get_module_fields($module) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        $cached = $this->cache('fields_' . $module);
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->get('settings/fields', array('module' => $module));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['fields'])) {
            return $this->cache('fields_' . $module, $response['data']['fields'], 86400);
        }
        
        return array();
    }

    /**
     * Buscar registros.
     *
     * @since    1.0.0
     * @param    string    $module     Módulo.
     * @param    array     $criteria   Criterios de búsqueda.
     * @param    array     $params     Parámetros adicionales.
     * @return   array|WP_Error        Registros encontrados.
     */
    public function search_records($module, $criteria, $params = array()) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        $default_params = array(
            'page' => 1,
            'per_page' => 200,
        );
        
        $params = array_merge($default_params, $params);
        
        // Construir query de búsqueda
        if (is_array($criteria)) {
            $search_criteria = array();
            foreach ($criteria as $field => $value) {
                $search_criteria[] = sprintf("(%s:equals:%s)", $field, $value);
            }
            $params['criteria'] = implode('and', $search_criteria);
        } else {
            $params['criteria'] = $criteria;
        }
        
        $response = $this->get($module . '/search', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['data']) ? $response['data']['data'] : array();
    }

    /**
     * Obtener un registro por ID.
     *
     * @since    1.0.0
     * @param    string    $module    Módulo.
     * @param    string    $id        ID del registro.
     * @return   array|WP_Error       Datos del registro.
     */
    public function get_record($module, $id) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        $response = $this->get($module . '/' . $id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['data'][0]) ? $response['data']['data'][0] : null;
    }

    /**
     * Crear registro.
     *
     * @since    1.0.0
     * @param    string    $module    Módulo.
     * @param    array     $data      Datos del registro.
     * @return   array|WP_Error       Registro creado.
     */
    public function create_record($module, $data) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        $formatted_data = $this->format_data_for_zoho($data);
        
        $body = array(
            'data' => array($formatted_data),
        );
        
        $response = $this->post($module, $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['data'][0])) {
            $record = $response['data']['data'][0];
            
            // Limpiar cache relacionado
            $this->clear_cache($module);
            
            return $record;
        }
        
        return new WP_Error('create_failed', __('No se pudo crear el registro', 'woocommerce-zoho-integration'));
    }

    /**
     * Actualizar registro.
     *
     * @since    1.0.0
     * @param    string    $module    Módulo.
     * @param    string    $id        ID del registro.
     * @param    array     $data      Datos a actualizar.
     * @return   array|WP_Error       Registro actualizado.
     */
    public function update_record($module, $id, $data) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        $formatted_data = $this->format_data_for_zoho($data);
        $formatted_data['id'] = $id;
        
        $body = array(
            'data' => array($formatted_data),
        );
        
        $response = $this->put($module, $body);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['data'][0])) {
            $record = $response['data']['data'][0];
            
            // Limpiar cache relacionado
            $this->clear_cache($module);
            
            return $record;
        }
        
        return new WP_Error('update_failed', __('No se pudo actualizar el registro', 'woocommerce-zoho-integration'));
    }

    /**
     * Eliminar registro.
     *
     * @since    1.0.0
     * @param    string    $module    Módulo.
     * @param    string    $id        ID del registro.
     * @return   bool|WP_Error        Resultado de la eliminación.
     */
    public function delete_record($module, $id) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        $response = $this->delete($module . '/' . $id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Limpiar cache relacionado
        $this->clear_cache($module);
        
        return true;
    }

    /**
     * Crear o actualizar registro (upsert).
     *
     * @since    1.0.0
     * @param    string    $module          Módulo.
     * @param    array     $data            Datos del registro.
     * @param    array     $search_fields   Campos para buscar duplicados.
     * @return   array|WP_Error             Registro creado o actualizado.
     */
    public function upsert_record($module, $data, $search_fields = array('Email')) {
        // Buscar registro existente
        $search_criteria = array();
        foreach ($search_fields as $field) {
            if (isset($data[$field])) {
                $search_criteria[$field] = $data[$field];
            }
        }
        
        if (!empty($search_criteria)) {
            $existing = $this->search_records($module, $search_criteria);
            
            if (!is_wp_error($existing) && !empty($existing)) {
                // Actualizar registro existente
                $record_id = $existing[0]['id'];
                return $this->update_record($module, $record_id, $data);
            }
        }
        
        // Crear nuevo registro
        return $this->create_record($module, $data);
    }

    /**
     * Obtener todos los registros de un módulo.
     *
     * @since    1.0.0
     * @param    string    $module    Módulo.
     * @param    array     $params    Parámetros adicionales.
     * @return   array|WP_Error       Todos los registros.
     */
    public function get_all_records($module, $params = array()) {
        if (!in_array($module, $this->modules)) {
            return new WP_Error('invalid_module', __('Módulo inválido', 'woocommerce-zoho-integration'));
        }
        
        return $this->get_all_pages($module, $params, 'data');
    }

    /**
     * Crear contacto desde cliente de WooCommerce.
     *
     * @since    1.0.0
     * @param    WC_Customer    $customer    Cliente de WooCommerce.
     * @return   array|WP_Error              Contacto creado.
     */
    public function create_contact_from_customer($customer) {
        $data = array(
            'First_Name' => $customer->get_first_name(),
            'Last_Name' => $customer->get_last_name(),
            'Email' => $customer->get_email(),
            'Phone' => $customer->get_billing_phone(),
            'Mailing_Street' => $customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2(),
            'Mailing_City' => $customer->get_billing_city(),
            'Mailing_State' => $customer->get_billing_state(),
            'Mailing_Zip' => $customer->get_billing_postcode(),
            'Mailing_Country' => $customer->get_billing_country(),
            'Description' => sprintf(__('Cliente importado desde WooCommerce (ID: %d)', 'woocommerce-zoho-integration'), $customer->get_id()),
        );
        
        // Aplicar filtro para personalización
        $data = apply_filters('wzi_crm_contact_data', $data, $customer);
        
        return $this->upsert_record('Contacts', $data, array('Email'));
    }

    /**
     * Crear deal desde pedido de WooCommerce.
     *
     * @since    1.0.0
     * @param    WC_Order      $order    Pedido de WooCommerce.
     * @param    string        $contact_id    ID del contacto en CRM.
     * @return   array|WP_Error           Deal creado.
     */
    public function create_deal_from_order($order, $contact_id = null) {
        $data = array(
            'Deal_Name' => sprintf(__('Pedido #%s', 'woocommerce-zoho-integration'), $order->get_order_number()),
            'Amount' => $order->get_total(),
            'Stage' => $this->map_order_status_to_stage($order->get_status()),
            'Closing_Date' => $order->get_date_created()->format('Y-m-d'),
            'Description' => $this->get_order_description($order),
        );
        
        // Asociar con contacto si existe
        if ($contact_id) {
            $data['Contact_Name'] = array('id' => $contact_id);
        }
        
        // Aplicar filtro para personalización
        $data = apply_filters('wzi_crm_deal_data', $data, $order);
        
        // Buscar deal existente
        $existing = $this->search_records('Deals', array(
            'Deal_Name' => $data['Deal_Name']
        ));
        
        if (!is_wp_error($existing) && !empty($existing)) {
            return $this->update_record('Deals', $existing[0]['id'], $data);
        }
        
        return $this->create_record('Deals', $data);
    }

    /**
     * Crear orden de venta desde pedido de WooCommerce.
     *
     * @since    1.0.0
     * @param    WC_Order      $order         Pedido de WooCommerce.
     * @param    string        $contact_id    ID del contacto en CRM.
     * @return   array|WP_Error              Orden de venta creada.
     */
    public function create_sales_order_from_order($order, $contact_id = null) {
        // Preparar líneas de productos
        $product_details = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            $line_item = array(
                'product' => array(
                    'Product_Code' => $product->get_sku(),
                    'name' => $product->get_name(),
                ),
                'quantity' => $item->get_quantity(),
                'list_price' => $item->get_subtotal() / $item->get_quantity(),
                'total' => $item->get_total(),
                'discount' => $item->get_subtotal() - $item->get_total(),
            );
            
            // Buscar producto en CRM
            $crm_product = $this->search_records('Products', array(
                'Product_Code' => $product->get_sku()
            ));
            
            if (!is_wp_error($crm_product) && !empty($crm_product)) {
                $line_item['product']['id'] = $crm_product[0]['id'];
            }
            
            $product_details[] = $line_item;
        }
        
        $data = array(
            'Subject' => sprintf(__('Pedido #%s', 'woocommerce-zoho-integration'), $order->get_order_number()),
            'Status' => $this->map_order_status_to_so_status($order->get_status()),
            'Due_Date' => $order->get_date_created()->format('Y-m-d'),
            'Sub_Total' => $order->get_subtotal(),
            'Tax' => $order->get_total_tax(),
            'Adjustment' => $order->get_total_discount() * -1,
            'Grand_Total' => $order->get_total(),
            'Billing_Street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'Billing_City' => $order->get_billing_city(),
            'Billing_State' => $order->get_billing_state(),
            'Billing_Code' => $order->get_billing_postcode(),
            'Billing_Country' => $order->get_billing_country(),
            'Shipping_Street' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
            'Shipping_City' => $order->get_shipping_city(),
            'Shipping_State' => $order->get_shipping_state(),
            'Shipping_Code' => $order->get_shipping_postcode(),
            'Shipping_Country' => $order->get_shipping_country(),
            'Product_Details' => $product_details,
        );
        
        // Asociar con contacto si existe
        if ($contact_id) {
            $data['Contact_Name'] = array('id' => $contact_id);
        }
        
        // Aplicar filtro para personalización
        $data = apply_filters('wzi_crm_sales_order_data', $data, $order);
        
        // Buscar orden existente
        $existing = $this->search_records('Sales_Orders', array(
            'Subject' => $data['Subject']
        ));
        
        if (!is_wp_error($existing) && !empty($existing)) {
            return $this->update_record('Sales_Orders', $existing[0]['id'], $data);
        }
        
        return $this->create_record('Sales_Orders', $data);
    }

    /**
     * Mapear estado de pedido a etapa de deal.
     *
     * @since    1.0.0
     * @param    string    $status    Estado del pedido.
     * @return   string              Etapa del deal.
     */
    private function map_order_status_to_stage($status) {
        $mapping = array(
            'pending' => 'Qualification',
            'processing' => 'Proposal/Price Quote',
            'on-hold' => 'Negotiation/Review',
            'completed' => 'Closed Won',
            'cancelled' => 'Closed Lost',
            'refunded' => 'Closed Lost',
            'failed' => 'Closed Lost',
        );
        
        $stage = isset($mapping[$status]) ? $mapping[$status] : 'Qualification';
        
        return apply_filters('wzi_crm_order_stage_mapping', $stage, $status);
    }

    /**
     * Mapear estado de pedido a estado de orden de venta.
     *
     * @since    1.0.0
     * @param    string    $status    Estado del pedido.
     * @return   string              Estado de la orden de venta.
     */
    private function map_order_status_to_so_status($status) {
        $mapping = array(
            'pending' => 'Created',
            'processing' => 'Approved',
            'on-hold' => 'Created',
            'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Cancelled',
            'failed' => 'Cancelled',
        );
        
        $so_status = isset($mapping[$status]) ? $mapping[$status] : 'Created';
        
        return apply_filters('wzi_crm_so_status_mapping', $so_status, $status);
    }

    /**
     * Obtener descripción del pedido.
     *
     * @since    1.0.0
     * @param    WC_Order    $order    Pedido.
     * @return   string                Descripción.
     */
    private function get_order_description($order) {
        $items = array();
        
        foreach ($order->get_items() as $item) {
            $items[] = sprintf('%s x %d', $item->get_name(), $item->get_quantity());
        }
        
        $description = sprintf(
            __("Pedido #%s\nFecha: %s\nProductos:\n%s\n\nTotal: %s", 'woocommerce-zoho-integration'),
            $order->get_order_number(),
            $order->get_date_created()->format('Y-m-d H:i:s'),
            implode("\n", $items),
            $order->get_formatted_order_total()
        );
        
        if ($order->get_customer_note()) {
            $description .= "\n\n" . __('Nota del cliente:', 'woocommerce-zoho-integration') . "\n" . $order->get_customer_note();
        }
        
        return $description;
    }

    /**
     * Obtener productos desde CRM.
     *
     * @since    1.0.0
     * @param    array    $params    Parámetros de búsqueda.
     * @return   array|WP_Error      Productos.
     */
    public function get_products($params = array()) {
        return $this->get_all_records('Products', $params);
    }

    /**
     * Crear producto en CRM.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto de WooCommerce.
     * @return   array|WP_Error           Producto creado.
     */
    public function create_product_from_wc_product($product) {
        $data = array(
            'Product_Name' => $product->get_name(),
            'Product_Code' => $product->get_sku(),
            'Product_Active' => $product->get_status() === 'publish' ? 'true' : 'false',
            'Product_Category' => $this->get_product_category($product),
            'Unit_Price' => $product->get_regular_price(),
            'Description' => $product->get_description(),
            'Qty_in_Stock' => $product->get_stock_quantity(),
        );
        
        // Aplicar filtro para personalización
        $data = apply_filters('wzi_crm_product_data', $data, $product);
        
        return $this->upsert_record('Products', $data, array('Product_Code'));
    }

    /**
     * Obtener categoría del producto.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   string                   Categoría.
     */
    private function get_product_category($product) {
        $categories = $product->get_category_ids();
        
        if (!empty($categories)) {
            $term = get_term($categories[0], 'product_cat');
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }
        
        return __('Sin categoría', 'woocommerce-zoho-integration');
    }

    /**
     * Sincronizar campos personalizados.
     *
     * @since    1.0.0
     * @param    string    $module    Módulo.
     * @param    string    $record_id    ID del registro.
     * @param    array     $custom_fields    Campos personalizados.
     * @return   bool|WP_Error            Resultado.
     */
    public function sync_custom_fields($module, $record_id, $custom_fields) {
        if (empty($custom_fields)) {
            return true;
        }
        
        $data = array(
            'id' => $record_id,
        );
        
        foreach ($custom_fields as $field => $value) {
            $data[$field] = $value;
        }
        
        $response = $this->update_record($module, $record_id, $data);
        
        return !is_wp_error($response);
    }
}