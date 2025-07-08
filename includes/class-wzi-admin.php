<?php
/**
 * La funcionalidad específica del admin del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * La funcionalidad específica del admin del plugin.
 *
 * Define el nombre del plugin, versión, y engancha los estilos y JavaScript
 * específicos del admin.
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Admin {

    /**
     * El ID de este plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    El ID de este plugin.
     */
    private $plugin_name;

    /**
     * La versión de este plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    La versión actual de este plugin.
     */
    private $version;

    /**
     * Inicializar la clase y establecer sus propiedades.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       El nombre de este plugin.
     * @param      string    $version    La versión de este plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Añadir gancho para la acción AJAX de prueba de conexión
        add_action('wp_ajax_wzi_test_api_connection', array($this, 'ajax_test_api_connection'));
    }

    /**
     * Registrar los estilos del área de administración.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        // Solo cargar en nuestras páginas del plugin
        if (!$this->is_plugin_page()) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            WZI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version,
            'all'
        );

        // Estilos adicionales para páginas específicas
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'wzi-') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_style('jquery-ui-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css');
        }
    }

    /**
     * Registrar el JavaScript del área de administración.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        // Solo cargar en nuestras páginas del plugin
        if (!$this->is_plugin_page()) {
            return;
        }

        // Scripts principales
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            WZI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            $this->version,
            true
        );

        // Script de sincronización
        wp_enqueue_script(
            $this->plugin_name . '-sync',
            WZI_PLUGIN_URL . 'assets/js/sync.js',
            array('jquery'),
            $this->version,
            true
        );

        // Localizar scripts
        wp_localize_script(
            $this->plugin_name . '-admin',
            'wzi_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wzi_admin_nonce'),
                'strings' => array(
                    'confirm_sync' => __('¿Estás seguro de que deseas iniciar la sincronización?', 'woocommerce-zoho-integration'),
                    'syncing' => __('Sincronizando...', 'woocommerce-zoho-integration'),
                    'sync_complete' => __('Sincronización completada', 'woocommerce-zoho-integration'),
                    'sync_error' => __('Error en la sincronización', 'woocommerce-zoho-integration'), // Usado para errores genéricos
                    'confirm_clear_logs' => __('¿Estás seguro de que deseas limpiar todos los logs?', 'woocommerce-zoho-integration'),
                    'testing_connection' => __('Probando conexión...', 'woocommerce-zoho-integration'),
                    'connection_success' => __('Conexión exitosa', 'woocommerce-zoho-integration'), // Usado para test_connection
                    'connection_failed' => __('Conexión fallida', 'woocommerce-zoho-integration'), // Usado para test_connection
                    'copied' => __('Copiado!', 'woocommerce-zoho-integration'),
                    'copy' => __('Copiar', 'woocommerce-zoho-integration'),
                    'test_connection_ok' => __('✓ Conexión OK', 'woocommerce-zoho-integration'),
                    'test_connection_error' => __('✗ Error', 'woocommerce-zoho-integration'),
                    'test_connection_button' => __('Probar Conexión', 'woocommerce-zoho-integration'),
                    'select_zoho_module' => __('-- Seleccionar Módulo Zoho --', 'woocommerce-zoho-integration'),
                    'select_all_modules_alert' => __('Por favor, seleccione todos los módulos.', 'woocommerce-zoho-integration'),
                    'loading_fields_mappings' => __('Cargando campos y mapeos...', 'woocommerce-zoho-integration'),
                    'error_loading_fields' => __('Error al cargar la estructura de campos.', 'woocommerce-zoho-integration'),
                    'load_fields_first_alert' => __('Por favor, cargue los campos primero.', 'woocommerce-zoho-integration'),
                    'confirm_save_empty_mapping' => __('No hay mapeos definidos. ¿Desea guardar una configuración de mapeo vacía para estos módulos (esto eliminará los mapeos existentes)?', 'woocommerce-zoho-integration'),
                    'error_ajax_saving_mapping' => __('Error AJAX al guardar el mapeo: ', 'woocommerce-zoho-integration'),
                    'select_wc_field' => __('Seleccionar campo WC', 'woocommerce-zoho-integration'),
                    'custom_meta_field' => __('Campo Meta Personalizado', 'woocommerce-zoho-integration'),
                    'meta_key_name' => __('Nombre del Meta Key', 'woocommerce-zoho-integration'),
                    'select_zoho_field_option' => __('Seleccionar campo Zoho', 'woocommerce-zoho-integration'), // Diferente de 'select_zoho_module'
                    'wc_to_zoho_direction' => __('WC → Zoho', 'woocommerce-zoho-integration'),
                    'zoho_to_wc_direction' => __('Zoho → WC', 'woocommerce-zoho-integration'),
                    'both_direction' => __('Ambos (Bidireccional)', 'woocommerce-zoho-integration'),
                    'remove_mapping_button' => __('Quitar', 'woocommerce-zoho-integration'),
                    'saving_button' => __('Guardando...', 'woocommerce-zoho-integration'),
                    'save_mappings_button' => __('Guardar Mapeos para este Módulo', 'woocommerce-zoho-integration'),
                    'add_mapping_row_button' => __('Añadir Fila de Mapeo', 'woocommerce-zoho-integration'),
                    'load_mapping_button' => __('Cargar Campos para Mapeo', 'woocommerce-zoho-integration'),
                     // Nombres de módulos de Zoho (estos podrían venir de PHP si son dinámicos, pero para JS es más fácil localizarlos)
                    'zoho_crm_contacts' => __('Contacts', 'woocommerce-zoho-integration'),
                    'zoho_crm_leads' => __('Leads', 'woocommerce-zoho-integration'),
                    'zoho_crm_deals' => __('Deals', 'woocommerce-zoho-integration'),
                    'zoho_crm_sales_orders' => __('Sales Orders (CRM)', 'woocommerce-zoho-integration'),
                    'zoho_crm_products' => __('Products (CRM)', 'woocommerce-zoho-integration'),
                    'zoho_inventory_items' => __('Items (Inventory)', 'woocommerce-zoho-integration'),
                    'zoho_inventory_sales_orders' => __('Sales Orders (Inventory)', 'woocommerce-zoho-integration'),
                    'zoho_inventory_contacts' => __('Contacts (Inventory)', 'woocommerce-zoho-integration'),
                    'zoho_books_invoices' => __('Invoices', 'woocommerce-zoho-integration'),
                    'zoho_books_customers' => __('Customers (Books)', 'woocommerce-zoho-integration'),
                    'zoho_books_items' => __('Items (Books)', 'woocommerce-zoho-integration'),
                    'select_wc_module' => __('-- Seleccionar Módulo WC --', 'woocommerce-zoho-integration'),
                    'select_zoho_service' => __('-- Seleccionar Servicio Zoho --', 'woocommerce-zoho-integration'),
                    'select_zoho_module_first' => __('Seleccione primero el Servicio de Zoho.', 'woocommerce-zoho-integration'),
                    'no_mapping_loaded_message' => __('Seleccione los módulos de WooCommerce y Zoho para ver y configurar el mapeo de campos.', 'woocommerce-zoho-integration'),
                ),
            )
        );

        // Scripts adicionales según la página
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_wzi-dashboard') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);
        }
    }

    /**
     * Verificar si estamos en una página del plugin.
     *
     * @since    1.0.0
     * @return   bool
     */
    private function is_plugin_page() {
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        $plugin_pages = array(
            'toplevel_page_wzi-dashboard',
            'zoho-integration_page_wzi-settings',
            'zoho-integration_page_wzi-sync',
            'zoho-integration_page_wzi-logs',
            'zoho-integration_page_wzi-help',
        );

        return in_array($screen->id, $plugin_pages) || strpos($screen->id, 'wzi-') !== false;
    }

    /**
     * Registrar el menú de administración del plugin.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        // Menú principal
        add_menu_page(
            __('Zoho Integration', 'woocommerce-zoho-integration'),
            __('Zoho Integration', 'woocommerce-zoho-integration'),
            'manage_wzi_settings',
            'wzi-dashboard',
            array($this, 'display_dashboard_page'),
            'dashicons-cloud',
            56
        );

        // Dashboard (renombrar el primer submenú)
        add_submenu_page(
            'wzi-dashboard',
            __('Dashboard', 'woocommerce-zoho-integration'),
            __('Dashboard', 'woocommerce-zoho-integration'),
            'manage_wzi_settings',
            'wzi-dashboard',
            array($this, 'display_dashboard_page')
        );

        // Configuración
        add_submenu_page(
            'wzi-dashboard',
            __('Configuración', 'woocommerce-zoho-integration'),
            __('Configuración', 'woocommerce-zoho-integration'),
            'manage_wzi_settings',
            'wzi-settings',
            array($this, 'display_settings_page')
        );

        // Sincronización
        add_submenu_page(
            'wzi-dashboard',
            __('Sincronización', 'woocommerce-zoho-integration'),
            __('Sincronización', 'woocommerce-zoho-integration'),
            'manage_wzi_sync',
            'wzi-sync',
            array($this, 'display_sync_page')
        );

        // Logs
        add_submenu_page(
            'wzi-dashboard',
            __('Logs', 'woocommerce-zoho-integration'),
            __('Logs', 'woocommerce-zoho-integration'),
            'view_wzi_logs',
            'wzi-logs',
            array($this, 'display_logs_page')
        );

        // Ayuda
        add_submenu_page(
            'wzi-dashboard',
            __('Ayuda', 'woocommerce-zoho-integration'),
            __('Ayuda', 'woocommerce-zoho-integration'),
            'manage_wzi_settings',
            'wzi-help',
            array($this, 'display_help_page')
        );
    }

    /**
     * Mostrar página del dashboard.
     *
     * @since    1.0.0
     */
    public function display_dashboard_page() {
        require_once WZI_PLUGIN_DIR . 'admin/partials/wzi-admin-display.php';
    }

    /**
     * Mostrar página de configuración.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        require_once WZI_PLUGIN_DIR . 'admin/partials/wzi-settings-display.php';
    }

    /**
     * Mostrar página de sincronización.
     *
     * @since    1.0.0
     */
    public function display_sync_page() {
        require_once WZI_PLUGIN_DIR . 'admin/partials/wzi-sync-display.php';
    }

    /**
     * Mostrar página de logs.
     *
     * @since    1.0.0
     */
    public function display_logs_page() {
        require_once WZI_PLUGIN_DIR . 'admin/partials/wzi-logs-display.php';
    }

    /**
     * Mostrar página de ayuda.
     *
     * @since    1.0.0
     */
    public function display_help_page() {
        require_once WZI_PLUGIN_DIR . 'admin/partials/wzi-help-display.php';
    }

    /**
     * Añadir enlaces de acción en la página de plugins.
     *
     * @since    1.0.0
     * @param    array    $links    Enlaces existentes.
     * @return   array              Enlaces modificados.
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wzi-settings') . '">' . __('Configuración', 'woocommerce-zoho-integration') . '</a>',
            '<a href="' . admin_url('admin.php?page=wzi-help') . '">' . __('Documentación', 'woocommerce-zoho-integration') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }

    /**
     * Mostrar avisos de administración.
     *
     * @since    1.0.0
     */
    public function display_admin_notices() {
        // Verificar si hay mensajes en la sesión
        if (isset($_GET['wzi_message'])) {
            $message_type = isset($_GET['wzi_type']) ? sanitize_text_field($_GET['wzi_type']) : 'info';
            $message = '';

            switch ($_GET['wzi_message']) {
                case 'auth_success':
                    $message = __('Autorización con Zoho completada exitosamente.', 'woocommerce-zoho-integration');
                    $message_type = 'success';
                    break;
                case 'auth_failed':
                    $message = __('Error al autorizar con Zoho. Por favor, intenta nuevamente.', 'woocommerce-zoho-integration');
                    $message_type = 'error';
                    break;
                case 'settings_saved':
                    $message = __('Configuración guardada exitosamente.', 'woocommerce-zoho-integration');
                    $message_type = 'success';
                    break;
                case 'sync_started':
                    $message = __('Sincronización iniciada. Revisa los logs para ver el progreso.', 'woocommerce-zoho-integration');
                    $message_type = 'info';
                    break;
            }

            if (!empty($message)) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($message_type),
                    esc_html($message)
                );
            }
        }

        // Verificar conexión con Zoho
        if ($this->is_plugin_page() && !get_transient('wzi_hide_connection_notice')) {
            $auth = new WZI_Zoho_Auth();
            if (!$auth->is_connected()) {
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <?php _e('WooCommerce Zoho Integration: No está conectado con Zoho.', 'woocommerce-zoho-integration'); ?>
                        <a href="<?php echo admin_url('admin.php?page=wzi-settings&tab=api'); ?>">
                            <?php _e('Configurar ahora', 'woocommerce-zoho-integration'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * AJAX: Probar conexión con Zoho.
     *
     * @since    1.0.0
     */
    public function ajax_test_api_connection() {
        // Intentar suprimir notices para asegurar una respuesta JSON limpia
        // Esto es un parche para el notice _load_textdomain_just_in_time que corrompe el JSON.
        $original_display_errors = ini_get('display_errors');
        $original_error_reporting = error_reporting();

        @ini_set('display_errors', 0);
        error_reporting($original_error_reporting & ~E_NOTICE & ~E_USER_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_WARNING & ~E_USER_WARNING); // Suprimir notices y warnings

        check_ajax_referer('wzi_admin_nonce', 'nonce');

        if (!current_user_can('manage_wzi_settings')) { // Ajustar capacidad si es necesario
            wp_send_json_error(array('message' => __('No tienes permisos para realizar esta acción.', 'woocommerce-zoho-integration')), 403);
            return;
        }

        $service = isset($_POST['service']) ? sanitize_text_field($_POST['service']) : null;

        if (empty($service)) {
            wp_send_json_error(array('message' => __('Servicio no especificado.', 'woocommerce-zoho-integration')), 400);
            return;
        }

        $api_handler = null;
        $service_name_map = array(
            'crm' => 'Zoho CRM',
            'inventory' => 'Zoho Inventory',
            'books' => 'Zoho Books',
            'campaigns' => 'Zoho Campaigns',
        );
        $service_display_name = isset($service_name_map[$service]) ? $service_name_map[$service] : ucfirst($service);

        try {
            switch ($service) {
                case 'crm':
                    if (!class_exists('WZI_Zoho_CRM')) throw new Exception(sprintf(__('La clase %s no existe.', 'woocommerce-zoho-integration'), 'WZI_Zoho_CRM'));
                    $api_handler = new WZI_Zoho_CRM();
                    break;
                case 'inventory':
                    if (!class_exists('WZI_Zoho_Inventory')) throw new Exception(sprintf(__('La clase %s no existe.', 'woocommerce-zoho-integration'), 'WZI_Zoho_Inventory'));
                    $api_handler = new WZI_Zoho_Inventory();
                    break;
                case 'books':
                    if (!class_exists('WZI_Zoho_Books')) throw new Exception(sprintf(__('La clase %s no existe.', 'woocommerce-zoho-integration'), 'WZI_Zoho_Books'));
                    $api_handler = new WZI_Zoho_Books();
                    break;
                case 'campaigns':
                    if (!class_exists('WZI_Zoho_Campaigns')) throw new Exception(sprintf(__('La clase %s no existe.', 'woocommerce-zoho-integration'), 'WZI_Zoho_Campaigns'));
                    $api_handler = new WZI_Zoho_Campaigns();
                    break;
                default:
                    wp_send_json_error(array('message' => sprintf(__('Servicio %s no reconocido.', 'woocommerce-zoho-integration'), esc_html($service_display_name))), 400);
                    return;
            }

            if (!method_exists($api_handler, 'test_connection')) {
                 throw new Exception(sprintf(__('El método test_connection no existe en la clase para %s.', 'woocommerce-zoho-integration'), esc_html($service_display_name)));
            }

            $result = $api_handler->test_connection();

            if ($result === true || (is_array($result) && !empty($result) && !is_wp_error($result))) { // Algunas pruebas pueden devolver datos en lugar de solo true
                wp_send_json_success(array('message' => sprintf(__('Conexión con %s exitosa.', 'woocommerce-zoho-integration'), esc_html($service_display_name))));
            } else {
                 $error_message = sprintf(__('Error al conectar con %s.', 'woocommerce-zoho-integration'), esc_html($service_display_name));
                 if (is_wp_error($result)) {
                     $error_message .= ' ' . $result->get_error_message();
                 } elseif (is_array($result) && isset($result['data']['message'])) { // A veces el error viene en data.message
                    $error_message .= ' ' . $result['data']['message'];
                 } elseif (is_string($result)) { // Si el test_connection devuelve un string de error
                    $error_message .= ' ' . $result;
                 }
                wp_send_json_error(array('message' => $error_message));
            }

        } catch (Exception $e) {
            $error_msg = sprintf(__('Error al probar la conexión para %s: %s', 'woocommerce-zoho-integration'), esc_html($service_display_name), $e->getMessage());
            // Registrar el error real para depuración
            if (class_exists('WZI_Logger')) {
                $logger = new WZI_Logger();
                $logger->error($error_msg, array('service' => $service, 'exception' => $e));
            }
            wp_send_json_error(array('message' => $error_msg));
        }
    }

    /**
     * AJAX: Iniciar sincronización manual.
     *
     * @since    1.0.0
     */
    public function ajax_manual_sync() {
        check_ajax_referer('wzi_admin_nonce', 'nonce');

        if (!current_user_can('manage_wzi_sync')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'woocommerce-zoho-integration'));
        }

        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'all';
        $direction = isset($_POST['direction']) ? sanitize_text_field($_POST['direction']) : 'both';

        $sync_manager = new WZI_Sync_Manager();
        $result = $sync_manager->start_manual_sync($sync_type, $direction);

        wp_send_json($result);
    }

    /**
     * AJAX: Obtener estado de sincronización.
     *
     * @since    1.0.0
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('wzi_admin_nonce', 'nonce');

        if (!current_user_can('view_wzi_logs')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'woocommerce-zoho-integration'));
        }

        $sync_manager = new WZI_Sync_Manager();
        $status = $sync_manager->get_sync_status();

        wp_send_json($status);
    }

    /**
     * AJAX: Guardar mapeo de campos.
     *
     * @since    1.0.0
     */
    public function ajax_save_field_mapping() {
        check_ajax_referer('wzi_admin_nonce', 'nonce');

        if (!current_user_can('manage_wzi_mappings')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'woocommerce-zoho-integration'));
        }

        $entity_type = isset($_POST['entity_type']) ? sanitize_text_field($_POST['entity_type']) : '';
        $mappings = isset($_POST['mappings']) ? $_POST['mappings'] : array();

        if (empty($entity_type) || empty($mappings)) {
            wp_send_json_error(__('Datos inválidos', 'woocommerce-zoho-integration'));
        }

        global $wpdb;
        $mapping_table = $wpdb->prefix . 'wzi_field_mapping'; // Unificado a singular

        // Primero, desactivar todos los mapeos existentes para este tipo
        $wpdb->update(
            $mapping_table,
            array('is_active' => 0),
            array('entity_type' => $entity_type),
            array('%d'),
            array('%s')
        );

        // Luego, insertar o actualizar los nuevos mapeos
        foreach ($mappings as $mapping) {
            $woo_field = sanitize_text_field($mapping['woo_field']);
            $zoho_field = sanitize_text_field($mapping['zoho_field']);
            $sync_direction = sanitize_text_field($mapping['sync_direction']);
            $transform_function = sanitize_text_field($mapping['transform_function']);

            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $mapping_table WHERE entity_type = %s AND woo_field = %s",
                $entity_type,
                $woo_field
            ));

            if ($existing) {
                $wpdb->update(
                    $mapping_table,
                    array(
                        'zoho_field' => $zoho_field,
                        'sync_direction' => $sync_direction,
                        'transform_function' => $transform_function,
                        'is_active' => 1,
                    ),
                    array('id' => $existing->id),
                    array('%s', '%s', '%s', '%d'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $mapping_table,
                    array(
                        'entity_type' => $entity_type,
                        'woo_field' => $woo_field,
                        'zoho_field' => $zoho_field,
                        'sync_direction' => $sync_direction,
                        'transform_function' => $transform_function,
                        'is_active' => 1,
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%d')
                );
            }
        }

        wp_send_json_success(__('Mapeo de campos guardado exitosamente', 'woocommerce-zoho-integration'));
    }

    /**
     * AJAX: Obtener logs.
     *
     * @since    1.0.0
     */
    public function ajax_get_logs() {
        check_ajax_referer('wzi_admin_nonce', 'nonce');

        if (!current_user_can('view_wzi_logs')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'woocommerce-zoho-integration'));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 20;
        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        $logger = new WZI_Logger();
        $logs = $logger->get_logs(array(
            'page' => $page,
            'per_page' => $per_page,
            'sync_type' => $sync_type,
            'status' => $status,
        ));

        wp_send_json($logs);
    }

    /**
     * AJAX: Limpiar logs.
     *
     * @since    1.0.0
     */
    public function ajax_clear_logs() {
        check_ajax_referer('wzi_admin_nonce', 'nonce');

        if (!current_user_can('manage_wzi_settings')) {
            wp_die(__('No tienes permisos para realizar esta acción.', 'woocommerce-zoho-integration'));
        }

        $days = isset($_POST['days']) ? intval($_POST['days']) : 0;

        $logger = new WZI_Logger();
        $result = $logger->clear_logs($days);

        if ($result) {
            wp_send_json_success(__('Logs limpiados exitosamente', 'woocommerce-zoho-integration'));
        } else {
            wp_send_json_error(__('Error al limpiar los logs', 'woocommerce-zoho-integration'));
        }
    }
}