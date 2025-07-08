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
    $target_db_version = '1.3'; // Incrementar versión por más cambios de esquema explícitos y logging
    error_log("WZI: Running wzi_create_mapping_table() - Target DB version: $target_db_version");

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

    if ($table_exists) {
        error_log("WZI: Table $table_name exists. Checking columns...");

        // Helper function to check if a column exists
        $column_exists = function($column_name) use ($wpdb, $table_name) {
            return !empty($wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE table_name = %s AND column_name = %s AND table_schema = %s",
                $table_name, $column_name, DB_NAME
            )));
        };

        // 1. Handle 'module' column (formerly 'entity_type')
        if (!$column_exists('module')) {
            error_log("WZI: Column 'module' does not exist in $table_name.");
            if ($column_exists('entity_type')) {
                error_log("WZI: Column 'entity_type' exists. Attempting to rename to 'module'.");
                if ($wpdb->query("ALTER TABLE `$table_name` CHANGE COLUMN `entity_type` `module` VARCHAR(100) NOT NULL") !== false) {
                    error_log("WZI: Successfully RENAMED column entity_type to module in $table_name.");
                } else {
                    error_log("WZI: FAILED to rename column entity_type to module. Error: " . $wpdb->last_error);
                }
            } else {
                error_log("WZI: Column 'entity_type' also does not exist. Attempting to ADD 'module'.");
                if ($wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `module` VARCHAR(100) NOT NULL AFTER `map_id`") !== false) { // Ajustar posición si es necesario
                    error_log("WZI: Successfully ADDED column 'module' to $table_name.");
                } else {
                    error_log("WZI: FAILED to add column 'module'. Error: " . $wpdb->last_error);
                }
            }
        } else {
            error_log("WZI: Column 'module' already exists in $table_name.");
        }

        // 2. Handle 'wc_field' column (formerly 'woo_field')
        if (!$column_exists('wc_field')) {
            error_log("WZI: Column 'wc_field' does not exist in $table_name.");
            if ($column_exists('woo_field')) {
                error_log("WZI: Column 'woo_field' exists. Attempting to rename to 'wc_field'.");
                if ($wpdb->query("ALTER TABLE `$table_name` CHANGE COLUMN `woo_field` `wc_field` VARCHAR(255) NOT NULL") !== false) {
                    error_log("WZI: Successfully RENAMED column woo_field to wc_field in $table_name.");
                } else {
                    error_log("WZI: FAILED to rename column woo_field to wc_field. Error: " . $wpdb->last_error);
                }
            } else {
                error_log("WZI: Column 'woo_field' also does not exist. Attempting to ADD 'wc_field'.");
                 // Determinar una posición razonable para añadirla si es necesario
                if ($wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `wc_field` VARCHAR(255) NOT NULL AFTER `module`") !== false) {
                    error_log("WZI: Successfully ADDED column 'wc_field' to $table_name.");
                } else {
                    error_log("WZI: FAILED to add column 'wc_field'. Error: " . $wpdb->last_error);
                }
            }
        } else {
            error_log("WZI: Column 'wc_field' already exists in $table_name.");
        }

        // 3. Handle 'direction' column (formerly 'sync_direction')
        if (!$column_exists('direction')) {
            error_log("WZI: Column 'direction' does not exist in $table_name.");
            if ($column_exists('sync_direction')) {
                error_log("WZI: Column 'sync_direction' exists. Attempting to rename to 'direction'.");
                if ($wpdb->query("ALTER TABLE `$table_name` CHANGE COLUMN `sync_direction` `direction` VARCHAR(20) DEFAULT 'wc_to_zoho'") !== false) {
                    error_log("WZI: Successfully RENAMED column sync_direction to direction in $table_name.");
                } else {
                    error_log("WZI: FAILED to rename column sync_direction to direction. Error: " . $wpdb->last_error);
                }
            } else {
                error_log("WZI: Column 'sync_direction' also does not exist. Attempting to ADD 'direction'.");
                // Determinar una posición razonable
                if ($wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `direction` VARCHAR(20) DEFAULT 'wc_to_zoho' AFTER `zoho_field_label`") !== false) {
                    error_log("WZI: Successfully ADDED column 'direction' to $table_name.");
                } else {
                    error_log("WZI: FAILED to add column 'direction'. Error: " . $wpdb->last_error);
                }
            }
        } else {
            error_log("WZI: Column 'direction' already exists in $table_name.");
        }

    } else {
         error_log("WZI: Table $table_name does not exist. dbDelta will attempt to create it.");
    }

    // SQL DDL para la tabla (versión 1.3)
    $sql = "CREATE TABLE `$table_name` (
        map_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        module VARCHAR(100) NOT NULL,
        wc_field VARCHAR(255) NOT NULL,
        wc_field_label VARCHAR(255) DEFAULT NULL,
        zoho_module VARCHAR(100) NOT NULL,
        zoho_field VARCHAR(255) NOT NULL,
        zoho_field_label VARCHAR(255) DEFAULT NULL,
        direction VARCHAR(20) DEFAULT 'wc_to_zoho',
        transform_function VARCHAR(255) DEFAULT NULL,
        is_custom BOOLEAN NOT NULL DEFAULT 0,
        is_active BOOLEAN NOT NULL DEFAULT 1,
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
        // Verificar columnas adicionales que dbDelta podría no añadir si la tabla ya existe de una forma muy básica
        $expected_columns = [
            'transform_function' => "ADD COLUMN transform_function VARCHAR(255) DEFAULT NULL AFTER direction",
            'is_active' => "ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT 1 AFTER is_custom",
            'wc_field_label' => "ADD COLUMN wc_field_label VARCHAR(255) DEFAULT NULL AFTER wc_field",
            'zoho_module' => "ADD COLUMN zoho_module VARCHAR(100) NOT NULL AFTER wc_field_label",
            'zoho_field_label' => "ADD COLUMN zoho_field_label VARCHAR(255) DEFAULT NULL AFTER zoho_field",
            'is_custom' => "ADD COLUMN is_custom BOOLEAN NOT NULL DEFAULT 0 AFTER transform_function"
        ];

        foreach($expected_columns as $col_name => $alter_query_part) {
            $column_exists = $wpdb->get_results( $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE table_name = %s AND column_name = %s AND table_schema = %s",
                $table_name, $col_name, DB_NAME
            ) );
            if ( empty( $column_exists ) ) {
                $wpdb->query("ALTER TABLE `$table_name` $alter_query_part");
                error_log( "WZI: ADDED column $col_name to $table_name." );
            }
        }
        
        if ( version_compare( $current_db_version, $target_db_version, '<' ) ) {
            update_option( 'wzi_field_mapping_table_version', $target_db_version );
            error_log( "WZI: Database table $table_name version updated to $target_db_version." );
        } elseif ($current_db_version === '0' && $wpdb->last_error === '') { 
             update_option( 'wzi_field_mapping_table_version', $target_db_version );
             error_log( "WZI: Database table $table_name created with version $target_db_version." );
        }

    } else {
        error_log( "WZI: Error - Database table $table_name could not be created/found after dbDelta. SQL used: $sql" );
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
