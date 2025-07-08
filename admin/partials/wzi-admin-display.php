<?php
/**
 * Vista del dashboard principal del plugin
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/admin/partials
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Obtener datos para el dashboard
$auth = new WZI_Zoho_Auth();
$sync_manager = new WZI_Sync_Manager();
$logger = new WZI_Logger();

$connected_services = $auth->get_connected_services();
$sync_status = $sync_manager->get_sync_status();
$log_summary = $logger->get_log_summary('today');
$sync_stats = $sync_manager->get_sync_stats('week');
?>

<div class="wrap wzi-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- Estado de Conexión -->
    <div class="wzi-status-cards">
        <div class="wzi-card <?php echo !empty($connected_services) ? 'connected' : 'disconnected'; ?>">
            <h3><?php _e('Estado de Conexión', 'woocommerce-zoho-integration'); ?></h3>
            <?php if (!empty($connected_services)): ?>
                <p class="status-connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Conectado a Zoho', 'woocommerce-zoho-integration'); ?>
                </p>
                <p><?php _e('Servicios activos:', 'woocommerce-zoho-integration'); ?> 
                    <?php echo implode(', ', array_map('ucfirst', $connected_services)); ?>
                </p>
            <?php else: ?>
                <p class="status-disconnected">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('No conectado', 'woocommerce-zoho-integration'); ?>
                </p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wzi-settings&tab=api'); ?>" class="button button-primary">
                        <?php _e('Configurar Conexión', 'woocommerce-zoho-integration'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Estado de Sincronización -->
        <div class="wzi-card">
            <h3><?php _e('Estado de Sincronización', 'woocommerce-zoho-integration'); ?></h3>
            <?php if ($sync_status['is_running']): ?>
                <p class="sync-running">
                    <span class="dashicons dashicons-update spin"></span>
                    <?php printf(__('Sincronizando %s...', 'woocommerce-zoho-integration'), $sync_status['current_type']); ?>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $sync_status['progress']; ?>%"></div>
                </div>
            <?php else: ?>
                <p class="sync-idle">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Sin sincronizaciones activas', 'woocommerce-zoho-integration'); ?>
                </p>
                <?php if (isset($sync_status['last_sync'])): ?>
                    <p class="last-sync">
                        <?php printf(
                            __('Última: %s - %s', 'woocommerce-zoho-integration'),
                            ucfirst($sync_status['last_sync']['type']),
                            human_time_diff(strtotime($sync_status['last_sync']['time']), current_time('timestamp'))
                        ); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Resumen de Logs -->
        <div class="wzi-card">
            <h3><?php _e('Actividad de Hoy', 'woocommerce-zoho-integration'); ?></h3>
            <div class="log-stats">
                <div class="stat">
                    <span class="count"><?php echo isset($log_summary['total']) ? intval($log_summary['total']) : 0; ?></span>
                    <span class="label"><?php _e('Total', 'woocommerce-zoho-integration'); ?></span>
                </div>
                <div class="stat success">
                    <span class="count"><?php echo isset($log_summary['INFO']) ? intval($log_summary['INFO']) : (isset($log_summary['success']) ? intval($log_summary['success']) : 0); // Priorizar INFO si existe, sino el antiguo success ?></span>
                    <span class="label"><?php _e('Exitosas (Info)', 'woocommerce-zoho-integration'); ?></span>
                </div>
                <div class="stat error">
                    <span class="count"><?php echo isset($log_summary['ERROR']) ? intval($log_summary['ERROR']) : (isset($log_summary['error']) ? intval($log_summary['error']) : 0); // Priorizar ERROR si existe, sino el antiguo error ?></span>
                    <span class="label"><?php _e('Errores', 'woocommerce-zoho-integration'); ?></span>
                </div>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=wzi-logs'); ?>">
                    <?php _e('Ver todos los logs →', 'woocommerce-zoho-integration'); ?>
                </a>
            </p>
        </div>
        
        <!-- Cola de Sincronización -->
        <div class="wzi-card">
            <h3><?php _e('Cola de Sincronización', 'woocommerce-zoho-integration'); ?></h3>
            <div class="queue-stats">
                <p>
                    <span class="dashicons dashicons-clock"></span>
                    <?php printf(
                        __('%d elementos pendientes', 'woocommerce-zoho-integration'),
                        $sync_status['queue']['pending']
                    ); ?>
                </p>
                <?php if ($sync_status['queue']['failed'] > 0): ?>
                    <p class="failed">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php printf(
                            __('%d elementos fallidos', 'woocommerce-zoho-integration'),
                            $sync_status['queue']['failed']
                        ); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas de Sincronización -->
    <div class="wzi-stats-section">
        <h2><?php _e('Estadísticas de la Última Semana', 'woocommerce-zoho-integration'); ?></h2>
        
        <div class="wzi-stats-grid">
            <?php
            $wzi_module_type_labels = array( // Debería definirse globalmente o pasarse, por ahora lo defino aquí para el ejemplo
                'customers' => __('Clientes', 'woocommerce-zoho-integration'),
                'orders'    => __('Pedidos', 'woocommerce-zoho-integration'),
                'products'  => __('Productos', 'woocommerce-zoho-integration'),
                'invoices'  => __('Facturas', 'woocommerce-zoho-integration'),
                'coupons'   => __('Cupones', 'woocommerce-zoho-integration'),
            );

            if (isset($sync_stats['by_type']) && is_array($sync_stats['by_type'])):
                foreach ($sync_stats['by_type'] as $source => $levels):
                    $source_label = isset($wzi_module_type_labels[$source]) ? $wzi_module_type_labels[$source] : ucfirst($source);
                    $total_source_records = 0;
                    $total_source_errors = 0;
                    if(is_array($levels)){ // Asegurarse que $levels es un array
                        $total_source_records = array_sum($levels);
                        $total_source_errors = isset($levels['ERROR']) ? $levels['ERROR'] : 0;
                        if(isset($levels['WARNING'])) $total_source_errors += $levels['WARNING']; // Considerar WARNINGS como errores también para el resumen
                    }
            ?>
                <div class="stat-card">
                    <h4><?php echo esc_html($source_label); ?></h4>
                    <div class="stat-details">
                        <p>
                            <?php printf(__('Total Registros: %d', 'woocommerce-zoho-integration'), $total_source_records); ?>
                        </p>
                        <?php if (is_array($levels)): ?>
                            <?php if (isset($levels['INFO']) && $levels['INFO'] > 0): ?>
                                <p class="success">
                                    <?php printf(__('Exitosas (Info): %d', 'woocommerce-zoho-integration'), $levels['INFO']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($total_source_errors > 0): ?>
                                <p class="error">
                                    <?php printf(__('Errores/Advertencias: %d', 'woocommerce-zoho-integration'), $total_source_errors); ?>
                                </p>
                            <?php endif; ?>
                             <?php if (isset($levels['DEBUG']) && $levels['DEBUG'] > 0): ?>
                                <p style="font-size:0.9em; opacity:0.7;">
                                    <?php printf(__('Debug: %d', 'woocommerce-zoho-integration'), $levels['DEBUG']); ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php
                endforeach;
            endif;
            ?>
        </div>
        
        <div class="total-stats">
            <p>
                <?php
                $total_successful_records = isset($sync_stats['totals']['INFO']) ? intval($sync_stats['totals']['INFO']) : 0;
                $total_error_records = isset($sync_stats['totals']['ERROR']) ? intval($sync_stats['totals']['ERROR']) : 0;
                if(isset($sync_stats['totals']['WARNING'])) $total_error_records += intval($sync_stats['totals']['WARNING']);

                printf(
                    __('Total General: %d operaciones informativas (exitosas), %d errores/advertencias de un total de %d registros procesados en la última semana.', 'woocommerce-zoho-integration'),
                    $total_successful_records,
                    $total_error_records,
                    isset($sync_stats['totals']['total_records']) ? intval($sync_stats['totals']['total_records']) : 0
                );
                ?>
            </p>
        </div>
    </div>
    
    <!-- Acciones Rápidas -->
    <div class="wzi-quick-actions">
        <h2><?php _e('Acciones Rápidas', 'woocommerce-zoho-integration'); ?></h2>
        
        <div class="action-buttons">
            <?php if (!empty($connected_services)): ?>
                <button type="button" class="button button-primary wzi-sync-now" data-sync-type="all">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Sincronizar Todo', 'woocommerce-zoho-integration'); ?>
                </button>
                
                <button type="button" class="button wzi-sync-now" data-sync-type="customers">
                    <?php _e('Sincronizar Clientes', 'woocommerce-zoho-integration'); ?>
                </button>
                
                <button type="button" class="button wzi-sync-now" data-sync-type="orders">
                    <?php _e('Sincronizar Pedidos', 'woocommerce-zoho-integration'); ?>
                </button>
                
                <button type="button" class="button wzi-sync-now" data-sync-type="products">
                    <?php _e('Sincronizar Productos', 'woocommerce-zoho-integration'); ?>
                </button>
            <?php else: ?>
                <p><?php _e('Conecta con Zoho para habilitar las acciones de sincronización.', 'woocommerce-zoho-integration'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Errores Recientes -->
    <?php if (!empty($log_summary['recent_errors'])): ?>
        <div class="wzi-recent-errors">
            <h2><?php _e('Errores Recientes', 'woocommerce-zoho-integration'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Hora', 'woocommerce-zoho-integration'); ?></th>
                        <th><?php _e('Tipo', 'woocommerce-zoho-integration'); ?></th>
                        <th><?php _e('Mensaje', 'woocommerce-zoho-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($log_summary['recent_errors']) && is_array($log_summary['recent_errors'])):
                        foreach ($log_summary['recent_errors'] as $error):
                            // Verificar que $error sea un objeto y tenga las propiedades esperadas
                            $time_to_display = isset($error->timestamp) ? human_time_diff(strtotime($error->timestamp), current_time('timestamp')) . ' ' . __('atrás', 'woocommerce-zoho-integration') : __('Fecha desconocida', 'woocommerce-zoho-integration');
                            $source_to_display = isset($error->source) ? esc_html($error->source) : __('Fuente desconocida', 'woocommerce-zoho-integration');
                            $message_to_display = isset($error->message) ? esc_html($error->message) : __('Mensaje no disponible', 'woocommerce-zoho-integration');
                    ?>
                        <tr>
                            <td><?php echo $time_to_display; ?></td>
                            <td><?php echo $source_to_display; ?></td>
                            <td><?php echo $message_to_display; ?></td>
                        </tr>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=wzi-logs&status=error'); ?>">
                    <?php _e('Ver todos los errores →', 'woocommerce-zoho-integration'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Sistema de Información -->
    <div class="wzi-system-info">
        <h2><?php _e('Información del Sistema', 'woocommerce-zoho-integration'); ?></h2>
        
        <div class="info-grid">
            <div class="info-item">
                <strong><?php _e('Versión del Plugin:', 'woocommerce-zoho-integration'); ?></strong>
                <?php echo WZI_VERSION; ?>
            </div>
            <div class="info-item">
                <strong><?php _e('Versión de WooCommerce:', 'woocommerce-zoho-integration'); ?></strong>
                <?php echo WC()->version; ?>
            </div>
            <div class="info-item">
                <strong><?php _e('Versión de PHP:', 'woocommerce-zoho-integration'); ?></strong>
                <?php echo PHP_VERSION; ?>
            </div>
            <div class="info-item">
                <strong><?php _e('Tamaño de Logs:', 'woocommerce-zoho-integration'); ?></strong>
                <?php echo size_format($logger->get_logs_size()); ?>
            </div>
        </div>
    </div>
</div>

<style>
.wzi-dashboard {
    max-width: 1200px;
}

.wzi-status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.wzi-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wzi-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
}

.wzi-card.connected {
    border-left: 4px solid #46b450;
}

.wzi-card.disconnected {
    border-left: 4px solid #dc3232;
}

.status-connected {
    color: #46b450;
    font-weight: 600;
}

.status-disconnected {
    color: #dc3232;
    font-weight: 600;
}

.sync-running {
    color: #f56e28;
}

.sync-idle {
    color: #46b450;
}

.dashicons.spin {
    animation: spin 2s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}

.progress-bar {
    background: #f0f0f1;
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    background: #007cba;
    height: 100%;
    transition: width 0.3s ease;
}

.log-stats {
    display: flex;
    justify-content: space-around;
    text-align: center;
    margin: 15px 0;
}

.log-stats .stat {
    flex: 1;
}

.log-stats .count {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #23282d;
}

.log-stats .stat.success .count {
    color: #46b450;
}

.log-stats .stat.error .count {
    color: #dc3232;
}

.log-stats .label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.wzi-stats-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.wzi-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e2e4e7;
}

.stat-card h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    text-transform: capitalize;
}

.stat-details .direction {
    margin: 5px 0;
    font-size: 13px;
}

.stat-details .label {
    display: inline-block;
    width: 150px;
    color: #666;
}

.stat-details .success {
    color: #46b450;
    font-weight: 600;
}

.stat-details .error {
    color: #dc3232;
    margin-left: 5px;
}

.wzi-quick-actions {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 15px;
}

.action-buttons .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.wzi-recent-errors {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    border-left: 4px solid #dc3232;
}

.wzi-system-info {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.info-item {
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.queue-stats p {
    margin: 5px 0;
    display: flex;
    align-items: center;
    gap: 5px;
}

.queue-stats .failed {
    color: #dc3232;
}

.last-sync {
    font-size: 12px;
    color: #666;
    margin-top: 10px;
}

.total-stats {
    text-align: center;
    padding: 15px;
    background: #f0f0f1;
    border-radius: 4px;
    margin-top: 20px;
}
</style>