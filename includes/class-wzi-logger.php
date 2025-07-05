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
 */
class WZI_Logger {

    private $table_name;
    private $log_level_numeric = array(
        'debug'   => 100,
        'info'    => 200,
        'warning' => 300,
        'error'   => 400,
    );
    private $current_log_level_numeric;
    private $debug_mode;
    private $log_dir;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wzi_sync_logs';
        
        $general_settings = get_option('wzi_general_settings', array());
        $this->debug_mode = isset($general_settings['debug_mode']) && $general_settings['debug_mode'] === 'yes';
        
        $config_log_level_setting = isset($general_settings['log_level']) ? $general_settings['log_level'] : 'info';
        $this->current_log_level_numeric = $this->log_level_numeric[$config_log_level_setting] ?? $this->log_level_numeric['info'];
        if ($this->debug_mode) {
            $this->current_log_level_numeric = $this->log_level_numeric['debug'];
        }
        
        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['basedir'])) {
            $this->log_dir = $upload_dir['basedir'] . '/wzi-logs';
            if (!file_exists($this->log_dir)) {
                if (wp_mkdir_p($this->log_dir)) {
                    @file_put_contents($this->log_dir . '/.htaccess', 'deny from all');
                    @file_put_contents($this->log_dir . '/index.html', ''); 
                }
            }
        } else {
            $this->log_dir = WZI_PLUGIN_DIR . 'logs';
             if (!file_exists($this->log_dir)) { @wp_mkdir_p($this->log_dir); }
            error_log('WZI_Logger: wp_upload_dir() did not return basedir. Falling back to plugin logs directory.');
        }
    }

    public function log_entry($level, $message, $context = array()) {
        global $wpdb;
        
        if (!$this->should_log($level)) {
            return false;
        }
        
        $source = isset($context['source']) ? sanitize_text_field($context['source']) : 'general';
        unset($context['source']); 
        
        $object_id = isset($context['object_id']) ? strval($context['object_id']) : null;
        unset($context['object_id']);
        
        $object_type = isset($context['object_type']) ? sanitize_text_field($context['object_type']) : null;
        unset($context['object_type']);

        $data_to_insert = array(
            'timestamp'   => current_time('mysql', 1), // GMT
            'log_level'   => sanitize_text_field($level),
            'source'      => $source,
            'message'     => $message, 
            'object_id'   => $object_id,
            'object_type' => $object_type,
            'details'     => !empty($context) ? wp_json_encode($context) : null,
        );
        
        $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s');
        
        $result = $wpdb->insert($this->table_name, $data_to_insert, $formats);
        
        if ($result === false) {
            $this->log_to_file('ERROR', 'DB Insert Failed: ' . $wpdb->last_error, $data_to_insert);
            return false;
        }
        
        if ($level === 'error' || $this->debug_mode) {
            $this->log_to_file(strtoupper($level), $message, $context);
        }
        
        return $wpdb->insert_id;
    }

    public function info($message, $context = array()) {
        return $this->log_entry('info', $message, $context);
    }

    public function warning($message, $context = array()) {
        return $this->log_entry('warning', $message, $context);
    }

    public function error($message, $context = array()) {
        if ($this->debug_mode && !isset($context['backtrace'])) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 7); 
            array_shift($backtrace); 
            $context['backtrace'] = $this->format_backtrace($backtrace);
        }
        return $this->log_entry('error', $message, $context);
    }

    public function debug($message, $context = array()) {
        return $this->log_entry('debug', $message, $context);
    }
    
    private function format_backtrace($backtrace_array) {
        $formatted = array();
        foreach ($backtrace_array as $item) {
            $formatted_item = (isset($item['file']) ? basename($item['file']) : '{unknown file}') . ':' .
                              (isset($item['line']) ? $item['line'] : '{unknown line}');
            $func_call = '';
            if (isset($item['class'])) $func_call .= $item['class'];
            if (isset($item['type'])) $func_call .= $item['type'];
            if (isset($item['function'])) $func_call .= $item['function'] . '()';
            if ($func_call) $formatted_item .= ' - ' . $func_call;
            
            $formatted[] = $formatted_item;
        }
        return $formatted;
    }

    private function should_log($level) {
        $level_numeric = $this->log_level_numeric[$level] ?? $this->log_level_numeric['info'];
        return $level_numeric >= $this->current_log_level_numeric;
    }

    private function log_to_file($level_string, $message, $context = array()) {
        if (!$this->log_dir || (!is_writable($this->log_dir) && !wp_mkdir_p($this->log_dir))) {
            return;
        }
        if (!$this->debug_mode && strtoupper($level_string) !== 'ERROR') {
            return; 
        }
        $filename = $this->log_dir . '/wzi-sync-' . date('Y-m-d') . '.log';
        $log_entry = sprintf(
            "[%s] [%s] %s %sn",
            current_time('mysql'), 
            strtoupper($level_string),
            $message,
            !empty($context) ? wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : ''
        );
        @file_put_contents($filename, $log_entry, FILE_APPEND | LOCK_EX);
    }

    public function get_logs($args = array()) { // ... (resto del método get_logs como te lo di antes) ... 
        global $wpdb;
        $defaults = array(
            'page' => 1, 'per_page' => 20, 'source' => '', 'log_level' => '',
            'date_from' => '', 'date_to' => '', 'search' => '',
            'orderby' => 'timestamp', 'order' => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);
        $where = array('1=1');
        $values = array();
        if (!empty($args['source'])) { $where[] = 'source = %s'; $values[] = $args['source']; }
        if (!empty($args['log_level'])) { $where[] = 'log_level = %s'; $values[] = $args['log_level']; }
        if (!empty($args['date_from'])) { $where[] = 'timestamp >= %s'; $values[] = $args['date_from'] . ' 00:00:00'; }
        if (!empty($args['date_to'])) { $where[] = 'timestamp <= %s'; $values[] = $args['date_to'] . ' 23:59:59'; }
        if (!empty($args['search'])) {
            $where[] = '(message LIKE %s OR details LIKE %s OR object_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term; $values[] = $search_term; $values[] = $search_term;
        }
        $where_clause = implode(' AND ', $where);
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        if (!empty($values)) { $count_query = $wpdb->prepare($count_query, $values); }
        $total = $wpdb->get_var($count_query);
        $offset = ($args['page'] - 1) * $args['per_page'];
        $orderby_allowed = array('log_id', 'timestamp', 'log_level', 'source', 'object_id', 'object_type');
        $orderby = in_array($args['orderby'], $orderby_allowed) ? $args['orderby'] : 'timestamp';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $main_query_sql_parts = array("SELECT * FROM {$this->table_name}");
        if ($where_clause !== '1=1') { $main_query_sql_parts[] = "WHERE {$where_clause}"; }
        $main_query_sql_parts[] = "ORDER BY {$orderby} {$order}";
        $main_query_sql_parts[] = "LIMIT %d OFFSET %d";
        $final_query_sql = implode(' ', $main_query_sql_parts);
        $query_values = $values; 
        $query_values[] = $args['per_page'];
        $query_values[] = $offset;
        $logs = $wpdb->get_results($wpdb->prepare($final_query_sql, $query_values));
        if (is_array($logs)) {
            foreach ($logs as &$log) {
                if (!empty($log->details)) {
                    $decoded_details = json_decode($log->details, true);
                    $log->details = (json_last_error() === JSON_ERROR_NONE) ? $decoded_details : $log->details;
                }
            }
        }
        return array(
            'logs' => $logs, 'total' => $total,
            'pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1,
            'current_page' => $args['page'],
        );
    }

    public function get_log_summary($period = 'today') { // ... (resto del método get_log_summary como te lo di antes) ...
        global $wpdb;
        $date_condition = '';
        switch ($period) {
            case 'today': $date_condition = 'DATE(timestamp) = CURDATE()'; break;
            case 'week': $date_condition = 'timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)'; break;
            case 'month': $date_condition = 'timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)'; break;
            default: $date_condition = '1=1';
        }
        $query = "SELECT log_level, COUNT(*) as count FROM {$this->table_name} WHERE {$date_condition} GROUP BY log_level";
        $results = $wpdb->get_results($query, ARRAY_A);
        $summary = array('total' => 0, 'debug' => 0, 'info' => 0, 'warning' => 0, 'error' => 0);
        if (is_array($results)) {
            foreach ($results as $result) {
                if (isset($summary[$result['log_level']])) {
                    $summary[$result['log_level']] = intval($result['count']);
                    $summary['total'] += intval($result['count']);
                }
            }
        }
        $query_source = "SELECT source, COUNT(*) as count FROM {$this->table_name} WHERE {$date_condition} GROUP BY source";
        $source_results = $wpdb->get_results($query_source, ARRAY_A);
        $summary['by_source'] = array();
         if (is_array($source_results)) {
            foreach ($source_results as $type) {
                $summary['by_source'][$type['source']] = intval($type['count']);
            }
        }
        $summary['recent_errors'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE log_level = %s AND {$date_condition} 
            ORDER BY timestamp DESC LIMIT %d", 
            'error', 5
        ));
        return $summary;
    }

    public function cleanup_old_logs($days = null) { // ... (resto del método cleanup_old_logs como te lo di antes) ...
        global $wpdb;
        if ($days === null) {
            $general_settings = get_option('wzi_general_settings', array());
            $days = isset($general_settings['log_retention_days']) ? intval($general_settings['log_retention_days']) : 30;
        }
        if (!is_numeric($days) || $days <= 0) return false;
        $deleted_db = $wpdb->query($wpdb->prepare("DELETE FROM {$this->table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", $days));
        $deleted_files = 0;
        if ($this->log_dir && is_dir($this->log_dir)) {
            $files = glob($this->log_dir . '/*.log');
            if (is_array($files)) {
                $cutoff_time = time() - ($days * 24 * 60 * 60);
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoff_time) {
                        if (@unlink($file)) $deleted_files++;
                    }
                }
            }
        }
        $this->info('Logs cleanup completed', array(
            'source' => 'logger_cleanup',
            'deleted_from_db' => $deleted_db,
            'deleted_files' => $deleted_files,
            'retention_days' => $days
        ));
        return $deleted_db !== false;
    }

    public function clear_logs($days = 0) { // ... (resto del método clear_logs como te lo di antes) ...
        global $wpdb;
        if ($days > 0) return $this->cleanup_old_logs($days);
        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        if ($this->log_dir && is_dir($this->log_dir)) {
            $files = glob($this->log_dir . '/*.log');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) @unlink($file);
                }
            }
        }
        return $result !== false;
    }

    public function export_logs($format = 'csv', $args = array()) { // ... (resto del método export_logs como te lo di antes) ...
        $logs_data = $this->get_logs(array_merge($args, array('per_page' => -1, 'page' => 1))); 
        $logs = $logs_data['logs'];
        if ($format === 'csv') {
            $csv_data = array();
            $csv_data[] = array('Log ID', 'Timestamp', 'Level', 'Source', 'Object ID', 'Object Type', 'Message', 'Details');
            if (is_array($logs)) {
                foreach ($logs as $log) {
                    $csv_data[] = array(
                        $log->log_id, $log->timestamp, $log->log_level, $log->source,
                        $log->object_id, $log->object_type, $log->message,
                        is_array($log->details) ? wp_json_encode($log->details) : $log->details,
                    );
                }
            }
            $output = fopen('php://temp', 'w'); 
            foreach ($csv_data as $row) { fputcsv($output, $row); }
            rewind($output);
            $csv_string = stream_get_contents($output);
            fclose($output);
            return $csv_string;
        } elseif ($format === 'json') {
            return wp_json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return '';
    }

    public function get_log_sources() { 
        global $wpdb;
        $query = "SELECT DISTINCT source FROM {$this->table_name} WHERE source IS NOT NULL AND source != '' ORDER BY source";
        return $wpdb->get_col($query);
    }

     public function get_log_levels() {
        global $wpdb;
        $query = "SELECT DISTINCT log_level FROM {$this->table_name} WHERE log_level IS NOT NULL AND log_level != '' ORDER BY log_level";
        return $wpdb->get_col($query);
    }

    public function get_current_log_file() {
        if (!$this->log_dir) return '';
        return $this->log_dir . '/wzi-sync-' . date('Y-m-d') . '.log';
    }

    public function get_logs_size() {
        $total_size = 0;
        if ($this->log_dir && is_dir($this->log_dir)) {
            $files = glob($this->log_dir . '/*.log');
             if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) $total_size += @filesize($file);
                }
            }
        }
        return $total_size;
    }
}