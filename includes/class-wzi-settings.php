<?php
/**
 * Gestión de configuraciones del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * Gestión de configuraciones del plugin.
 *
 * Esta clase define y registra todas las configuraciones del plugin
 * utilizando la API de Settings de WordPress.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Settings {

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
     * Opciones del plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $options    Array de opciones del plugin.
     */
    private $options = array();

    /**
     * Inicializar la clase y establecer sus propiedades.
     *
     * @since    1.0.0
     * @param    string    $plugin_name    El nombre del plugin.
     * @param    string    $version        La versión de este plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Cargar todas las opciones
        $this->load_options();
    }

    /**
     * Cargar todas las opciones del plugin.
     *
     * @since    1.0.0
     */
    private function load_options() {
        $this->options['general'] = get_option('wzi_general_settings', array());
        $this->options['api'] = get_option('wzi_api_settings', array());
        $this->options['sync'] = get_option('wzi_sync_settings', array());
        $this->options['mapping'] = get_option('wzi_field_mapping', array());
        $this->options['webhook'] = get_option('wzi_webhook_settings', array());
    }

    /**
     * Registrar todas las configuraciones del plugin.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // Registrar configuraciones generales
        $this->register_general_settings();
        
        // Registrar configuraciones de API
        $this->register_api_settings();
        
        // Registrar configuraciones de sincronización
        $this->register_sync_settings();
        
        // Registrar configuraciones de webhook
        $this->register_webhook_settings();
    }

    /**
     * Registrar configuraciones generales.
     *
     * @since    1.0.0
     */
    private function register_general_settings() {
        register_setting(
            'wzi_general_settings_group',
            'wzi_general_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_general_settings'),
                'default' => array(
                    'enable_sync' => 'yes',
                    'sync_mode' => 'manual',
                    'debug_mode' => 'no',
                    'log_retention_days' => 30,
                ),
            )
        );

        add_settings_section(
            'wzi_general_section',
            __('Configuración General', 'woocommerce-zoho-integration'),
            array($this, 'general_section_callback'),
            'wzi_general_settings'
        );

        add_settings_field(
            'enable_sync',
            __('Habilitar Sincronización', 'woocommerce-zoho-integration'),
            array($this, 'enable_sync_callback'),
            'wzi_general_settings',
            'wzi_general_section'
        );

        add_settings_field(
            'sync_mode',
            __('Modo de Sincronización', 'woocommerce-zoho-integration'),
            array($this, 'sync_mode_callback'),
            'wzi_general_settings',
            'wzi_general_section'
        );

        add_settings_field(
            'debug_mode',
            __('Modo Debug', 'woocommerce-zoho-integration'),
            array($this, 'debug_mode_callback'),
            'wzi_general_settings',
            'wzi_general_section'
        );

        add_settings_field(
            'log_retention_days',
            __('Retención de Logs (días)', 'woocommerce-zoho-integration'),
            array($this, 'log_retention_callback'),
            'wzi_general_settings',
            'wzi_general_section'
        );
    }

    /**
     * Registrar configuraciones de API.
     *
     * @since    1.0.0
     */
    private function register_api_settings() {
        register_setting(
            'wzi_api_settings_group',
            'wzi_api_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_api_settings'),
                'default' => array(
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => admin_url('admin.php?page=wzi-settings&tab=api&action=callback'),
                    'data_center' => 'com',
                    'sandbox_mode' => 'no',
                ),
            )
        );

        add_settings_section(
            'wzi_api_section',
            __('Configuración de API de Zoho', 'woocommerce-zoho-integration'),
            array($this, 'api_section_callback'),
            'wzi_api_settings'
        );

        add_settings_field(
            'client_id',
            __('Client ID', 'woocommerce-zoho-integration'),
            array($this, 'client_id_callback'),
            'wzi_api_settings',
            'wzi_api_section'
        );

        add_settings_field(
            'client_secret',
            __('Client Secret', 'woocommerce-zoho-integration'),
            array($this, 'client_secret_callback'),
            'wzi_api_settings',
            'wzi_api_section'
        );

        add_settings_field(
            'redirect_uri',
            __('Redirect URI', 'woocommerce-zoho-integration'),
            array($this, 'redirect_uri_callback'),
            'wzi_api_settings',
            'wzi_api_section'
        );

        add_settings_field(
            'data_center',
            __('Centro de Datos', 'woocommerce-zoho-integration'),
            array($this, 'data_center_callback'),
            'wzi_api_settings',
            'wzi_api_section'
        );

        add_settings_field(
            'sandbox_mode',
            __('Modo Sandbox', 'woocommerce-zoho-integration'),
            array($this, 'sandbox_mode_callback'),
            'wzi_api_settings',
            'wzi_api_section'
        );
    }

    /**
     * Registrar configuraciones de sincronización.
     *
     * @since    1.0.0
     */
    private function register_sync_settings() {
        register_setting(
            'wzi_sync_settings_group',
            'wzi_sync_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_sync_settings'),
                'default' => array(
                    'sync_customers' => 'yes',
                    'sync_orders' => 'yes',
                    'sync_products' => 'yes',
                    'sync_invoices' => 'yes',
                    'sync_coupons' => 'no',
                    'sync_direction' => 'both',
                    'batch_size' => 50,
                    'sync_interval' => 'hourly',
                ),
            )
        );

        add_settings_section(
            'wzi_sync_section',
            __('Configuración de Sincronización', 'woocommerce-zoho-integration'),
            array($this, 'sync_section_callback'),
            'wzi_sync_settings'
        );

        // Tipos de sincronización
        add_settings_field(
            'sync_customers',
            __('Sincronizar Clientes', 'woocommerce-zoho-integration'),
            array($this, 'sync_customers_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        add_settings_field(
            'sync_orders',
            __('Sincronizar Pedidos', 'woocommerce-zoho-integration'),
            array($this, 'sync_orders_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        add_settings_field(
            'sync_products',
            __('Sincronizar Productos', 'woocommerce-zoho-integration'),
            array($this, 'sync_products_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        add_settings_field(
            'sync_invoices',
            __('Sincronizar Facturas', 'woocommerce-zoho-integration'),
            array($this, 'sync_invoices_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        add_settings_field(
            'sync_coupons',
            __('Sincronizar Cupones', 'woocommerce-zoho-integration'),
            array($this, 'sync_coupons_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        // Configuraciones adicionales
        add_settings_field(
            'sync_direction',
            __('Dirección de Sincronización', 'woocommerce-zoho-integration'),
            array($this, 'sync_direction_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        add_settings_field(
            'batch_size',
            __('Tamaño de Lote', 'woocommerce-zoho-integration'),
            array($this, 'batch_size_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );

        add_settings_field(
            'sync_interval',
            __('Intervalo de Sincronización', 'woocommerce-zoho-integration'),
            array($this, 'sync_interval_callback'),
            'wzi_sync_settings',
            'wzi_sync_section'
        );
    }

    /**
     * Registrar configuraciones de webhook.
     *
     * @since    1.0.0
     */
    private function register_webhook_settings() {
        register_setting(
            'wzi_webhook_settings_group',
            'wzi_webhook_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_webhook_settings'),
                'default' => array(
                    'enable_webhooks' => 'no',
                    'webhook_secret' => wp_generate_password(32, false),
                ),
            )
        );

        add_settings_section(
            'wzi_webhook_section',
            __('Configuración de Webhooks', 'woocommerce-zoho-integration'),
            array($this, 'webhook_section_callback'),
            'wzi_webhook_settings'
        );

        add_settings_field(
            'enable_webhooks',
            __('Habilitar Webhooks', 'woocommerce-zoho-integration'),
            array($this, 'enable_webhooks_callback'),
            'wzi_webhook_settings',
            'wzi_webhook_section'
        );

        add_settings_field(
            'webhook_secret',
            __('Webhook Secret', 'woocommerce-zoho-integration'),
            array($this, 'webhook_secret_callback'),
            'wzi_webhook_settings',
            'wzi_webhook_section'
        );

        add_settings_field(
            'webhook_urls',
            __('URLs de Webhook', 'woocommerce-zoho-integration'),
            array($this, 'webhook_urls_callback'),
            'wzi_webhook_settings',
            'wzi_webhook_section'
        );
    }

    /**
     * Callbacks de secciones
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure las opciones generales del plugin.', 'woocommerce-zoho-integration') . '</p>';
    }

    public function api_section_callback() {
        echo '<p>' . __('Configure las credenciales de API de Zoho. Puede obtenerlas desde la consola de desarrolladores de Zoho.', 'woocommerce-zoho-integration') . '</p>';
    }

    public function sync_section_callback() {
        echo '<p>' . __('Configure qué elementos desea sincronizar y cómo.', 'woocommerce-zoho-integration') . '</p>';
    }

    public function webhook_section_callback() {
        echo '<p>' . __('Configure los webhooks para recibir actualizaciones en tiempo real desde Zoho.', 'woocommerce-zoho-integration') . '</p>';
    }

    /**
     * Callbacks de campos - Configuración General
     */
    public function enable_sync_callback() {
        $value = isset($this->options['general']['enable_sync']) ? $this->options['general']['enable_sync'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="wzi_general_settings[enable_sync]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Habilitar sincronización entre WooCommerce y Zoho', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function sync_mode_callback() {
        $value = isset($this->options['general']['sync_mode']) ? $this->options['general']['sync_mode'] : 'manual';
        ?>
        <select name="wzi_general_settings[sync_mode]">
            <option value="manual" <?php selected($value, 'manual'); ?>><?php _e('Manual', 'woocommerce-zoho-integration'); ?></option>
            <option value="automatic" <?php selected($value, 'automatic'); ?>><?php _e('Automático', 'woocommerce-zoho-integration'); ?></option>
            <option value="realtime" <?php selected($value, 'realtime'); ?>><?php _e('Tiempo Real', 'woocommerce-zoho-integration'); ?></option>
        </select>
        <p class="description"><?php _e('Seleccione cómo desea que se realice la sincronización.', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function debug_mode_callback() {
        $value = isset($this->options['general']['debug_mode']) ? $this->options['general']['debug_mode'] : 'no';
        ?>
        <label>
            <input type="checkbox" name="wzi_general_settings[debug_mode]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Habilitar modo debug (registra información detallada en los logs)', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function log_retention_callback() {
        $value = isset($this->options['general']['log_retention_days']) ? $this->options['general']['log_retention_days'] : 30;
        ?>
        <input type="number" name="wzi_general_settings[log_retention_days]" value="<?php echo esc_attr($value); ?>" min="1" max="365" />
        <p class="description"><?php _e('Número de días para mantener los logs antes de eliminarlos automáticamente.', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    /**
     * Callbacks de campos - Configuración de API
     */
    public function client_id_callback() {
        $value = isset($this->options['api']['client_id']) ? $this->options['api']['client_id'] : '';
        ?>
        <input type="text" name="wzi_api_settings[client_id]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Client ID de su aplicación Zoho', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function client_secret_callback() {
        $value = isset($this->options['api']['client_secret']) ? $this->options['api']['client_secret'] : '';
        ?>
        <input type="password" name="wzi_api_settings[client_secret]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Client Secret de su aplicación Zoho', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function redirect_uri_callback() {
        $value = isset($this->options['api']['redirect_uri']) ? $this->options['api']['redirect_uri'] : admin_url('admin.php?page=wzi-settings&tab=api&action=callback');
        ?>
        <input type="text" name="wzi_api_settings[redirect_uri]" value="<?php echo esc_attr($value); ?>" class="regular-text" readonly />
        <p class="description"><?php _e('Copie esta URL y péguela en la configuración de su aplicación Zoho', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function data_center_callback() {
        $value = isset($this->options['api']['data_center']) ? $this->options['api']['data_center'] : 'com';
        ?>
        <select name="wzi_api_settings[data_center]">
            <option value="com" <?php selected($value, 'com'); ?>>.com (Estados Unidos)</option>
            <option value="eu" <?php selected($value, 'eu'); ?>>.eu (Europa)</option>
            <option value="in" <?php selected($value, 'in'); ?>>.in (India)</option>
            <option value="com.cn" <?php selected($value, 'com.cn'); ?>>.com.cn (China)</option>
            <option value="com.au" <?php selected($value, 'com.au'); ?>>.com.au (Australia)</option>
        </select>
        <p class="description"><?php _e('Seleccione el centro de datos de su cuenta Zoho', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function sandbox_mode_callback() {
        $value = isset($this->options['api']['sandbox_mode']) ? $this->options['api']['sandbox_mode'] : 'no';
        ?>
        <label>
            <input type="checkbox" name="wzi_api_settings[sandbox_mode]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Usar entorno sandbox de Zoho', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    /**
     * Callbacks de campos - Configuración de Sincronización
     */
    public function sync_customers_callback() {
        $value = isset($this->options['sync']['sync_customers']) ? $this->options['sync']['sync_customers'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="wzi_sync_settings[sync_customers]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Sincronizar clientes con Zoho CRM', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function sync_orders_callback() {
        $value = isset($this->options['sync']['sync_orders']) ? $this->options['sync']['sync_orders'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="wzi_sync_settings[sync_orders]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Sincronizar pedidos con Zoho CRM', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function sync_products_callback() {
        $value = isset($this->options['sync']['sync_products']) ? $this->options['sync']['sync_products'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="wzi_sync_settings[sync_products]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Sincronizar productos con Zoho Inventory', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function sync_invoices_callback() {
        $value = isset($this->options['sync']['sync_invoices']) ? $this->options['sync']['sync_invoices'] : 'yes';
        ?>
        <label>
            <input type="checkbox" name="wzi_sync_settings[sync_invoices]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Sincronizar facturas con Zoho Books', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function sync_coupons_callback() {
        $value = isset($this->options['sync']['sync_coupons']) ? $this->options['sync']['sync_coupons'] : 'no';
        ?>
        <label>
            <input type="checkbox" name="wzi_sync_settings[sync_coupons]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Sincronizar cupones con Zoho Campaigns', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function sync_direction_callback() {
        $value = isset($this->options['sync']['sync_direction']) ? $this->options['sync']['sync_direction'] : 'both';
        ?>
        <select name="wzi_sync_settings[sync_direction]">
            <option value="woo_to_zoho" <?php selected($value, 'woo_to_zoho'); ?>><?php _e('WooCommerce → Zoho', 'woocommerce-zoho-integration'); ?></option>
            <option value="zoho_to_woo" <?php selected($value, 'zoho_to_woo'); ?>><?php _e('Zoho → WooCommerce', 'woocommerce-zoho-integration'); ?></option>
            <option value="both" <?php selected($value, 'both'); ?>><?php _e('Bidireccional', 'woocommerce-zoho-integration'); ?></option>
        </select>
        <p class="description"><?php _e('Dirección de la sincronización de datos', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function batch_size_callback() {
        $value = isset($this->options['sync']['batch_size']) ? $this->options['sync']['batch_size'] : 50;
        ?>
        <input type="number" name="wzi_sync_settings[batch_size]" value="<?php echo esc_attr($value); ?>" min="10" max="200" />
        <p class="description"><?php _e('Número de registros a procesar por lote', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function sync_interval_callback() {
        $value = isset($this->options['sync']['sync_interval']) ? $this->options['sync']['sync_interval'] : 'hourly';
        ?>
        <select name="wzi_sync_settings[sync_interval]">
            <option value="wzi_five_minutes" <?php selected($value, 'wzi_five_minutes'); ?>><?php _e('Cada 5 minutos', 'woocommerce-zoho-integration'); ?></option>
            <option value="wzi_fifteen_minutes" <?php selected($value, 'wzi_fifteen_minutes'); ?>><?php _e('Cada 15 minutos', 'woocommerce-zoho-integration'); ?></option>
            <option value="hourly" <?php selected($value, 'hourly'); ?>><?php _e('Cada hora', 'woocommerce-zoho-integration'); ?></option>
            <option value="twicedaily" <?php selected($value, 'twicedaily'); ?>><?php _e('Dos veces al día', 'woocommerce-zoho-integration'); ?></option>
            <option value="daily" <?php selected($value, 'daily'); ?>><?php _e('Diariamente', 'woocommerce-zoho-integration'); ?></option>
        </select>
        <p class="description"><?php _e('Frecuencia de sincronización automática', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    /**
     * Callbacks de campos - Configuración de Webhooks
     */
    public function enable_webhooks_callback() {
        $value = isset($this->options['webhook']['enable_webhooks']) ? $this->options['webhook']['enable_webhooks'] : 'no';
        ?>
        <label>
            <input type="checkbox" name="wzi_webhook_settings[enable_webhooks]" value="yes" <?php checked($value, 'yes'); ?> />
            <?php _e('Habilitar webhooks para recibir actualizaciones en tiempo real', 'woocommerce-zoho-integration'); ?>
        </label>
        <?php
    }

    public function webhook_secret_callback() {
        $value = isset($this->options['webhook']['webhook_secret']) ? $this->options['webhook']['webhook_secret'] : '';
        ?>
        <input type="text" name="wzi_webhook_settings[webhook_secret]" value="<?php echo esc_attr($value); ?>" class="regular-text" readonly />
        <button type="button" class="button" onclick="wziGenerateWebhookSecret()"><?php _e('Generar Nuevo', 'woocommerce-zoho-integration'); ?></button>
        <p class="description"><?php _e('Secret para validar webhooks entrantes', 'woocommerce-zoho-integration'); ?></p>
        <?php
    }

    public function webhook_urls_callback() {
        $base_url = rest_url('wzi/v1/webhook/');
        ?>
        <div class="wzi-webhook-urls">
            <p><strong><?php esc_html_e('URLs de Webhook para configurar en Zoho:', 'woocommerce-zoho-integration'); ?></strong></p>
            <p><?php esc_html_e('CRM:', 'woocommerce-zoho-integration'); ?> <code><?php echo esc_url($base_url . 'crm'); ?></code></p>
            <p><?php esc_html_e('Inventory:', 'woocommerce-zoho-integration'); ?> <code><?php echo esc_url($base_url . 'inventory'); ?></code></p>
            <p><?php esc_html_e('Books:', 'woocommerce-zoho-integration'); ?> <code><?php echo esc_url($base_url . 'books'); ?></code></p>
            <p><?php esc_html_e('Campaigns:', 'woocommerce-zoho-integration'); ?> <code><?php echo esc_url($base_url . 'campaigns'); ?></code></p>
        </div>
        <?php
    }

    /**
     * Sanitización de configuraciones
     */
    public function sanitize_general_settings($input) {
        $output = array();
        
        $output['enable_sync'] = isset($input['enable_sync']) ? 'yes' : 'no';
        $output['sync_mode'] = in_array($input['sync_mode'], array('manual', 'automatic', 'realtime')) ? $input['sync_mode'] : 'manual';
        $output['debug_mode'] = isset($input['debug_mode']) ? 'yes' : 'no';
        $output['log_retention_days'] = absint($input['log_retention_days']);
        
        return $output;
    }

    public function sanitize_api_settings($input) {
        $output = array();
        
        $output['client_id'] = sanitize_text_field($input['client_id']);
        $output['client_secret'] = sanitize_text_field($input['client_secret']);
        $output['redirect_uri'] = esc_url_raw($input['redirect_uri']);
        $output['data_center'] = in_array($input['data_center'], array('com', 'eu', 'in', 'com.cn', 'com.au')) ? $input['data_center'] : 'com';
        $output['sandbox_mode'] = isset($input['sandbox_mode']) ? 'yes' : 'no';
        
        return $output;
    }

    public function sanitize_sync_settings($input) {
        $output = array();
        
        $output['sync_customers'] = isset($input['sync_customers']) ? 'yes' : 'no';
        $output['sync_orders'] = isset($input['sync_orders']) ? 'yes' : 'no';
        $output['sync_products'] = isset($input['sync_products']) ? 'yes' : 'no';
        $output['sync_invoices'] = isset($input['sync_invoices']) ? 'yes' : 'no';
        $output['sync_coupons'] = isset($input['sync_coupons']) ? 'yes' : 'no';
        $output['sync_direction'] = in_array($input['sync_direction'], array('woo_to_zoho', 'zoho_to_woo', 'both')) ? $input['sync_direction'] : 'both';
        $output['batch_size'] = absint($input['batch_size']);
        $output['sync_interval'] = sanitize_text_field($input['sync_interval']);
        
        return $output;
    }

    public function sanitize_webhook_settings($input) {
        $output = array();
        
        $output['enable_webhooks'] = isset($input['enable_webhooks']) ? 'yes' : 'no';
        $output['webhook_secret'] = sanitize_text_field($input['webhook_secret']);
        
        return $output;
    }

    /**
     * Obtener una opción específica.
     *
     * @since    1.0.0
     * @param    string    $group      Grupo de opciones.
     * @param    string    $key        Clave de la opción.
     * @param    mixed     $default    Valor por defecto.
     * @return   mixed                 Valor de la opción.
     */
    public function get_option($group, $key = null, $default = null) {
        if (!isset($this->options[$group])) {
            return $default;
        }
        
        if ($key === null) {
            return $this->options[$group];
        }
        
        return isset($this->options[$group][$key]) ? $this->options[$group][$key] : $default;
    }

    /**
     * Actualizar una opción específica.
     *
     * @since    1.0.0
     * @param    string    $group    Grupo de opciones.
     * @param    string    $key      Clave de la opción.
     * @param    mixed     $value    Nuevo valor.
     * @return   bool                Resultado de la actualización.
     */
    public function update_option($group, $key, $value) {
        if (!isset($this->options[$group])) {
            $this->options[$group] = array();
        }
        
        $this->options[$group][$key] = $value;
        
        $option_name = 'wzi_' . $group . '_settings';
        return update_option($option_name, $this->options[$group]);
    }
}