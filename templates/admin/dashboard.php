<?php
/**
 * Admin Dashboard Template for WooCommerce Zoho Integration.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-zoho-integration/admin/dashboard.php.
 *
 * @package WooCommerceZohoIntegration/Templates/Admin
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// These variables would ideally be passed to this template from the calling PHP function
// For example:
// $last_sync_times = get_option('wzi_last_sync_times', array());
// $api_status = wzi_get_api_status(); // A helper function to check API status
// $sync_errors_count = wzi_get_sync_errors_count();
// $pending_items_count = wzi_get_pending_items_count();

// Placeholder data if not passed:
$last_sync_times = isset($last_sync_times) ? $last_sync_times : array(
    'customers' => __('Never', 'woocommerce-zoho-integration'),
    'products' => __('Never', 'woocommerce-zoho-integration'),
    'orders' => __('Never', 'woocommerce-zoho-integration'),
    'invoices' => __('Never', 'woocommerce-zoho-integration'),
    'coupons' => __('Never', 'woocommerce-zoho-integration'),
);

$wzi_module_type_labels = array(
    'customers' => __('Customers', 'woocommerce-zoho-integration'),
    'products'  => __('Products', 'woocommerce-zoho-integration'),
    'orders'    => __('Orders', 'woocommerce-zoho-integration'),
    'invoices'  => __('Invoices', 'woocommerce-zoho-integration'),
    'coupons'   => __('Coupons', 'woocommerce-zoho-integration'),
    'all'       => __('All', 'woocommerce-zoho-integration'),
    // Add any other type that might appear in sync_status['current_type'] or sync_stats['by_type'] keys
);

$api_status_info = isset($api_status_info) ? $api_status_info : array('status' => 'unknown', 'message' => __('Unknown', 'woocommerce-zoho-integration')); // Expected: array('status' => 'connected|disconnected|pending', 'message' => 'Details')
$sync_errors_count = isset($sync_errors_count) ? $sync_errors_count : 0;
$pending_items_count = isset($pending_items_count) ? $pending_items_count : 0;
// Ensure $sync_stats is an array and has the expected structure
$sync_stats = isset($sync_stats) && is_array($sync_stats) ? $sync_stats : array('by_type' => array(), 'totals' => array('success' => 0, 'error' => 0));
if (!isset($sync_stats['by_type'])) {
    $sync_stats['by_type'] = array();
}
if (!isset($sync_stats['totals'])) {
    $sync_stats['totals'] = array('success' => 0, 'error' => 0);
}


$sync_page_url = admin_url( 'admin.php?page=wzi-sync' );
$settings_page_url = admin_url( 'admin.php?page=wzi-settings' );
$logs_page_url = admin_url( 'admin.php?page=wzi-logs' );

?>
<div class="wzi-dashboard">

    <div class="wzi-dashboard-header">
        <h1><?php esc_html_e( 'WooCommerce Zoho Integration Dashboard', 'woocommerce-zoho-integration' ); ?></h1>
        <p><?php esc_html_e( 'Overview of your Zoho integration status and activity.', 'woocommerce-zoho-integration' ); ?></p>
    </div>

    <div class="wzi-dashboard-widgets-wrapper">
        <div class="wzi-dashboard-widget wzi-api-status-widget">
            <h3><?php esc_html_e( 'Zoho API Status', 'woocommerce-zoho-integration' ); ?></h3>
            <p>
                <span class="wzi-api-status <?php echo esc_attr( strtolower( $api_status_info['status'] ) ); ?>">
                    <?php echo esc_html( ucfirst( $api_status_info['status'] ) ); ?>
                </span>
                <?php if ( ! empty( $api_status_info['message'] ) ) : ?>
                    <span class="wzi-api-status-message">- <?php echo esc_html( $api_status_info['message'] ); ?></span>
                <?php endif; ?>
            </p>
            <?php if ( $api_status_info['status'] !== 'connected' ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wzi-settings&tab=api' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Check API Settings', 'woocommerce-zoho-integration' ); ?>
                </a>
            <?php endif; ?>
        </div>

        <div class="wzi-dashboard-widget wzi-last-sync-widget">
            <h3><?php esc_html_e( 'Last Synchronization Times', 'woocommerce-zoho-integration' ); ?></h3>
            <ul>
                <li><strong><?php esc_html_e( 'Customers:', 'woocommerce-zoho-integration' ); ?></strong> <?php echo esc_html( is_numeric($last_sync_times['customers']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_times['customers'] ) : $last_sync_times['customers'] ); ?></li>
                <li><strong><?php esc_html_e( 'Products:', 'woocommerce-zoho-integration' ); ?></strong> <?php echo esc_html( is_numeric($last_sync_times['products']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_times['products'] ) : $last_sync_times['products'] ); ?></li>
                <li><strong><?php esc_html_e( 'Orders:', 'woocommerce-zoho-integration' ); ?></strong> <?php echo esc_html( is_numeric($last_sync_times['orders']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_times['orders'] ) : $last_sync_times['orders'] ); ?></li>
                <li><strong><?php esc_html_e( 'Invoices:', 'woocommerce-zoho-integration' ); ?></strong> <?php echo esc_html( is_numeric($last_sync_times['invoices']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_times['invoices'] ) : $last_sync_times['invoices'] ); ?></li>
                 <li><strong><?php esc_html_e( 'Coupons:', 'woocommerce-zoho-integration' ); ?></strong> <?php echo esc_html( is_numeric($last_sync_times['coupons']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync_times['coupons'] ) : $last_sync_times['coupons'] ); ?></li>
            </ul>
            <a href="<?php echo esc_url( $sync_page_url ); ?>" class="button button-secondary">
                <?php esc_html_e( 'Go to Sync Page', 'woocommerce-zoho-integration' ); ?>
            </a>
        </div>

        <div class="wzi-dashboard-widget wzi-sync-status-widget">
            <h3><?php esc_html_e('Estado de Sincronización', 'woocommerce-zoho-integration'); ?></h3>
            <?php if ($sync_status['is_running']): ?>
                <p class="sync-running">
                    <span class="dashicons dashicons-update spin"></span>
                    <?php
                    $current_type_label = isset($wzi_module_type_labels[$sync_status['current_type']]) ? $wzi_module_type_labels[$sync_status['current_type']] : ucfirst($sync_status['current_type']);
                    printf(esc_html__('Sincronizando %s...', 'woocommerce-zoho-integration'), esc_html($current_type_label));
                    ?>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo intval($sync_status['progress']); ?>%"></div>
                </div>
            <?php else: ?>
                <p class="sync-idle">
                    <span class="dashicons dashicons-yes"></span>
                    <?php esc_html_e('Sin sincronizaciones activas', 'woocommerce-zoho-integration'); ?>
                </p>
                <?php if (isset($sync_status['last_sync']) && isset($sync_status['last_sync']['type'])):
                    $last_sync_type_label = isset($wzi_module_type_labels[$sync_status['last_sync']['type']]) ? $wzi_module_type_labels[$sync_status['last_sync']['type']] : ucfirst($sync_status['last_sync']['type']);
                ?>
                    <p class="last-sync">
                        <?php printf(
                            esc_html__('Última: %s - %s atrás', 'woocommerce-zoho-integration'),
                            esc_html($last_sync_type_label),
                            human_time_diff(strtotime($sync_status['last_sync']['time']), current_time('timestamp'))
                        ); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="wzi-dashboard-widget wzi-sync-queue-widget">
            <h3><?php esc_html_e( 'Sync Queue', 'woocommerce-zoho-integration' ); ?></h3>
            <p>
                <?php printf(
                    // translators: %d is the number of pending items.
                    esc_html__( 'There are currently %d items pending in the synchronization queue.', 'woocommerce-zoho-integration' ),
                    (int) $pending_items_count
                ); ?>
            </p>
            <a href="<?php echo esc_url( $sync_page_url . '#wzi-sync-status' ); ?>" class="button button-secondary">
                <?php esc_html_e( 'View Queue', 'woocommerce-zoho-integration' ); ?>
            </a>
        </div>

        <div class="wzi-dashboard-widget wzi-sync-errors-widget">
            <h3><?php esc_html_e( 'Recent Sync Errors', 'woocommerce-zoho-integration' ); ?></h3>
            <p>
                <?php if ( $sync_errors_count > 0 ) : ?>
                    <strong style="color: #dc3232;">
                    <?php printf(
                        // translators: %d is the number of errors.
                        esc_html__( '%d synchronization error(s) recorded recently.', 'woocommerce-zoho-integration' ),
                        (int) $sync_errors_count
                    ); ?>
                    </strong>
                <?php else : ?>
                    <span style="color: green;">
                        <?php esc_html_e( 'No synchronization errors recorded recently.', 'woocommerce-zoho-integration' ); ?>
                    </span>
                <?php endif; ?>
            </p>
            <a href="<?php echo esc_url( $logs_page_url ); ?>" class="button button-secondary">
                <?php esc_html_e( 'View Logs', 'woocommerce-zoho-integration' ); ?>
            </a>
        </div>

    </div>

    <!-- Estadísticas de Sincronización -->
    <div class="wzi-stats-section">
        <h2><?php esc_html_e('Estadísticas de la Última Semana', 'woocommerce-zoho-integration'); ?></h2>

        <div class="wzi-stats-grid">
            <?php foreach ($sync_stats['by_type'] as $type => $data):
                $type_label = isset($wzi_module_type_labels[$type]) ? $wzi_module_type_labels[$type] : ucfirst($type);
                $woo_to_zoho_success = isset($data['woo_to_zoho']['success']) ? intval($data['woo_to_zoho']['success']) : 0;
                $woo_to_zoho_error = isset($data['woo_to_zoho']['error']) ? intval($data['woo_to_zoho']['error']) : 0;
                $zoho_to_woo_success = isset($data['zoho_to_woo']['success']) ? intval($data['zoho_to_woo']['success']) : 0;
                $zoho_to_woo_error = isset($data['zoho_to_woo']['error']) ? intval($data['zoho_to_woo']['error']) : 0;
            ?>
                <div class="stat-card">
                    <h4><?php echo esc_html($type_label); ?></h4>
                    <div class="stat-details">
                        <div class="direction">
                            <span class="label"><?php esc_html_e('WooCommerce → Zoho:', 'woocommerce-zoho-integration'); ?></span>
                            <span class="success"><?php echo $woo_to_zoho_success; ?></span>
                            <?php if ($woo_to_zoho_error > 0): ?>
                                <span class="error">
                                    <?php
                                    printf(
                                        _n(
                                            '(%s error)',
                                            '(%s errores)',
                                            $woo_to_zoho_error,
                                            'woocommerce-zoho-integration'
                                        ),
                                        number_format_i18n($woo_to_zoho_error)
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="direction">
                            <span class="label"><?php esc_html_e('Zoho → WooCommerce:', 'woocommerce-zoho-integration'); ?></span>
                            <span class="success"><?php echo $zoho_to_woo_success; ?></span>
                            <?php if ($zoho_to_woo_error > 0): ?>
                                 <span class="error">
                                    <?php
                                    printf(
                                        _n(
                                            '(%s error)',
                                            '(%s errores)',
                                            $zoho_to_woo_error,
                                            'woocommerce-zoho-integration'
                                        ),
                                        number_format_i18n($zoho_to_woo_error)
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="total-stats">
            <p>
                <?php printf(
                    esc_html__('Total: %d sincronizaciones exitosas, %d errores', 'woocommerce-zoho-integration'),
                    isset($sync_stats['totals']['success']) ? intval($sync_stats['totals']['success']) : 0,
                    isset($sync_stats['totals']['error']) ? intval($sync_stats['totals']['error']) : 0
                ); ?>
            </p>
        </div>
    </div>


    <div class="wzi-dashboard-quick-links">
        <h3><?php esc_html_e( 'Quick Links', 'woocommerce-zoho-integration' ); ?></h3>
        <ul>
            <li><a href="<?php echo esc_url( $settings_page_url ); ?>"><?php esc_html_e( 'Plugin Settings', 'woocommerce-zoho-integration' ); ?></a></li>
            <li><a href="<?php echo esc_url( $sync_page_url ); ?>"><?php esc_html_e( 'Manual Synchronization', 'woocommerce-zoho-integration' ); ?></a></li>
            <li><a href="<?php echo esc_url( $logs_page_url ); ?>"><?php esc_html_e( 'Activity Logs', 'woocommerce-zoho-integration' ); ?></a></li>
            <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wzi-help' ) ); ?>"><?php esc_html_e( 'Help & Support', 'woocommerce-zoho-integration' ); ?></a></li>
        </ul>
    </div>

</div>

<style type="text/css">
.wzi-dashboard-widgets-wrapper {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}
.wzi-dashboard-widget {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    flex: 1 1 calc(50% - 20px); /* Two columns, accounting for gap */
    box-sizing: border-box;
    min-width: 300px; /* Minimum width before wrapping */
}
.wzi-dashboard-widget h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.wzi-dashboard-widget ul {
    list-style: none;
    padding-left: 0;
    margin-bottom:15px;
}
.wzi-dashboard-widget ul li {
    margin-bottom: 8px;
}
.wzi-dashboard-widget .button {
    margin-top: 10px;
}
.wzi-api-status {
    padding: 3px 8px;
    border-radius: 4px;
    color: #fff;
    font-weight: bold;
    text-transform: capitalize;
}
.wzi-api-status.connected { background-color: #4CAF50; }
.wzi-api-status.disconnected { background-color: #F44336; }
.wzi-api-status.pending { background-color: #FFC107; color: #333; }
.wzi-api-status.unknown { background-color: #9E9E9E; }

.wzi-dashboard-quick-links ul {
    list-style: none;
    padding-left: 0;
    display: flex;
    gap: 15px;
}
.wzi-dashboard-quick-links ul li a {
    text-decoration: none;
}
</style>
