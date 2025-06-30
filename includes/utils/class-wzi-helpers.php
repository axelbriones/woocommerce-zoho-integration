<?php
/**
 * Funciones auxiliares
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/utils
 */

/**
 * Funciones auxiliares.
 *
 * Esta clase contiene funciones de utilidad que se usan
 * en todo el plugin.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/utils
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Helpers {

    /**
     * Formatear precio para Zoho.
     *
     * @since    1.0.0
     * @param    float    $price    Precio.
     * @return   float              Precio formateado.
     */
    public static function format_price_for_zoho($price) {
        return round(floatval($price), 2);
    }

    /**
     * Formatear fecha para Zoho.
     *
     * @since    1.0.0
     * @param    mixed     $date      Fecha (string, timestamp o DateTime).
     * @param    string    $format    Formato de salida.
     * @return   string               Fecha formateada.
     */
    public static function format_date_for_zoho($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return '';
        }
        
        if (is_numeric($date)) {
            // Es un timestamp
            $datetime = new DateTime();
            $datetime->setTimestamp($date);
        } elseif (is_string($date)) {
            // Es una cadena de fecha
            $datetime = new DateTime($date);
        } elseif ($date instanceof DateTime) {
            $datetime = $date;
        } else {
            return '';
        }
        
        return $datetime->format($format);
    }

    /**
     * Convertir código de país a nombre completo.
     *
     * @since    1.0.0
     * @param    string    $country_code    Código de país (ISO 3166-1 alpha-2).
     * @return   string                     Nombre del país.
     */
    public static function get_country_name($country_code) {
        $countries = WC()->countries->get_countries();
        
        return isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
    }

    /**
     * Convertir código de estado/provincia a nombre completo.
     *
     * @since    1.0.0
     * @param    string    $country_code    Código de país.
     * @param    string    $state_code      Código de estado.
     * @return   string                     Nombre del estado.
     */
    public static function get_state_name($country_code, $state_code) {
        $states = WC()->countries->get_states($country_code);
        
        if (is_array($states) && isset($states[$state_code])) {
            return $states[$state_code];
        }
        
        return $state_code;
    }

    /**
     * Limpiar número de teléfono.
     *
     * @since    1.0.0
     * @param    string    $phone    Número de teléfono.
     * @return   string              Número limpio.
     */
    public static function clean_phone_number($phone) {
        // Eliminar todos los caracteres excepto números y +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Si no empieza con +, añadir código de país por defecto
        if (!empty($phone) && substr($phone, 0, 1) !== '+') {
            $default_country_code = get_option('woocommerce_default_country');
            
            // Extraer código de país
            if ($default_country_code) {
                $country_calling_codes = self::get_country_calling_codes();
                $country = substr($default_country_code, 0, 2);
                
                if (isset($country_calling_codes[$country])) {
                    $phone = '+' . $country_calling_codes[$country] . $phone;
                }
            }
        }
        
        return $phone;
    }

    /**
     * Obtener códigos de llamada de países.
     *
     * @since    1.0.0
     * @return   array    Array de códigos de país => código de llamada.
     */
    public static function get_country_calling_codes() {
        return array(
            'US' => '1',
            'CA' => '1',
            'GB' => '44',
            'AU' => '61',
            'DE' => '49',
            'FR' => '33',
            'IT' => '39',
            'ES' => '34',
            'MX' => '52',
            'BR' => '55',
            'AR' => '54',
            'CL' => '56',
            'CO' => '57',
            'PE' => '51',
            'IN' => '91',
            'CN' => '86',
            'JP' => '81',
            // Añadir más según sea necesario
        );
    }

    /**
     * Obtener mapeo de estados de pedido.
     *
     * @since    1.0.0
     * @param    string    $direction    Dirección del mapeo (woo_to_zoho o zoho_to_woo).
     * @return   array                   Array de mapeo.
     */
    public static function get_order_status_mapping($direction = 'woo_to_zoho') {
        $woo_to_zoho = array(
            'pending' => 'Draft',
            'processing' => 'Confirmed',
            'on-hold' => 'On Hold',
            'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Void',
            'failed' => 'Void',
        );
        
        if ($direction === 'woo_to_zoho') {
            return $woo_to_zoho;
        } else {
            return array_flip($woo_to_zoho);
        }
    }

    /**
     * Transformar estado de pedido.
     *
     * @since    1.0.0
     * @param    string    $status       Estado original.
     * @param    string    $direction    Dirección de transformación.
     * @return   string                  Estado transformado.
     */
    public static function transform_order_status($status, $direction = 'woo_to_zoho') {
        $mapping = self::get_order_status_mapping($direction);
        
        return isset($mapping[$status]) ? $mapping[$status] : $status;
    }

    /**
     * Generar SKU único.
     *
     * @since    1.0.0
     * @param    string    $base_sku    SKU base.
     * @return   string                 SKU único.
     */
    public static function generate_unique_sku($base_sku = '') {
        if (empty($base_sku)) {
            $base_sku = 'WC-' . time();
        }
        
        $sku = $base_sku;
        $counter = 1;
        
        while (wc_get_product_id_by_sku($sku)) {
            $sku = $base_sku . '-' . $counter;
            $counter++;
        }
        
        return $sku;
    }

    /**
     * Sanitizar datos para Zoho.
     *
     * @since    1.0.0
     * @param    mixed    $data    Datos a sanitizar.
     * @return   mixed             Datos sanitizados.
     */
    public static function sanitize_for_zoho($data) {
        if (is_array($data)) {
            return array_map(array(__CLASS__, 'sanitize_for_zoho'), $data);
        }
        
        if (is_string($data)) {
            // Eliminar caracteres de control
            $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
            
            // Limitar longitud
            if (strlen($data) > 255) {
                $data = substr($data, 0, 252) . '...';
            }
            
            return wp_strip_all_tags($data);
        }
        
        return $data;
    }

    /**
     * Obtener valor de metadato con fallback.
     *
     * @since    1.0.0
     * @param    int       $object_id    ID del objeto.
     * @param    string    $meta_key     Clave del metadato.
     * @param    mixed     $default      Valor por defecto.
     * @param    string    $object_type  Tipo de objeto (post, user, etc).
     * @return   mixed                   Valor del metadato.
     */
    public static function get_meta_with_fallback($object_id, $meta_key, $default = '', $object_type = 'post') {
        switch ($object_type) {
            case 'post':
                $value = get_post_meta($object_id, $meta_key, true);
                break;
            case 'user':
                $value = get_user_meta($object_id, $meta_key, true);
                break;
            case 'term':
                $value = get_term_meta($object_id, $meta_key, true);
                break;
            default:
                $value = '';
        }
        
        return !empty($value) ? $value : $default;
    }

    /**
     * Merge arrays recursivamente con soporte para índices numéricos.
     *
     * @since    1.0.0
     * @param    array    $array1    Primer array.
     * @param    array    $array2    Segundo array.
     * @return   array              Array combinado.
     */
    public static function array_merge_recursive_distinct($array1, $array2) {
        $merged = $array1;
        
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::array_merge_recursive_distinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }

    /**
     * Convertir objeto a array recursivamente.
     *
     * @since    1.0.0
     * @param    mixed    $obj    Objeto o array.
     * @return   array            Array.
     */
    public static function object_to_array($obj) {
        if (is_object($obj)) {
            $obj = (array) $obj;
        }
        
        if (is_array($obj)) {
            $new = array();
            foreach ($obj as $key => $val) {
                $new[$key] = self::object_to_array($val);
            }
        } else {
            $new = $obj;
        }
        
        return $new;
    }

    /**
     * Verificar si una cadena es JSON válido.
     *
     * @since    1.0.0
     * @param    string    $string    Cadena a verificar.
     * @return   bool                 Si es JSON válido.
     */
    public static function is_json($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Obtener dirección IP del cliente.
     *
     * @since    1.0.0
     * @return   string    Dirección IP.
     */
    public static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Formatear bytes a tamaño legible.
     *
     * @since    1.0.0
     * @param    int       $bytes       Bytes.
     * @param    int       $precision   Decimales.
     * @return   string                 Tamaño formateado.
     */
    public static function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Calcular porcentaje.
     *
     * @since    1.0.0
     * @param    float    $value    Valor.
     * @param    float    $total    Total.
     * @param    int      $decimals Decimales.
     * @return   float              Porcentaje.
     */
    public static function calculate_percentage($value, $total, $decimals = 2) {
        if ($total == 0) {
            return 0;
        }
        
        return round(($value / $total) * 100, $decimals);
    }

    /**
     * Obtener todas las zonas horarias.
     *
     * @since    1.0.0
     * @return   array    Array de zonas horarias.
     */
    public static function get_timezone_list() {
        $zones = timezone_identifiers_list();
        $timezone_list = array();
        
        foreach ($zones as $zone) {
            $parts = explode('/', $zone);
            $continent = isset($parts[0]) ? $parts[0] : '';
            
            if ($continent === 'UTC' || $continent === 'GMT') {
                continue;
            }
            
            $timezone_list[$zone] = str_replace('_', ' ', $zone);
        }
        
        return $timezone_list;
    }

    /**
     * Convertir entre zonas horarias.
     *
     * @since    1.0.0
     * @param    string    $date          Fecha.
     * @param    string    $from_timezone Zona horaria origen.
     * @param    string    $to_timezone   Zona horaria destino.
     * @param    string    $format        Formato de salida.
     * @return   string                   Fecha convertida.
     */
    public static function convert_timezone($date, $from_timezone, $to_timezone, $format = 'Y-m-d H:i:s') {
        try {
            $datetime = new DateTime($date, new DateTimeZone($from_timezone));
            $datetime->setTimezone(new DateTimeZone($to_timezone));
            return $datetime->format($format);
        } catch (Exception $e) {
            return $date;
        }
    }

    /**
     * Obtener configuración con caché.
     *
     * @since    1.0.0
     * @param    string    $option_name    Nombre de la opción.
     * @param    mixed     $default        Valor por defecto.
     * @param    int       $expiration     Tiempo de caché en segundos.
     * @return   mixed                     Valor de la opción.
     */
    public static function get_cached_option($option_name, $default = false, $expiration = 3600) {
        $cache_key = 'wzi_option_' . $option_name;
        $value = get_transient($cache_key);
        
        if ($value === false) {
            $value = get_option($option_name, $default);
            set_transient($cache_key, $value, $expiration);
        }
        
        return $value;
    }

    /**
     * Limpiar caché de opción.
     *
     * @since    1.0.0
     * @param    string    $option_name    Nombre de la opción.
     */
    public static function clear_cached_option($option_name) {
        delete_transient('wzi_option_' . $option_name);
    }

    /**
     * Verificar si estamos en modo debug.
     *
     * @since    1.0.0
     * @return   bool    Si el modo debug está activo.
     */
    public static function is_debug_mode() {
        $general_settings = get_option('wzi_general_settings', array());
        return isset($general_settings['debug_mode']) && $general_settings['debug_mode'] === 'yes';
    }

    /**
     * Log de debug condicional.
     *
     * @since    1.0.0
     * @param    string    $message    Mensaje.
     * @param    array     $context    Contexto.
     */
    public static function debug_log($message, $context = array()) {
        if (self::is_debug_mode()) {
            $logger = new WZI_Logger();
            $logger->debug($message, $context);
        }
    }

    /**
     * Obtener tipos de producto de WooCommerce.
     *
     * @since    1.0.0
     * @return   array    Tipos de producto.
     */
    public static function get_product_types() {
        return wc_get_product_types();
    }

    /**
     * Verificar si un producto es de tipo específico.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Producto.
     * @param    string        $type       Tipo a verificar.
     * @return   bool                      Si es del tipo especificado.
     */
    public static function is_product_type($product, $type) {
        if (!$product instanceof WC_Product) {
            return false;
        }
        
        return $product->is_type($type);
    }

    /**
     * Obtener URL de administración del plugin.
     *
     * @since    1.0.0
     * @param    string    $page    Página del plugin.
     * @param    array     $args    Argumentos adicionales.
     * @return   string             URL completa.
     */
    public static function get_admin_url($page = 'wzi-dashboard', $args = array()) {
        $base_args = array('page' => $page);
        $args = array_merge($base_args, $args);
        
        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Verificar capacidades del usuario.
     *
     * @since    1.0.0
     * @param    string    $capability    Capacidad a verificar.
     * @param    int       $user_id       ID del usuario (opcional).
     * @return   bool                     Si tiene la capacidad.
     */
    public static function user_can($capability, $user_id = null) {
        if ($user_id) {
            return user_can($user_id, $capability);
        }
        
        return current_user_can($capability);
    }

    /**
     * Obtener lista de monedas soportadas.
     *
     * @since    1.0.0
     * @return   array    Array de código => nombre de moneda.
     */
    public static function get_supported_currencies() {
        return get_woocommerce_currencies();
    }

    /**
     * Convertir precio entre monedas.
     *
     * @since    1.0.0
     * @param    float     $amount         Cantidad.
     * @param    string    $from_currency  Moneda origen.
     * @param    string    $to_currency    Moneda destino.
     * @return   float                     Cantidad convertida.
     */
    public static function convert_currency($amount, $from_currency, $to_currency) {
        if ($from_currency === $to_currency) {
            return $amount;
        }
        
        // Aquí podrías implementar conversión real usando tasas de cambio
        // Por ahora, retornamos el mismo valor
        return apply_filters('wzi_convert_currency', $amount, $from_currency, $to_currency);
    }

    /**
     * Generar hash único.
     *
     * @since    1.0.0
     * @param    mixed    $data    Datos para hashear.
     * @return   string            Hash único.
     */
    public static function generate_hash($data) {
        if (is_array($data) || is_object($data)) {
            $data = serialize($data);
        }
        
        return md5($data . time() . wp_rand());
    }

    /**
     * Validar formato de email.
     *
     * @since    1.0.0
     * @param    string    $email    Email a validar.
     * @return   bool                Si es válido.
     */
    public static function is_valid_email($email) {
        return is_email($email) !== false;
    }

    /**
     * Obtener información del sistema.
     *
     * @since    1.0.0
     * @return   array    Información del sistema.
     */
    public static function get_system_info() {
        global $wpdb;
        
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'wc_version' => WC()->version,
            'mysql_version' => $wpdb->db_version(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_max_execution_time' => ini_get('max_execution_time'),
            'php_memory_limit' => ini_get('memory_limit'),
            'wp_memory_limit' => WP_MEMORY_LIMIT,
            'wp_debug' => WP_DEBUG,
            'wp_cron' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON,
            'timezone' => get_option('timezone_string') ?: 'UTC',
        );
    }

    /**
     * Crear nonce con namespace.
     *
     * @since    1.0.0
     * @param    string    $action    Acción del nonce.
     * @return   string              Nonce.
     */
    public static function create_nonce($action) {
        return wp_create_nonce('wzi_' . $action);
    }

    /**
     * Verificar nonce con namespace.
     *
     * @since    1.0.0
     * @param    string    $nonce     Nonce a verificar.
     * @param    string    $action    Acción del nonce.
     * @return   bool                 Si es válido.
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, 'wzi_' . $action);
    }

    /**
     * Obtener plantilla.
     *
     * @since    1.0.0
     * @param    string    $template_name    Nombre de la plantilla.
     * @param    array     $args             Argumentos para la plantilla.
     * @param    bool      $return           Si retornar o imprimir.
     * @return   string|void                 Contenido si $return es true.
     */
    public static function get_template($template_name, $args = array(), $return = false) {
        $template_path = WZI_PLUGIN_DIR . 'templates/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return '';
        }
        
        if ($return) {
            ob_start();
        }
        
        extract($args);
        include $template_path;
        
        if ($return) {
            return ob_get_clean();
        }
    }
}