<?php
/**
 * Provides the admin area display for Help & Support.
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
    <h1><?php echo esc_html( get_admin_page_title() ); ?> - <?php esc_html_e( 'Help & Support', 'woocommerce-zoho-integration' ); ?></h1>

    <div class="wzi-help-section">
        <h2><?php esc_html_e( 'Getting Started', 'woocommerce-zoho-integration' ); ?></h2>
        <p>
            <?php esc_html_e( 'Welcome to the WooCommerce Zoho Integration plugin! Follow these steps to get started:', 'woocommerce-zoho-integration' ); ?>
        </p>
        <ol>
            <li><?php
                printf(
                    // translators: %s: URL to settings page
                    wp_kses_post(__( '<strong>Configure API Credentials:</strong> Go to the <a href="%s">API Settings tab</a> to enter your Zoho API client ID, client secret, and authorize the plugin to connect to your Zoho account.', 'woocommerce-zoho-integration' )),
                    esc_url( admin_url( 'admin.php?page=wzi-settings&tab=api' ) ) // Adjust if your settings page slug/tab is different
                );
            ?></li>
            <li><?php
                 printf(
                    // translators: %s: URL to settings page
                    wp_kses_post(__( '<strong>Set Synchronization Options:</strong> Visit the <a href="%s">Synchronization Settings tab</a> to choose which data (customers, products, orders, etc.) you want to sync and configure how it syncs (manual, automatic, real-time).', 'woocommerce-zoho-integration' )),
                    esc_url( admin_url( 'admin.php?page=wzi-settings&tab=sync' ) ) // Adjust if your settings page slug/tab is different
                );
            ?></li>
            <li><?php
                 printf(
                    // translators: %s: URL to settings page
                    wp_kses_post(__( '<strong>Field Mapping:</strong> (If applicable) Use the <a href="%s">Field Mapping tab</a> to map WooCommerce fields to corresponding Zoho fields for accurate data transfer.', 'woocommerce-zoho-integration' )),
                    esc_url( admin_url( 'admin.php?page=wzi-settings&tab=mapping' ) ) // Adjust if your settings page slug/tab is different
                );
            ?></li>
            <li><?php
                 printf(
                    // translators: %s: URL to sync page
                    wp_kses_post(__( '<strong>Initial Sync:</strong> Perform an initial manual sync from the <a href="%s">Synchronization page</a> for each data type to ensure existing data is transferred to Zoho.', 'woocommerce-zoho-integration' )),
                    esc_url( admin_url( 'admin.php?page=wzi-sync' ) ) // Adjust if your sync page slug is different
                );
            ?></li>
            <li><?php
                 printf(
                    // translators: %s: URL to logs page
                    wp_kses_post(__( '<strong>Monitor Logs:</strong> Check the <a href="%s">Logs page</a> regularly to monitor synchronization activity and troubleshoot any issues.', 'woocommerce-zoho-integration' )),
                    esc_url( admin_url( 'admin.php?page=wzi-logs' ) ) // Adjust if your logs page slug is different
                );
            ?></li>
        </ol>
    </div>

    <div class="wzi-help-section">
        <h2><?php esc_html_e( 'Frequently Asked Questions (FAQ)', 'woocommerce-zoho-integration' ); ?></h2>

        <h4><?php esc_html_e( 'Q: How do I generate Zoho API credentials?', 'woocommerce-zoho-integration' ); ?></h4>
        <p>
            <?php
            echo wp_kses_post(
                __( 'A: You need to create a new API client in your Zoho API Console. For detailed instructions, please refer to the <a href="https://www.zoho.com/crm/developer/docs/api/v5/oauth-overview.html" target="_blank" rel="noopener noreferrer">official Zoho OAuth documentation</a> (this link is for CRM, adapt if using other Zoho services primarily). Choose "Server-based Applications". Your redirect URI will be displayed on the API settings page of this plugin.', 'woocommerce-zoho-integration' )
            );
            // You can retrieve and display the actual redirect URI:
            // $redirect_uri = WZI_Zoho_Auth::get_redirect_uri(); // Assuming you have such a method
            // if ($redirect_uri) {
            // echo ' ' . sprintf(esc_html__('Your specific redirect URI is: %s', 'woocommerce-zoho-integration'), '<code>' . esc_url($redirect_uri) . '</code>');
            // }
            ?>
        </p>

        <h4><?php esc_html_e( 'Q: Data is not syncing. What should I do?', 'woocommerce-zoho-integration' ); ?></h4>
        <p>
            <?php esc_html_e( 'A: First, check the following:', 'woocommerce-zoho-integration' ); ?>
        </p>
        <ul>
            <li><?php esc_html_e( 'Ensure your API credentials are correct and the connection is authorized.', 'woocommerce-zoho-integration' ); ?></li>
            <li><?php esc_html_e( 'Verify that the specific data type (e.g., Customers) is enabled for synchronization in the settings.', 'woocommerce-zoho-integration' ); ?></li>
            <li><?php
                 printf(
                    // translators: %s: URL to logs page
                    wp_kses_post(__( 'Check the <a href="%s">Logs page</a> for any specific error messages. These messages often provide clues about the problem.', 'woocommerce-zoho-integration' )),
                    esc_url( admin_url( 'admin.php?page=wzi-logs' ) )
                );
            ?></li>
            <li><?php esc_html_e( 'If using cron jobs for automatic sync, ensure WordPress cron is functioning correctly on your server.', 'woocommerce-zoho-integration' ); ?></li>
        </ul>

        <h4><?php esc_html_e( 'Q: Can I map custom fields?', 'woocommerce-zoho-integration' ); ?></h4>
        <p>
            <?php
            printf(
                // translators: %s: URL to settings page
                wp_kses_post(__( 'A: Yes, the plugin provides a <a href="%s">Field Mapping section</a> in the settings where you can map standard WooCommerce fields and custom fields (post meta or user meta) to your Zoho fields.', 'woocommerce-zoho-integration' )),
                esc_url( admin_url( 'admin.php?page=wzi-settings&tab=mapping' ) )
            );
            ?>
        </p>

        <h4><?php esc_html_e( 'Q: How does real-time sync work?', 'woocommerce-zoho-integration' ); ?></h4>
        <p>
            <?php esc_html_e( 'A: When real-time sync is enabled for a data type (e.g., new order placed, customer account created), the plugin uses WordPress hooks to trigger an immediate synchronization attempt to Zoho for that specific item.', 'woocommerce-zoho-integration' ); ?>
        </p>

        <!-- Add more FAQs as needed -->
    </div>

    <div class="wzi-help-section">
        <h2><?php esc_html_e( 'Troubleshooting', 'woocommerce-zoho-integration' ); ?></h2>
        <p>
            <?php esc_html_e( 'If you encounter issues, the first place to check is the Logs page. Common issues include:', 'woocommerce-zoho-integration' ); ?>
        </p>
        <ul>
            <li><strong><?php esc_html_e( 'Authentication Errors:', 'woocommerce-zoho-integration' ); ?></strong> <?php esc_html_e( 'Invalid client ID/secret, expired access token, or incorrect scopes. Re-authorize the plugin.', 'woocommerce-zoho-integration' ); ?></li>
            <li><strong><?php esc_html_e( 'API Rate Limits:', 'woocommerce-zoho-integration' ); ?></strong> <?php esc_html_e( 'Zoho APIs have rate limits. If you sync a large amount of data, you might hit these limits. The plugin should handle this gracefully, but logs will show if this is an issue.', 'woocommerce-zoho-integration' ); ?></li>
            <li><strong><?php esc_html_e( 'Data Validation Errors:', 'woocommerce-zoho-integration' ); ?></strong> <?php esc_html_e( 'Zoho might reject data if it doesn\'t meet its validation rules (e.g., a required field is missing, data format is incorrect). Check field mappings and ensure data quality.', 'woocommerce-zoho-integration' ); ?></li>
            <li><strong><?php esc_html_e( 'Plugin Conflicts:', 'woocommerce-zoho-integration' ); ?></strong> <?php esc_html_e( 'Another plugin might interfere. Try deactivating other plugins temporarily to identify a conflict.', 'woocommerce-zoho-integration' ); ?></li>
            <li><strong><?php esc_html_e( 'Server Configuration:', 'woocommerce-zoho-integration' ); ?></strong> <?php esc_html_e( 'Ensure your server\'s cURL version is up-to-date and can make outbound HTTPS requests. WordPress cron must also be functional for scheduled syncs.', 'woocommerce-zoho-integration' ); ?></li>
        </ul>
    </div>

    <div class="wzi-help-section">
        <h2><?php esc_html_e( 'Support', 'woocommerce-zoho-integration' ); ?></h2>
        <p>
            <?php esc_html_e( 'If you need further assistance, please provide as much detail as possible, including:', 'woocommerce-zoho-integration' ); ?>
        </p>
        <ul>
            <li><?php esc_html_e( 'Version of WooCommerce.', 'woocommerce-zoho-integration' ); ?></li>
            <li><?php esc_html_e( 'Version of this plugin.', 'woocommerce-zoho-integration' ); ?></li>
            <li><?php esc_html_e( 'Relevant error messages from the Logs page.', 'woocommerce-zoho-integration' ); ?></li>
            <li><?php esc_html_e( 'Steps to reproduce the issue.', 'woocommerce-zoho-integration' ); ?></li>
            <li><?php esc_html_e( 'Your Zoho services being used (CRM, Books, Inventory, Campaigns).', 'woocommerce-zoho-integration' ); ?></li>
        </ul>
        <p>
            <?php
            // Replace with your actual support channel (e.g., email, support forum link)
            echo wp_kses_post( __( 'You can reach out for support via <a href="mailto:your-support-email@example.com">your-support-email@example.com</a> or visit our <a href="YOUR_SUPPORT_FORUM_URL" target="_blank" rel="noopener noreferrer">support forum</a>.', 'woocommerce-zoho-integration' ) );
            ?>
        </p>
    </div>

    <div class="wzi-help-section">
        <h2><?php esc_html_e( 'System Information', 'woocommerce-zoho-integration' ); ?></h2>
        <p><?php esc_html_e( 'This information can be helpful when seeking support.', 'woocommerce-zoho-integration' ); ?></p>
        <textarea readonly="readonly" onclick="this.focus();this.select()" rows="10" style="width:100%; font-family: monospace;">
WordPress Version: <?php echo esc_html( get_bloginfo( 'version' ) ); ?>

PHP Version: <?php echo esc_html( phpversion() ); ?>

WooCommerce Version: <?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A' ); ?>

<?php echo esc_html( $this->plugin->get_plugin_name() ); // Assuming $this is WZI_Admin and has access to plugin object ?> Version: <?php echo esc_html( $this->plugin->get_version() ); // Assuming $this is WZI_Admin and has access to plugin object ?>

Active Theme: <?php echo esc_html( wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) ); ?>

Active Plugins:
<?php
$active_plugins = get_option( 'active_plugins' );
$plugin_details = array();
foreach ( $active_plugins as $plugin_file ) {
    $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
    $plugin_details[] = $plugin_data['Name'] . ' (' . $plugin_data['Version'] . ')';
}
echo esc_html( implode( "\n", $plugin_details ) );
?>

Zoho API Connection Status: <?php
    // This requires a method in your auth class to check status without making a full API call if possible
    // Or just indicate if tokens are present.
    $auth_handler = new WZI_Zoho_Auth(); // This might need to be fetched from the plugin container or passed
    $tokens = $auth_handler->get_tokens();
    if ( !empty($tokens['access_token']) && !$auth_handler->is_access_token_expired() ) {
        echo esc_html__('Connected (Access Token seems valid)', 'woocommerce-zoho-integration');
    } elseif (!empty($tokens['access_token']) && $auth_handler->is_access_token_expired()) {
        echo esc_html__('Disconnected (Access Token Expired - Awaiting Refresh)', 'woocommerce-zoho-integration');
    } else {
        echo esc_html__('Disconnected (No Access Token)', 'woocommerce-zoho-integration');
    }
?>

WP Cron Status: <?php echo ( defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ) ? esc_html__('Disabled via config', 'woocommerce-zoho-integration') : esc_html__('Enabled', 'woocommerce-zoho-integration'); ?>

        </textarea>
    </div>


</div>
