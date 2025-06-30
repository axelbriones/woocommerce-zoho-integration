<?php
/**
 * Autenticación con Zoho OAuth 2.0
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 */

/**
 * Autenticación con Zoho OAuth 2.0
 *
 * Esta clase maneja todo el proceso de autenticación OAuth 2.0
 * con las APIs de Zoho, incluyendo la obtención y renovación de tokens.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Zoho_Auth {

    /**
     * Configuración de API.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $api_config    Configuración de API.
     */
    private $api_config;

    /**
     * URLs base de Zoho por centro de datos.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $base_urls    URLs base por centro de datos.
     */
    private $base_urls = array(
        'com' => array(
            'accounts' => 'https://accounts.zoho.com',
            'api' => 'https://www.zohoapis.com',
        ),
        'eu' => array(
            'accounts' => 'https://accounts.zoho.eu',
            'api' => 'https://www.zohoapis.eu',
        ),
        'in' => array(
            'accounts' => 'https://accounts.zoho.in',
            'api' => 'https://www.zohoapis.in',
        ),
        'com.cn' => array(
            'accounts' => 'https://accounts.zoho.com.cn',
            'api' => 'https://www.zohoapis.com.cn',
        ),
        'com.au' => array(
            'accounts' => 'https://accounts.zoho.com.au',
            'api' => 'https://www.zohoapis.com.au',
        ),
    );

    /**
     * Scopes requeridos por servicio.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $scopes    Scopes por servicio.
     */
    private $scopes = array(
        'crm' => array(
            'ZohoCRM.modules.ALL',
            'ZohoCRM.settings.ALL',
            'ZohoCRM.users.READ',
        ),
        'inventory' => array(
            'ZohoInventory.items.ALL',
            'ZohoInventory.salesorders.ALL',
            'ZohoInventory.invoices.ALL',
            'ZohoInventory.contacts.ALL',
        ),
        'books' => array(
            'ZohoBooks.invoices.ALL',
            'ZohoBooks.contacts.ALL',
            'ZohoBooks.items.ALL',
            'ZohoBooks.salesorders.ALL',
        ),
        'campaigns' => array(
            'ZohoCampaigns.campaign.ALL',
            'ZohoCampaigns.contact.ALL',
            'ZohoCampaigns.list.ALL',
        ),
    );

    /**
     * Logger.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Logger    $logger    Instancia del logger.
     */
    private $logger;

    /**
     * Tabla de tokens.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $tokens_table    Nombre de la tabla de tokens.
     */
    private $tokens_table;

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        
        $this->api_config = get_option('wzi_api_settings', array());
        $this->logger = new WZI_Logger();
        $this->tokens_table = $wpdb->prefix . 'wzi_auth_tokens';
    }

    /**
     * Obtener URL de autorización.
     *
     * @since    1.0.0
     * @param    array    $services    Servicios a autorizar.
     * @return   string                URL de autorización.
     */
    public function get_authorization_url($services = array('crm')) {
        $client_id = $this->get_client_id();
        $redirect_uri = $this->get_redirect_uri();
        $data_center = $this->get_data_center();
        
        if (empty($client_id) || empty($redirect_uri)) {
            $this->logger->error('Missing API credentials for authorization');
            return false;
        }
        
        // Construir scopes
        $all_scopes = array();
        foreach ($services as $service) {
            if (isset($this->scopes[$service])) {
                $all_scopes = array_merge($all_scopes, $this->scopes[$service]);
            }
        }
        
        $scope = implode(',', array_unique($all_scopes));
        
        // Construir URL
        $params = array(
            'response_type' => 'code',
            'client_id' => $client_id,
            'scope' => $scope,
            'redirect_uri' => $redirect_uri,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('wzi_oauth_' . get_current_user_id()),
        );
        
        $auth_url = $this->base_urls[$data_center]['accounts'] . '/oauth/v2/auth?' . http_build_query($params);
        
        $this->logger->debug('Generated authorization URL', array(
            'services' => $services,
            'data_center' => $data_center,
        ));
        
        return $auth_url;
    }

    /**
     * Manejar callback de OAuth.
     *
     * @since    1.0.0
     * @param    string    $code    Código de autorización.
     * @return   bool               Resultado del proceso.
     */
    public function handle_oauth_callback($code) {
        // Verificar state/nonce
        if (!isset($_GET['state']) || !wp_verify_nonce($_GET['state'], 'wzi_oauth_' . get_current_user_id())) {
            $this->logger->error('Invalid OAuth state/nonce');
            return false;
        }
        
        // Intercambiar código por tokens
        $tokens = $this->exchange_code_for_tokens($code);
        
        if (!$tokens) {
            return false;
        }
        
        // Guardar tokens
        return $this->save_tokens($tokens);
    }

    /**
     * Intercambiar código por tokens.
     *
     * @since    1.0.0
     * @param    string    $code    Código de autorización.
     * @return   array|false        Array de tokens o false en error.
     */
    private function exchange_code_for_tokens($code) {
        $client_id = $this->get_client_id();
        $client_secret = $this->get_client_secret();
        $redirect_uri = $this->get_redirect_uri();
        $data_center = $this->get_data_center();
        
        if (empty($client_id) || empty($client_secret)) {
            $this->logger->error('Missing API credentials for token exchange');
            return false;
        }
        
        $token_url = $this->base_urls[$data_center]['accounts'] . '/oauth/v2/token';
        
        $params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $params,
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            $this->logger->error('Token exchange request failed', array(
                'error' => $response->get_error_message(),
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $this->logger->error('Token exchange error', array(
                'error' => $data['error'],
                'description' => isset($data['error_description']) ? $data['error_description'] : '',
            ));
            return false;
        }
        
        if (!isset($data['access_token']) || !isset($data['refresh_token'])) {
            $this->logger->error('Invalid token response', array(
                'response' => $data,
            ));
            return false;
        }
        
        $this->logger->info('Successfully exchanged code for tokens');
        
        return $data;
    }

    /**
     * Guardar tokens.
     *
     * @since    1.0.0
     * @param    array    $tokens    Array de tokens.
     * @return   bool                Resultado de la operación.
     */
    private function save_tokens($tokens) {
        global $wpdb;
        
        // Determinar servicio basado en el scope
        $scope = isset($tokens['scope']) ? $tokens['scope'] : '';
        $services = $this->detect_services_from_scope($scope);
        
        // Guardar tokens para cada servicio detectado
        foreach ($services as $service) {
            $data = array(
                'service' => $service,
                'token_type' => 'bearer',
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'expires_at' => date('Y-m-d H:i:s', time() + intval($tokens['expires_in'])),
                'scope' => $scope,
            );
            
            // Verificar si ya existe
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->tokens_table} WHERE service = %s",
                $service
            ));
            
            if ($existing) {
                $result = $wpdb->update(
                    $this->tokens_table,
                    $data,
                    array('id' => $existing),
                    array('%s', '%s', '%s', '%s', '%s', '%s'),
                    array('%d')
                );
            } else {
                $result = $wpdb->insert(
                    $this->tokens_table,
                    $data,
                    array('%s', '%s', '%s', '%s', '%s', '%s')
                );
            }
            
            if ($result === false) {
                $this->logger->error('Failed to save tokens', array(
                    'service' => $service,
                    'error' => $wpdb->last_error,
                ));
                return false;
            }
        }
        
        // Limpiar caché de tokens
        delete_transient('wzi_token_cache');
        
        $this->logger->info('Tokens saved successfully', array(
            'services' => $services,
        ));
        
        return true;
    }

    /**
     * Obtener token de acceso.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio (crm, inventory, books, campaigns).
     * @return   string|false          Token de acceso o false.
     */
    public function get_access_token($service = 'crm') {
        global $wpdb;
        
        // Verificar caché
        $cache_key = 'wzi_token_' . $service;
        $cached_token = get_transient($cache_key);
        
        if ($cached_token !== false) {
            return $cached_token;
        }
        
        // Obtener de la base de datos
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tokens_table} WHERE service = %s",
            $service
        ));
        
        if (!$token_data) {
            $this->logger->error('No tokens found for service', array('service' => $service));
            return false;
        }
        
        // Verificar expiración
        $expires_at = strtotime($token_data->expires_at);
        $current_time = time();
        
        // Si expira en menos de 5 minutos, renovar
        if ($expires_at - $current_time < 300) {
            $this->logger->info('Token expiring soon, refreshing', array('service' => $service));
            
            if (!$this->refresh_token($service)) {
                return false;
            }
            
            // Obtener token actualizado
            $token_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->tokens_table} WHERE service = %s",
                $service
            ));
        }
        
        // Cachear por 5 minutos
        set_transient($cache_key, $token_data->access_token, 300);
        
        return $token_data->access_token;
    }

    /**
     * Renovar token.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio a renovar.
     * @return   bool                  Resultado de la operación.
     */
    public function refresh_token($service) {
        global $wpdb;
        
        // Obtener refresh token
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tokens_table} WHERE service = %s",
            $service
        ));
        
        if (!$token_data || empty($token_data->refresh_token)) {
            $this->logger->error('No refresh token found', array('service' => $service));
            return false;
        }
        
        $client_id = $this->get_client_id();
        $client_secret = $this->get_client_secret();
        $data_center = $this->get_data_center();
        
        $token_url = $this->base_urls[$data_center]['accounts'] . '/oauth/v2/token';
        
        $params = array(
            'grant_type' => 'refresh_token',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $token_data->refresh_token,
        );
        
        $response = wp_remote_post($token_url, array(
            'body' => $params,
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            $this->logger->error('Token refresh request failed', array(
                'service' => $service,
                'error' => $response->get_error_message(),
            ));
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            $this->logger->error('Token refresh error', array(
                'service' => $service,
                'error' => $data['error'],
            ));
            
            // Si el refresh token es inválido, eliminar tokens
            if ($data['error'] === 'invalid_grant') {
                $this->revoke_tokens($service);
            }
            
            return false;
        }
        
        if (!isset($data['access_token'])) {
            $this->logger->error('Invalid refresh response', array(
                'service' => $service,
                'response' => $data,
            ));
            return false;
        }
        
        // Actualizar tokens
        $update_data = array(
            'access_token' => $data['access_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + intval($data['expires_in'])),
        );
        
        $result = $wpdb->update(
            $this->tokens_table,
            $update_data,
            array('id' => $token_data->id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            $this->logger->error('Failed to update refreshed token', array(
                'service' => $service,
                'error' => $wpdb->last_error,
            ));
            return false;
        }
        
        // Limpiar caché
        delete_transient('wzi_token_' . $service);
        
        $this->logger->info('Token refreshed successfully', array('service' => $service));
        
        return true;
    }

    /**
     * Renovar todos los tokens.
     *
     * @since    1.0.0
     * @return   int    Número de tokens renovados.
     */
    public function refresh_all_tokens() {
        global $wpdb;
        
        $tokens = $wpdb->get_results("SELECT DISTINCT service FROM {$this->tokens_table}");
        $refreshed = 0;
        
        foreach ($tokens as $token) {
            if ($this->refresh_token($token->service)) {
                $refreshed++;
            }
        }
        
        $this->logger->info('Refreshed all tokens', array('count' => $refreshed));
        
        return $refreshed;
    }

    /**
     * Revocar tokens.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio a revocar (null para todos).
     * @return   bool                  Resultado de la operación.
     */
    public function revoke_tokens($service = null) {
        global $wpdb;
        
        if ($service) {
            $result = $wpdb->delete(
                $this->tokens_table,
                array('service' => $service),
                array('%s')
            );
        } else {
            $result = $wpdb->query("TRUNCATE TABLE {$this->tokens_table}");
        }
        
        // Limpiar caché
        delete_transient('wzi_token_cache');
        
        if ($service) {
            delete_transient('wzi_token_' . $service);
        } else {
            // Limpiar todos los transients de tokens
            $services = array('crm', 'inventory', 'books', 'campaigns');
            foreach ($services as $s) {
                delete_transient('wzi_token_' . $s);
            }
        }
        
        $this->logger->info('Tokens revoked', array('service' => $service));
        
        return $result !== false;
    }

    /**
     * Verificar si está conectado.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio a verificar (null para cualquiera).
     * @return   bool                  Si está conectado.
     */
    public function is_connected($service = null) {
        global $wpdb;
        
        if ($service) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tokens_table} WHERE service = %s",
                $service
            ));
        } else {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->tokens_table}");
        }
        
        return $count > 0;
    }

    /**
     * Obtener servicios conectados.
     *
     * @since    1.0.0
     * @return   array    Array de servicios conectados.
     */
    public function get_connected_services() {
        global $wpdb;
        
        return $wpdb->get_col("SELECT DISTINCT service FROM {$this->tokens_table}");
    }

    /**
     * Detectar servicios desde el scope.
     *
     * @since    1.0.0
     * @param    string    $scope    String de scopes.
     * @return   array              Array de servicios detectados.
     */
    private function detect_services_from_scope($scope) {
        $services = array();
        
        if (strpos($scope, 'ZohoCRM') !== false) {
            $services[] = 'crm';
        }
        if (strpos($scope, 'ZohoInventory') !== false) {
            $services[] = 'inventory';
        }
        if (strpos($scope, 'ZohoBooks') !== false) {
            $services[] = 'books';
        }
        if (strpos($scope, 'ZohoCampaigns') !== false) {
            $services[] = 'campaigns';
        }
        
        return !empty($services) ? $services : array('crm');
    }

    /**
     * Obtener URL base de API.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio.
     * @return   string                URL base.
     */
    public function get_api_base_url($service = 'crm') {
        $data_center = $this->get_data_center();
        $base_url = $this->base_urls[$data_center]['api'];
        
        switch ($service) {
            case 'crm':
                return $base_url . '/crm/v2';
            case 'inventory':
                return $base_url . '/inventory/v1';
            case 'books':
                return $base_url . '/books/v3';
            case 'campaigns':
                return $base_url . '/campaigns/v1';
            default:
                return $base_url;
        }
    }

    /**
     * Getters para configuración.
     */
    private function get_client_id() {
        return isset($this->api_config['client_id']) ? $this->api_config['client_id'] : '';
    }

    private function get_client_secret() {
        return isset($this->api_config['client_secret']) ? $this->api_config['client_secret'] : '';
    }

    private function get_redirect_uri() {
        return isset($this->api_config['redirect_uri']) ? $this->api_config['redirect_uri'] : '';
    }

    private function get_data_center() {
        return isset($this->api_config['data_center']) ? $this->api_config['data_center'] : 'com';
    }

    /**
     * Obtener información de token.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio.
     * @return   object|null          Información del token.
     */
    public function get_token_info($service) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT service, expires_at, scope, created_at, updated_at 
             FROM {$this->tokens_table} 
             WHERE service = %s",
            $service
        ));
    }
}