<?php
/**
 * Sistema de logs del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * Sistema de logs del plugin.
 *
 * Esta clase maneja todo el registro de logs del plugin,
 * incluyendo logs de sincronización, errores y debug.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Logger {

    /**
     * Tabla de logs en la base de datos.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $table_name    Nombre de la tabla de logs.
     */
    private $table_name;

    /**
     * Nivel de log actual.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $log_level    Nivel de log (debug, info, warning, error).
     */
    private $log_level;

    /**
     * Si el modo debug está activo.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug_mode    Estado del modo debug.
     */
    private $debug_mode;

    /**
     * Directorio de logs.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $log_dir    Ruta del directorio de logs.
     */
    private $log_dir;

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wzi_sync_logs';
        
        // Obtener configuración de debug
        $general_settings = get_option('wzi_general_settings', array());
        $this->debug_mode = isset($general_settings['debug_mode']) && $general_settings['debug_mode'] === 'yes';
        
        // Establecer nivel de log
        $this->log_level = $this->debug_mode ? 'debug' : 'info';
        
        // Establecer directorio de logs
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/wzi-logs';
        
        // Crear directorio si no existe
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            file_put_contents($this->log_dir . '/.htaccess', 'deny from all');
        }
    }

    /**
     * Registrar un log.
     *
     * @since    1.0.0
     * @param    string    $sync_type        Tipo de sincronización.
     * @param    string    $sync_direction   Dirección de la sincronización.
     * @param    string    $status           Estado (success, error, warning, info).
     * @param    string    $message          Mensaje del log.
     * @param    array     $details          Detalles adicionales.
     * @return   int|false                   ID del log insertado o false en caso de error.
     */
    public function log($sync_type, $sync_direction, $status, $message, $details = array()) {
        global $wpdb;
        
        // Verificar nivel de log
        if (!$this->should_log($status)) {
            return false;
        }
        
        // Preparar datos
        $data = array(
            'sync_type' => $sync_type,
            'sync_direction' => $sync_direction,
            'status' => $status,
            'message' => $message,
            'details' => json_encode($details),
            'created_at' => current_time('mysql'),
        );
        
        // Insertar en base de datos
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            $this->log_to_file('error', 'Failed to insert log to database: ' . $wpdb->last_error);
            return false;
        }
        
        // También escribir en archivo si es error o modo debug
        if ($status === 'error' || $this->debug_mode) {
            $this->log_to_file($status, $message, array_merge($details, array(
                'sync_type' => $sync_type,
                'sync_direction' => $sync_direction,
            )));
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Registrar log de información.
     *
     * @since    1.0.0
     * @param    string    $message    Mensaje.
     * @param    array     $context    Contexto adicional.
     */
    public function info($message, $context = array()) {
        $sync_type = isset($context['sync_type']) ? $context['sync_type'] : 'general';
        $sync_direction = isset($context['sync_direction']) ? $context['sync_direction'] : 'none';
        
        return $this->log($sync_type, $sync_direction, 'info', $message, $context);
    }

    /**
     * Registrar log de advertencia.
     *
     * @since    1.0.0
     * @param    string    $message    Mensaje.
     * @param    array     $context    Contexto adicional.
     */
    public function warning($message, $context = array()) {
        $sync_type = isset($context['sync_type']) ? $context['sync_type'] : 'general';
        $sync_direction = isset($context['sync_direction']) ? $context['sync_direction'] : 'none';
        
        return $this->log($sync_type, $sync_direction, 'warning', $message, $context);
    }

    /**
     * Registrar log de error.
     *
     * @since    1.0.0
     * @param    string    $message    Mensaje.
     * @param    array     $context    Contexto adicional.
     */
    public function error($message, $context = array()) {
        $sync_type = isset($context['sync_type']) ? $context['sync_type'] : 'general';
        $sync_direction = isset($context['sync_direction']) ? $context['sync_direction'] : 'none';
        
        // Agregar backtrace si está en modo debug
        if ($this->debug_mode && !isset($context['backtrace'])) {
            $context['backtrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        }
        
        return $this->log($sync_type, $sync_direction, 'error', $message, $context);
    }

    /**
     * Registrar log de debug.
     *
     * @since    1.0.0
     * @param    string    $message    Mensaje.
     * @param    array     $context    Contexto adicional.
     */
    public function debug($message, $context = array()) {
        if (!$this->debug_mode) {
            return false;
        }
        
        $sync_type = isset($context['sync_type']) ? $context['sync_type'] : 'general';
        $sync_direction = isset($context['sync_direction']) ? $context['sync_direction'] : 'none';
        
        return $this->log($sync_type, $sync_direction, 'debug', $message, $context);
    }

    /**
     * Verificar si se debe registrar según el nivel.
     *
     * @since    1.0.0
     * @param    string    $status    Estado del log.
     * @return   bool                 Si se debe registrar.
     */
    private function should_log($status) {
        $levels = array(
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
        );
        
        $current_level = isset($levels[$this->log_level]) ? $levels[$this->log_level] : 1;
        $status_level = isset($levels[$status]) ? $levels[$status] : 1;
        
        return $status_level >= $current_level;
    }

    /**
     * Escribir log en archivo.
     *
     * @since    1.0.0
     * @param    string    $level      Nivel del log.
     * @param    string    $message    Mensaje.
     * @param    array     $context    Contexto adicional.
     */
    private function log_to_file($level, $message, $context = array()) {
        $filename = $this->log_dir . '/wzi-' . date('Y-m-d') . '.log';
        
        $log_entry = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );
        
        file_put_contents($filename, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Obtener logs.
     *
     * @since    1.0.0
     * @param    array    $args    Argumentos de consulta.
     * @return   array             Array con logs y total.
     */
    public function get_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
            'sync_type' => '',
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Construir consulta
        $where = array('1=1');
        $values = array();
        
        if (!empty($args['sync_type'])) {
            $where[] = 'sync_type = %s';
            $values[] = $args['sync_type'];
        }
        
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $args['date_to'] . ' 23:59:59';
        }
        
        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR details LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Contar total
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($values)) {
            $count_query = $wpdb->prepare($count_query, $values);
        }
        $total = $wpdb->get_var($count_query);
        
        // Obtener logs
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby = in_array($args['orderby'], array('id', 'sync_type', 'status', 'created_at')) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $values[] = $args['per_page'];
        $values[] = $offset;
        
        $logs = $wpdb->get_results($wpdb->prepare($query, $values));
        
        // Decodificar detalles JSON
        foreach ($logs as &$log) {
            $log->details = json_decode($log->details, true);
        }
        
        return array(
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }

    /**
     * Obtener resumen de logs.
     *
     * @since    1.0.0
     * @param    string    $period    Período (today, week, month).
     * @return   array                Array con estadísticas.
     */
    public function get_log_summary($period = 'today') {
        global $wpdb;
        
        $date_condition = '';
        
        switch ($period) {
            case 'today':
                $date_condition = 'DATE(created_at) = CURDATE()';
                break;
            case 'week':
                $date_condition = 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $date_condition = 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            default:
                $date_condition = '1=1';
        }
        
        // Obtener conteos por estado
        $query = "SELECT status, COUNT(*) as count 
                  FROM {$this->table_name} 
                  WHERE {$date_condition} 
                  GROUP BY status";
        
        $results = $wpdb->get_results($query);
        
        $summary = array(
            'total' => 0,
            'success' => 0,
            'error' => 0,
            'warning' => 0,
            'info' => 0,
        );
        
        foreach ($results as $result) {
            $summary[$result->status] = intval($result->count);
            $summary['total'] += intval($result->count);
        }
        
        // Obtener conteos por tipo de sincronización
        $query = "SELECT sync_type, COUNT(*) as count 
                  FROM {$this->table_name} 
                  WHERE {$date_condition} 
                  GROUP BY sync_type";
        
        $sync_types = $wpdb->get_results($query);
        $summary['by_type'] = array();
        
        foreach ($sync_types as $type) {
            $summary['by_type'][$type->sync_type] = intval($type->count);
        }
        
        // Obtener últimos errores
        $query = "SELECT * FROM {$this->table_name} 
                  WHERE status = 'error' AND {$date_condition} 
                  ORDER BY created_at DESC 
                  LIMIT 5";
        
        $summary['recent_errors'] = $wpdb->get_results($query);
        
        return $summary;
    }

    /**
     * Limpiar logs antiguos.
     *
     * @since    1.0.0
     * @param    int    $days    Número de días a mantener (0 = limpiar todos).
     * @return   int|false       Número de filas eliminadas o false en error.
     */
    public function cleanup_old_logs($days = null) {
        global $wpdb;
        
        if ($days === null) {
            $general_settings = get_option('wzi_general_settings', array());
            $days = isset($general_settings['log_retention_days']) ? intval($general_settings['log_retention_days']) : 30;
        }
        
        if ($days <= 0) {
            return false;
        }
        
        // Limpiar de la base de datos
        $query = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        
        $deleted_db = $wpdb->query($query);
        
        // Limpiar archivos de log
        $deleted_files = 0;
        $files = glob($this->log_dir . '/*.log');
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_files++;
                }
            }
        }
        
        $this->info('Logs cleanup completed', array(
            'deleted_from_db' => $deleted_db,
            'deleted_files' => $deleted_files,
        ));
        
        return $deleted_db;
    }

    /**
     * Limpiar todos los logs.
     *
     * @since    1.0.0
     * @param    int    $days    Si se especifica, solo limpiar logs más antiguos que estos días.
     * @return   bool             Resultado de la operación.
     */
    public function clear_logs($days = 0) {
        global $wpdb;
        
        if ($days > 0) {
            return $this->cleanup_old_logs($days) !== false;
        }
        
        // Limpiar toda la tabla
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        
        // Limpiar todos los archivos de log
        $files = glob($this->log_dir . '/*.log');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return $result !== false;
    }

    /**
     * Exportar logs.
     *
     * @since    1.0.0
     * @param    string    $format    Formato de exportación (csv, json).
     * @param    array     $args      Argumentos de filtro.
     * @return   string               Contenido del archivo o ruta del archivo.
     */
    public function export_logs($format = 'csv', $args = array()) {
        $logs_data = $this->get_logs(array_merge($args, array('per_page' => -1)));
        $logs = $logs_data['logs'];
        
        if ($format === 'csv') {
            $csv_data = array();
            $csv_data[] = array('ID', 'Tipo', 'Dirección', 'Estado', 'Mensaje', 'Detalles', 'Fecha');
            
            foreach ($logs as $log) {
                $csv_data[] = array(
                    $log->id,
                    $log->sync_type,
                    $log->sync_direction,
                    $log->status,
                    $log->message,
                    json_encode($log->details),
                    $log->created_at,
                );
            }
            
            $output = '';
            foreach ($csv_data as $row) {
                $output .= implode(',', array_map('wzi_csv_escape', $row)) . "\n";
            }
            
            return $output;
        } elseif ($format === 'json') {
            return json_encode($logs, JSON_PRETTY_PRINT);
        }
        
        return '';
    }

    /**
     * Obtener tipos de sincronización únicos.
     *
     * @since    1.0.0
     * @return   array    Array de tipos de sincronización.
     */
    public function get_sync_types() {
        global $wpdb;
        
        $query = "SELECT DISTINCT sync_type FROM {$this->table_name} ORDER BY sync_type";
        return $wpdb->get_col($query);
    }

    /**
     * Obtener archivo de log actual.
     *
     * @since    1.0.0
     * @return   string    Ruta del archivo de log.
     */
    public function get_current_log_file() {
        return $this->log_dir . '/wzi-' . date('Y-m-d') . '.log';
    }

    /**
     * Obtener tamaño total de logs.
     *
     * @since    1.0.0
     * @return   int    Tamaño en bytes.
     */
    public function get_logs_size() {
        $total_size = 0;
        
        // Tamaño de archivos
        $files = glob($this->log_dir . '/*.log');
        foreach ($files as $file) {
            if (is_file($file)) {
                $total_size += filesize($file);
            }
        }
        
        // Estimar tamaño en base de datos
        global $wpdb;
        $db_size = $wpdb->get_var("SELECT SUM(LENGTH(message) + LENGTH(details)) FROM {$this->table_name}");
        $total_size += intval($db_size);
        
        return $total_size;
    }
}

/**
 * Función auxiliar para escapar valores CSV.
 *
 * @since    1.0.0
 * @param    string    $value    Valor a escapar.
 * @return   string              Valor escapado.
 */
function wzi_csv_escape($value) {
    if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}