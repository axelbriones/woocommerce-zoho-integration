<?php
/**
 * Migration: Create Auth Tokens Table.
 *
 * This file defines the schema for the authentication tokens table.
 *
 * @package WooCommerceZohoIntegration/Database/Migrations
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Creates or updates the auth tokens table.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wzi_create_auth_tokens_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wzi_auth_tokens';
    $charset_collate = $wpdb->get_charset_collate();

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service VARCHAR(50) NOT NULL,
            token_type VARCHAR(20) NOT NULL,
            access_token TEXT NOT NULL,
            refresh_token TEXT,
            expires_at DATETIME DEFAULT NULL,
            scope TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY service_type (service(50), token_type(20))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'wzi_auth_tokens_table_version', '1.0' );
        error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' created.' );

    } else {
        // Table exists, check for updates/alterations if needed based on version.
        $current_version = get_option( 'wzi_auth_tokens_table_version', '0' );
        if ( version_compare( $current_version, '1.0', '<' ) ) {
            // Example for future alterations
            // $alter_sql = "ALTER TABLE $table_name ADD COLUMN new_column_auth VARCHAR(255) DEFAULT NULL;";
            // $wpdb->query($alter_sql);
            // update_option( 'wzi_auth_tokens_table_version', '1.1' );
            // error_log( 'WooCommerce Zoho Integration: Database table ' . $table_name . ' updated to version 1.1.' );
        }
    }
}

/**
 * (Optional) Function to drop the table on plugin uninstallation.
 */
// function wzi_drop_auth_tokens_table() {
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'wzi_auth_tokens';
//     $wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );
//     delete_option( 'wzi_auth_tokens_table_version' );
// }
