<?php
/**
 * La clase principal del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * La clase principal del plugin.
 *
 * Esta es la clase que define los hooks del plugin, las funciones administrativas
 * y las funciones del lado público del sitio.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Main {

    /**
     * El cargador que es responsable de mantener y registrar todos los hooks.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WZI_Loader    $loader    Mantiene y registra todos los hooks del plugin.
     */
    protected $loader;

    /**
     * El identificador único de este plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    El string usado para identificar este plugin.
     */
    protected $plugin_name;

    /**
     * La versión actual del plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    La versión actual del plugin.
     */
    protected $version;

    /**
     * Instancia única del plugin
     *
     * @since    1.0.0
     * @access   protected
     * @var      WZI_Main    $instance    Instancia del plugin.
     */
    protected static $instance = null;

    /**
     * Define la funcionalidad principal del plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('WZI_VERSION')) {
            $this->version = WZI_VERSION;
        } else {
            $this->version = '1.2.5'; // Fallback, pero WZI_VERSION debería estar definida
        }
        $this->plugin_name = 'woocommerce-zoho-integration';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
        $this->define_sync_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Obtener instancia única del plugin
     *
     * @since    1.0.0
     * @return   WZI_Main    Instancia del plugin.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Cargar las dependencias requeridas para este plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * La clase responsable de orquestar las acciones y filtros del plugin.
         */
        require_once WZI_PLUGIN_DIR . 'includes/class-wzi-loader.php';

        /**
         * La clase responsable de definir la funcionalidad de internacionalización.
         */
        require_once WZI_PLUGIN_DIR . 'includes/class-wzi-i18n.php';

        /**
         * La clase responsable de definir todas las acciones del área de administración.
         */
        require_once WZI_PLUGIN_DIR . 'includes/class-wzi-admin.php';

        /**
         * La clase responsable de definir todas las acciones del lado público.
         */
        require_once WZI_PLUGIN_DIR . 'public/class-wzi-public.php';

        /**
         * Clases de configuración y utilidades
         */
        require_once WZI_PLUGIN_DIR . 'includes/class-wzi-settings.php';
        require_once WZI_PLUGIN_DIR . 'includes/class-wzi-logger.php';
        require_once WZI_PLUGIN_DIR . 'includes/utils/class-wzi-helpers.php';
        require_once WZI_PLUGIN_DIR . 'includes/utils/class-wzi-validator.php';
        require_once WZI_PLUGIN_DIR . 'includes/utils/class-wzi-cache.php';

        /**
         * Clases de API
         */
        require_once WZI_PLUGIN_DIR . 'includes/api/class-wzi-api-handler.php';
        require_once WZI_PLUGIN_DIR . 'includes/api/class-wzi-zoho-auth.php';
        require_once WZI_PLUGIN_DIR . 'includes/api/class-wzi-zoho-crm.php';
        require_once WZI_PLUGIN_DIR . 'includes/api/class-wzi-zoho-inventory.php';
        require_once WZI_PLUGIN_DIR . 'includes/api/class-wzi-zoho-books.php';
        require_once WZI_PLUGIN_DIR . 'includes/api/class-wzi-zoho-campaigns.php';

        /**
         * Clases de sincronización
         */
        require_once WZI_PLUGIN_DIR . 'includes/sync/class-wzi-sync-manager.php';
        require_once WZI_PLUGIN_DIR . 'includes/sync/class-wzi-sync-customers.php';
        require_once WZI_PLUGIN_DIR . 'includes/sync/class-wzi-sync-orders.php';
        require_once WZI_PLUGIN_DIR . 'includes/sync/class-wzi-sync-products.php';
        require_once WZI_PLUGIN_DIR . 'includes/sync/class-wzi-sync-invoices.php';
        require_once WZI_PLUGIN_DIR . 'includes/sync/class-wzi-sync-coupons.php';

        /**
         * Clases de hooks
         */
        require_once WZI_PLUGIN_DIR . 'includes/hooks/class-wzi-woocommerce-hooks.php';
        require_once WZI_PLUGIN_DIR . 'includes/hooks/class-wzi-custom-hooks.php';

        $this->loader = new WZI_Loader();
    }

    /**
     * Define la configuración regional para internacionalización.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new WZI_i18n();
        // Cambiar el hook de 'plugins_loaded' a 'init' para la carga del textdomain.
        // El textdomain se define en la cabecera del plugin, WZI_i18n debería usar ese.
        $this->loader->add_action('init', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Registrar todos los hooks relacionados con la funcionalidad del área de administración.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new WZI_Admin($this->get_plugin_name(), $this->get_version());

        // Estilos y scripts del admin
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Menú de administración
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');

        // Enlaces en la página de plugins
        $this->loader->add_filter('plugin_action_links_' . WZI_PLUGIN_BASENAME, $plugin_admin, 'add_action_links');

        // Avisos de administración
        $this->loader->add_action('admin_notices', $plugin_admin, 'display_admin_notices');

        // AJAX handlers
        $this->loader->add_action('wp_ajax_wzi_test_connection', $plugin_admin, 'ajax_test_connection');
        $this->loader->add_action('wp_ajax_wzi_manual_sync', $plugin_admin, 'ajax_manual_sync');
        $this->loader->add_action('wp_ajax_wzi_get_sync_status', $plugin_admin, 'ajax_get_sync_status');
        $this->loader->add_action('wp_ajax_wzi_save_field_mapping', $plugin_admin, 'ajax_save_field_mapping');
        $this->loader->add_action('wp_ajax_wzi_get_logs', $plugin_admin, 'ajax_get_logs');
        $this->loader->add_action('wp_ajax_wzi_clear_logs', $plugin_admin, 'ajax_clear_logs');

        // Configuraciones
        $plugin_settings = new WZI_Settings($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('admin_init', $plugin_settings, 'register_settings');
    }

    /**
     * Registrar todos los hooks relacionados con la funcionalidad pública del sitio.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new WZI_Public($this->get_plugin_name(), $this->get_version());

        // Estilos y scripts públicos
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');

        // REST API endpoints
        $this->loader->add_action('rest_api_init', $plugin_public, 'register_rest_routes');
    }

    /**
     * Registrar hooks relacionados con las APIs de Zoho.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_api_hooks() {
        // OAuth callback
        $this->loader->add_action('admin_init', $this, 'handle_oauth_callback');

        // Renovación de tokens
        $this->loader->add_action('wzi_refresh_tokens', $this, 'refresh_all_tokens');

        // Webhooks de Zoho
        $this->loader->add_action('rest_api_init', $this, 'register_webhook_endpoints');
    }

    /**
     * Registrar hooks relacionados con la sincronización.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_sync_hooks() {
        $woo_hooks = new WZI_WooCommerce_Hooks();

        // Hooks de clientes
        $this->loader->add_action('user_register', $woo_hooks, 'sync_customer_on_register', 10, 1);
        $this->loader->add_action('profile_update', $woo_hooks, 'sync_customer_on_update', 10, 2);
        $this->loader->add_action('woocommerce_customer_save_address', $woo_hooks, 'sync_customer_on_address_update', 10, 2);

        // Hooks de pedidos
        $this->loader->add_action('woocommerce_checkout_order_processed', $woo_hooks, 'sync_order_on_checkout', 10, 3);
        $this->loader->add_action('woocommerce_order_status_changed', $woo_hooks, 'sync_order_on_status_change', 10, 4);

        // Hooks de productos
        $this->loader->add_action('woocommerce_new_product', $woo_hooks, 'sync_product_on_create', 10, 1);
        $this->loader->add_action('woocommerce_update_product', $woo_hooks, 'sync_product_on_update', 10, 1);
        $this->loader->add_action('before_delete_post', $woo_hooks, 'sync_product_on_delete', 10, 1);
        $this->loader->add_action('woocommerce_product_set_stock', $woo_hooks, 'sync_product_stock', 10, 1);

        // Hooks de cupones
        $this->loader->add_action('woocommerce_new_coupon', $woo_hooks, 'sync_coupon_on_create', 10, 1);
        $this->loader->add_action('woocommerce_update_coupon', $woo_hooks, 'sync_coupon_on_update', 10, 1);

        // Hooks personalizados
        $custom_hooks = new WZI_Custom_Hooks();
        $this->loader->add_filter('wzi_before_sync_customer', $custom_hooks, 'filter_customer_data', 10, 2);
        $this->loader->add_filter('wzi_before_sync_order', $custom_hooks, 'filter_order_data', 10, 2);
        $this->loader->add_filter('wzi_before_sync_product', $custom_hooks, 'filter_product_data', 10, 2);
    }

    /**
     * Registrar hooks de tareas cron.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_cron_hooks() {
        // Sincronización automática
        $this->loader->add_action('wzi_auto_sync', $this, 'run_auto_sync');

        // Limpieza de logs
        $this->loader->add_action('wzi_cleanup_logs', $this, 'cleanup_old_logs');

        // Procesamiento de cola
        $this->loader->add_action('wzi_process_sync_queue', $this, 'process_sync_queue');

        // Añadir intervalos personalizados
        $this->loader->add_filter('cron_schedules', $this, 'add_cron_intervals');
    }

    /**
     * Ejecutar el cargador para ejecutar todos los hooks con WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * El nombre del plugin usado para identificarlo de forma única.
     *
     * @since     1.0.0
     * @return    string    El nombre del plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * El cargador de la clase responsable de registrar todos los hooks con WordPress.
     *
     * @since     1.0.0
     * @return    WZI_Loader    Orquesta los hooks del plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Recuperar el número de versión del plugin.
     *
     * @since     1.0.0
     * @return    string    El número de versión del plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Manejar callback de OAuth.
     *
     * Se ejecuta durante la inicialización del admin para procesar el código de autorización
     * devuelto por Zoho después de que el usuario autoriza la aplicación.
     *
     * @since    1.0.0
     * @return   void
     */
    public function handle_oauth_callback() {
        if (isset($_GET['page']) && $_GET['page'] === 'wzi-settings' && 
            isset($_GET['tab']) && $_GET['tab'] === 'api' && 
            isset($_GET['action']) && $_GET['action'] === 'callback' &&
            isset($_GET['code'])) {
            
            $auth = new WZI_Zoho_Auth();
            $result = $auth->handle_oauth_callback($_GET['code']);
            
            if ($result) {
                wp_redirect(admin_url('admin.php?page=wzi-settings&tab=api&auth=success'));
            } else {
                wp_redirect(admin_url('admin.php?page=wzi-settings&tab=api&auth=failed'));
            }
            exit;
        }
    }

    /**
     * Ejecutar sincronización automática.
     * Callback para la acción cron 'wzi_auto_sync'.
     *
     * @since    1.0.0
     * @return   void
     */
    public function run_auto_sync() {
        $sync_manager = new WZI_Sync_Manager();
        $sync_manager->run_scheduled_sync();
    }

    /**
     * Limpiar logs antiguos.
     * Callback para la acción cron 'wzi_cleanup_logs'.
     *
     * @since    1.0.0
     * @return   void
     */
    public function cleanup_old_logs() {
        $logger = new WZI_Logger();
        $logger->cleanup_old_logs();
    }

    /**
     * Procesar cola de sincronización.
     * Callback para la acción cron 'wzi_process_sync_queue'.
     *
     * @since    1.0.0
     * @return   void
     */
    public function process_sync_queue() {
        $sync_manager = new WZI_Sync_Manager();
        $sync_manager->process_queue();
    }

    /**
     * Añadir intervalos personalizados de cron.
     * Callback para el filtro 'cron_schedules'.
     *
     * @since    1.0.0
     * @param    array    $schedules    Intervalos existentes de WordPress.
     * @return   array                  Intervalos modificados con las adiciones del plugin.
     */
    public function add_cron_intervals($schedules) {
        $schedules['wzi_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Cada 5 minutos', 'woocommerce-zoho-integration')
        );
        
        $schedules['wzi_fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => __('Cada 15 minutos', 'woocommerce-zoho-integration')
        );
        
        return $schedules;
    }

    /**
     * Refrescar todos los tokens.
     * Callback para la acción 'wzi_refresh_tokens'.
     *
     * @since    1.0.0
     * @return   void
     */
    public function refresh_all_tokens() {
        $auth = new WZI_Zoho_Auth();
        $auth->refresh_all_tokens();
    }

    /**
     * Registrar endpoints de webhooks.
     * Callback para la acción 'rest_api_init'.
     *
     * @since    1.0.0
     * @return   void
     */
    public function register_webhook_endpoints() {
        register_rest_route('wzi/v1', '/webhook/(?P<service>[a-zA-Z]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature'),
            'args' => array(
                'service' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return in_array($param, array('crm', 'inventory', 'books', 'campaigns'));
                    }
                ),
            ),
        ));
    }

    /**
     * Manejar webhook
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Objeto de solicitud.
     * @return   WP_REST_Response              Respuesta.
     */
    public function handle_webhook($request) {
        $service = $request->get_param('service');
        $data = $request->get_json_params();
        
        $sync_manager = new WZI_Sync_Manager();
        $result = $sync_manager->handle_webhook($service, $data);
        
        return new WP_REST_Response($result, 200);
    }

    /**
     * Verificar firma del webhook
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Objeto de solicitud.
     * @return   bool                           Si la firma es válida.
     */
    public function verify_webhook_signature($request) {
        $webhook_settings = get_option('wzi_webhook_settings', array());
        
        if (!isset($webhook_settings['enable_webhooks']) || $webhook_settings['enable_webhooks'] !== 'yes') {
            return false;
        }
        
        $signature = $request->get_header('X-Zoho-Signature');
        $secret = isset($webhook_settings['webhook_secret']) ? $webhook_settings['webhook_secret'] : '';
        
        if (empty($signature) || empty($secret)) {
            return false;
        }
        
        $payload = $request->get_body();
        $expected_signature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expected_signature, $signature);
    }
}