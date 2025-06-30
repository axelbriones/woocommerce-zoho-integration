<?php
/**
 * Admin Sync Status Template for WooCommerce Zoho Integration.
 *
 * This template is used to display the synchronization status, queue, and history.
 * It might be included in the main Sync page or a dedicated status page.
 * It often relies on a WP_List_Table for displaying queue/history.
 *
 * @package WooCommerceZohoIntegration/Templates/Admin
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// This template might require several variables to be passed:
// - $sync_queue_table: An instance of a WP_List_Table for the sync queue.
// - $sync_history_table: An instance of a WP_List_Table for sync history.
// - $real_time_sync_status: Array indicating status of real-time sync for modules.
// - $cron_status: Information about WordPress cron and plugin-specific cron jobs.

$wzi_module_type_labels = array(
    'customers' => __('Customers', 'woocommerce-zoho-integration'),
    'products'  => __('Products', 'woocommerce-zoho-integration'),
    'orders'    => __('Orders', 'woocommerce-zoho-integration'),
    'invoices'  => __('Invoices', 'woocommerce-zoho-integration'),
    'coupons'   => __('Coupons', 'woocommerce-zoho-integration'),
    'all'       => __('All', 'woocommerce-zoho-integration'),
    // Add any other type that might appear
);

// Placeholder data if not passed
$real_time_sync_status = isset($real_time_sync_status) ? $real_time_sync_status : array(
    'customers' => array('enabled' => false, 'status' => __('Disabled', 'woocommerce-zoho-integration')),
    'orders'    => array('enabled' => false, 'status' => __('Disabled', 'woocommerce-zoho-integration')),
    'products'  => array('enabled' => false, 'status' => __('Disabled', 'woocommerce-zoho-integration')),
    // ... add other modules
);

$cron_status = isset($cron_status) ? $cron_status : array(
    'wp_cron_enabled' => ! (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
    'next_scheduled_sync' => __('Not scheduled or info unavailable', 'woocommerce-zoho-integration'),
    // ... other cron job details
);

?>
<div class="wzi-sync-status-container">

    <div class="wzi-sync-status-section">
        <h3><?php esc_html_e( 'Real-time Synchronization Status', 'woocommerce-zoho-integration' ); ?></h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Module', 'woocommerce-zoho-integration' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'woocommerce-zoho-integration' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $real_time_sync_status as $module_key => $status_info ) :
                    $module_label = isset($wzi_module_type_labels[$module_key]) ? $wzi_module_type_labels[$module_key] : ucfirst($module_key);
                ?>
                    <tr>
                        <td><?php echo esc_html( $module_label ); ?></td>
                        <td>
                            <span style="color: <?php echo $status_info['enabled'] ? 'green' : 'red'; ?>;">
                                <?php echo esc_html( $status_info['status'] ); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php
            printf(
                // translators: %s: URL to settings page
                wp_kses_post( __( 'Real-time synchronization triggers when specific events occur (e.g., new order). Configure this in <a href="%s">Synchronization Settings</a>.', 'woocommerce-zoho-integration' ) ),
                esc_url( admin_url( 'admin.php?page=wzi-settings&tab=sync' ) )
            );
            ?>
        </p>
    </div>

    <div class="wzi-sync-status-section">
        <h3><?php esc_html_e( 'Scheduled Synchronization (Cron)', 'woocommerce-zoho-integration' ); ?></h3>
        <p>
            <strong><?php esc_html_e( 'WordPress Cron:', 'woocommerce-zoho-integration' ); ?></strong>
            <?php if ( $cron_status['wp_cron_enabled'] ) : ?>
                <span style="color: green;"><?php esc_html_e( 'Enabled', 'woocommerce-zoho-integration' ); ?></span>
            <?php else : ?>
                <span style="color: red;"><?php esc_html_e( 'Disabled (DISABLE_WP_CRON is true). Scheduled syncs may not run.', 'woocommerce-zoho-integration' ); ?></span>
            <?php endif; ?>
        </p>
        <p>
            <strong><?php esc_html_e( 'Next Scheduled Sync (All Modules):', 'woocommerce-zoho-integration' ); ?></strong>
            <?php
            $next_sync = wp_next_scheduled('wzi_cron_sync_all'); // Example hook name
            if ($next_sync) {
                echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_sync ) );
                echo ' (' . human_time_diff( $next_sync, current_time('timestamp') ) . ')';
            } else {
                echo esc_html( $cron_status['next_scheduled_sync'] );
            }
            ?>
        </p>

        <?php
        // You might list individual cron jobs if you have them
        // $jobs = array( 'wzi_cron_sync_customers', 'wzi_cron_sync_products', ...);
        // foreach ($jobs as $job_hook) { ... wp_next_scheduled($job_hook) ... }
        ?>
        <p class="description">
             <?php
            printf(
                // translators: %s: URL to settings page
                wp_kses_post( __( 'Scheduled synchronization runs periodically in the background. Configure intervals in <a href="%s">Synchronization Settings</a>.', 'woocommerce-zoho-integration' ) ),
                esc_url( admin_url( 'admin.php?page=wzi-settings&tab=sync' ) )
            );
            ?>
        </p>
    </div>

    <div class="wzi-sync-status-section">
        <h3><?php esc_html_e( 'Synchronization Queue', 'woocommerce-zoho-integration' ); ?></h3>
        <?php
        // This is where you would display the WP_List_Table for the sync queue.
        // The $sync_queue_table object should be instantiated and items prepared
        // by the function that includes this template.
        if ( isset( $sync_queue_table ) && $sync_queue_table instanceof WP_List_Table ) {
            // $sync_queue_table->prepare_items(); // Should be done before including template
            $sync_queue_table->display();
        } elseif ( isset( $GLOBALS['wzi_sync_queue_list_table_instance'] ) && $GLOBALS['wzi_sync_queue_list_table_instance'] instanceof WP_List_Table ) {
            // Fallback if passed via global (less ideal)
            $GLOBALS['wzi_sync_queue_list_table_instance']->prepare_items();
            $GLOBALS['wzi_sync_queue_list_table_instance']->display();
        }
         else {
            echo '<p>' . esc_html__( 'The synchronization queue table is currently unavailable.', 'woocommerce-zoho-integration' ) . '</p>';
            echo '<!-- Debug: $sync_queue_table or $GLOBALS[\'wzi_sync_queue_list_table_instance\'] not set or not a WP_List_Table -->';
        }
        ?>
    </div>

    <div class="wzi-sync-status-section">
        <h3><?php esc_html_e( 'Recent Synchronization History', 'woocommerce-zoho-integration' ); ?></h3>
        <?php
        // This is where you would display the WP_List_Table for sync history.
        // The $sync_history_table object should be instantiated and items prepared
        // by the function that includes this template.
        if ( isset( $sync_history_table ) && $sync_history_table instanceof WP_List_Table ) {
            // $sync_history_table->prepare_items(); // Should be done before including template
            $sync_history_table->display();
        } elseif ( isset( $GLOBALS['wzi_sync_history_list_table_instance'] ) && $GLOBALS['wzi_sync_history_list_table_instance'] instanceof WP_List_Table ) {
             // Fallback if passed via global (less ideal)
            $GLOBALS['wzi_sync_history_list_table_instance']->prepare_items();
            $GLOBALS['wzi_sync_history_list_table_instance']->display();
        }
        else {
            echo '<p>' . esc_html__( 'The synchronization history table is currently unavailable.', 'woocommerce-zoho-integration' ) . '</p>';
            echo '<!-- Debug: $sync_history_table or $GLOBALS[\'wzi_sync_history_list_table_instance\'] not set or not a WP_List_Table -->';
             printf(
                // translators: %s: URL to logs page
                '<p>' . wp_kses_post( __( 'For detailed logs, please visit the <a href="%s">Activity Logs page</a>.', 'woocommerce-zoho-integration' ) ) . '</p>',
                esc_url( admin_url( 'admin.php?page=wzi-logs' ) )
            );
        }
        ?>
    </div>

</div>

<style type="text/css">
.wzi-sync-status-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px 20px;
    margin-bottom: 20px;
}
.wzi-sync-status-section h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.wzi-sync-status-section .widefat {
    margin-bottom: 10px;
}
.wzi-sync-status-section .description {
    font-size: 0.9em;
    color: #555;
}
</style>
