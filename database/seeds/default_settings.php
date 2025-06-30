<?php
/**
 * Default Settings Seed.
 *
 * This file can be used to define default settings for the plugin,
 * which might be applied on activation or when settings are reset.
 *
 * @package WooCommerceZohoIntegration/Database/Seeds
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Get the default plugin settings.
 *
 * These settings are typically stored in the wp_options table.
 * The main settings key could be 'wzi_settings'.
 *
 * @return array Default settings.
 */
function wzi_get_default_settings() {
    $defaults = array(
        'api_credentials' => array(
            'client_id' => '',
            'client_secret' => '',
            'redirect_uri' => admin_url( 'admin.php?page=wzi-settings&tab=api&action=wzi_oauth_callback' ), // Example, make it dynamic
            'access_token' => '',
            'refresh_token' => '',
            'token_expiry' => 0,
            'authorized_scopes' => '',
            'zoho_domain' => 'com', // e.g., com, eu, in
            'api_console_url' => 'https://api-console.zoho.com', // Default, may vary by DC
        ),
        'sync_settings' => array(
            'customers' => array(
                'enabled' => true,
                'real_time' => true, // Sync on new customer creation / update
                'cron_schedule' => 'daily', // WP Cron schedule name for bulk/background sync
                'wc_to_zoho_crm_module' => 'Contacts', // Contacts or Leads
                'conflict_resolution' => 'wc_wins', // wc_wins, zoho_wins, manual
            ),
            'products' => array(
                'enabled' => true,
                'real_time' => true, // Sync on product create/update
                'cron_schedule' => 'daily',
                'wc_to_zoho_module' => 'Items', // Zoho Inventory Items
                'sync_stock' => true,
                'sync_price' => true,
            ),
            'orders' => array(
                'enabled' => true,
                'real_time' => true, // Sync on new order, order status change
                'wc_to_zoho_crm_module' => 'SalesOrders', // SalesOrders or Deals
                'trigger_on_status' => array( 'processing', 'completed' ), // WC order statuses that trigger sync
            ),
            'invoices' => array(
                'enabled' => true,
                'real_time' => true, // Sync when order is paid / invoice generated in WC
                'wc_to_zoho_books_module' => 'Invoices',
                'trigger_on_order_status' => array('completed'),
            ),
            'coupons' => array(
                'enabled' => false, // Disabled by default, can be niche
                'real_time' => false,
                'wc_to_zoho_campaigns_module' => 'Campaigns', // Or a custom module for promos
            ),
            'sync_deleted_items' => false, // Whether to attempt to delete items in Zoho when deleted in WC
        ),
        'field_mapping' => array(
            // Default mappings can be pre-populated here or handled by the mapping table seeder.
            // This array might just store a reference or version for default mappings.
            'version' => '1.0',
            'use_default_mappings' => true,
        ),
        'logging_settings' => array(
            'enable_logging' => true,
            'log_level' => 'INFO', // ERROR, WARNING, INFO, DEBUG
            'log_retention_days' => 30, // 0 for unlimited
        ),
        'advanced_settings' => array(
            'enable_webhooks' => false, // For receiving updates from Zoho
            'webhook_secret' => wp_generate_password( 32, false ),
            'background_processing_method' => 'cron', // cron, action_scheduler, direct
            'max_queue_items_per_cron' => 50,
            'api_request_timeout' => 30, // seconds
        ),
        'plugin_version' => WOOCOMMERCE_ZOHO_INTEGRATION_VERSION, // Store current plugin version for upgrade routines
        'setup_wizard_completed' => false,
    );

    return apply_filters( 'wzi_default_settings', $defaults );
}

/**
 * Apply default settings upon plugin activation or reset.
 *
 * @param bool $force Force update even if settings exist (e.g., for reset).
 */
function wzi_apply_default_settings( $force = false ) {
    $settings_option_name = 'wzi_settings'; // Your main settings option key
    $current_settings = get_option( $settings_option_name );

    if ( $force || false === $current_settings ) {
        $default_settings = wzi_get_default_settings();
        update_option( $settings_option_name, $default_settings );
        error_log('WooCommerce Zoho Integration: Default settings applied.');
    } elseif ( is_array( $current_settings ) ) {
        // If settings exist, merge with defaults to add any new default options
        // introduced in plugin updates, without overwriting existing user configurations.
        $default_settings = wzi_get_default_settings();
        $updated_settings = array_replace_recursive( $default_settings, $current_settings );

        // Ensure specific structures if needed (e.g. if a default array key was removed by user but should exist)
        // For example, ensure all main keys from defaults are present
        foreach($default_settings as $key => $value) {
            if (!isset($updated_settings[$key])) {
                $updated_settings[$key] = $value;
            } elseif (is_array($value)) {
                 $updated_settings[$key] = array_replace_recursive($value, $updated_settings[$key]);
            }
        }


        // Only update if there are actual changes to avoid unnecessary db writes
        if ($updated_settings !== $current_settings) {
            update_option( $settings_option_name, $updated_settings );
            error_log('WooCommerce Zoho Integration: Settings updated with new defaults.');
        }
    }
}

// How to use:
// 1. On plugin activation:
//    wzi_apply_default_settings();
//
// 2. On settings reset action from plugin admin page:
//    if ( current_user_can('manage_options') && isset($_POST['wzi_reset_settings']) ) {
//        wzi_apply_default_settings( true ); // Force apply
//    }
//
// 3. During plugin updates to introduce new default options:
//    $current_version = get_option('wzi_settings')['plugin_version'] ?? '0.0.0';
//    if (version_compare($current_version, NEW_PLUGIN_VERSION, '<')) {
//        wzi_apply_default_settings(); // This will merge defaults
//        // then update plugin_version in settings
//    }

?>
