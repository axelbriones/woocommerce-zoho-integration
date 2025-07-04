<?php
/**
 * Sincronización de pedidos
 *
 * @link       https://github.com/axelbriones/woocommerce-zoho-integration
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 */

/**
 * Sincronización de pedidos.
 *
 * Esta clase maneja la sincronización bidireccional de pedidos
 * entre WooCommerce y Zoho CRM (Deals y Sales Orders).
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 * @author     Axel Briones <axel@bbrion.es>
 */
class WZI_Sync_Orders {

    /**
     * API de Zoho CRM.
     * @var WZI_Zoho_CRM
     */
    private $crm_api;

    /**
     * Logger.
     * @var WZI_Logger
     */
    private $logger;

    /**
     * Sincronizador de clientes.
     * @var WZI_Sync_Customers|null
     */
    private $customer_sync;

    /**
     * Mapeo de campos para pedidos.
     * @var array
     */
    private $field_mapping = array();

    private $zoho_deal_meta_key = '_wzi_zoho_deal_id';
    private $zoho_so_meta_key = '_wzi_zoho_sales_order_id';
    private $last_sync_meta_key = '_wzi_order_last_sync';
    private $zoho_product_meta_key = '_wzi_zoho_product_id'; // Asegúrate que este sea el metakey correcto para el ID de producto de Zoho CRM

    public function __construct() {
        $this->crm_api = new WZI_Zoho_CRM();
        $this->logger = new WZI_Logger();
        
        if (class_exists('WZI_Sync_Customers')) {
            $this->customer_sync = new WZI_Sync_Customers();
        } else {
            if(isset($this->logger)) $this->logger->error('WZI_Sync_Customers class not found during WZI_Sync_Orders instantiation.');
            $this->customer_sync = null;
        }
        // No inicializamos Inventory y Books API aquí a menos que sean siempre necesarias.
        // Se pueden instanciar bajo demanda en los métodos que las usen.
        $this->load_field_mapping();
    }

    /**
     * Cargar mapeo de campos desde la base de datos para pedidos.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_field_mapping() {
        global $wpdb;
        $this->field_mapping = array();

        $mapping_table = $wpdb->prefix . 'wzi_field_mapping';
        
        $mappings = $wpdb->get_results($wpdb->prepare(
            "SELECT woo_field, zoho_field, sync_direction, transform_function FROM {$mapping_table} 
             WHERE entity_type = %s AND is_active = 1",
            'order' 
        ));
        
        foreach ($mappings as $mapping) {
            $this->field_mapping[$mapping->woo_field] = array(
                'zoho_field' => $mapping->zoho_field,
                'sync_direction' => $mapping->sync_direction,
                'transform_function' => $mapping->transform_function,
            );
        }
        
        // Mapeo por defecto si no hay configuración
        if (empty($this->field_mapping)) {
            $this->field_mapping = $this->get_default_field_mapping();
        }
    }

    /**
     * Obtener mapeo de campos por defecto.
     *
     * @since    1.0.0
     * @return   array    Mapeo por defecto.
     */
    private function get_default_field_mapping() {
        // Estos mapeos por defecto pueden apuntar a campos de 'Deals' o 'Sales_Orders' en Zoho CRM.
        // La UI de mapeo permitiría al usuario refinar esto.
        // Los nombres de zoho_field aquí son ejemplos comunes.
        return array(
            'order_number' => array( // WC_Order->get_order_number()
                'zoho_field' => 'Deal_Name', // Para Deals. Para Sales_Orders sería 'Subject'.
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => null,
            ),
            'total' => array( // WC_Order->get_total()
                'zoho_field' => 'Amount', // Para Deals. Para Sales_Orders sería 'Grand_Total'.
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => null,
            ),
            'status' => array( // WC_Order->get_status()
                'zoho_field' => 'Stage', // Para Deals. Para Sales_Orders sería 'Status'.
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => 'wzi_map_order_status_to_deal_stage_helper', // O wzi_map_order_status_to_so_status_helper
            ),
            'customer_id' => array( // WC_Order->get_customer_id()
                'zoho_field' => 'Contact_Name', // Este es un campo de lookup, la transform_function debería devolver { "id": "zoho_contact_id" }
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => 'wzi_get_zoho_contact_id_from_wc_customer_id', // Necesita crearse
            ),
            'billing_company' => array( // WC_Order->get_billing_company()
                'zoho_field' => 'Account_Name', // Este es un campo de lookup, similar a Contact_Name
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => 'wzi_get_zoho_account_id_from_company_name', // Necesita crearse (más complejo, implica buscar/crear cuenta)
            ),
            'date_created_gmt' => array( // WC_Order->get_date_created()
                'zoho_field' => 'Closing_Date', // Para Deals (espera YYYY-MM-DD). Para Sales_Orders podría ser 'Date' o 'Created_Time' (DateTime).
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => 'wzi_format_date_for_zoho_crm_date', // Formatea a YYYY-MM-DD
            ),
            'currency' => array( // WC_Order->get_currency()
                'zoho_field' => 'Currency', // Para Deals y Sales Orders (si soportan multimoneda y está habilitado en Zoho)
                'sync_direction' => 'wc_to_zoho',
                'transform_function' => null,
            ),
            // Ejemplo de cómo se mapearían campos de dirección (si Zoho module los tiene directamente)
            // 'billing_email' => array(
            //     'zoho_field' => 'Billing_Email', // Esto no existe directamente en Deals/SO, es a través del Contacto.
            //     'sync_direction' => 'wc_to_zoho',
            //     'transform_function' => null,
            // ),
        );
    }

