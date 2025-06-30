<?php
/**
 * Migration: Create Sync Logs Table.
 *
 * This file defines the schema for the synchronization logs table.
 * It should be run during plugin activation or update if the table doesn't exist.
 *
 * @package WooCommerceZohoIntegration/Database/Migrations
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Creates or updates the sync logs table.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wzi_create_sync_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wzi_sync_logs';
    $charset_collate = $wpdb->get_charset_collate();

    // Check if table exists
    // $wpdb->prepare is not needed for table name in SHOW TABLES LIKE, but using it for consistency or if table name could be dynamic.
    // However, for fixed table names, direct usage is also common and safe.
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {

        $sql = "CREATE TABLE $table_name (
            log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log_level VARCHAR(20) NOT NULL DEFAULT 'INFO', -- e.g., INFO, ERROR, WARNING, DEBUG
            source VARCHAR(100) DEFAULT NULL, -- e.g., CustomerSync, OrderSync, ZohoAPI
            object_id VARCHAR(255) DEFAULT NULL, -- WC Order ID, User ID, Product ID, Zoho Record ID
            object_type VARCHAR(100) DEFAULT NULL, -- e.g., order, customer, product, zoho_contact
            message TEXT NOT NULL,
            details LONGTEXT DEFAULT NULL, -- For storing more detailed error info, stack traces, API responses
            PRIMARY KEY  (log_id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_log_level (log_level),
            INDEX idx_source (source),
            INDEX idx_object_id_type (object_id(100), object_type) -- Indexing part of object_id if it's too long for a combined index
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Optionally, add a version for this table to WordPress options to manage future schema changes.
        update_option( 'wzi_sync_logs_table_version', '1.0' );

        // Log the creation (using a simple error_log for this example, or your plugin's logger if available early)
        // WZI_Logger::log('Database table ' . $table_name . ' created.');
        error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' created.' );

    } else {
        // Table exists, check for updates/alterations if needed based on version.
        $current_version = get_option( 'wzi_sync_logs_table_version', '0' );
        if ( version_compare( $current_version, '1.0', '<' ) ) {
            // Example: Add a new column if upgrading from an older version
            // $alter_sql = "ALTER TABLE $table_name ADD COLUMN new_column VARCHAR(255) DEFAULT NULL;";
            // $wpdb->query($alter_sql);
            // update_option( 'wzi_sync_logs_table_version', '1.1' ); // New version
            // error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' updated to version 1.1.' );
        }
    }
}

/**
 * (Optional) Function to drop the table on plugin uninstallation.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
// function wzi_drop_sync_logs_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'wzi_sync_logs';
//     // $wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table_name ) ); // %i is not a valid placeholder
//     $wpdb->query( "DROP TABLE IF EXISTS `$table_name`" ); // Backticks are safer for table names
//     delete_option( 'wzi_sync_logs_table_version' );
// }

// How to run this:
// During plugin activation:
// register_activation_hook( __FILE__, 'wzi_create_sync_logs_table' ); (If this file is the main plugin file)
// Or call wzi_create_sync_logs_table() from your plugin's activation hook callback in the main plugin file or activator class.

// Example:
// class WZI_Activator {
//     public static function activate() {
//         require_once plugin_dir_path( __FILE__ ) . '../database/migrations/create_sync_logs_table.php';
//         wzi_create_sync_logs_table();
//         // ... other activation tasks ...
//     }
// }
