<?php
/**
 * Sincronización de productos
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 */

/**
 * Sincronización de productos.
 *
 * Esta clase maneja la sincronización bidireccional de productos
 * entre WooCommerce y Zoho Inventory/CRM.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Sync_Products {

    /**
     * API de Zoho Inventory.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Zoho_Inventory    $inventory_api    Instancia de la API de Inventory.
     */
    private $inventory_api;

    /**
     * API de Zoho CRM.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Zoho_CRM    $crm_api    Instancia de la API de CRM.
     */
    private $crm_api;

    /**
     * Logger.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Logger    $logger    Instancia del logger.
     */
    private $logger;

    /**
     * Configuración de sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $sync_settings    Configuración.
     */
    private $sync_settings;

    /**
     * Mapeo de campos.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $field_mapping    Mapeo de campos.
     */
    private $field_mapping = array();

    /**
     * Meta key para almacenar ID de Zoho Inventory.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $zoho_item_meta_key    Meta key.
     */
    private $zoho_item_meta_key = '_wzi_zoho_item_id';

    /**
     * Meta key para almacenar ID de producto en CRM.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $zoho_product_meta_key    Meta key.
     */
    private $zoho_product_meta_key = '_wzi_zoho_product_id';

    /**
     * Meta key para almacenar fecha de última sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $last_sync_meta_key    Meta key.
     */
    private $last_sync_meta_key = '_wzi_product_last_sync';

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->inventory_api = new WZI_Zoho_Inventory();
        $this->crm_api = new WZI_Zoho_CRM();
        $this->logger = new WZI_Logger();
        
        // Cargar configuración
        $this->sync_settings = get_option('wzi_sync_settings', array());
        
        // Cargar mapeo de campos
        $this->load_field_mapping();
    }

    /**
     * Cargar mapeo de campos desde la base de datos.
     *
     * @since    1.0.0
     */
    private function load_field_mapping() {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'wzi_field_mapping'; // Unificado a singular
        
        $mappings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$mapping_table} 
             WHERE entity_type = %s AND is_active = 1",
            'product'
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
        return array(
            'name' => array(
                'zoho_field' => 'name',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'sku' => array(
                'zoho_field' => 'sku',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'regular_price' => array(
                'zoho_field' => 'rate',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'description' => array(
                'zoho_field' => 'description',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'stock_quantity' => array(
                'zoho_field' => 'initial_stock',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'weight' => array(
                'zoho_field' => 'weight',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
        );
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
        
        $this->logger->info('Starting product sync from WooCommerce', array(
            'sync_type' => 'products',
            'sync_direction' => 'woo_to_zoho',
            'batch_size' => $batch_size,
        ));
        
        // Obtener productos para sincronizar
        $products = $this->get_products_to_sync($batch_size);
        
        foreach ($products as $product_id) {
            try {
                $product = wc_get_product($product_id);
                
                if (!$product || $product->get_status() === 'trash') {
                    continue;
                }
                
                // Sincronizar producto
                $sync_result = $this->sync_product_to_zoho($product);
                
                if (is_wp_error($sync_result)) {
                    $result['errors'][] = sprintf(
                        __('Error sincronizando producto %s: %s', 'woocommerce-zoho-integration'),
                        $product->get_name(),
                        $sync_result->get_error_message()
                    );
                    
                    $this->logger->error('Failed to sync product', array(
                        'product_id' => $product_id,
                        'error' => $sync_result->get_error_message(),
                    ));
                } else {
                    $result['synced']++;
                    
                    // Guardar IDs de Zoho
                    if (isset($sync_result['item_id'])) {
                        update_post_meta($product_id, $this->zoho_item_meta_key, $sync_result['item_id']);
                    }
                    if (isset($sync_result['product_id'])) {
                        update_post_meta($product_id, $this->zoho_product_meta_key, $sync_result['product_id']);
                    }
                    
                    update_post_meta($product_id, $this->last_sync_meta_key, current_time('mysql'));
                    
                    $this->logger->info('Product synced successfully', array(
                        'product_id' => $product_id,
                        'item_id' => $sync_result['item_id'] ?? null,
                        'crm_product_id' => $sync_result['product_id'] ?? null,
                    ));
                }
                
            } catch (Exception $e) {
                $result['errors'][] = sprintf(
                    __('Error procesando producto %d: %s', 'woocommerce-zoho-integration'),
                    $product_id,
                    $e->getMessage()
                );
                
                $this->logger->error('Exception syncing product', array(
                    'product_id' => $product_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ));
            }
        }
        
        $this->logger->info('Product sync from WooCommerce completed', array(
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
        
        $this->logger->info('Starting product sync from Zoho', array(
            'sync_type' => 'products',
            'sync_direction' => 'zoho_to_woo',
            'batch_size' => $batch_size,
        ));
        
        try {
            // Obtener última fecha de sincronización
            $last_sync = get_option('wzi_last_product_sync_from_zoho', '1970-01-01 00:00:00');
            
            // Obtener items de Inventory
            $params = array(
                'sort_column' => 'last_modified_time',
                'sort_order' => 'A',
                'per_page' => $batch_size,
            );
            
            $items = $this->inventory_api->get_items($params);
            
            if (is_wp_error($items)) {
                throw new Exception($items->get_error_message());
            }
            
            foreach ($items as $item) {
                // Verificar si el item fue modificado después de la última sincronización
                if (isset($item['last_modified_time']) && 
                    strtotime($item['last_modified_time']) <= strtotime($last_sync)) {
                    continue;
                }
                
                try {
                    // Sincronizar item
                    $product = $this->sync_item_to_woocommerce($item);
                    
                    if (is_wp_error($product)) {
                        $result['errors'][] = sprintf(
                            __('Error sincronizando item %s: %s', 'woocommerce-zoho-integration'),
                            $item['name'],
                            $product->get_error_message()
                        );
                    } else {
                        $result['synced']++;
                    }
                    
                } catch (Exception $e) {
                    $result['errors'][] = sprintf(
                        __('Error procesando item %s: %s', 'woocommerce-zoho-integration'),
                        $item['name'],
                        $e->getMessage()
                    );
                }
            }
            
            // Actualizar fecha de última sincronización
            if ($result['synced'] > 0) {
                update_option('wzi_last_product_sync_from_zoho', current_time('mysql'));
            }
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            
            $this->logger->error('Failed to sync from Zoho', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
        
        $this->logger->info('Product sync from Zoho completed', array(
            'synced' => $result['synced'],
            'errors' => count($result['errors']),
        ));
        
        return $result;
    }

    /**
     * Sincronizar un producto individual.
     *
     * @since    1.0.0
     * @param    int      $product_id    ID del producto.
     * @param    array    $data          Datos adicionales.
     * @return   bool                    Resultado de la sincronización.
     */
    public function sync_single($product_id, $data = array()) {
        try {
            $product = wc_get_product($product_id);
            
            if (!$product) {
                throw new Exception(__('Producto no encontrado', 'woocommerce-zoho-integration'));
            }
            
            // Si es una actualización de stock solamente
            if (isset($data['stock_update']) && $data['stock_update']) {
                return $this->sync_product_stock($product);
            }
            
            $sync_result = $this->sync_product_to_zoho($product);
            
            if (is_wp_error($sync_result)) {
                throw new Exception($sync_result->get_error_message());
            }
            
            // Guardar IDs de Zoho
            if (isset($sync_result['item_id'])) {
                update_post_meta($product_id, $this->zoho_item_meta_key, $sync_result['item_id']);
            }
            if (isset($sync_result['product_id'])) {
                update_post_meta($product_id, $this->zoho_product_meta_key, $sync_result['product_id']);
            }
            
            update_post_meta($product_id, $this->last_sync_meta_key, current_time('mysql'));
            
            $this->logger->info('Single product synced', array(
                'product_id' => $product_id,
                'item_id' => $sync_result['item_id'] ?? null,
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to sync single product', array(
                'product_id' => $product_id,
                'error' => $e->getMessage(),
            ));
            
            return false;
        }
    }

    /**
     * Eliminar producto en Zoho.
     *
     * @since    1.0.0
     * @param    int      $product_id    ID del producto.
     * @param    array    $data          Datos adicionales.
     * @return   bool                    Resultado de la eliminación.
     */
    public function delete_single($product_id, $data = array()) {
        try {
            // Obtener IDs de Zoho
            $item_id = get_post_meta($product_id, $this->zoho_item_meta_key, true);
            $crm_product_id = get_post_meta($product_id, $this->zoho_product_meta_key, true);
            
            $deleted = false;
            
            // Eliminar de Inventory
            if ($item_id) {
                $result = $this->inventory_api->delete_item($item_id);
                if (!is_wp_error($result)) {
                    delete_post_meta($product_id, $this->zoho_item_meta_key);
                    $deleted = true;
                }
            }
            
            // Eliminar de CRM
            if ($crm_product_id) {
                $result = $this->crm_api->delete_record('Products', $crm_product_id);
                if (!is_wp_error($result)) {
                    delete_post_meta($product_id, $this->zoho_product_meta_key);
                    $deleted = true;
                }
            }
            
            if ($deleted) {
                delete_post_meta($product_id, $this->last_sync_meta_key);
                
                $this->logger->info('Product deleted from Zoho', array(
                    'product_id' => $product_id,
                    'item_id' => $item_id,
                    'crm_product_id' => $crm_product_id,
                ));
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete product from Zoho', array(
                'product_id' => $product_id,
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
            
            if ($entity_type === 'item' || $entity_type === 'items') {
                return $this->handle_inventory_webhook($action, $data);
            } elseif ($entity_type === 'Products') {
                return $this->handle_crm_webhook($action, $data);
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
     * Obtener productos para sincronizar.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 IDs de productos.
     */
    private function get_products_to_sync($batch_size) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => $this->zoho_item_meta_key,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => $this->last_sync_meta_key,
                    'compare' => 'NOT EXISTS',
                ),
            ),
        );
        
        $new_products = get_posts($args);
        
        // También obtener productos modificados
        $modified_args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size - count($new_products),
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'column' => 'post_modified',
                    'after' => '1 day ago',
                ),
            ),
            'meta_query' => array(
                array(
                    'key' => $this->zoho_item_meta_key,
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        $modified_products = get_posts($modified_args);
        
        return array_unique(array_merge($new_products, $modified_products));
    }

    /**
     * Sincronizar producto a Zoho.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto de WooCommerce.
     * @return   array|WP_Error           Resultado de la sincronización.
     */
    private function sync_product_to_zoho($product) {
        $result = array();
        
        // Verificar a qué servicios sincronizar
        $sync_to_inventory = isset($this->sync_settings['sync_to_inventory']) ? 
            $this->sync_settings['sync_to_inventory'] === 'yes' : true;
        
        $sync_to_crm = isset($this->sync_settings['sync_to_crm']) ? 
            $this->sync_settings['sync_to_crm'] === 'yes' : false;
        
        // Sincronizar a Inventory
        if ($sync_to_inventory) {
            $item_result = $this->sync_product_to_inventory($product);
            
            if (is_wp_error($item_result)) {
                return $item_result;
            }
            
            $result['item_id'] = $item_result['item_id'];
        }
        
        // Sincronizar a CRM
        if ($sync_to_crm) {
            $crm_result = $this->sync_product_to_crm($product);
            
            if (!is_wp_error($crm_result)) {
                $result['product_id'] = $crm_result['id'];
            }
        }
        
        return $result;
    }

    /**
     * Sincronizar producto a Zoho Inventory.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   array|WP_Error           Item de Inventory.
     */
    private function sync_product_to_inventory($product) {
        $item_id = get_post_meta($product->get_id(), $this->zoho_item_meta_key, true);
        
        if ($item_id) {
            // Actualizar item existente
            $result = $this->inventory_api->update_item_from_product($item_id, $product);
        } else {
            // Crear nuevo item
            $result = $this->inventory_api->create_item_from_product($product);
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $result;
    }

    /**
     * Sincronizar producto a Zoho CRM.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   array|WP_Error           Producto de CRM.
     */
    private function sync_product_to_crm($product) {
        $crm_product_id = get_post_meta($product->get_id(), $this->zoho_product_meta_key, true);
        
        if ($crm_product_id) {
            // Actualizar producto existente
            return $this->crm_api->update_record('Products', $crm_product_id, $this->prepare_crm_product_data($product));
        } else {
            // Crear nuevo producto
            return $this->crm_api->create_product_from_wc_product($product);
        }
    }

    /**
     * Sincronizar stock de producto.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   bool                     Resultado.
     */
    private function sync_product_stock($product) {
        if (!$product->managing_stock()) {
            return true;
        }
        
        $item_id = get_post_meta($product->get_id(), $this->zoho_item_meta_key, true);
        
        if (!$item_id) {
            return false;
        }
        
        $result = $this->inventory_api->update_item_stock(
            $item_id, 
            $product->get_stock_quantity()
        );
        
        if (is_wp_error($result)) {
            $this->logger->error('Failed to update stock', array(
                'product_id' => $product->get_id(),
                'item_id' => $item_id,
                'error' => $result->get_error_message(),
            ));
            
            return false;
        }
        
        $this->logger->info('Stock updated', array(
            'product_id' => $product->get_id(),
            'item_id' => $item_id,
            'stock' => $product->get_stock_quantity(),
        ));
        
        return true;
    }

    /**
     * Sincronizar item de Inventory a WooCommerce.
     *
     * @since    1.0.0
     * @param    array    $item    Item de Zoho Inventory.
     * @return   WC_Product|WP_Error Producto de WooCommerce.
     */
    private function sync_item_to_woocommerce($item) {
        // Buscar producto existente por SKU
        $sku = isset($item['sku']) ? $item['sku'] : '';
        $product_id = 0;
        
        if (!empty($sku)) {
            $product_id = wc_get_product_id_by_sku($sku);
        }
        
        // Si no se encuentra por SKU, buscar por ID de Zoho
        if (!$product_id) {
            $product_id = $this->get_product_by_zoho_item_id($item['item_id']);
        }
        
        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            // Crear nuevo producto
            $product = new WC_Product_Simple();
        }
        
        // Mapear campos desde Zoho
        foreach ($this->field_mapping as $woo_field => $mapping) {
            // Verificar dirección de sincronización
            if (!in_array($mapping['sync_direction'], array('zoho_to_woo', 'both'))) {
                continue;
            }
            
            $zoho_field = $mapping['zoho_field'];
            
            if (isset($item[$zoho_field])) {
                $value = $item[$zoho_field];
                
                // Aplicar transformación si existe
                if (!empty($mapping['transform_function']) && function_exists($mapping['transform_function'])) {
                    $value = call_user_func($mapping['transform_function'], $value, 'zoho_to_woo');
                }
                
                $this->set_product_field_value($product, $woo_field, $value);
            }
        }
        
        // Campos adicionales
        if (isset($item['sku'])) {
            $product->set_sku($item['sku']);
        }
        
        if (isset($item['is_taxable'])) {
            $product->set_tax_status($item['is_taxable'] ? 'taxable' : 'none');
        }
        
        // Gestión de stock
        if (isset($item['track_inventory']) && $item['track_inventory']) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(isset($item['stock_on_hand']) ? intval($item['stock_on_hand']) : 0);
            $product->set_stock_status($item['stock_on_hand'] > 0 ? 'instock' : 'outofstock');
        }
        
        // Aplicar filtro para personalización
        $product = apply_filters('wzi_zoho_item_to_product', $product, $item);
        
        try {
            // Guardar producto
            $product->save();
            
            // Guardar ID de Zoho
            update_post_meta($product->get_id(), $this->zoho_item_meta_key, $item['item_id']);
            update_post_meta($product->get_id(), $this->last_sync_meta_key, current_time('mysql'));
            
            $this->logger->info('Item synced to WooCommerce', array(
                'item_id' => $item['item_id'],
                'product_id' => $product->get_id(),
            ));
            
            return $product;
            
        } catch (Exception $e) {
            return new WP_Error('save_failed', $e->getMessage());
        }
    }

    /**
     * Obtener valor de campo del producto.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @param    string        $field      Campo.
     * @return   mixed                     Valor del campo.
     */
    private function get_product_field_value($product, $field) {
        switch ($field) {
            case 'name':
                return $product->get_name();
            case 'sku':
                return $product->get_sku();
            case 'regular_price':
                return $product->get_regular_price();
            case 'sale_price':
                return $product->get_sale_price();
            case 'description':
                return $product->get_description();
            case 'short_description':
                return $product->get_short_description();
            case 'stock_quantity':
                return $product->get_stock_quantity();
            case 'weight':
                return $product->get_weight();
            case 'length':
                return $product->get_length();
            case 'width':
                return $product->get_width();
            case 'height':
                return $product->get_height();
            default:
                return $product->get_meta($field);
        }
    }

    /**
     * Establecer valor de campo del producto.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @param    string        $field      Campo.
     * @param    mixed         $value      Valor.
     */
    private function set_product_field_value($product, $field, $value) {
        switch ($field) {
            case 'name':
                $product->set_name($value);
                break;
            case 'sku':
                $product->set_sku($value);
                break;
            case 'regular_price':
                $product->set_regular_price($value);
                break;
            case 'sale_price':
                $product->set_sale_price($value);
                break;
            case 'description':
                $product->set_description($value);
                break;
            case 'short_description':
                $product->set_short_description($value);
                break;
            case 'stock_quantity':
                $product->set_stock_quantity($value);
                break;
            case 'weight':
                $product->set_weight($value);
                break;
            case 'length':
                $product->set_length($value);
                break;
            case 'width':
                $product->set_width($value);
                break;
            case 'height':
                $product->set_height($value);
                break;
            default:
                $product->update_meta_data($field, $value);
        }
    }

    /**
     * Obtener producto por ID de item de Zoho.
     *
     * @since    1.0.0
     * @param    string    $item_id    ID del item.
     * @return   int|false             ID del producto o false.
     */
    private function get_product_by_zoho_item_id($item_id) {
        global $wpdb;
        
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value = %s 
             LIMIT 1",
            $this->zoho_item_meta_key,
            $item_id
        ));
        
        return $product_id ? intval($product_id) : false;
    }

    /**
     * Preparar datos de producto para CRM.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   array                     Datos para CRM.
     */
    private function prepare_crm_product_data($product) {
        $data = array(
            'Product_Name' => $product->get_name(),
            'Product_Code' => $product->get_sku(),
            'Product_Active' => $product->get_status() === 'publish',
            'Unit_Price' => $product->get_regular_price(),
            'Description' => $product->get_description(),
            'Qty_in_Stock' => $product->get_stock_quantity(),
        );
        
        // Categoría
        $categories = $product->get_category_ids();
        if (!empty($categories)) {
            $category = get_term($categories[0], 'product_cat');
            if ($category && !is_wp_error($category)) {
                $data['Product_Category'] = $category->name;
            }
        }
        
        return apply_filters('wzi_product_to_crm_data', $data, $product);
    }

    /**
     * Manejar webhook de Inventory.
     *
     * @since    1.0.0
     * @param    string    $action    Acción.
     * @param    array     $data      Datos.
     * @return   bool                 Resultado.
     */
    private function handle_inventory_webhook($action, $data) {
        if (!isset($data['item_id'])) {
            return false;
        }
        
        switch ($action) {
            case 'create':
            case 'update':
                // Obtener item completo
                $item = $this->inventory_api->get_item($data['item_id']);
                
                if (!is_wp_error($item)) {
                    $product = $this->sync_item_to_woocommerce($item);
                    return !is_wp_error($product);
                }
                break;
                
            case 'delete':
                // Buscar producto por ID de item
                $product_id = $this->get_product_by_zoho_item_id($data['item_id']);
                
                if ($product_id) {
                    // Opcionalmente eliminar producto o solo desvincular
                    delete_post_meta($product_id, $this->zoho_item_meta_key);
                    delete_post_meta($product_id, $this->last_sync_meta_key);
                    
                    return true;
                }
                break;
        }
        
        return false;
    }

    /**
     * Manejar webhook de CRM.
     *
     * @since    1.0.0
     * @param    string    $action    Acción.
     * @param    array     $data      Datos.
     * @return   bool                 Resultado.
     */
    private function handle_crm_webhook($action, $data) {
        // Por ahora, solo logear
        $this->logger->info('CRM Product webhook received', array(
            'action' => $action,
            'product_id' => isset($data['id']) ? $data['id'] : 'unknown',
        ));
        
        return true;
    }

    /**
     * Obtener estadísticas de sincronización.
     *
     * @since    1.0.0
     * @return   array    Estadísticas.
     */
    public function get_sync_stats() {
        global $wpdb;
        
        // Total de productos
        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );
        
        // Productos sincronizados con Inventory
        $synced_inventory = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            $this->zoho_item_meta_key
        ));
        
        // Productos sincronizados con CRM
        $synced_crm = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            $this->zoho_product_meta_key
        ));
        
        return array(
            'total' => $total_products,
            'synced_inventory' => $synced_inventory,
            'synced_crm' => $synced_crm,
            'pending' => $total_products - max($synced_inventory, $synced_crm),
            'percentage' => $total_products > 0 
                ? round((max($synced_inventory, $synced_crm) / $total_products) * 100, 2) 
                : 0,
        );
    }
}