    /**
     * Helper para la función de transformación de estado de pedido a etapa de Deal.
     *
     * @param string $status Estado del pedido de WooCommerce.
     * @return string Etapa del Deal en Zoho CRM.
     */
    public static function wzi_map_order_status_to_deal_stage_helper($status) {
        $mapping = array(
            'pending'    => 'Qualification',
            'processing' => 'Proposal/Price Quote',
            'on-hold'    => 'Negotiation/Review',
            'completed'  => 'Closed Won',
            'cancelled'  => 'Closed Lost',
            'refunded'   => 'Closed Lost', // O una etapa personalizada
            'failed'     => 'Closed Lost',
        );
        return $mapping[$status] ?? 'Qualification'; // Fallback
    }

    /**
     * Helper para la función de transformación de estado de pedido a estado de Sales Order.
     *
     * @param string $status Estado del pedido de WooCommerce.
     * @return string Estado de Sales Order en Zoho CRM.
     */
    public static function wzi_map_order_status_to_so_status_helper($status) {
        $mapping = array(
            'pending'    => 'Created',
            'processing' => 'Approved',
            'on-hold'    => 'On Hold',
            'completed'  => 'Delivered', // O 'Fulfilled'
            'cancelled'  => 'Cancelled',
            'refunded'   => 'Cancelled',
            'failed'     => 'Cancelled',
        );
        return $mapping[$status] ?? 'Created'; // Fallback
    }
  
    /**
     * Helper para formatear fecha para campos de fecha de Zoho CRM (YYYY-MM-DD).
     *
     * @param string|WC_DateTime $date Objeto de fecha o string.
     * @return string Fecha formateada o string vacío.
     */
    public static function wzi_format_date_for_zoho_crm_date($date) {
        if ($date instanceof WC_DateTime) {
            return $date->date('Y-m-d');
        } elseif (is_string($date) && !empty($date)) {
            try {
                $dt = new DateTime($date);
                return $dt->format('Y-m-d');
            } catch (Exception $e) {
                return '';
            }
        }
        return '';
    }


