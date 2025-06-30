<?php
/**
 * Integración con Zoho Inventory
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 */

/**
 * Integración con Zoho Inventory.
 *
 * Esta clase maneja todas las operaciones con la API de Zoho Inventory
 * incluyendo productos, órdenes de venta, contactos y ajustes de inventario.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Zoho_Inventory extends WZI_API_Handler {

    /**
     * ID de la organización.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $organization_id    ID de la organización en Zoho.
     */
    private $organization_id;

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        parent::__construct('inventory');
        
        // Obtener ID de organización
        $this->organization_id = get_option('wzi_inventory_organization_id', '');
        
        // Si no hay ID de organización, intentar obtenerlo
        if (empty($this->organization_id)) {
            $this->fetch_organization_id();
        }
    }

    /**
     * Probar conexión con Zoho Inventory.
     *
     * @since    1.0.0
     * @return   bool    Si la conexión es exitosa.
     */
    public function test_connection() {
        $response = $this->get('organizations');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return isset($response['data']['organizations']) && !empty($response['data']['organizations']);
    }

    /**
     * Obtener y guardar ID de organización.
     *
     * @since    1.0.0
     * @return   string|false    ID de organización o false.
     */
    private function fetch_organization_id() {
        $response = $this->get('organizations');
        
        if (is_wp_error($response)) {
            return false;
        }
        
        if (isset($response['data']['organizations']) && !empty($response['data']['organizations'])) {
            // Usar la primera organización
            $org = $response['data']['organizations'][0];
            $this->organization_id = $org['organization_id'];
            
            // Guardar para uso futuro
            update_option('wzi_inventory_organization_id', $this->organization_id);
            
            return $this->organization_id;
        }
        
        return false;
    }

    /**
     * Añadir organización ID a los parámetros.
     *
     * @since    1.0.0
     * @param    array    $params    Parámetros existentes.
     * @return   array               Parámetros con organization_id.
     */
    private function add_organization_id($params = array()) {
        if (!empty($this->organization_id)) {
            $params['organization_id'] = $this->organization_id;
        }
        return $params;
    }

    /**
     * GET request con organization_id.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @param    array     $params      Parámetros.
     * @return   array|WP_Error         Respuesta.
     */
    public function get($endpoint, $params = array()) {
        $params = $this->add_organization_id($params);
        return parent::get($endpoint, $params);
    }

    /**
     * POST request con organization_id.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @param    array     $data        Datos.
     * @return   array|WP_Error         Respuesta.
     */
    public function post($endpoint, $data = array()) {
        $params = $this->add_organization_id();
        $endpoint = add_query_arg($params, $endpoint);
        return parent::post($endpoint, $data);
    }

    /**
     * PUT request con organization_id.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @param    array     $data        Datos.
     * @return   array|WP_Error         Respuesta.
     */
    public function put($endpoint, $data = array()) {
        $params = $this->add_organization_id();
        $endpoint = add_query_arg($params, $endpoint);
        return parent::put($endpoint, $data);
    }

    /**
     * DELETE request con organization_id.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @return   array|WP_Error         Respuesta.
     */
    public function delete($endpoint) {
        $params = $this->add_organization_id();
        $endpoint = add_query_arg($params, $endpoint);
        return parent::delete($endpoint);
    }

    /**
     * Obtener todos los items (productos).
     *
     * @since    1.0.0
     * @param    array    $params    Parámetros adicionales.
     * @return   array|WP_Error      Items.
     */
    public function get_items($params = array()) {
        return $this->get_all_pages('items', $params, 'items');
    }

    /**
     * Obtener un item por ID.
     *
     * @since    1.0.0
     * @param    string    $item_id    ID del item.
     * @return   array|WP_Error        Datos del item.
     */
    public function get_item($item_id) {
        $response = $this->get('items/' . $item_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['item']) ? $response['data']['item'] : null;
    }

    /**
     * Buscar items.
     *
     * @since    1.0.0
     * @param    string    $search_text    Texto de búsqueda.
     * @param    array     $params         Parámetros adicionales.
     * @return   array|WP_Error            Items encontrados.
     */
    public function search_items($search_text, $params = array()) {
        $params['search_text'] = $search_text;
        $response = $this->get('items', $params);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['items']) ? $response['data']['items'] : array();
    }

    /**
     * Crear item desde producto de WooCommerce.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto de WooCommerce.
     * @return   array|WP_Error           Item creado.
     */
    public function create_item_from_product($product) {
        $data = $this->prepare_item_data($product);
        
        $response = $this->post('items', array('JSONString' => json_encode($data)));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['item'])) {
            // Limpiar caché
            $this->clear_cache('items');
            
            return $response['data']['item'];
        }
        
        return new WP_Error('create_failed', __('No se pudo crear el item', 'woocommerce-zoho-integration'));
    }

    /**
     * Actualizar item.
     *
     * @since    1.0.0
     * @param    string        $item_id    ID del item.
     * @param    WC_Product    $product    Producto de WooCommerce.
     * @return   array|WP_Error           Item actualizado.
     */
    public function update_item_from_product($item_id, $product) {
        $data = $this->prepare_item_data($product);
        
        $response = $this->put('items/' . $item_id, array('JSONString' => json_encode($data)));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['item'])) {
            // Limpiar caché
            $this->clear_cache('items');
            
            return $response['data']['item'];
        }
        
        return new WP_Error('update_failed', __('No se pudo actualizar el item', 'woocommerce-zoho-integration'));
    }

    /**
     * Eliminar item.
     *
     * @since    1.0.0
     * @param    string    $item_id    ID del item.
     * @return   bool|WP_Error        Resultado.
     */
    public function delete_item($item_id) {
        $response = $this->delete('items/' . $item_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Limpiar caché
        $this->clear_cache('items');
        
        return true;
    }

    /**
     * Actualizar stock de item.
     *
     * @since    1.0.0
     * @param    string    $item_id         ID del item.
     * @param    int       $quantity        Nueva cantidad.
     * @param    string    $warehouse_id    ID del almacén (opcional).
     * @return   bool|WP_Error             Resultado.
     */
    public function update_item_stock($item_id, $quantity, $warehouse_id = null) {
        // Primero obtener el item para conocer el stock actual
        $item = $this->get_item($item_id);
        
        if (is_wp_error($item)) {
            return $item;
        }
        
        if (!$item) {
            return new WP_Error('item_not_found', __('Item no encontrado', 'woocommerce-zoho-integration'));
        }
        
        // Calcular ajuste
        $current_stock = isset($item['stock_on_hand']) ? intval($item['stock_on_hand']) : 0;
        $adjustment = $quantity - $current_stock;
        
        if ($adjustment == 0) {
            return true; // No hay cambio
        }
        
        // Crear ajuste de inventario
        $adjustment_data = array(
            'date' => date('Y-m-d'),
            'reason' => 'WooCommerce Sync',
            'description' => sprintf(
                __('Stock actualizado desde WooCommerce. Anterior: %d, Nuevo: %d', 'woocommerce-zoho-integration'),
                $current_stock,
                $quantity
            ),
            'line_items' => array(
                array(
                    'item_id' => $item_id,
                    'quantity_adjusted' => $adjustment,
                )
            ),
        );
        
        if ($warehouse_id) {
            $adjustment_data['line_items'][0]['warehouse_id'] = $warehouse_id;
        }
        
        $response = $this->post('inventoryadjustments', array('JSONString' => json_encode($adjustment_data)));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['inventory_adjustment']);
    }

    /**
     * Obtener todas las órdenes de venta.
     *
     * @since    1.0.0
     * @param    array    $params    Parámetros.
     * @return   array|WP_Error      Órdenes de venta.
     */
    public function get_salesorders($params = array()) {
        return $this->get_all_pages('salesorders', $params, 'salesorders');
    }

    /**
     * Obtener una orden de venta.
     *
     * @since    1.0.0
     * @param    string    $salesorder_id    ID de la orden.
     * @return   array|WP_Error             Orden de venta.
     */
    public function get_salesorder($salesorder_id) {
        $response = $this->get('salesorders/' . $salesorder_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['salesorder']) ? $response['data']['salesorder'] : null;
    }

    /**
     * Crear orden de venta desde pedido de WooCommerce.
     *
     * @since    1.0.0
     * @param    WC_Order    $order         Pedido de WooCommerce.
     * @param    string      $contact_id    ID del contacto en Inventory.
     * @return   array|WP_Error             Orden de venta creada.
     */
    public function create_salesorder_from_order($order, $contact_id = null) {
        // Primero, asegurar que el contacto existe
        if (!$contact_id) {
            $contact = $this->create_or_update_contact_from_order($order);
            if (is_wp_error($contact)) {
                return $contact;
            }
            $contact_id = $contact['contact_id'];
        }
        
        $data = $this->prepare_salesorder_data($order, $contact_id);
        
        $response = $this->post('salesorders', array('JSONString' => json_encode($data)));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['salesorder'])) {
            return $response['data']['salesorder'];
        }
        
        return new WP_Error('create_failed', __('No se pudo crear la orden de venta', 'woocommerce-zoho-integration'));
    }

    /**
     * Actualizar orden de venta.
     *
     * @since    1.0.0
     * @param    string      $salesorder_id    ID de la orden.
     * @param    WC_Order    $order            Pedido de WooCommerce.
     * @return   array|WP_Error                Orden actualizada.
     */
    public function update_salesorder_from_order($salesorder_id, $order) {
        // Obtener orden existente para mantener contact_id
        $existing = $this->get_salesorder($salesorder_id);
        
        if (is_wp_error($existing)) {
            return $existing;
        }
        
        $contact_id = isset($existing['customer_id']) ? $existing['customer_id'] : null;
        
        $data = $this->prepare_salesorder_data($order, $contact_id);
        
        $response = $this->put('salesorders/' . $salesorder_id, array('JSONString' => json_encode($data)));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['salesorder'])) {
            return $response['data']['salesorder'];
        }
        
        return new WP_Error('update_failed', __('No se pudo actualizar la orden de venta', 'woocommerce-zoho-integration'));
    }

    /**
     * Obtener todos los contactos.
     *
     * @since    1.0.0
     * @param    array    $params    Parámetros.
     * @return   array|WP_Error      Contactos.
     */
    public function get_contacts($params = array()) {
        return $this->get_all_pages('contacts', $params, 'contacts');
    }

    /**
     * Buscar contacto por email.
     *
     * @since    1.0.0
     * @param    string    $email    Email del contacto.
     * @return   array|null          Contacto encontrado o null.
     */
    public function search_contact_by_email($email) {
        $response = $this->get('contacts', array('email' => $email));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        if (isset($response['data']['contacts']) && !empty($response['data']['contacts'])) {
            return $response['data']['contacts'][0];
        }
        
        return null;
    }

    /**
     * Crear o actualizar contacto desde pedido.
     *
     * @since    1.0.0
     * @param    WC_Order    $order    Pedido.
     * @return   array|WP_Error        Contacto.
     */
    public function create_or_update_contact_from_order($order) {
        $email = $order->get_billing_email();
        
        // Buscar contacto existente
        $existing = $this->search_contact_by_email($email);
        
        $data = array(
            'contact_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'company_name' => $order->get_billing_company(),
            'email' => $email,
            'phone' => $order->get_billing_phone(),
            'billing_address' => array(
                'address' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'zip' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
            ),
            'shipping_address' => array(
                'address' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'zip' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ),
        );
        
        if ($existing) {
            // Actualizar contacto existente
            $response = $this->put('contacts/' . $existing['contact_id'], array('JSONString' => json_encode($data)));
        } else {
            // Crear nuevo contacto
            $response = $this->post('contacts', array('JSONString' => json_encode($data)));
        }
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['data']['contact']) ? $response['data']['contact'] : $existing;
    }

    /**
     * Preparar datos de item desde producto.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   array                     Datos del item.
     */
    private function prepare_item_data($product) {
        $data = array(
            'name' => $product->get_name(),
            'sku' => $product->get_sku() ?: 'WC-' . $product->get_id(),
            'unit' => 'pcs',
            'item_type' => 'inventory',
            'description' => $product->get_short_description(),
            'rate' => $product->get_regular_price(),
            'purchase_rate' => $product->get_meta('_purchase_price') ?: $product->get_regular_price() * 0.6,
            'reorder_level' => $product->get_low_stock_amount() ?: 5,
            'initial_stock' => $product->get_stock_quantity() ?: 0,
        );
        
        // Añadir categorías como grupos
        $categories = $product->get_category_ids();
        if (!empty($categories)) {
            $category = get_term($categories[0], 'product_cat');
            if ($category && !is_wp_error($category)) {
                $data['group_name'] = $category->name;
            }
        }
        
        // Información de impuestos
        if ($product->is_taxable()) {
            $data['is_taxable'] = true;
            // Aquí podrías mapear las clases de impuestos de WooCommerce a las de Zoho
        }
        
        // Aplicar filtro para personalización
        $data = apply_filters('wzi_product_to_inventory_item', $data, $product);
        
        return $data;
    }

    /**
     * Preparar datos de orden de venta.
     *
     * @since    1.0.0
     * @param    WC_Order    $order        Pedido.
     * @param    string      $contact_id   ID del contacto.
     * @return   array                     Datos de la orden.
     */
    private function prepare_salesorder_data($order, $contact_id) {
        // Preparar líneas de items
        $line_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (!$product) {
                continue;
            }
            
            // Buscar item en Inventory
            $sku = $product->get_sku() ?: 'WC-' . $product->get_id();
            $inventory_items = $this->search_items($sku);
            
            $line_item = array(
                'name' => $item->get_name(),
                'description' => $item->get_name(),
                'rate' => $item->get_subtotal() / $item->get_quantity(),
                'quantity' => $item->get_quantity(),
                'unit' => 'pcs',
            );
            
            if (!is_wp_error($inventory_items) && !empty($inventory_items)) {
                $line_item['item_id'] = $inventory_items[0]['item_id'];
            }
            
            // Descuento
            $discount = $item->get_subtotal() - $item->get_total();
            if ($discount > 0) {
                $line_item['discount'] = ($discount / $item->get_subtotal()) * 100;
            }
            
            $line_items[] = $line_item;
        }
        
        // Añadir línea de envío si existe
        if ($order->get_shipping_total() > 0) {
            $line_items[] = array(
                'name' => __('Envío', 'woocommerce-zoho-integration'),
                'description' => $order->get_shipping_method(),
                'rate' => $order->get_shipping_total(),
                'quantity' => 1,
                'unit' => 'pcs',
            );
        }
        
        $data = array(
            'customer_id' => $contact_id,
            'salesorder_number' => 'SO-' . $order->get_order_number(),
            'reference_number' => $order->get_order_number(),
            'date' => $order->get_date_created()->format('Y-m-d'),
            'shipment_date' => $order->get_date_created()->format('Y-m-d'),
            'line_items' => $line_items,
            'notes' => $order->get_customer_note(),
            'terms' => $order->get_payment_method_title(),
            'shipping_charge' => $order->get_shipping_total(),
            'adjustment' => 0,
            'adjustment_description' => '',
        );
        
        // Estado de la orden
        $status_mapping = array(
            'pending' => 'draft',
            'processing' => 'confirmed',
            'on-hold' => 'draft',
            'completed' => 'confirmed',
            'cancelled' => 'void',
            'refunded' => 'void',
            'failed' => 'void',
        );
        
        $data['status'] = isset($status_mapping[$order->get_status()]) 
            ? $status_mapping[$order->get_status()] 
            : 'draft';
        
        // Aplicar filtro para personalización
        $data = apply_filters('wzi_order_to_inventory_salesorder', $data, $order);
        
        return $data;
    }

    /**
     * Obtener almacenes.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Almacenes.
     */
    public function get_warehouses() {
        $cached = $this->cache('warehouses');
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->get('settings/warehouses');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['warehouses'])) {
            return $this->cache('warehouses', $response['data']['warehouses'], 3600);
        }
        
        return array();
    }

    /**
     * Obtener configuración de impuestos.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Configuración de impuestos.
     */
    public function get_taxes() {
        $cached = $this->cache('taxes');
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->get('settings/taxes');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['taxes'])) {
            return $this->cache('taxes', $response['data']['taxes'], 3600);
        }
        
        return array();
    }

    /**
     * Obtener monedas.
     *
     * @since    1.0.0
     * @return   array|WP_Error    Monedas.
     */
    public function get_currencies() {
        $cached = $this->cache('currencies');
        if ($cached !== false) {
            return $cached;
        }
        
        $response = $this->get('settings/currencies');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data']['currencies'])) {
            return $this->cache('currencies', $response['data']['currencies'], 86400);
        }
        
        return array();
    }
}