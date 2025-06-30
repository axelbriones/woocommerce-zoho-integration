<?php
/**
 * Se activa durante la desactivación del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 */

/**
 * Se activa durante la desactivación del plugin.
 *
 * Esta clase define todo el código necesario para ejecutar durante la desactivación del plugin.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Deactivator {

    /**
     * Desactivación del plugin.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Limpiar tareas cron programadas
        self::clear_scheduled_hooks();
        
        // Limpiar transients
        self::clear_transients();
        
        // Opcional: Limpiar logs antiguos
        self::cleanup_old_logs();
        
        // Limpiar caché
        flush_rewrite_rules();
    }
    
    /**
     * Limpiar tareas cron programadas
     */
    private static function clear_scheduled_hooks() {
        // Desprogramar sincronización automática
        $timestamp = wp_next_scheduled('wzi_auto_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wzi_auto_sync');
        }
        
        // Desprogramar limpieza de logs
        $timestamp = wp_next_scheduled('wzi_cleanup_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wzi_cleanup_logs');
        }
        
        // Desprogramar procesamiento de cola
        $timestamp = wp_next_scheduled('wzi_process_sync_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wzi_process_sync_queue');
        }
        
        // Limpiar todos los crons del plugin
        wp_clear_scheduled_hook('wzi_auto_sync');
        wp_clear_scheduled_hook('wzi_cleanup_logs');
        wp_clear_scheduled_hook('wzi_process_sync_queue');
        wp_clear_scheduled_hook('wzi_token_refresh');
    }
    
    /**
     * Limpiar transients del plugin
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Eliminar todos los transients del plugin
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_wzi_%' 
            OR option_name LIKE '_transient_timeout_wzi_%'"
        );
        
        // Limpiar caché de objetos si está disponible
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    /**
     * Limpiar logs antiguos (opcional)
     */
    private static function cleanup_old_logs() {
        global $wpdb;
        
        // Obtener configuración de retención de logs
        $general_settings = get_option('wzi_general_settings', array());
        $retention_days = isset($general_settings['log_retention_days']) ? intval($general_settings['log_retention_days']) : 30;
        
        // Solo limpiar si está configurado para hacerlo
        if ($retention_days > 0) {
            $sync_logs_table = $wpdb->prefix . 'wzi_sync_logs';
            
            // Eliminar logs más antiguos que el período de retención
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $sync_logs_table 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            ));
        }
        
        // Limpiar archivos de log físicos
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wzi-logs';
        
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '/*.log');
            $now = time();
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    if ($now - filemtime($file) >= 60 * 60 * 24 * $retention_days) {
                        unlink($file);
                    }
                }
            }
        }
    }
    
    /**
     * Método para desinstalación completa (no se ejecuta en desactivación)
     * Este método se puede llamar manualmente si se desea una limpieza completa
     */
    public static function uninstall() {
        global $wpdb;
        
        // Solo ejecutar si se solicita específicamente
        if (!defined('WZI_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Eliminar todas las opciones del plugin
        $options = array(
            'wzi_general_settings',
            'wzi_api_settings',
            'wzi_sync_settings',
            'wzi_field_mapping',
            'wzi_webhook_settings',
            'wzi_db_version',
            'wzi_install_date',
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Eliminar tablas de la base de datos
        $tables = array(
            $wpdb->prefix . 'wzi_sync_logs',
            $wpdb->prefix . 'wzi_sync_queue',
            $wpdb->prefix . 'wzi_field_mappings',
            $wpdb->prefix . 'wzi_auth_tokens',
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Eliminar capacidades
        $capabilities = array(
            'manage_wzi_settings',
            'view_wzi_logs',
            'manage_wzi_sync',
            'manage_wzi_mappings',
        );
        
        // Eliminar capacidades del rol de administrador
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
        
        // Eliminar rol personalizado
        remove_role('wzi_manager');
        
        // Eliminar carpeta de logs
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wzi-logs';
        
        if (is_dir($log_dir)) {
            self::delete_directory($log_dir);
        }
        
        // Limpiar cualquier dato residual
        self::clear_transients();
    }
    
    /**
     * Eliminar un directorio recursivamente
     *
     * @param string $dir Ruta del directorio
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }
}