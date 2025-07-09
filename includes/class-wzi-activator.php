<?php
/**
 * Se activa durante la activación del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * Se activa durante la activación del plugin.
 *
 * Esta clase define todo el código necesario para ejecutar durante la activación del plugin.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Activator {

    /**
     * Activación del plugin.
     *
     * @since    1.0.0
     */
    public static function activate() {
        error_log("WZI_Activator: activate() INICIO");
        // Crear tablas de base de datos
        self::create_database_tables();
        error_log("WZI_Activator: DESPUÉS de create_database_tables()");
        
        // Establecer configuraciones por defecto
        // self::set_default_settings();
        // error_log("WZI_Activator: DESPUÉS de set_default_settings()");
        
        // Crear roles y capacidades
        // self::create_roles_and_capabilities();
        // error_log("WZI_Activator: DESPUÉS de create_roles_and_capabilities()");
        
        // Programar tareas cron
        // self::schedule_cron_jobs();
        // error_log("WZI_Activator: DESPUÉS de schedule_cron_jobs()");
        
        // Crear carpetas necesarias
        // self::create_plugin_folders();
        // error_log("WZI_Activator: DESPUÉS de create_plugin_folders()");
        
        // Limpiar caché
        // flush_rewrite_rules();
        // error_log("WZI_Activator: DESPUÉS de flush_rewrite_rules()");
        error_log("WZI_Activator: activate() FIN - SOLO TABLAS ACTIVAS");
    }

    /**
     * Crear tablas de base de datos
     */
    private static function create_database_tables() {
        error_log("WZI_Activator: create_database_tables() INICIO");

        // Incluir los archivos de migración
        require_once WZI_PLUGIN_DIR . 'database/migrations/create_sync_logs_table.php';
        require_once WZI_PLUGIN_DIR . 'database/migrations/create_sync_queue_table.php';
        require_once WZI_PLUGIN_DIR . 'database/migrations/create_mapping_table.php';
        require_once WZI_PLUGIN_DIR . 'database/migrations/create_auth_tokens_table.php';

        // Ejecutar SOLO UNA a la vez para probar, comenzando con la que más hemos modificado.
        // Comenta/descomenta para aislar.

        // error_log("WZI_Activator: ANTES de wzi_create_sync_logs_table()");
        // wzi_create_sync_logs_table();
        // error_log("WZI_Activator: DESPUÉS de wzi_create_sync_logs_table()");

        // error_log("WZI_Activator: ANTES de wzi_create_sync_queue_table()");
        // wzi_create_sync_queue_table();
        // error_log("WZI_Activator: DESPUÉS de wzi_create_sync_queue_table()");

        error_log("WZI_Activator: ANTES de wzi_create_mapping_table()");
        wzi_create_mapping_table(); // <--- SOLO ESTA ACTIVA INICIALMENTE
        error_log("WZI_Activator: DESPUÉS de wzi_create_mapping_table()");

        // error_log("WZI_Activator: ANTES de wzi_create_auth_tokens_table()");
        // wzi_create_auth_tokens_table();
        // error_log("WZI_Activator: DESPUÉS de wzi_create_auth_tokens_table()");

        update_option('wzi_db_schema_version', WZI_VERSION);
        error_log("WZI_Activator: create_database_tables() FIN. Opción wzi_db_schema_version actualizada a " . WZI_VERSION);
    }
    
    /**
     * Establecer configuraciones por defecto
     */
    private static function set_default_settings() {
        $default_settings = array(
            'wzi_general_settings' => array(
                'enable_sync' => 'yes',
                'sync_mode' => 'manual',
                'debug_mode' => 'no',
                'log_retention_days' => 30,
            ),
            'wzi_api_settings' => array(
                'client_id' => '',
                'client_secret' => '',
                'redirect_uri' => admin_url('admin.php?page=wzi-settings&tab=api&action=callback'),
                'data_center' => 'com',
                'sandbox_mode' => 'no',
            ),
            'wzi_sync_settings' => array(
                'sync_customers' => 'yes',
                'sync_orders' => 'yes',
                'sync_products' => 'yes',
                'sync_invoices' => 'yes',
                'sync_coupons' => 'no',
                'sync_direction' => 'both',
                'batch_size' => 50,
                'sync_interval' => 'hourly',
            ),
            'wzi_field_mapping' => array(
                'customer_fields' => array(),
                'order_fields' => array(),
                'product_fields' => array(),
            ),
            'wzi_webhook_settings' => array(
                'enable_webhooks' => 'no',
                'webhook_secret' => wp_generate_password(32, false),
            ),
        );
        
        foreach ($default_settings as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
        
        // Establecer mapeos de campos por defecto
        self::set_default_field_mappings();
    }
    
    /**
     * Establecer mapeos de campos por defecto
     */
    private static function set_default_field_mappings() {
        global $wpdb;
        $mapping_table = $wpdb->prefix . 'wzi_field_mapping'; // Unificado a singular
        
        $default_mappings = array(
            // Mapeo de clientes
            array('customer', 'email', 'Email', 'both', null),
            array('customer', 'first_name', 'First_Name', 'both', null),
            array('customer', 'last_name', 'Last_Name', 'both', null),
            array('customer', 'billing_phone', 'Phone', 'both', null),
            array('customer', 'billing_company', 'Account_Name', 'both', null),
            array('customer', 'billing_address_1', 'Mailing_Street', 'both', null),
            array('customer', 'billing_city', 'Mailing_City', 'both', null),
            array('customer', 'billing_state', 'Mailing_State', 'both', null),
            array('customer', 'billing_postcode', 'Mailing_Zip', 'both', null),
            array('customer', 'billing_country', 'Mailing_Country', 'both', null),
            
            // Mapeo de pedidos
            array('order', 'order_number', 'Subject', 'both', null),
            array('order', 'total', 'Grand_Total', 'both', null),
            array('order', 'status', 'Status', 'both', 'transform_order_status'),
            array('order', 'billing_email', 'Contact_Email', 'both', null),
            array('order', 'date_created', 'Created_Time', 'both', null),
            
            // Mapeo de productos
            array('product', 'name', 'Product_Name', 'both', null),
            array('product', 'sku', 'Product_Code', 'both', null),
            array('product', 'regular_price', 'Unit_Price', 'both', null),
            array('product', 'description', 'Description', 'both', null),
            array('product', 'stock_quantity', 'Qty_in_Stock', 'both', null),
        );
        
        foreach ($default_mappings as $mapping) {
            $wpdb->insert(
                $mapping_table,
                array(
                    'module' => $mapping[0],             // entity_type -> module
                    'wc_field' => $mapping[1],           // woo_field -> wc_field
                    'zoho_field' => $mapping[2],
                    'direction' => $mapping[3],          // sync_direction -> direction
                    'transform_function' => $mapping[4],
                    // Faltaban zoho_module y is_custom/is_active si se quieren poner valores no por defecto aquí
                    // Asumiendo que zoho_module es el mismo que el módulo de Zoho destino (ej. Contacts para customer)
                    // y que los mapeos por defecto son 'is_custom' = 0, 'is_active' = 1
                    'zoho_module' => self::map_entity_to_default_zoho_module($mapping[0]), // Helper para obtener el módulo Zoho
                    'is_custom' => 0,
                    'is_active' => 1,
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d') // Actualizar formatos
            );
        }
    }

    /**
     * Mapear tipo de entidad de WC a módulo de Zoho por defecto.
     * Helper para set_default_field_mappings.
     *
     * @param string $entity_type Tipo de entidad de WooCommerce (e.g., 'customer', 'order', 'product')
     * @return string Nombre del módulo de Zoho correspondiente.
     */
    private static function map_entity_to_default_zoho_module($entity_type) {
        $map = array(
            'customer' => 'Contacts', // O 'Leads' según la configuración deseada
            'order'    => 'Sales_Orders', // O 'Deals'
            'product'  => 'Products', // O 'Items' si se usa más Zoho Inventory/Books para productos
        );
        return $map[$entity_type] ?? ucfirst($entity_type) . 's'; // Fallback simple
    }
    
    /**
     * Crear roles y capacidades
     */
    private static function create_roles_and_capabilities() {
        // Obtener el rol de administrador
        $admin_role = get_role('administrator');
        
        // Capacidades del plugin
        $capabilities = array(
            'manage_wzi_settings',
            'view_wzi_logs',
            'manage_wzi_sync',
            'manage_wzi_mappings',
        );
        
        // Añadir capacidades al administrador
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Crear rol personalizado para gestores de Zoho
        add_role(
            'wzi_manager',
            __('Gestor Zoho', 'woocommerce-zoho-integration'),
            array(
                'read' => true,
                'manage_wzi_settings' => true,
                'view_wzi_logs' => true,
                'manage_wzi_sync' => true,
                'manage_wzi_mappings' => true,
            )
        );
    }
    
    /**
     * Programar tareas cron
     */
    private static function schedule_cron_jobs() {
        // Obtener intervalo de sincronización
        $sync_settings = get_option('wzi_sync_settings', array());
        $interval = isset($sync_settings['sync_interval']) ? $sync_settings['sync_interval'] : 'hourly';
        
        // Programar sincronización automática
        if (!wp_next_scheduled('wzi_auto_sync')) {
            wp_schedule_event(time(), $interval, 'wzi_auto_sync');
        }
        
        // Programar limpieza de logs
        if (!wp_next_scheduled('wzi_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wzi_cleanup_logs');
        }
        
        // Programar procesamiento de cola
        if (!wp_next_scheduled('wzi_process_sync_queue')) {
            wp_schedule_event(time(), 'wzi_five_minutes', 'wzi_process_sync_queue');
        }
    }
    
    /**
     * Crear carpetas necesarias
     */
    private static function create_plugin_folders() {
        $upload_dir = wp_upload_dir();
        $wzi_dir = $upload_dir['basedir'] . '/wzi-logs';
        
        if (!file_exists($wzi_dir)) {
            wp_mkdir_p($wzi_dir);
            
            // Crear archivo .htaccess para proteger los logs
            $htaccess_content = 'deny from all';
            file_put_contents($wzi_dir . '/.htaccess', $htaccess_content);
        }
    }
}