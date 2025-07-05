<?php
/**
 * Migration: Create Mapping Table.
 *
 * This file defines the schema for the field mapping table.
 * This table stores custom mappings between WooCommerce fields and Zoho fields.
 *
 * @package WooCommerceZohoIntegration/Database/Migrations
 * @version 1.1.0
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
    $target_db_version = '1.1'; // The version this file defines

    // SQL DDL para la tabla (versión 1.1 con transform_function)
    // Corregido: Eliminada la línea 'direction' duplicada
    $sql = "CREATE TABLE $table_name (
        map_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        module VARCHAR(100) NOT NULL, -- e.g., customer, product, order
        wc_field VARCHAR(255) NOT NULL, -- WooCommerce field key (e.g., billing_first_name, _sku, meta:my_custom_field)
        wc_field_label VARCHAR(255) DEFAULT NULL, -- Human-readable label for WC field
        zoho_module VARCHAR(100) NOT NULL, -- Zoho module (e.g., Contacts, Items, SalesOrders)
        zoho_field VARCHAR(255) NOT NULL, -- Zoho field API name (e.g., First_Name, Item_Name, SKU)
        zoho_field_label VARCHAR(255) DEFAULT NULL, -- Human-readable label for Zoho field
        direction VARCHAR(20) DEFAULT 'wc_to_zoho', -- wc_to_zoho, zoho_to_wc, both
        transform_function VARCHAR(255) DEFAULT NULL, -- Stores the name of a PHP callback function for data transformation
        is_custom BOOLEAN NOT NULL DEFAULT 0, -- 1 if this is a user-defined mapping, 0 for default/system
        is_active BOOLEAN NOT NULL DEFAULT 1, -- Whether this mapping is currently active
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (map_id),
        UNIQUE KEY uk_wc_zoho_field_map (module, wc_field(100), zoho_module, zoho_field(100), direction), 
        INDEX idx_module (module),
        INDEX idx_zoho_module (zoho_module),
        INDEX idx_is_active (is_active)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql ); 

    $current_db_version = get_option( 'wzi_field_mapping_table_version', '0' );

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) === $table_name ) {
        // Verificar si la columna 'transform_function' existe
        $column_exists_transform = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name = %s AND column_name = %s AND table_schema = %s",
            $table_name, 'transform_function', DB_NAME
        ) );

        if ( empty( $column_exists_transform ) ) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN transform_function VARCHAR(255) DEFAULT NULL AFTER direction");
            error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' - ADDED transform_function column via direct ALTER.' );
        }
        
        // Asegurar que 'is_active' exista
        $column_exists_is_active = $wpdb->get_results( $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
            WHERE table_name = %s AND column_name = %s AND table_schema = %s",
            $table_name, 'is_active', DB_NAME
        ) );
         if ( empty( $column_exists_is_active ) ) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT 1 AFTER is_custom");
            error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' - ADDED is_active column via direct ALTER.' );
        }

        if ( version_compare( $current_db_version, $target_db_version, '<' ) ) {
            update_option( 'wzi_field_mapping_table_version', $target_db_version );
            error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' version updated to ' . $target_db_version );
        } elseif ($current_db_version === '0' && $wpdb->last_error === '') { 
             update_option( 'wzi_field_mapping_table_version', $target_db_version );
             error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' created with version ' . $target_db_version );
        }

    } else {
        error_log( 'WooCommerce Zoho Integration: Error - Database table ' . $table_name . ' could not be created/found after dbDelta. SQL used: ' . $sql );
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
