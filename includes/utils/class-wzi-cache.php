<?php
/**
 * Sistema de caché
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/utils
 */

/**
 * Sistema de caché.
 *
 * Esta clase maneja el caché de datos para mejorar el rendimiento
 * y reducir las llamadas a la API de Zoho.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/utils
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Cache {

    /**
     * Prefijo para las claves de caché.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $prefix    Prefijo de caché.
     */
    private $prefix = 'wzi_cache_';

    /**
     * Tiempo de expiración por defecto (1 hora).
     *
     * @since    1.0.0
     * @access   private
     * @var      int    $default_expiration    Tiempo en segundos.
     */
    private $default_expiration = 3600;

    /**
     * Grupos de caché.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $cache_groups    Grupos de caché definidos.
     */
    private $cache_groups = array(
        'api_responses' => 3600,      // 1 hora
        'field_mappings' => 86400,    // 24 horas
        'zoho_fields' => 86400,       // 24 horas
        'sync_status' => 300,         // 5 minutos
        'organization_info' => 86400, // 24 horas
        'auth_tokens' => 300,         // 5 minutos
        'products' => 1800,           // 30 minutos
        'customers' => 1800,          // 30 minutos
        'orders' => 900,              // 15 minutos
    );

    /**
     * Estadísticas de caché.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $stats    Estadísticas de uso.
     */
    private $stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
    );

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        // Cargar estadísticas guardadas
        $saved_stats = get_option('wzi_cache_stats', array());
        if (!empty($saved_stats)) {
            $this->stats = $saved_stats;
        }
        
        // Registrar shutdown hook para guardar estadísticas
        add_action('shutdown', array($this, 'save_stats'));
    }

    /**
     * Obtener valor del caché.
     *
     * @since    1.0.0
     * @param    string    $key        Clave del caché.
     * @param    string    $group      Grupo de caché.
     * @param    mixed     $default    Valor por defecto si no existe.
     * @return   mixed                 Valor del caché o default.
     */
    public function get($key, $group = 'default', $default = false) {
        $cache_key = $this->build_key($key, $group);
        
        // Intentar obtener de caché de objetos si está disponible
        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($cache_key, 'wzi');
            
            if ($value !== false) {
                $this->stats['hits']++;
                return $this->maybe_unserialize($value);
            }
        }
        
        // Intentar obtener de transients
        $value = get_transient($cache_key);
        
        if ($value !== false) {
            $this->stats['hits']++;
            
            // Si está usando caché de objetos, también guardarlo ahí
            if (wp_using_ext_object_cache()) {
                wp_cache_set($cache_key, $value, 'wzi', $this->get_expiration($group));
            }
            
            return $this->maybe_unserialize($value);
        }
        
        $this->stats['misses']++;
        return $default;
    }

    /**
     * Establecer valor en caché.
     *
     * @since    1.0.0
     * @param    string    $key          Clave del caché.
     * @param    mixed     $value        Valor a cachear.
     * @param    string    $group        Grupo de caché.
     * @param    int       $expiration   Tiempo de expiración personalizado.
     * @return   bool                    Si se guardó correctamente.
     */
    public function set($key, $value, $group = 'default', $expiration = null) {
        $cache_key = $this->build_key($key, $group);
        
        if ($expiration === null) {
            $expiration = $this->get_expiration($group);
        }
        
        $value = $this->maybe_serialize($value);
        
        // Guardar en caché de objetos si está disponible
        if (wp_using_ext_object_cache()) {
            wp_cache_set($cache_key, $value, 'wzi', $expiration);
        }
        
        // Guardar en transients
        $result = set_transient($cache_key, $value, $expiration);
        
        if ($result) {
            $this->stats['sets']++;
        }
        
        return $result;
    }

    /**
     * Eliminar valor del caché.
     *
     * @since    1.0.0
     * @param    string    $key     Clave del caché.
     * @param    string    $group   Grupo de caché.
     * @return   bool               Si se eliminó correctamente.
     */
    public function delete($key, $group = 'default') {
        $cache_key = $this->build_key($key, $group);
        
        // Eliminar de caché de objetos
        if (wp_using_ext_object_cache()) {
            wp_cache_delete($cache_key, 'wzi');
        }
        
        // Eliminar transient
        $result = delete_transient($cache_key);
        
        if ($result) {
            $this->stats['deletes']++;
        }
        
        return $result;
    }

    /**
     * Limpiar grupo de caché.
     *
     * @since    1.0.0
     * @param    string    $group    Grupo a limpiar.
     * @return   int                 Número de elementos eliminados.
     */
    public function flush_group($group) {
        global $wpdb;
        
        $prefix = '_transient_' . $this->prefix . $group . '_';
        
        // Eliminar transients del grupo
        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            $wpdb->esc_like($prefix) . '%',
            $wpdb->esc_like('_transient_timeout_' . $this->prefix . $group . '_') . '%'
        );
        
        $deleted = $wpdb->query($query);
        
        // Si usa caché de objetos, intentar limpiar
        if (wp_using_ext_object_cache() && function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('wzi');
        }
        
        $this->stats['deletes'] += $deleted;
        
        return $deleted;
    }

    /**
     * Limpiar todo el caché del plugin.
     *
     * @since    1.0.0
     * @return   int    Número de elementos eliminados.
     */
    public function flush_all() {
        global $wpdb;
        
        $prefix = '_transient_' . $this->prefix;
        
        // Eliminar todos los transients del plugin
        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s 
             OR option_name LIKE %s",
            $wpdb->esc_like($prefix) . '%',
            $wpdb->esc_like('_transient_timeout_' . $this->prefix) . '%'
        );
        
        $deleted = $wpdb->query($query);
        
        // Si usa caché de objetos, limpiar grupo
        if (wp_using_ext_object_cache()) {
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('wzi');
            } else {
                wp_cache_flush();
            }
        }
        
        // Resetear estadísticas
        $this->stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => $deleted,
        );
        
        return $deleted;
    }

    /**
     * Obtener o establecer valor (caché aside pattern).
     *
     * @since    1.0.0
     * @param    string    $key        Clave del caché.
     * @param    callable  $callback   Función para obtener el valor.
     * @param    string    $group      Grupo de caché.
     * @param    int       $expiration Tiempo de expiración.
     * @return   mixed                 Valor cacheado o generado.
     */
    public function remember($key, $callback, $group = 'default', $expiration = null) {
        $value = $this->get($key, $group);
        
        if ($value === false && is_callable($callback)) {
            $value = call_user_func($callback);
            
            if ($value !== false) {
                $this->set($key, $value, $group, $expiration);
            }
        }
        
        return $value;
    }

    /**
     * Invalidar caché relacionado.
     *
     * @since    1.0.0
     * @param    string    $entity_type    Tipo de entidad.
     * @param    int       $entity_id      ID de la entidad.
     */
    public function invalidate_entity($entity_type, $entity_id) {
        $patterns = array(
            'customer' => array(
                'customers_' . $entity_id,
                'customer_orders_' . $entity_id,
                'customer_stats_' . $entity_id,
            ),
            'order' => array(
                'orders_' . $entity_id,
                'order_items_' . $entity_id,
                'order_customer_*',
            ),
            'product' => array(
                'products_' . $entity_id,
                'product_variations_' . $entity_id,
                'product_stock_' . $entity_id,
            ),
        );
        
        if (isset($patterns[$entity_type])) {
            foreach ($patterns[$entity_type] as $pattern) {
                if (strpos($pattern, '*') !== false) {
                    // Patrón con wildcard
                    $this->delete_by_pattern($pattern, $entity_type . 's');
                } else {
                    $this->delete($pattern, $entity_type . 's');
                }
            }
        }
    }

    /**
     * Eliminar por patrón.
     *
     * @since    1.0.0
     * @param    string    $pattern    Patrón de clave.
     * @param    string    $group      Grupo de caché.
     * @return   int                   Número de elementos eliminados.
     */
    private function delete_by_pattern($pattern, $group) {
        global $wpdb;
        
        $pattern = str_replace('*', '%', $pattern);
        $prefix = '_transient_' . $this->prefix . $group . '_' . $pattern;
        
        $query = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $prefix
        );
        
        return $wpdb->query($query);
    }

    /**
     * Construir clave de caché.
     *
     * @since    1.0.0
     * @param    string    $key     Clave base.
     * @param    string    $group   Grupo.
     * @return   string             Clave completa.
     */
    private function build_key($key, $group) {
        // Sanitizar clave
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        
        // Limitar longitud (transient names max 172 chars)
        $full_key = $this->prefix . $group . '_' . $key;
        
        if (strlen($full_key) > 172) {
            $full_key = $this->prefix . $group . '_' . md5($key);
        }
        
        return $full_key;
    }

    /**
     * Obtener tiempo de expiración para un grupo.
     *
     * @since    1.0.0
     * @param    string    $group    Grupo de caché.
     * @return   int                 Tiempo en segundos.
     */
    private function get_expiration($group) {
        if (isset($this->cache_groups[$group])) {
            return $this->cache_groups[$group];
        }
        
        return $this->default_expiration;
    }

    /**
     * Serializar si es necesario.
     *
     * @since    1.0.0
     * @param    mixed    $value    Valor a serializar.
     * @return   mixed              Valor serializado.
     */
    private function maybe_serialize($value) {
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }
        
        return $value;
    }

    /**
     * Deserializar si es necesario.
     *
     * @since    1.0.0
     * @param    mixed    $value    Valor a deserializar.
     * @return   mixed              Valor deserializado.
     */
    private function maybe_unserialize($value) {
        if (is_serialized($value)) {
            return unserialize($value);
        }
        
        return $value;
    }

    /**
     * Obtener estadísticas de caché.
     *
     * @since    1.0.0
     * @return   array    Estadísticas.
     */
    public function get_stats() {
        $this->stats['hit_rate'] = $this->stats['hits'] + $this->stats['misses'] > 0 
            ? round($this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses']) * 100, 2) 
            : 0;
        
        return $this->stats;
    }

    /**
     * Guardar estadísticas.
     *
     * @since    1.0.0
     */
    public function save_stats() {
        update_option('wzi_cache_stats', $this->stats, false);
    }

    /**
     * Resetear estadísticas.
     *
     * @since    1.0.0
     */
    public function reset_stats() {
        $this->stats = array(
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
        );
        
        delete_option('wzi_cache_stats');
    }

    /**
     * Precalentar caché.
     *
     * @since    1.0.0
     * @param    string    $type    Tipo de datos a precalentar.
     */
    public function warm_cache($type = 'all') {
        $types = array();
        
        if ($type === 'all') {
            $types = array('field_mappings', 'zoho_fields', 'organization_info');
        } else {
            $types[] = $type;
        }
        
        foreach ($types as $cache_type) {
            switch ($cache_type) {
                case 'field_mappings':
                    $this->warm_field_mappings();
                    break;
                    
                case 'zoho_fields':
                    $this->warm_zoho_fields();
                    break;
                    
                case 'organization_info':
                    $this->warm_organization_info();
                    break;
            }
        }
    }

    /**
     * Precalentar mapeos de campos.
     *
     * @since    1.0.0
     */
    private function warm_field_mappings() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'wzi_field_mappings';
        $entity_types = $wpdb->get_col("SELECT DISTINCT entity_type FROM {$table}");
        
        foreach ($entity_types as $entity_type) {
            $mappings = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_type = %s AND is_active = 1",
                $entity_type
            ));
            
            $this->set('mappings_' . $entity_type, $mappings, 'field_mappings');
        }
    }

    /**
     * Precalentar campos de Zoho.
     *
     * @since    1.0.0
     */
    private function warm_zoho_fields() {
        $crm_api = new WZI_Zoho_CRM();
        $modules = array('Contacts', 'Leads', 'Deals', 'Products');
        
        foreach ($modules as $module) {
            $fields = $crm_api->get_module_fields($module);
            if (!is_wp_error($fields)) {
                $this->set('fields_' . $module, $fields, 'zoho_fields');
            }
        }
    }

    /**
     * Precalentar información de organización.
     *
     * @since    1.0.0
     */
    private function warm_organization_info() {
        $crm_api = new WZI_Zoho_CRM();
        $org_info = $crm_api->get_organization_info();
        
        if (!is_wp_error($org_info)) {
            $this->set('organization', $org_info, 'organization_info');
        }
    }

    /**
     * Obtener información de uso de caché.
     *
     * @since    1.0.0
     * @return   array    Información de uso.
     */
    public function get_cache_info() {
        global $wpdb;
        
        // Contar transients del plugin
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_' . $this->prefix . '%'
        ));
        
        // Calcular tamaño aproximado
        $size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '_transient_' . $this->prefix . '%'
        ));
        
        return array(
            'entries' => intval($count),
            'size' => intval($size),
            'size_formatted' => size_format($size),
            'using_object_cache' => wp_using_ext_object_cache(),
            'groups' => array_keys($this->cache_groups),
            'stats' => $this->get_stats(),
        );
    }

    /**
     * Programar limpieza automática de caché.
     *
     * @since    1.0.0
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('wzi_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wzi_cache_cleanup');
        }
        
        add_action('wzi_cache_cleanup', array($this, 'cleanup_expired'));
    }

    /**
     * Limpiar entradas expiradas.
     *
     * @since    1.0.0
     * @return   int    Número de entradas eliminadas.
     */
    public function cleanup_expired() {
        global $wpdb;
        
        // WordPress limpia automáticamente los transients expirados,
        // pero podemos forzar la limpieza
        $time = time();
        
        $query = $wpdb->prepare(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE %s
             AND a.option_name NOT LIKE %s
             AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
             AND b.option_value < %d",
            $wpdb->esc_like('_transient_' . $this->prefix) . '%',
            $wpdb->esc_like('_transient_timeout_') . '%',
            $time
        );
        
        return $wpdb->query($query);
    }
}