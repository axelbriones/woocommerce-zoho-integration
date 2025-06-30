<?php
/**
 * Provides the admin area display for Zoho Integration Logs.
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

// Check if the WZI_Logs_List_Table class exists.
// It should be loaded by the admin class responsible for this page.
if ( ! class_exists( 'WZI_Logs_List_Table' ) ) {
    // You might need to include it here if it's not auto-loaded,
    // though ideally it's part of the main plugin admin setup.
    // require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/class-wzi-logs-list-table.php';
    // Note: The above path might need adjustment based on your actual file structure for WP_List_Table classes.
    // For now, we'll assume it's loaded. If not, this page will show a fatal error.
}

?>

<div class="wrap wzi-admin-page">
    <h1><?php echo esc_html( get_admin_page_title() ); ?> - <?php esc_html_e( 'Activity Logs', 'woocommerce-zoho-integration' ); ?></h1>

    <p>
        <?php esc_html_e( 'Review synchronization events, API calls, and any errors that occurred during the integration process.', 'woocommerce-zoho-integration' ); ?>
    </p>

    <?php
    // Display any admin notices or settings errors
    settings_errors('wzi_logs_notices');
    ?>

    <form id="wzi-logs-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr( isset($_REQUEST['page']) ? $_REQUEST['page'] : '' ); ?>" />
        <?php
        // Instantiate and prepare the list table
        // This assumes you have a class WZI_Logs_List_Table that extends WP_List_Table
        // The instance might be created and prepared in the main admin page callback function
        // and passed here, or you can instantiate it directly.

        // $logs_list_table = new WZI_Logs_List_Table(); // If not already instantiated
        // $logs_list_table->prepare_items();

        // For the purpose of this template, we assume $logs_list_table is available
        // if it's instantiated and items prepared by the calling admin page function.
        // If you are including this file directly without such setup,
        // you'll need to create and prepare the $logs_list_table object here.

        // Example:
        // global $wzi_logs_list_table; // if set globally by the menu page callback
        // if (is_object($wzi_logs_list_table) && $wzi_logs_list_table instanceof WP_List_Table) {
        // $wzi_logs_list_table->search_box( __( 'Search Logs', 'woocommerce-zoho-integration' ), 'wzi-log-search' );
        // $wzi_logs_list_table->display();
        // } else {
        // echo '<p>' . esc_html__( 'Log table is currently unavailable. The WZI_Logs_List_Table might not be loaded correctly.', 'woocommerce-zoho-integration' ) . '</p>';
        // }

        // Placeholder for where the WP_List_Table would be displayed.
        // The actual instantiation and display call for the WP_List_Table
        // is typically handled by the function that registers the admin page.
        // For example, in your WZI_Admin class:
        // function display_logs_page() {
        //     $list_table = new WZI_Logs_List_Table();
        //     $list_table->prepare_items();
        //     include 'partials/wzi-logs-display.php'; // This file
        //     // And then in this file, you would call $list_table->display();
        // }
        // So, for this partial, we'll assume $list_table is in scope.
        if ( isset( $GLOBALS['wzi_logs_list_table_instance'] ) && $GLOBALS['wzi_logs_list_table_instance'] instanceof WP_List_Table ) {
            $GLOBALS['wzi_logs_list_table_instance']->prepare_items(); // Prepare items again just in case.
            $GLOBALS['wzi_logs_list_table_instance']->search_box( __( 'Search Logs', 'woocommerce-zoho-integration' ), 'log_search' );
            $GLOBALS['wzi_logs_list_table_instance']->display();
        } else {
             echo '<div class="notice notice-warning"><p>';
             echo esc_html__( 'The logs table is not available. Please ensure the WZI_Logs_List_Table class is correctly loaded and instantiated.', 'woocommerce-zoho-integration' );
             echo '</p></div>';
             echo '<!-- Debug: $GLOBALS[wzi_logs_list_table_instance] not set or not a WP_List_Table -->';
        }
        ?>
    </form>

    <div id="wzi-log-management" style="margin-top: 30px;">
        <h2><?php esc_html_e( 'Log Management', 'woocommerce-zoho-integration' ); ?></h2>
        <p>
            <button id="wzi-clear-logs-button" class="button button-danger">
                <?php esc_html_e( 'Clear All Logs', 'woocommerce-zoho-integration' ); ?>
            </button>
            <span class="spinner"></span>
        </p>
        <p class="description">
            <?php esc_html_e( 'Warning: This will permanently delete all log entries. This action cannot be undone.', 'woocommerce-zoho-integration' ); ?>
        </p>
        <div id="wzi-clear-logs-feedback" style="margin-top: 10px;"></div>
         <p>
            <?php
            printf(
                // translators: %s: URL to settings page
                wp_kses_post( __( 'Log settings (e.g., log level, retention period) can be configured in the <a href="%s">plugin settings</a>.', 'woocommerce-zoho-integration' ) ),
                esc_url( admin_url( 'admin.php?page=wzi-settings&tab=logging' ) ) // Adjust if your settings page slug/tab is different
            );
            ?>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#wzi-clear-logs-button').on('click', function(e) {
        e.preventDefault();

        if (!confirm('<?php echo esc_js( __( "Are you sure you want to delete all logs? This action cannot be undone.", "woocommerce-zoho-integration" ) ); ?>')) {
            return;
        }

        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $feedback = $('#wzi-clear-logs-feedback');

        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        $feedback.html('<p><?php esc_html_e( "Clearing logs...", "woocommerce-zoho-integration" ); ?></p>').removeClass('error success');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wzi_clear_logs', // WordPress AJAX action hook
                nonce: '<?php echo wp_create_nonce('wzi_clear_logs_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $feedback.html('<p>' + response.data.message + '</p>').addClass('updated success');
                    // Optionally, refresh the list table or redirect
                    // For now, just show message. User might need to refresh to see empty table.
                    if (typeof пост !== 'undefined' && пост.wzi_logs_list_table_instance !== 'undefined') {
                         // This is tricky as the list table is server-rendered.
                         // A full page reload or AJAX reload of the table content is needed.
                         // For now, we'll just inform the user.
                         $feedback.append('<p><?php esc_html_e( "Please refresh the page to see the updated log table.", "woocommerce-zoho-integration" ); ?></p>');
                    } else {
                        // If table object is not available, suggest page refresh
                         $feedback.append('<p><?php esc_html_e( "Logs cleared. Please refresh the page.", "woocommerce-zoho-integration" ); ?></p>');
                    }
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : '<?php esc_html_e( "An error occurred while clearing logs.", "woocommerce-zoho-integration" ); ?>';
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

    // If you have filters in your WP_List_Table, they typically submit the form.
    // If you want to handle filters via AJAX, you'd need more JS here.
    // For standard WP_List_Table behavior, the form submission is fine.
});
</script>
