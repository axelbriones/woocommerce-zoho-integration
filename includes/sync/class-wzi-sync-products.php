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
        
        $this->sync_settings = get_option('wzi_sync_settings', array());
        $this->load_field_mapping();
    }

    /**
     * Cargar mapeo de campos desde la base de datos para productos.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_field_mapping() {
        global $wpdb;
        
        $this->field_mapping = array();
        $mapping_table = $wpdb->prefix . 'wzi_field_mapping';
        
        $mappings = $wpdb->get_results($wpdb->prepare(
            // Seleccionar explícitamente las columnas con los nombres correctos por claridad y eficiencia
            "SELECT wc_field, zoho_field, direction, transform_function FROM {$mapping_table}
             WHERE module = %s AND is_active = 1", // entity_type -> module
            'product'
        ));
        
        foreach ($mappings as $mapping) {
            // Usar los nombres de columna correctos del objeto $mapping
            $this->field_mapping[$mapping->wc_field] = array(
                'zoho_field' => $mapping->zoho_field,
                'sync_direction' => $mapping->direction, // sync_direction -> direction
                'transform_function' => $mapping->transform_function,
            );
        }
        
        if (empty($this->field_mapping)) {
            $this->field_mapping = $this->get_default_field_mapping();
            if(isset($this->logger)) $this->logger->info('No custom field mappings found for products. Using default mappings.');
        } else {
            if(isset($this->logger)) $this->logger->info(sprintf('%d custom field mappings loaded for products.', count($this->field_mapping)));
        }
    }

    /**
     * Obtener mapeo de campos por defecto para productos.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    Mapeo por defecto.
     */
    private function get_default_field_mapping() {
        // Estos son campos comunes para Zoho Inventory Items.
        // Si se sincroniza con Zoho CRM Products, los zoho_field pueden ser diferentes (ej. Product_Name, Product_Code).
        return array(
            'name' => array('zoho_field' => 'name', 'sync_direction' => 'both', 'transform_function' => null),
            'sku' => array('zoho_field' => 'sku', 'sync_direction' => 'both', 'transform_function' => null),
            'regular_price' => array('zoho_field' => 'rate', 'sync_direction' => 'both', 'transform_function' => null),
            'description' => array('zoho_field' => 'description', 'sync_direction' => 'both', 'transform_function' => null),
            'short_description' => array('zoho_field' => 'short_description_for_zoho', 'sync_direction' => 'both', 'transform_function' => null), // Placeholder Zoho field
            'stock_quantity' => array('zoho_field' => 'stock_on_hand', 'sync_direction' => 'both', 'transform_function' => null), // Para Inventory
            'manage_stock' => array('zoho_field' => 'track_inventory', 'sync_direction' => 'both', 'transform_function' => null), // Para Inventory
            'weight' => array('zoho_field' => 'weight', 'sync_direction' => 'both', 'transform_function' => null),
            'length' => array('zoho_field' => 'length', 'sync_direction' => 'both', 'transform_function' => null),
            'width' => array('zoho_field' => 'width', 'sync_direction' => 'both', 'transform_function' => null),
            'height' => array('zoho_field' => 'height', 'sync_direction' => 'both', 'transform_function' => null),
            'tax_status' => array('zoho_field' => 'is_taxable', 'sync_direction' => 'both', 'transform_function' => 'wzi_map_tax_status_to_boolean'),
            // 'purchase_price' => array('zoho_field' => 'purchase_rate', 'sync_direction' => 'both', 'transform_function' => null), // Si tienes un meta para precio de compra
            // 'inventory_account_id' => array('zoho_field' => 'inventory_account_id', 'sync_direction' => 'wc_to_zoho', 'transform_function' => null), // Configurable
            // 'purchase_account_id' => array('zoho_field' => 'purchase_account_id', 'sync_direction' => 'wc_to_zoho', 'transform_function' => null), // Configurable
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
        $result = array('synced' => 0, 'errors' => array());
        if(isset($this->logger)) $this->logger->info('Starting product sync from WooCommerce', array('batch_size' => $batch_size));
        
        $products_to_sync = $this->get_products_to_sync($batch_size);
        
        foreach ($products_to_sync as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_status() === 'trash' || $product->is_type('variation')) { // Omitir variaciones individuales por ahora
                continue;
            }
            
            $sync_result = $this->sync_product_to_zoho($product);
            
            if (is_wp_error($sync_result)) {
                $error_message = sprintf(__('Error sincronizando producto %s (ID: %d): %s', 'woocommerce-zoho-integration'), $product->get_name(), $product_id, $sync_result->get_error_message());
                $result['errors'][] = $error_message;
                if(isset($this->logger)) $this->logger->error('Failed to sync product to Zoho', array('product_id' => $product_id, 'error' => $sync_result->get_error_message(), 'data' => $sync_result->get_error_data()));
            } else {
                $result['synced']++;
                update_post_meta($product_id, $this->last_sync_meta_key, current_time('mysql'));
                // El ID de Zoho (item_id o product_id) se guarda dentro de sync_product_to_zoho -> sync_product_to_inventory/crm
                 if(isset($this->logger)) $this->logger->info('Product synced successfully to Zoho', array('product_id' => $product_id, 'zoho_response' => $sync_result));
            }
        }
        if(isset($this->logger)) $this->logger->info('Product sync from WooCommerce completed.', $result);
        return $result;
    }

    /**
     * Sincronizar un producto de WooCommerce a los servicios de Zoho configurados (Inventory y/o CRM).
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product    El objeto del producto de WooCommerce.
     * @return   array|WP_Error           Un array con los IDs de Zoho si tiene éxito (ej. ['item_id' => id1, 'product_id' => id2]), o WP_Error si falla.
     */
    private function sync_product_to_zoho(WC_Product $product) {
        $results = array();
        $has_error = false;
        $product_id = $product->get_id();

        $sync_to_inventory = $this->sync_settings['sync_products_to_inventory'] ?? true; // Asumir true si no está seteado
        $sync_to_crm_products = $this->sync_settings['sync_products_to_crm'] ?? false;

        if ($sync_to_inventory) {
            $inventory_item_data = $this->prepare_data_for_zoho_service($product, 'inventory');
            $inventory_item_data = apply_filters('wzi_product_to_inventory_item_data', $inventory_item_data, $product, $this->field_mapping);
            
            $sku = $product->get_sku() ?: 'WC-' . $product_id;
            if(empty($inventory_item_data['sku']) && !empty($sku)) $inventory_item_data['sku'] = $sku;


            $response = $this->inventory_api->upsert_item_by_sku($sku, $inventory_item_data);

            if (is_wp_error($response)) {
                if(isset($this->logger)) $this->logger->error("Error syncing product ID {$product_id} to Zoho Inventory.", array('error' => $response->get_error_message(), 'data_sent' => $inventory_item_data));
                // No retornar inmediatamente, intentar CRM si está habilitado
                $results['inventory_error'] = $response;
                $has_error = true;
            } elseif (isset($response['item_id'])) {
                update_post_meta($product_id, $this->zoho_item_meta_key, $response['item_id']);
                $results['item_id'] = $response['item_id'];
            }
        }

        if ($sync_to_crm_products) {
            $crm_product_data = $this->prepare_data_for_zoho_service($product, 'crm_products');
            $crm_product_data = apply_filters('wzi_product_to_crm_product_data', $crm_product_data, $product, $this->field_mapping);
            
            $sku = $product->get_sku() ?: 'WC-' . $product_id;
            if(empty($crm_product_data['Product_Code']) && !empty($sku)) $crm_product_data['Product_Code'] = $sku; // Product_Code es el SKU en CRM Products


            $response = $this->crm_api->upsert_record('Products', $crm_product_data, ['Product_Code' => $sku]);

            if (is_wp_error($response)) {
                 if(isset($this->logger)) $this->logger->error("Error syncing product ID {$product_id} to Zoho CRM Products.", array('error' => $response->get_error_message(), 'data_sent' => $crm_product_data));
                $results['crm_product_error'] = $response;
                $has_error = true;
            } elseif (isset($response['id'])) {
                update_post_meta($product_id, $this->zoho_product_meta_key, $response['id']);
                $results['product_id'] = $response['id']; // CRM Product ID
            }
        }
        
        if ($has_error && empty($results['item_id']) && empty($results['product_id'])) {
            // Si ambos fallaron o solo uno estaba activo y falló.
            return new WP_Error('product_sync_failed', __('Falló la sincronización del producto a todos los servicios activos de Zoho.', 'woocommerce-zoho-integration'), $results);
        }

        return $results; // Contendrá item_id, product_id o errores.
    }

    /**
     * Prepara los datos de un producto de WooCommerce para un servicio específico de Zoho
     * utilizando el mapeo de campos.
     *
     * @since 1.0.0
     * @access private
     * @param WC_Product $product Objeto del producto de WooCommerce.
     * @param string $target_service 'inventory' o 'crm_products'.
     * @return array Datos preparados para Zoho.
     */
    private function prepare_data_for_zoho_service(WC_Product $product, $target_service) {
        $zoho_data = array();
        $product_id = $product->get_id();

        foreach ($this->field_mapping as $woo_field_key => $mapping_details) {
            if (!in_array($mapping_details['sync_direction'], array('wc_to_zoho', 'both'))) {
                continue;
            }

            // Aquí podríamos tener una lógica para determinar si el zoho_field pertenece al target_service
            // Por ahora, asumimos que el mapeo es genérico y se filtrará por la API de Zoho si el campo no existe.
            // Una mejora sería tener mapeos por WC_Module -> Zoho_Service -> Zoho_Module.

            $value = $this->get_product_field_value($product, $woo_field_key);

            if (!empty($mapping_details['transform_function'])) {
                $transform_function = $mapping_details['transform_function'];
                if (is_callable($transform_function)) {
                    $value = call_user_func($transform_function, $value, 'wc_to_zoho', $product, $mapping_details);
                } elseif (method_exists('WZI_Helpers', $transform_function)) {
                    $value = WZI_Helpers::{$transform_function}($value, 'wc_to_zoho', $product, $mapping_details);
                } else {
                    if(isset($this->logger)) $this->logger->warning(sprintf('Transform function %s not found for field %s, WC Product ID: %d.', $transform_function, $woo_field_key, $product_id));
                }
            }
            
            if ($value !== null) {
                $zoho_data[$mapping_details['zoho_field']] = $value;
            }
        }

        // Campos requeridos/por defecto específicos del servicio
        if ($target_service === 'inventory') {
            if (empty($zoho_data['name'])) $zoho_data['name'] = $product->get_name() ?: ('WC Product #' . $product_id);
            if (empty($zoho_data['unit'])) $zoho_data['unit'] = 'pcs';
            if (empty($zoho_data['item_type'])) $zoho_data['item_type'] = $product->is_virtual() || $product->is_downloadable() ? 'service' : 'inventory';
            if ($product->managing_stock() && !isset($zoho_data['track_inventory'])) $zoho_data['track_inventory'] = true;
            if ($product->managing_stock() && !isset($zoho_data['stock_on_hand'])) $zoho_data['stock_on_hand'] = $product->get_stock_quantity();

        } elseif ($target_service === 'crm_products') {
            if (empty($zoho_data['Product_Name'])) $zoho_data['Product_Name'] = $product->get_name() ?: ('WC Product #' . $product_id);
            // Zoho CRM Products a menudo requiere 'Product_Category', 'Vendor_Name' (si es un producto de terceros) etc.
            // Estos deberían ser mapeables o tener fallbacks.
            if (empty($zoho_data['Product_Active'])) $zoho_data['Product_Active'] = ($product->get_status() === 'publish');

        }
        
        // Eliminar nulos antes de enviar
        return array_filter($zoho_data, function($value){ return $value !== null; });
    }


    /**
     * Sincronizar producto a Zoho Inventory.
     * (Este método ahora es un wrapper o podría ser obsoleto si sync_product_to_zoho maneja la lógica)
     *
     * @since    1.0.0
     * @deprecated 1.x Use sync_product_to_zoho y configure sync_settings['sync_products_to_inventory']
     * @param    WC_Product    $product    Producto.
     * @return   array|WP_Error           Item de Inventory.
     */
    private function sync_product_to_inventory_legacy(WC_Product $product) {
        $item_data = $this->prepare_data_for_zoho_service($product, 'inventory');
        $item_data = apply_filters('wzi_product_to_inventory_item_data', $item_data, $product, $this->field_mapping); // Mantener filtro por si acaso
        
        $sku = $item_data['sku'] ?? $product->get_sku() ?: 'WC-' . $product->get_id();
        
        $response = $this->inventory_api->upsert_item_by_sku($sku, $item_data);

        if (is_wp_error($response)) {
            return $response;
        }
        
        return isset($response['item_id']) ? $response : (isset($response['data']['item']) ? $response['data']['item'] : new WP_Error('zoho_item_id_missing_legacy', 'Zoho item ID missing in response'));
    }

    /**
     * Sincronizar producto a Zoho CRM.
     * (Este método ahora es un wrapper o podría ser obsoleto)
     * @deprecated 1.x Use sync_product_to_zoho y configure sync_settings['sync_products_to_crm']
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @return   array|WP_Error           Producto de CRM.
     */
    private function sync_product_to_crm_legacy(WC_Product $product) {
        $crm_product_data = $this->prepare_data_for_zoho_service($product, 'crm_products');
        $crm_product_data = apply_filters('wzi_product_to_crm_product_data', $crm_product_data, $product, $this->field_mapping);

        $sku = $crm_product_data['Product_Code'] ?? $product->get_sku() ?: 'WC-' . $product->get_id();

        $response = $this->crm_api->upsert_record('Products', $crm_product_data, ['Product_Code' => $sku]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        return isset($response['id']) ? $response : new WP_Error('zoho_crm_product_id_missing_legacy', 'Zoho CRM Product ID missing in response');
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
            if(isset($this->logger)) $this->logger->info("Skipping stock sync for product ID {$product->get_id()}: not managing stock.");
            return true;
        }
        
        $item_id = get_post_meta($product->get_id(), $this->zoho_item_meta_key, true);
        
        if (!$item_id) {
             if(isset($this->logger)) $this->logger->warning("Cannot sync stock for product ID {$product->get_id()}: Zoho Inventory Item ID not found in meta.");
            // Podríamos intentar sincronizar el producto aquí si no existe en Zoho Inventory aún.
            // $sync_result = $this->sync_product_to_inventory($product);
            // if (!is_wp_error($sync_result) && isset($sync_result['item_id'])) {
            //    $item_id = $sync_result['item_id'];
            // } else {
            //    return false;
            // }
            return false; // Por ahora, si no hay item_id, no se puede actualizar stock.
        }
        
        $result = $this->inventory_api->update_item_stock(
            $item_id, 
            $product->get_stock_quantity()
        );
        
        if (is_wp_error($result)) {
            if(isset($this->logger)) $this->logger->error('Failed to update stock in Zoho Inventory', array(
                'product_id' => $product->get_id(),
                'item_id' => $item_id,
                'error' => $result->get_error_message(),
            ));
            return false;
        }
        
        if(isset($this->logger)) $this->logger->info('Stock updated successfully in Zoho Inventory', array(
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
        $sku = isset($item['sku']) ? $item['sku'] : null;
        $zoho_item_id = $item['item_id'] ?? null;
        $product_id = 0;

        if(isset($this->logger)) $this->logger->info("Syncing Zoho Inventory item to WC.", ['zoho_item_id' => $zoho_item_id, 'sku' => $sku]);

        if (!empty($sku)) {
            $product_id = wc_get_product_id_by_sku($sku);
        }
        if (!$product_id && $zoho_item_id) {
            $product_id = $this->get_product_by_zoho_item_id($zoho_item_id);
        }
        
        $product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();
        if (!$product) { // wc_get_product puede devolver false
             $product = new WC_Product_Simple();
        }
        
        $original_product_data_for_log = $product->get_data();

        foreach ($this->field_mapping as $woo_field_key => $mapping_details) {
            if (!in_array($mapping_details['sync_direction'], array('zoho_to_woo', 'both'))) {
                continue;
            }
            
            $zoho_field_name = $mapping_details['zoho_field'];
            if (isset($item[$zoho_field_name])) {
                $value = $item[$zoho_field_name];
                
                if (!empty($mapping_details['transform_function'])) {
                     $transform_function = $mapping_details['transform_function'];
                    if (is_callable($transform_function)) {
                        $value = call_user_func($transform_function, $value, 'zoho_to_wc', $item, $mapping_details);
                    } elseif (method_exists('WZI_Helpers', $transform_function)) {
                        $value = WZI_Helpers::{$transform_function}($value, 'zoho_to_wc', $item, $mapping_details);
                    } else {
                        if(isset($this->logger)) $this->logger->warning(sprintf('Transform function %s not found for Zoho field %s (Woo field: %s).', $transform_function, $zoho_field_name, $woo_field_key));
                    }
                }
                $this->set_product_field_value($product, $woo_field_key, $value);
            }
        }
        
        // Campos estándar importantes si no están en el mapeo
        if (empty($this->field_mapping['name']) && isset($item['name'])) {
            $product->set_name($item['name']);
        }
        if (empty($this->field_mapping['sku']) && isset($item['sku'])) {
            $product->set_sku($item['sku']);
        }
        if (empty($this->field_mapping['regular_price']) && isset($item['rate'])) {
            $product->set_regular_price(WZI_Helpers::format_price_for_zoho($item['rate']));
        }
        if (empty($this->field_mapping['description']) && isset($item['description'])) {
            $product->set_description($item['description']);
        }
        if (empty($this->field_mapping['manage_stock']) && isset($item['track_inventory'])) {
            $product->set_manage_stock((bool)$item['track_inventory']);
        }
        if ($product->managing_stock() && empty($this->field_mapping['stock_quantity']) && isset($item['stock_on_hand'])) {
            $product->set_stock_quantity(intval($item['stock_on_hand']));
        }
        $product->set_stock_status($product->get_stock_quantity() > 0 ? 'instock' : 'outofstock');


        $product = apply_filters('wzi_zoho_item_to_product_data', $product, $item, $this->field_mapping);
        
        try {
            $new_product_id = $product->save();
            if (!$new_product_id) {
                 throw new Exception(__('Error al guardar el producto en WooCommerce.', 'woocommerce-zoho-integration'));
            }
            
            update_post_meta($new_product_id, $this->zoho_item_meta_key, $zoho_item_id);
            update_post_meta($new_product_id, $this->last_sync_meta_key, current_time('mysql'));
            
            if(isset($this->logger)) $this->logger->info('Zoho Inventory Item synced to WC Product.', array(
                'zoho_item_id' => $zoho_item_id, 'wc_product_id' => $new_product_id,
                'old_data' => $product_id ? $original_product_data_for_log : 'new_product',
                'new_data' => $product->get_data()
            ));
            return wc_get_product($new_product_id);
        } catch (Exception $e) {
            if(isset($this->logger)) $this->logger->error('Failed to save WC Product from Zoho Item.', array('zoho_item_id' => $zoho_item_id, 'error' => $e->getMessage()));
            return new WP_Error('wc_product_save_failed', $e->getMessage());
        }
    }

    /**
     * Obtener valor de campo del producto.
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product    Producto.
     * @param    string        $field      Campo.
     * @return   mixed                     Valor del campo.
     */
    private function get_product_field_value($product, $field) {
        switch ($field) {
            case 'name': return $product->get_name();
            case 'sku': return $product->get_sku();
            case 'regular_price': return $product->get_regular_price();
            case 'sale_price': return $product->get_sale_price();
            case 'description': return $product->get_description();
            case 'short_description': return $product->get_short_description();
            case 'stock_quantity': return $product->get_stock_quantity();
            case 'manage_stock': return $product->managing_stock() ? 'yes' : 'no'; // Convertir a string para mapeo si es necesario
            case 'weight': return $product->get_weight();
            case 'length': return $product->get_length();
            case 'width': return $product->get_width();
            case 'height': return $product->get_height();
            case 'tax_status': return $product->get_tax_status();
            case 'tax_class': return $product->get_tax_class();
            case 'categories':
                $term_ids = $product->get_category_ids();
                $term_names = array_map(function($term_id){ $term = get_term($term_id, 'product_cat'); return $term ? $term->name : ''; }, $term_ids);
                return implode(', ', $term_names); // O devolver array, depende de cómo se mapee
            case 'tags':
                $term_ids = $product->get_tag_ids();
                $term_names = array_map(function($term_id){ $term = get_term($term_id, 'product_tag'); return $term ? $term->name : ''; }, $term_ids);
                return implode(', ', $term_names);
            default:
                // Para campos meta personalizados que no tienen un getter directo en WC_Product
                if (strpos($field, '_') === 0) { // Convención para meta keys
                    return $product->get_meta($field, true);
                }
                // Si es una propiedad directa del objeto producto (menos común para datos principales)
                if (method_exists($product, 'get_' . $field)) {
                    return $product->{'get_' . $field}();
                }
                return $product->get_meta($field, true); // fallback a meta
        }
    }

    /**
     * Establecer valor de campo del producto.
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product    Producto.
     * @param    string        $field      Campo.
     * @param    mixed         $value      Valor.
     */
    private function set_product_field_value($product, $field, $value) {
        switch ($field) {
            case 'name': $product->set_name(sanitize_text_field($value)); break;
            case 'sku': $product->set_sku(sanitize_text_field($value)); break;
            case 'regular_price': $product->set_regular_price(wc_format_decimal($value)); break;
            case 'sale_price': $product->set_sale_price(wc_format_decimal($value)); break;
            case 'description': $product->set_description(wp_kses_post($value)); break;
            case 'short_description': $product->set_short_description(wp_kses_post($value)); break;
            case 'stock_quantity': $product->set_stock_quantity(intval($value)); break;
            case 'manage_stock': $product->set_manage_stock(filter_var($value, FILTER_VALIDATE_BOOLEAN)); break;
            case 'weight': $product->set_weight(wc_format_decimal($value)); break;
            case 'length': $product->set_length(wc_format_decimal($value)); break;
            case 'width': $product->set_width(wc_format_decimal($value)); break;
            case 'height': $product->set_height(wc_format_decimal($value)); break;
            case 'tax_status': $product->set_tax_status(sanitize_text_field($value)); break; // 'taxable', 'shipping', 'none'
            case 'tax_class': $product->set_tax_class(sanitize_text_field($value)); break;
            // El mapeo de categorías/tags desde Zoho a WC es más complejo, requeriría buscar/crear términos.
            // Por ahora, se omite la asignación directa para 'categories' y 'tags' desde Zoho.
            default:
                $product->update_meta_data(sanitize_key($field), wp_kses_post($value)); // Usar wp_kses_post para sanitizar valor de meta
        }
    }

    /**
     * Obtener producto por ID de item de Zoho.
     *
     * @since    1.0.0
     * @access   private
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
     * (Este método podría necesitar usar $this->field_mapping si el mapeo a CRM es diferente)
     *
     * @since    1.0.0
     * @access   private
     * @param    WC_Product    $product    Producto.
     * @return   array                     Datos para CRM.
     */
    private function prepare_crm_product_data($product) {
        // Esta es una preparación básica, debería usar mapeo de campos si se define para CRM Products.
        $data = array(
            'Product_Name' => $product->get_name(),
            'Product_Code' => $product->get_sku() ?: 'WC-' . $product->get_id(),
            'Product_Active' => ($product->get_status() === 'publish'),
            'Unit_Price' => $product->get_regular_price(),
            'Description' => $product->get_description(),
            // 'Qty_in_Stock' => $product->get_stock_quantity(), // Qty_in_Stock es más de Inventory. CRM Products es más un catálogo.
        );
        
        $categories = $product->get_category_ids();
        if (!empty($categories)) {
            $category = get_term($categories[0], 'product_cat');
            if ($category && !is_wp_error($category)) {
                $data['Product_Category'] = $category->name;
            }
        }
        
        return apply_filters('wzi_product_to_crm_product_data', $data, $product);
    }

    /**
     * Manejar webhook de Inventory.
     *
     * @since    1.0.0
     * @access   public
     * @param    string    $action    Acción.
     * @param    array     $data      Datos.
     * @return   bool                 Resultado.
     */
    public function handle_inventory_webhook($action, $data) {
        if (!isset($data['item_id']) && !isset($data['item']['item_id'])) { // Zoho puede anidar el item
             if(isset($this->logger)) $this->logger->warning('Webhook de Inventory recibido sin item_id.', $data);
            return false;
        }
        $item_payload = $data['item'] ?? $data; // Si está anidado en 'item'
        $item_id = $item_payload['item_id'];

        if(isset($this->logger)) $this->logger->info("Webhook de Inventory recibido para item {$item_id}, acción: {$action}");
        
        switch ($action) {
            case 'create':
            case 'update':
                // El payload del webhook puede ser suficiente o puede que necesites hacer un GET para obtener el item completo.
                // Por simplicidad, asumimos que $item_payload tiene los datos necesarios.
                $product = $this->sync_item_to_woocommerce($item_payload);
                return !is_wp_error($product);
                
            case 'delete':
                $product_id = $this->get_product_by_zoho_item_id($item_id);
                if ($product_id) {
                    delete_post_meta($product_id, $this->zoho_item_meta_key);
                    delete_post_meta($product_id, $this->last_sync_meta_key);
                     if(isset($this->logger)) $this->logger->info("Desvinculado producto WC ID {$product_id} de Zoho Item ID {$item_id} debido a webhook de eliminación.");
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
     * @access   public
     * @param    string    $action    Acción.
     * @param    array     $data      Datos.
     * @return   bool                 Resultado.
     */
    public function handle_crm_webhook($action, $data) {
        if(isset($this->logger)) $this->logger->info('CRM Product webhook recibido (actualmente solo logeando)', array(
            'action' => $action,
            'product_data' => $data
        ));
        // TODO: Implementar lógica si se desea sincronizar Productos de CRM a WooCommerce
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
        
        $total_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'product' AND post_status = 'publish'"
        );
        
        $synced_inventory = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            $this->zoho_item_meta_key
        ));
        
        $synced_crm = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
             WHERE meta_key = %s AND meta_value != ''",
            $this->zoho_product_meta_key
        ));
        
        return array(
            'total' => $total_products,
            'synced_inventory' => $synced_inventory,
            'synced_crm' => $synced_crm,
            'pending' => $total_products - max($synced_inventory, $synced_crm), // Estimación simple
            'percentage' => $total_products > 0 
                ? round((max($synced_inventory, $synced_crm) / $total_products) * 100, 2) 
                : 0,
        );
    }
}