<?php
/**
 * Manejador general de API
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 */

/**
 * Manejador general de API.
 *
 * Esta clase proporciona funcionalidad base para todas las
 * clases de API específicas de cada servicio de Zoho.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/api
 * @author     Tu Nombre <tu@email.com>
 */
abstract class WZI_API_Handler {

    /**
     * Nombre del servicio.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $service    Nombre del servicio (crm, inventory, books, campaigns).
     */
    protected $service;

    /**
     * Instancia de autenticación.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WZI_Zoho_Auth    $auth    Instancia de autenticación.
     */
    protected $auth;

    /**
     * Logger.
     *
     * @since    1.0.0
     * @access   protected
     * @var      WZI_Logger    $logger    Instancia del logger.
     */
    protected $logger;

    /**
     * URL base de la API.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $api_base_url    URL base de la API.
     */
    protected $api_base_url;

    /**
     * Límite de rate.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $rate_limit    Información del límite de rate.
     */
    protected $rate_limit = array(
        'limit' => 0,
        'remaining' => 0,
        'reset' => 0,
    );

    /**
     * Constructor.
     *
     * @since    1.0.0
     * @param    string    $service    Nombre del servicio.
     */
    public function __construct($service) {
        $this->service = $service;
        $this->auth = new WZI_Zoho_Auth();
        $this->logger = new WZI_Logger();
        $this->api_base_url = $this->auth->get_api_base_url($service);
    }

    /**
     * Realizar solicitud a la API.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint de la API.
     * @param    string    $method      Método HTTP (GET, POST, PUT, DELETE).
     * @param    array     $data        Datos a enviar.
     * @param    array     $headers     Headers adicionales.
     * @return   array|WP_Error         Respuesta de la API o error.
     */
    protected function make_request($endpoint, $method = 'GET', $data = array(), $headers = array()) {
        // Obtener token de acceso
        $access_token = $this->auth->get_access_token($this->service);
        
        if (!$access_token) {
            $error_message = sprintf(
                __('No se pudo obtener token de acceso para %s', 'woocommerce-zoho-integration'),
                $this->service
            );
            
            $this->logger->error($error_message, array(
                'service' => $this->service,
                'endpoint' => $endpoint,
            ));
            
            return new WP_Error('no_access_token', $error_message);
        }
        
        // Construir URL completa
        $url = $this->api_base_url . '/' . ltrim($endpoint, '/');
        
        // Headers por defecto
        $default_headers = array(
            'Authorization' => 'Zoho-oauthtoken ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        );
        
        $headers = array_merge($default_headers, $headers);
        
        // Configurar argumentos de la solicitud
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
        );
        
        // Añadir datos según el método
        if (in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        } elseif ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }
        
        // Log de debug
        $this->logger->debug('API Request', array(
            'service' => $this->service,
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'data' => $data,
        ));
        
        // Realizar solicitud
        $response = wp_remote_request($url, $args);
        
        // Manejar errores de conexión
        if (is_wp_error($response)) {
            $this->logger->error('API Request Failed', array(
                'service' => $this->service,
                'url' => $url,
                'error' => $response->get_error_message(),
            ));
            
            return $response;
        }
        
        // Obtener código de respuesta
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // Actualizar información de rate limit
        $this->update_rate_limit($response_headers);
        
        // Log de respuesta
        $this->logger->debug('API Response', array(
            'service' => $this->service,
            'url' => $url,
            'code' => $response_code,
            'body' => $response_body,
        ));
        
        // Decodificar respuesta JSON
        $decoded_response = json_decode($response_body, true);
        
        // Manejar errores de la API
        if ($response_code >= 400) {
            return $this->handle_api_error($response_code, $decoded_response, $url);
        }
        