    /**
     * Sincronizar desde WooCommerce a Zoho.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 Resultado de la sincronización.
     */
    public function sync_from_woocommerce($batch_size = 50) {
        $result = array(
            'synced' => 0,
            'errors' => array(),
        );
        
        $this->logger->info('Starting order sync from WooCommerce', array(
            'sync_type' => 'orders',
            'sync_direction' => 'woo_to_zoho',
            'batch_size' => $batch_size,
        ));
        
        // Obtener pedidos no sincronizados o modificados
        $orders = $this->get_orders_to_sync($batch_size);
        
        foreach ($orders as $order_id) {
            try {
                $order = wc_get_order($order_id);
                
                if (!$order) {
                    continue;
                }
                
                // Sincronizar pedido
                $sync_result = $this->sync_order_to_zoho($order);
                
                if (is_wp_error($sync_result)) {
                    $result['errors'][] = sprintf(
                        __('Error sincronizando pedido %s: %s', 'woocommerce-zoho-integration'),
                        $order->get_order_number(),
                        $sync_result->get_error_message()
                    );
                    
                    $this->logger->error('Failed to sync order', array(
                        'order_id' => $order_id,
                        'error' => $sync_result->get_error_message(),
                    ));
                } else {
                    $result['synced']++;
                    
                    // Guardar IDs de Zoho
                    if (isset($sync_result['deal_id'])) {
                        $order->update_meta_data($this->zoho_deal_meta_key, $sync_result['deal_id']);
                    }
                    if (isset($sync_result['sales_order_id'])) {
                        $order->update_meta_data($this->zoho_so_meta_key, $sync_result['sales_order_id']);
                    }
                    
                    $order->update_meta_data($this->last_sync_meta_key, current_time('mysql'));
                    $order->save();
                    
                    $this->logger->info('Order synced successfully', array(
                        'order_id' => $order_id,
                        'deal_id' => $sync_result['deal_id'] ?? null,
                        'sales_order_id' => $sync_result['sales_order_id'] ?? null,
                    ));
                }
                
            } catch (Exception $e) {
                $result['errors'][] = sprintf(
                    __('Error procesando pedido %d: %s', 'woocommerce-zoho-integration'),
                    $order_id,
                    $e->getMessage()
                );
                
                $this->logger->error('Exception syncing order', array(
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ));
            }
        }
        
        $this->logger->info('Order sync from WooCommerce completed', array(
            'synced' => $result['synced'],
            'errors' => count($result['errors']),
        ));
        
        return $result;
    }

    /**
     * Sincronizar desde Zoho a WooCommerce.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 Resultado de la sincronización.
     */
    public function sync_from_zoho($batch_size = 50) {
        $result = array(
            'synced' => 0,
            'errors' => array(),
        );
        
        $this->logger->info('Starting order sync from Zoho', array(
            'sync_type' => 'orders',
            'sync_direction' => 'zoho_to_woo',
            'batch_size' => $batch_size,
        ));
        
        try {
            // Obtener última fecha de sincronización
            $last_sync = get_option('wzi_last_order_sync_from_zoho', '1970-01-01 00:00:00');
            
            // Sincronizar Sales Orders
            $params = array(
                'sort_by' => 'Modified_Time',
                'sort_order' => 'asc',
                'per_page' => $batch_size,
            );
            
            if ($last_sync !== '1970-01-01 00:00:00') {
                $params['modified_since'] = date('c', strtotime($last_sync));
            }
            
            $sales_orders = $this->crm_api->get_all_records('Sales_Orders', $params);
            
            if (is_wp_error($sales_orders)) {
                throw new Exception($sales_orders->get_error_message());
            }
            
            foreach ($sales_orders as $sales_order) {
                try {
                    // Sincronizar orden de venta
                    $order = $this->sync_sales_order_to_woocommerce($sales_order);
                    
                    if (is_wp_error($order)) {
                        $result['errors'][] = sprintf(
                            __('Error sincronizando orden de venta %s: %s', 'woocommerce-zoho-integration'),
                            $sales_order['id'],
                            $order->get_error_message()
                        );
                    } else {
                        $result['synced']++;
                    }
                    
                } catch (Exception $e) {
                    $result['errors'][] = sprintf(
                        __('Error procesando orden de venta %s: %s', 'woocommerce-zoho-integration'),
                        $sales_order['id'],
                        $e->getMessage()
                    );
                }
            }
            
            // Actualizar fecha de última sincronización
            if ($result['synced'] > 0) {
                update_option('wzi_last_order_sync_from_zoho', current_time('mysql'));
            }
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            
            $this->logger->error('Failed to sync from Zoho', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
        
        $this->logger->info('Order sync from Zoho completed', array(
            'synced' => $result['synced'],
            'errors' => count($result['errors']),
        ));
        
        return $result;
    }

    /**
     * Sincronizar un pedido individual.
     *
     * @since    1.0.0
     * @param    int      $order_id    ID del pedido.
     * @param    array    $data        Datos adicionales.
     * @return   bool                  Resultado de la sincronización.
     */
    public function sync_single($order_id, $data = array()) {
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                throw new Exception(__('Pedido no encontrado', 'woocommerce-zoho-integration'));
            }
            
            $sync_result = $this->sync_order_to_zoho($order);
            
            if (is_wp_error($sync_result)) {
                throw new Exception($sync_result->get_error_message());
            }
            
            // Guardar IDs de Zoho
            if (isset($sync_result['deal_id'])) {
                $order->update_meta_data($this->zoho_deal_meta_key, $sync_result['deal_id']);
            }
            if (isset($sync_result['sales_order_id'])) {
                $order->update_meta_data($this->zoho_so_meta_key, $sync_result['sales_order_id']);
            }
            
            $order->update_meta_data($this->last_sync_meta_key, current_time('mysql'));
            $order->save();
            
            $this->logger->info('Single order synced', array(
                'order_id' => $order_id,
                'deal_id' => $sync_result['deal_id'] ?? null,
                'sales_order_id' => $sync_result['sales_order_id'] ?? null,
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to sync single order', array(
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ));
            
            return false;
        }
    }

