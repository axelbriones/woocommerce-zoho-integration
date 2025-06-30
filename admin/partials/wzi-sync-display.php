<?php
/**
 * Provides the admin area display for Zoho Synchronization.
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       YOUR_PLUGIN_URL
 * @since      1.0.0
 *
 * @package    WooCommerceZohoIntegration
 * @subpackage WooCommerceZohoIntegration/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>

<div class="wrap wzi-admin-page">
    <h1><?php echo esc_html( get_admin_page_title() ); ?> - <?php esc_html_e( 'Synchronization', 'woocommerce-zoho-integration' ); ?></h1>

    <?php
    // Display any admin notices here if needed
    // settings_errors();
    ?>

    <div id="wzi-sync-controls">
        <h2><?php esc_html_e( 'Manual Synchronization', 'woocommerce-zoho-integration' ); ?></h2>
        <p><?php esc_html_e( 'Manually trigger synchronization processes for different data types.', 'woocommerce-zoho-integration' ); ?></p>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Customers', 'woocommerce-zoho-integration' ); ?></th>
                    <td>
                        <button id="wzi-sync-customers-button" class="button button-secondary">
                            <?php esc_html_e( 'Sync All Customers to Zoho', 'woocommerce-zoho-integration' ); ?>
                        </button>
                        <span class="spinner"></span>
                        <p class="description"><?php esc_html_e( 'Syncs all WooCommerce customers to Zoho CRM (Contacts/Leads).', 'woocommerce-zoho-integration' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Products', 'woocommerce-zoho-integration' ); ?></th>
                    <td>
                        <button id="wzi-sync-products-button" class="button button-secondary">
                            <?php esc_html_e( 'Sync All Products to Zoho', 'woocommerce-zoho-integration' ); ?>
                        </button>
                        <span class="spinner"></span>
                        <p class="description"><?php esc_html_e( 'Syncs all WooCommerce products to Zoho Inventory.', 'woocommerce-zoho-integration' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Orders', 'woocommerce-zoho-integration' ); ?></th>
                    <td>
                        <button id="wzi-sync-orders-button" class="button button-secondary">
                            <?php esc_html_e( 'Sync All Orders to Zoho', 'woocommerce-zoho-integration' ); ?>
                        </button>
                        <span class="spinner"></span>
                        <p class="description"><?php esc_html_e( 'Syncs all WooCommerce orders to Zoho CRM (Sales Orders/Deals).', 'woocommerce-zoho-integration' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Invoices', 'woocommerce-zoho-integration' ); ?></th>
                    <td>
                        <button id="wzi-sync-invoices-button" class="button button-secondary">
                            <?php esc_html_e( 'Sync All Invoices to Zoho', 'woocommerce-zoho-integration' ); ?>
                        </button>
                        <span class="spinner"></span>
                        <p class="description"><?php esc_html_e( 'Syncs all WooCommerce invoices (from completed orders) to Zoho Books.', 'woocommerce-zoho-integration' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Coupons', 'woocommerce-zoho-integration' ); ?></th>
                    <td>
                        <button id="wzi-sync-coupons-button" class="button button-secondary">
                            <?php esc_html_e( 'Sync All Coupons to Zoho', 'woocommerce-zoho-integration' ); ?>
                        </button>
                        <span class="spinner"></span>
                        <p class="description"><?php esc_html_e( 'Syncs all WooCommerce coupons to Zoho Campaigns.', 'woocommerce-zoho-integration' ); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <div id="wzi-sync-feedback" style="margin-top: 20px;"></div>
    </div>

    <hr>

    <div id="wzi-sync-status">
        <h2><?php esc_html_e( 'Synchronization Status & Queue', 'woocommerce-zoho-integration' ); ?></h2>
        <p><?php esc_html_e( 'Overview of the synchronization queue and recent activity.', 'woocommerce-zoho-integration' ); ?></p>
        <!-- Placeholder for sync status table or display -->
        <?php
        // This part would typically be handled by a WP_List_Table for the sync queue or recent items.
        // For now, a simple placeholder:
        echo '<p>' . esc_html__( 'Synchronization status and queue details will be displayed here.', 'woocommerce-zoho-integration' ) . '</p>';
        // Example: do_action('wzi_display_sync_queue_table');
        ?>
         <?php include plugin_dir_path( __FILE__ ) . '../../templates/admin/sync-status.php'; ?>
    </div>

    <hr>

    <div id="wzi-sync-settings-info">
        <h2><?php esc_html_e( 'Synchronization Settings', 'woocommerce-zoho-integration' ); ?></h2>
        <p>
            <?php
            printf(
                // translators: %s: URL to settings page
                wp_kses_post( __( 'Configure automatic synchronization, field mappings, and other sync options in the <a href="%s">plugin settings</a>.', 'woocommerce-zoho-integration' ) ),
                esc_url( admin_url( 'admin.php?page=wzi-settings' ) ) // Adjust if your settings page slug is different
            );
            ?>
        </p>
    </div>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#wzi-sync-controls button').on('click', function() {
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $feedback = $('#wzi-sync-feedback');
        var syncType = $button.attr('id').replace('wzi-sync-', '').replace('-button', '');

        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        $feedback.html('<p><?php esc_html_e( "Processing...", "woocommerce-zoho-integration" ); ?></p>').removeClass('error success');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wzi_manual_sync', // This will be the WordPress AJAX action hook
                nonce: '<?php echo wp_create_nonce('wzi_manual_sync_nonce'); ?>',
                sync_type: syncType, // e.g., 'customers', 'products'
                sync_all: true // Or specific IDs if you implement that later
            },
            success: function(response) {
                if (response.success) {
                    $feedback.html('<p>' + response.data.message + '</p>').addClass('updated success');
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : '<?php esc_html_e( "An unknown error occurred.", "woocommerce-zoho-integration" ); ?>';
                    $feedback.html('<p>' + errorMessage + '</p>').addClass('error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $feedback.html('<p><?php esc_html_e( "AJAX request failed: ", "woocommerce-zoho-integration" ); ?>' + textStatus + ' - ' + errorThrown + '</p>').addClass('error');
            },
            complete: function() {
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
            }
        });
    });
});
</script>
