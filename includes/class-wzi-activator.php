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
        // Crear tablas de base de datos
        self::create_database_tables();
        
        // Establecer configuraciones por defecto
        self::set_default_settings();
        
        // Crear roles y capacidades
        self::create_roles_and_capabilities();
        
        // Programar tareas cron
        self::schedule_cron_jobs();
        
        // Crear carpetas necesarias
        self::create_plugin_folders();
        
        // Limpiar caché
        flush_rewrite_rules();
    }

    /**
     * Crear tablas de base de datos
     */
    private static function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla de logs de sincronización
        $sync_logs_table = $wpdb->prefix . 'wzi_sync_logs';
        $sql_sync_logs = "CREATE TABLE IF NOT EXISTS $sync_logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            sync_type varchar(50) NOT NULL,
            sync_direction varchar(20) NOT NULL,
            status varchar(20) NOT NULL,
            message text,
            details longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY sync_type (sync_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabla de cola de sincronización
        $sync_queue_table = $wpdb->prefix . 'wzi_sync_queue';
        $sql_sync_queue = "CREATE TABLE IF NOT EXISTS $sync_queue_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            item_type varchar(50) NOT NULL,
            item_id bigint(20) unsigned NOT NULL,
            action varchar(20) NOT NULL,
            priority int(11) DEFAULT 10,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            last_attempt datetime,
            data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY item_type_id (item_type, item_id),
            KEY status_priority (status, priority),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabla de mapeo de campos
        $mapping_table = $wpdb->prefix . 'wzi_field_mappings';
        $sql_mapping = "CREATE TABLE IF NOT EXISTS $mapping_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(50) NOT NULL,
            woo_field varchar(100) NOT NULL,
            zoho_field varchar(100) NOT NULL,
            sync_direction varchar(20) DEFAULT 'both',
            transform_function varchar(100),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entity_fields (entity_type, woo_field, zoho_field),
            KEY entity_type (entity_type)
        ) $charset_collate;";
        
        // Tabla de tokens de autenticación
        $auth_tokens_table = $wpdb->prefix . 'wzi_auth_tokens';
        $sql_auth_tokens = "CREATE TABLE IF NOT EXISTS $auth_tokens_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service varchar(50) NOT NULL,
            token_type varchar(20) NOT NULL,
            access_token text NOT NULL,
            refresh_token text,
            expires_at datetime,
            scope text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY service_type (service, token_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sync_logs);
        dbDelta($sql_sync_queue);
        dbDelta($sql_mapping);
        dbDelta($sql_auth_tokens);
        
        // Guardar versión de la base de datos
        update_option('wzi_db_version', WZI_VERSION);
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
        $mapping_table = $wpdb->prefix . 'wzi_field_mappings';
        
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
                    'entity_type' => $mapping[0],
                    'woo_field' => $mapping[1],
                    'zoho_field' => $mapping[2],
                    'sync_direction' => $mapping[3],
                    'transform_function' => $mapping[4],
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
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