        // Retornar respuesta exitosa
        return array(
            'success' => true,
            'code' => $response_code,
            'data' => $decoded_response,
            'headers' => $response_headers,
        );
    }

    /**
     * Manejar error de la API.
     *
     * @since    1.0.0
     * @param    int       $code         Código de respuesta HTTP.
     * @param    mixed     $response     Respuesta decodificada.
     * @param    string    $url          URL de la solicitud.
     * @return   WP_Error                Error formateado.
     */
    protected function handle_api_error($code, $response, $url) {
        $error_message = '';
        $error_code = 'api_error';
        
        // Manejar diferentes tipos de errores
        switch ($code) {
            case 400:
                $error_code = 'bad_request';
                $error_message = __('Solicitud inválida', 'woocommerce-zoho-integration');
                break;
                
            case 401:
                $error_code = 'unauthorized';
                $error_message = __('No autorizado. El token puede haber expirado.', 'woocommerce-zoho-integration');
                
                // Intentar renovar token
                $this->auth->refresh_token($this->service);
                break;
                
            case 403:
                $error_code = 'forbidden';
                $error_message = __('Acceso denegado. Verifique los permisos.', 'woocommerce-zoho-integration');
                break;
                
            case 404:
                $error_code = 'not_found';
                $error_message = __('Recurso no encontrado', 'woocommerce-zoho-integration');
                break;
                
            case 429:
                $error_code = 'rate_limit_exceeded';
                $error_message = __('Límite de solicitudes excedido', 'woocommerce-zoho-integration');
                break;
                
            case 500:
            case 502:
            case 503:
                $error_code = 'server_error';
                $error_message = __('Error del servidor de Zoho', 'woocommerce-zoho-integration');
                break;
                
            default:
                $error_message = sprintf(
                    __('Error de API: Código %d', 'woocommerce-zoho-integration'),
                    $code
                );
        }
        
        // Extraer mensaje de error de la respuesta si está disponible
        if (is_array($response)) {
            if (isset($response['message'])) {
                $error_message .= ': ' . $response['message'];
            } elseif (isset($response['error']['message'])) {
                $error_message .= ': ' . $response['error']['message'];
            } elseif (isset($response['errors']) && is_array($response['errors'])) {
                $errors = array_map(function($error) {
                    return isset($error['message']) ? $error['message'] : json_encode($error);
                }, $response['errors']);
                $error_message .= ': ' . implode(', ', $errors);
            }
        }
        
        // Registrar error
        $this->logger->error('API Error', array(
            'service' => $this->service,
            'url' => $url,
            'code' => $code,
            'error' => $error_message,
            'response' => $response,
        ));
        
        return new WP_Error($error_code, $error_message, array(
            'status' => $code,
            'response' => $response,
        ));
    }

    /**
     * Actualizar información de rate limit.
     *
     * @since    1.0.0
     * @param    array    $headers    Headers de respuesta.
     */
    protected function update_rate_limit($headers) {
        if (isset($headers['x-rate-limit-limit'])) {
            $this->rate_limit['limit'] = intval($headers['x-rate-limit-limit']);
        }
        
        if (isset($headers['x-rate-limit-remaining'])) {
            $this->rate_limit['remaining'] = intval($headers['x-rate-limit-remaining']);
        }
        
        if (isset($headers['x-rate-limit-reset'])) {
            $this->rate_limit['reset'] = intval($headers['x-rate-limit-reset']);
        }
        
        // Guardar en transient si el límite es bajo
        if ($this->rate_limit['remaining'] < 10) {
            set_transient(
                'wzi_rate_limit_' . $this->service,
                $this->rate_limit,
                $this->rate_limit['reset'] - time()
            );
            
            $this->logger->warning('Rate limit warning', array(
                'service' => $this->service,
                'remaining' => $this->rate_limit['remaining'],
                'reset_in' => $this->rate_limit['reset'] - time(),
            ));
        }
    }

    /**
     * Verificar límite de rate antes de hacer solicitud.
     *
     * @since    1.0.0
     * @return   bool    Si se puede hacer la solicitud.
     */
    protected function check_rate_limit() {
        $rate_limit = get_transient('wzi_rate_limit_' . $this->service);
        
        if ($rate_limit && isset($rate_limit['remaining']) && $rate_limit['remaining'] <= 0) {
            $wait_time = $rate_limit['reset'] - time();
            
            if ($wait_time > 0) {
                $this->logger->warning('Rate limit exceeded, waiting', array(
                    'service' => $this->service,
                    'wait_seconds' => $wait_time,
                ));
                
                return false;
            }
        }
        
        return true;
    }

    /**
     * GET request.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @param    array     $params      Parámetros de query.
     * @return   array|WP_Error         Respuesta.
     */
    public function get($endpoint, $params = array()) {
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit', __('Límite de solicitudes excedido. Por favor, espere.', 'woocommerce-zoho-integration'));
        }
        
        return $this->make_request($endpoint, 'GET', $params);
    }

    /**
     * POST request.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @param    array     $data        Datos a enviar.
     * @return   array|WP_Error         Respuesta.
     */
    public function post($endpoint, $data = array()) {
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit', __('Límite de solicitudes excedido. Por favor, espere.', 'woocommerce-zoho-integration'));
        }
        
        return $this->make_request($endpoint, 'POST', $data);
    }

    /**
     * PUT request.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @param    array     $data        Datos a enviar.
     * @return   array|WP_Error         Respuesta.
     */
    public function put($endpoint, $data = array()) {
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit', __('Límite de solicitudes excedido. Por favor, espere.', 'woocommerce-zoho-integration'));
        }
        
        return $this->make_request($endpoint, 'PUT', $data);
    }

    /**
     * DELETE request.
     *
     * @since    1.0.0
     * @param    string    $endpoint    Endpoint.
     * @return   array|WP_Error         Respuesta.
     */
    public function delete($endpoint) {
        if (!$this->check_rate_limit()) {
            return new WP_Error('rate_limit', __('Límite de solicitudes excedido. Por favor, espere.', 'woocommerce-zoho-integration'));
        }
        
        return $this->make_request($endpoint, 'DELETE');
    }

    /**
     * Procesar respuesta por lotes.
     *
     * @since    1.0.0
     * @param    string    $endpoint       Endpoint.
     * @param    array     $params         Parámetros.
     * @param    string    $data_key       Clave donde están los datos en la respuesta.
     * @param    int       $per_page       Elementos por página.
     * @return   array                     Todos los elementos.
     */
    protected function get_all_pages($endpoint, $params = array(), $data_key = 'data', $per_page = 200) {
        $all_items = array();
        $page = 1;
        $has_more = true;
        
        $params['per_page'] = $per_page;
        
        while ($has_more) {
            $params['page'] = $page;
            
            $response = $this->get($endpoint, $params);
            
            if (is_wp_error($response)) {
                $this->logger->error('Failed to get page', array(
                    'service' => $this->service,
                    'endpoint' => $endpoint,
                    'page' => $page,
                    'error' => $response->get_error_message(),
                ));
                break;
            }
            
            $data = $response['data'];
            
            // Obtener elementos de la página actual
            if (isset($data[$data_key]) && is_array($data[$data_key])) {
                $all_items = array_merge($all_items, $data[$data_key]);
            } else {
                break;
            }
            
            // Verificar si hay más páginas
            $has_more = isset($data['info']['more_records']) && $data['info']['more_records'] === true;
            
            // Alternativamente, verificar por conteo
            if (!$has_more && isset($data['info']['count'])) {
                $total_fetched = count($all_items);
                $total_available = intval($data['info']['count']);
                $has_more = $total_fetched < $total_available;
            }
            
            $page++;
            
            // Evitar bucles infinitos
            if ($page > 100) {
                $this->logger->warning('Page limit reached', array(
                    'service' => $this->service,
                    'endpoint' => $endpoint,
                    'pages_fetched' => $page - 1,
                ));
                break;
            }
            
            // Pequeña pausa para respetar rate limits
            if ($has_more) {
                usleep(100000); // 100ms
            }
        }
        
        return $all_items;
    }

    /**
     * Probar conexión con el servicio.
     *
     * @since    1.0.0
     * @return   bool    Si la conexión es exitosa.
     */
    abstract public function test_connection();

    /**
     * Obtener información de rate limit.
     *
     * @since    1.0.0
     * @return   array    Información de rate limit.
     */
    public function get_rate_limit_info() {
        return $this->rate_limit;
    }

    /**
     * Formatear datos para Zoho.
     *
     * @since    1.0.0
     * @param    array    $data    Datos a formatear.
     * @return   array             Datos formateados.
     */
    protected function format_data_for_zoho($data) {
        // Eliminar campos vacíos
        $data = array_filter($data, function($value) {
            return $value !== null && $value !== '';
        });
        
        // Convertir booleanos
        array_walk($data, function(&$value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
        });
        
        return $data;
    }

    /**
     * Cache de respuestas.
     *
     * @since    1.0.0
     * @param    string    $key           Clave de cache.
     * @param    mixed     $data          Datos a cachear (null para obtener).
     * @param    int       $expiration    Tiempo de expiración en segundos.
     * @return   mixed                    Datos cacheados o false.
     */
    protected function cache($key, $data = null, $expiration = 300) {
        $cache_key = 'wzi_' . $this->service . '_' . md5($key);
        
        if ($data === null) {
            // Obtener de cache
            return get_transient($cache_key);
        } else {
            // Guardar en cache
            set_transient($cache_key, $data, $expiration);
            return $data;
        }
    }

    /**
     * Limpiar cache.
     *
     * @since    1.0.0
     * @param    string    $pattern    Patrón de claves a limpiar (opcional).
     */
    public function clear_cache($pattern = '') {
        global $wpdb;
        
        $prefix = '_transient_wzi_' . $this->service . '_';
        
        if ($pattern) {
            $prefix .= $pattern;
        }
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($prefix) . '%'
        ));
    }
}