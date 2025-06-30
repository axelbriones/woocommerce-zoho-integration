<?php
/**
 * Migration: Create Mapping Table.
 *
 * This file defines the schema for the field mapping table.
 * This table stores custom mappings between WooCommerce fields and Zoho fields.
 *
 * @package WooCommerceZohoIntegration/Database/Migrations
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Creates or updates the field mapping table.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wzi_create_mapping_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wzi_field_mapping';
    $charset_collate = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {

        $sql = "CREATE TABLE $table_name (
            map_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            module VARCHAR(100) NOT NULL, -- e.g., customer, product, order, invoice_item
            wc_field VARCHAR(255) NOT NULL, -- WooCommerce field key (e.g., billing_first_name, _sku, meta:my_custom_field)
            wc_field_label VARCHAR(255) DEFAULT NULL, -- Human-readable label for WC field
            zoho_module VARCHAR(100) NOT NULL, -- Zoho module (e.g., Contacts, Items, SalesOrders, Invoices)
            zoho_field VARCHAR(255) NOT NULL, -- Zoho field API name (e.g., First_Name, Item_Name, SKU)
            zoho_field_label VARCHAR(255) DEFAULT NULL, -- Human-readable label for Zoho field
            direction VARCHAR(20) DEFAULT 'wc_to_zoho', -- wc_to_zoho, zoho_to_wc, bidirectional
            is_custom BOOLEAN NOT NULL DEFAULT 0, -- 1 if this is a user-defined mapping, 0 for default/system
            is_active BOOLEAN NOT NULL DEFAULT 1, -- Whether this mapping is currently active
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (map_id),
            UNIQUE KEY uk_wc_zoho_field_map (module, wc_field(100), zoho_module, zoho_field(100), direction), -- Ensure unique mapping combinations
            INDEX idx_module (module),
            INDEX idx_zoho_module (zoho_module),
            INDEX idx_is_active (is_active)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wzi_field_mapping_table_version', '1.0' );
        error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' created.' );

        // Seed default mappings after table creation if needed
        // wzi_seed_default_field_mappings(); // You would define this function elsewhere

    } else {
        // Table exists, check for updates/alterations if needed based on version.
        $current_version = get_option( 'wzi_field_mapping_table_version', '0' );
        if ( version_compare( $current_version, '1.0', '<' ) ) {
            // Example: Add a new column
            // $alter_sql = "ALTER TABLE $table_name ADD COLUMN new_config TEXT DEFAULT NULL;";
            // $wpdb->query($alter_sql);
            // update_option( 'wzi_field_mapping_table_version', '1.1' );
            // error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' updated to version 1.1.' );
        }
    }
}

/**
 * (Optional) Function to drop the table on plugin uninstallation.
 */
// function wzi_drop_mapping_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'wzi_field_mapping';
//     $wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );
//     delete_option( 'wzi_field_mapping_table_version' );
// }

// How to run this:
// Call wzi_create_mapping_table() from your plugin's activation hook callback.
// Example:
// class WZI_Activator {
//     public static function activate() {
//         require_once plugin_dir_path( __FILE__ ) . '../database/migrations/create_mapping_table.php';
//         wzi_create_mapping_table();
//         // ... other activation tasks ...
//     }
// }