    /**
     * Eliminar pedido en Zoho.
     *
     * @since    1.0.0
     * @param    int      $order_id    ID del pedido.
     * @param    array    $data        Datos adicionales.
     * @return   bool                  Resultado de la eliminación.
     */
    public function delete_single($order_id, $data = array()) {
        try {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return true;
            }
            
            // Eliminar Deal si existe
            $deal_id = $order->get_meta($this->zoho_deal_meta_key);
            if ($deal_id) {
                $result = $this->crm_api->delete_record('Deals', $deal_id);
                if (!is_wp_error($result)) {
                    $order->delete_meta_data($this->zoho_deal_meta_key);
                }
            }
            
            // Eliminar Sales Order si existe
            $so_id = $order->get_meta($this->zoho_so_meta_key);
            if ($so_id) {
                $result = $this->crm_api->delete_record('Sales_Orders', $so_id);
                if (!is_wp_error($result)) {
                    $order->delete_meta_data($this->zoho_so_meta_key);
                }
            }
            
            $order->delete_meta_data($this->last_sync_meta_key);
            $order->save();
            
            $this->logger->info('Order deleted from Zoho', array(
                'order_id' => $order_id,
                'deal_id' => $deal_id,
                'sales_order_id' => $so_id,
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete order from Zoho', array(
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ));
            
            return false;
        }
    }

    /**
     * Manejar webhook de Zoho.
     *
     * @since    1.0.0
     * @param    string    $action    Acción del webhook.
     * @param    array     $data      Datos del webhook.
     * @return   bool                 Resultado del procesamiento.
     */
    public function handle_webhook($action, $data) {
        try {
            $entity_type = isset($data['entity_type']) ? $data['entity_type'] : '';
            
            switch ($entity_type) {
                case 'Deals':
                    return $this->handle_deal_webhook($action, $data);
                    
                case 'Sales_Orders':
                    return $this->handle_sales_order_webhook($action, $data);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle webhook', array(
                'action' => $action,
                'error' => $e->getMessage(),
            ));
        }
        
        return false;
    }

    /**
     * Obtener pedidos para sincronizar.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 IDs de pedidos.
     */
    private function get_orders_to_sync($batch_size) {
        $args = array(
            'limit' => $batch_size,
            'return' => 'ids',
            'status' => array('pending', 'processing', 'on-hold', 'completed'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => $this->zoho_deal_meta_key,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => $this->last_sync_meta_key,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => $this->last_sync_meta_key,
                    'value' => '',
                    'compare' => '=',
                ),
            ),
        );
        
        // También incluir pedidos modificados
        $modified_args = array(
            'limit' => $batch_size,
            'return' => 'ids',
            'status' => array('pending', 'processing', 'on-hold', 'completed'),
            'date_modified' => '>' . date('Y-m-d H:i:s', strtotime('-1 day')),
            'meta_query' => array(
                array(
                    'key' => $this->zoho_deal_meta_key,
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        $new_orders = wc_get_orders($args);
        $modified_orders = wc_get_orders($modified_args);
        
        return array_unique(array_merge($new_orders, $modified_orders));
    }

    /**
     * Sincronizar pedido a Zoho.
     *
     * @since    1.0.0
     * @param    WC_Order    $order    Pedido de WooCommerce.
     * @return   array|WP_Error        Resultado de la sincronización.
     */
    private function sync_order_to_zoho($order) {
        $result = array();
        
        // Primero, asegurarse de que el cliente esté sincronizado
        $customer_id = $order->get_customer_id();
        $contact_id = null;
        
        if ($customer_id) {
            // Cliente registrado
            $zoho_contact_id = get_user_meta($customer_id, '_wzi_zoho_contact_id', true);
            
            if (!$zoho_contact_id) {
                // Sincronizar cliente primero
                $this->customer_sync->sync_single($customer_id);
                $zoho_contact_id = get_user_meta($customer_id, '_wzi_zoho_contact_id', true);
            }
            
            $contact_id = $zoho_contact_id;
        } else {
            // Cliente invitado - crear contacto
            $contact_data = array(
                'First_Name' => $order->get_billing_first_name(),
                'Last_Name' => $order->get_billing_last_name(),
                'Email' => $order->get_billing_email(),
                'Phone' => $order->get_billing_phone(),
                'Mailing_Street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
                'Mailing_City' => $order->get_billing_city(),
                'Mailing_State' => $order->get_billing_state(),
                'Mailing_Zip' => $order->get_billing_postcode(),
                'Mailing_Country' => $order->get_billing_country(),
                'Lead_Source' => 'WooCommerce Guest',
            );
            
            $contact = $this->crm_api->upsert_record('Contacts', $contact_data, array('Email'));
            
            if (!is_wp_error($contact)) {
                $contact_id = $contact['id'];
            }
        }
        
        // Sincronizar como Deal
        $sync_settings = get_option('wzi_sync_settings', array());
        $create_deals = isset($sync_settings['create_deals']) ? $sync_settings['create_deals'] === 'yes' : true;
        
        if ($create_deals) {
            $deal = $this->sync_order_as_deal($order, $contact_id);
            
            if (is_wp_error($deal)) {
                return $deal;
            }
            
            $result['deal_id'] = $deal['id'];
        }
        
        // Sincronizar como Sales Order
        $create_sales_orders = isset($sync_settings['create_sales_orders']) ? $sync_settings['create_sales_orders'] === 'yes' : true;
        
        if ($create_sales_orders) {
            $sales_order = $this->sync_order_as_sales_order($order, $contact_id);
            
            if (is_wp_error($sales_order)) {
                // Si falla Sales Order pero Deal tuvo éxito, no es error crítico
                $this->logger->warning('Failed to create sales order', array(
                    'order_id' => $order->get_id(),
                    'error' => $sales_order->get_error_message(),
                ));
            } else {
                $result['sales_order_id'] = $sales_order['id'];
            }
        }
        
        return $result;
    }

    /**
     * Sincronizar pedido como Deal.
     *
     * @since    1.0.0
     * @param    WC_Order    $order        Pedido.
     * @param    string      $contact_id   ID del contacto en Zoho.
     * @return   array|WP_Error           Deal creado/actualizado.
     */
    private function sync_order_as_deal($order, $contact_id = null) {
        // Preparar datos del Deal
        $deal_data = array(
            'Deal_Name' => sprintf(__('Pedido #%s', 'woocommerce-zoho-integration'), $order->get_order_number()),
            'Amount' => $order->get_total(),
            'Stage' => $this->map_order_status_to_deal_stage($order->get_status()),
            'Closing_Date' => $order->get_date_created()->format('Y-m-d'),
            'Description' => $this->get_order_description($order),
            'Currency' => $order->get_currency(),
            'Exchange_Rate' => 1,
        );
        
        // Asociar con contacto
        if ($contact_id) {
            $deal_data['Contact_Name'] = array('id' => $contact_id);
        }
        
        // Campos personalizados
        $custom_fields = array(
            'Order_Number' => $order->get_order_number(),
            'Payment_Method' => $order->get_payment_method_title(),
            'Shipping_Method' => $order->get_shipping_method(),
            'Order_Status' => $order->get_status(),
            'Customer_Note' => $order->get_customer_note(),
        );
        
        $deal_data = array_merge($deal_data, $custom_fields);
        
        // Aplicar filtro para personalización
        $deal_data = apply_filters('wzi_order_to_deal_data', $deal_data, $order);
        
        // Verificar si ya existe
        $existing_deal_id = $order->get_meta($this->zoho_deal_meta_key);
        
        if ($existing_deal_id) {
            return $this->crm_api->update_record('Deals', $existing_deal_id, $deal_data);
        } else {
            return $this->crm_api->create_record('Deals', $deal_data);
        }
    }

    /**
     * Sincronizar pedido como Sales Order.
     *
     * @since    1.0.0
     * @param    WC_Order    $order        Pedido.
     * @param    string      $contact_id   ID del contacto en Zoho.
     * @return   array|WP_Error           Sales Order creado/actualizado.
     */
    private function sync_order_as_sales_order($order, $contact_id = null) {
        // Preparar líneas de productos
        $product_details = $this->prepare_product_details($order);
        
        // Preparar datos de Sales Order
        $so_data = array(
            'Subject' => sprintf(__('Pedido #%s', 'woocommerce-zoho-integration'), $order->get_order_number()),
            'Status' => $this->map_order_status_to_so_status($order->get_status()),
            'Due_Date' => $order->get_date_created()->format('Y-m-d'),
            'Sub_Total' => $order->get_subtotal(),
            'Tax' => $order->get_total_tax(),
            'Adjustment' => $order->get_total_discount() * -1,
            'Grand_Total' => $order->get_total(),
            'Currency' => $order->get_currency(),
            'Exchange_Rate' => 1,
            'Product_Details' => $product_details,
            
            // Dirección de facturación
            'Billing_Street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'Billing_City' => $order->get_billing_city(),
            'Billing_State' => $order->get_billing_state(),
            'Billing_Code' => $order->get_billing_postcode(),
            'Billing_Country' => $order->get_billing_country(),
            
            // Dirección de envío
            'Shipping_Street' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
            'Shipping_City' => $order->get_shipping_city(),
            'Shipping_State' => $order->get_shipping_state(),
            'Shipping_Code' => $order->get_shipping_postcode(),
            'Shipping_Country' => $order->get_shipping_country(),
        );
        
        // Asociar con contacto
        if ($contact_id) {
            $so_data['Contact_Name'] = array('id' => $contact_id);
        }
        
        // Añadir costos de envío si existen
        if ($order->get_shipping_total() > 0) {
            $so_data['Shipping_Charges'] = $order->get_shipping_total();
        }
        
        // Aplicar filtro para personalización
        $so_data = apply_filters('wzi_order_to_sales_order_data', $so_data, $order);
        
        // Verificar si ya existe
        $existing_so_id = $order->get_meta($this->zoho_so_meta_key);
        
        if ($existing_so_id) {
            return $this->crm_api->update_record('Sales_Orders', $existing_so_id, $so_data);
        } else {
            return $this->crm_api->create_record('Sales_Orders', $so_data);
        }
    }

    /**
     * Preparar detalles de productos para Sales Order.
     *
     * @since    1.0.0
     * @param    WC_Order    $order    Pedido.
     * @return   array                 Detalles de productos.
     */
    private function prepare_product_details($order) {
        $product_details = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            if (!$product) {
                continue;
            }
            
            $line_item = array(
                'product' => array(
                    'Product_Code' => $product->get_sku() ?: 'WC-' . $product->get_id(),
                    'name' => $product->get_name(),
                ),
                'quantity' => $item->get_quantity(),
                'list_price' => $item->get_subtotal() / $item->get_quantity(),
                'total' => $item->get_total(),
                'product_description' => $item->get_name(),
            );
            
            // Calcular descuento si existe
            $discount = $item->get_subtotal() - $item->get_total();
            if ($discount > 0) {
                $line_item['discount'] = $discount;
            }
            
            // Buscar producto en CRM
            $crm_product = $this->get_or_create_product_in_crm($product);
            
            if ($crm_product && !is_wp_error($crm_product)) {
                $line_item['product']['id'] = $crm_product['id'];
            }
            
            $product_details[] = $line_item;
        }
        
        // Añadir líneas de envío si es necesario
        if ($order->get_shipping_total() > 0) {
            $shipping_line = array(
                'product' => array(
                    'Product_Code' => 'SHIPPING',
                    'name' => __('Envío', 'woocommerce-zoho-integration'),
                ),
                'quantity' => 1,
                'list_price' => $order->get_shipping_total(),
                'total' => $order->get_shipping_total(),
                'product_description' => $order->get_shipping_method(),
            );
            
            $product_details[] = $shipping_line;
        }
        
        // Añadir líneas de fees si existen
        foreach ($order->get_fees() as $fee) {
            $fee_line = array(
                'product' => array(
                    'Product_Code' => 'FEE-' . sanitize_title($fee->get_name()),
                    'name' => $fee->get_name(),
                ),
                'quantity' => 1,
                'list_price' => $fee->get_total(),
                'total' => $fee->get_total(),
                'product_description' => $fee->get_name(),
            );
            
            $product_details[] = $fee_line;
        }
        
        return $product_details;
    }

    /**
     * Obtener o crear producto en CRM.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto de WooCommerce.
     * @return   array|WP_Error           Producto en CRM.
     */
    private function get_or_create_product_in_crm($product) {
        $sku = $product->get_sku() ?: 'WC-' . $product->get_id();
        
        // Buscar producto existente
        $existing = $this->crm_api->search_records('Products', array(
            'Product_Code' => $sku
        ));
        
        if (!is_wp_error($existing) && !empty($existing)) {
            return $existing[0];
        }
        
        // Crear nuevo producto
        return $this->crm_api->create_product_from_wc_product($product);
    }

    /**
     * Sincronizar Sales Order a WooCommerce.
     *
     * @since    1.0.0
     * @param    array    $sales_order    Sales Order de Zoho.
     * @return   WC_Order|WP_Error       Pedido de WooCommerce.
     */
    private function sync_sales_order_to_woocommerce($sales_order) {
        // Esta funcionalidad es más compleja y depende de los requisitos específicos
        // Por ahora, solo actualizaremos el estado de pedidos existentes
        
        $order_number = $this->extract_order_number($sales_order['Subject']);
        
        if (!$order_number) {
            return new WP_Error('no_order_number', __('No se pudo extraer el número de pedido', 'woocommerce-zoho-integration'));
        }
        
        // Buscar pedido por número
        $orders = wc_get_orders(array(
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_order_number',
            'meta_value' => $order_number,
        ));
        
        if (empty($orders)) {
            // Buscar por ID
            $order = wc_get_order($order_number);
            
            if (!$order) {
                return new WP_Error('order_not_found', __('Pedido no encontrado en WooCommerce', 'woocommerce-zoho-integration'));
            }
        } else {
            $order = $orders[0];
        }
        
        // Actualizar estado si es diferente
        $new_status = $this->map_so_status_to_order_status($sales_order['Status']);
        
        if ($order->get_status() !== $new_status) {
            $order->update_status($new_status, __('Estado actualizado desde Zoho CRM', 'woocommerce-zoho-integration'));
        }
        
        // Actualizar metadatos
        $order->update_meta_data($this->zoho_so_meta_key, $sales_order['id']);
        $order->update_meta_data($this->last_sync_meta_key, current_time('mysql'));
        $order->save();
        
        return $order;
    }

    /**
     * Mapear estado de pedido a etapa de Deal.
     *
     * @since    1.0.0
     * @param    string    $status    Estado del pedido.
     * @return   string              Etapa del Deal.
     */
    private function map_order_status_to_deal_stage($status) {
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
        
        return apply_filters('wzi_order_to_deal_stage', $stage, $status);
    }

    /**
     * Mapear estado de pedido a estado de Sales Order.
     *
     * @since    1.0.0
     * @param    string    $status    Estado del pedido.
     * @return   string              Estado de Sales Order.
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
        
        return apply_filters('wzi_order_to_so_status', $so_status, $status);
    }

    /**
     * Mapear estado de Sales Order a estado de pedido.
     *
     * @since    1.0.0
     * @param    string    $so_status    Estado de Sales Order.
     * @return   string                  Estado del pedido.
     */
    private function map_so_status_to_order_status($so_status) {
        $mapping = array(
            'Created' => 'pending',
            'Approved' => 'processing',
            'Delivered' => 'completed',
            'Cancelled' => 'cancelled',
            'Confirmed' => 'processing',
        );
        
        $order_status = isset($mapping[$so_status]) ? $mapping[$so_status] : 'pending';
        
        return apply_filters('wzi_so_status_to_order', $order_status, $so_status);
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
     * Extraer número de pedido del subject.
     *
     * @since    1.0.0
     * @param    string    $subject    Subject del Sales Order.
     * @return   string|null          Número de pedido.
     */
    private function extract_order_number($subject) {
        // Intentar extraer número de pedido del formato "Pedido #123"
        if (preg_match('/#(\d+)/', $subject, $matches)) {
            return $matches[1];
        }
        
        // Intentar otros formatos
        if (preg_match('/Order\s+(\d+)/i', $subject, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/Pedido\s+(\d+)/i', $subject, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Manejar webhook de Deal.
     *
     * @since    1.0.0
     * @param    string    $action    Acción.
     * @param    array     $data      Datos.
     * @return   bool                 Resultado.
     */
    private function handle_deal_webhook($action, $data) {
        // Por ahora, solo logear
        $this->logger->info('Deal webhook received', array(
            'action' => $action,
            'deal_id' => isset($data['id']) ? $data['id'] : 'unknown',
        ));
        
        return true;
    }

    /**
     * Manejar webhook de Sales Order.
     *
     * @since    1.0.0
     * @param    string    $action    Acción.
     * @param    array     $data      Datos.
     * @return   bool                 Resultado.
     */
    private function handle_sales_order_webhook($action, $data) {
        if ($action === 'update' && isset($data['id'])) {
            // Obtener Sales Order completo
            $sales_order = $this->crm_api->get_record('Sales_Orders', $data['id']);
            
            if (!is_wp_error($sales_order)) {
                $order = $this->sync_sales_order_to_woocommerce($sales_order);
                return !is_wp_error($order);
            }
        }
        
        return false;
    }

    /**
     * Obtener estadísticas de sincronización.
     *
     * @since    1.0.0
     * @return   array    Estadísticas.
     */
    public function get_sync_stats() {
        global $wpdb;
        
        // Total de pedidos
        $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'");
        
        // Pedidos con Deal ID
        $orders_with_deal = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
            $this->zoho_deal_meta_key
        ));
        
        // Pedidos con Sales Order ID
        $orders_with_so = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
            $this->zoho_so_meta_key
        ));
        
        return array(
            'total' => $total_orders,
            'synced_deals' => $orders_with_deal,
            'synced_sales_orders' => $orders_with_so,
            'pending' => $total_orders - max($orders_with_deal, $orders_with_so),
            'percentage' => $total_orders > 0 ? round((max($orders_with_deal, $orders_with_so) / $total_orders) * 100, 2) : 0,
        );
    }
}