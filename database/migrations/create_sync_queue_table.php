<?php
/**
 * Migration: Create Sync Queue Table.
 *
 * This file defines the schema for the synchronization queue table.
 * This table holds items that are pending synchronization with Zoho.
 *
 * @package WooCommerceZohoIntegration/Database/Migrations
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Creates or updates the sync queue table.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wzi_create_sync_queue_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wzi_sync_queue';
    $charset_collate = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {

        $sql = "CREATE TABLE $table_name (
            queue_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_id VARCHAR(255) NOT NULL, -- WC Order ID, User ID, Product ID, Coupon ID
            object_type VARCHAR(100) NOT NULL, -- e.g., order, customer, product, coupon, invoice
            sync_type VARCHAR(100) NOT NULL, -- e.g., create, update, delete. Could also be specific like 'order_to_salesorder', 'customer_to_contact'
            priority TINYINT NOT NULL DEFAULT 10, -- Lower number = higher priority
            status VARCHAR(50) NOT NULL DEFAULT 'pending', -- e.g., pending, processing, failed, completed (though completed items might be moved/deleted)
            attempts TINYINT NOT NULL DEFAULT 0, -- Number of times this sync has been attempted
            last_attempt_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            data LONGTEXT DEFAULT NULL, -- Optional: Store serialized data needed for the sync if it's complex or to avoid re-fetching
            error_message TEXT DEFAULT NULL, -- If status is 'failed', store the error here
            PRIMARY KEY  (queue_id),
            INDEX idx_status_priority_created (status(20), priority, created_at), -- For fetching pending tasks
            INDEX idx_object_id_type (object_id(100), object_type(50), sync_type(50)) -- To check if an item is already queued
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wzi_sync_queue_table_version', '1.0' );
        error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' created.' );

    } else {
        // Table exists, check for updates/alterations if needed based on version.
        $current_version = get_option( 'wzi_sync_queue_table_version', '0' );
        if ( version_compare( $current_version, '1.0', '<' ) ) {
            // Example: Add a new column 'data' if upgrading from an older version that didn't have it
            // $alter_sql = "ALTER TABLE $table_name ADD COLUMN data LONGTEXT DEFAULT NULL AFTER updated_at;";
            // $wpdb->query($alter_sql);
            // update_option( 'wzi_sync_queue_table_version', '1.1' );
            // error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' updated to version 1.1.' );
        }
    }
}

/**
 * (Optional) Function to drop the table on plugin uninstallation.
 */
// function wzi_drop_sync_queue_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'wzi_sync_queue';
//     $wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );
//     delete_option( 'wzi_sync_queue_table_version' );
// }

// How to run this:
// Call wzi_create_sync_queue_table() from your plugin's activation hook callback.
// Example:
// class WZI_Activator {
//     public static function activate() {
//         require_once plugin_dir_path( __FILE__ ) . '../database/migrations/create_sync_queue_table.php';
//         wzi_create_sync_queue_table();
//         // ... other activation tasks ...
//     }
// }
