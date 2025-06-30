<?php
/**
 * Admin Settings Tabs Template for WooCommerce Zoho Integration.
 *
 * This template defines the tabs for the settings page.
 * It's typically included at the top of each settings tab's content display.
 *
 * @package WooCommerceZohoIntegration/Templates/Admin
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// The $tabs array and $active_tab string should be passed to this template.
// Example:
// $tabs = array(
// 'api' => __('API Settings', 'woocommerce-zoho-integration'),
// 'sync' => __('Synchronization', 'woocommerce-zoho-integration'),
// 'mapping' => __('Field Mapping', 'woocommerce-zoho-integration'),
// 'logging' => __('Logging', 'woocommerce-zoho-integration'),
// 'advanced' => __('Advanced', 'woocommerce-zoho-integration'),
// );
// $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'api';

if ( empty( $tabs ) || ! is_array( $tabs ) ) {
    // Fallback if tabs are not provided, though this shouldn't happen in normal use.
    $tabs = array( 'general' => __( 'General', 'woocommerce-zoho-integration' ) );
}
if ( empty( $active_tab ) ) {
    $active_tab = key( $tabs ); // Default to the first tab.
}

$settings_page_slug = 'wzi-settings'; // Make sure this matches your settings page slug.

?>
<h2 class="nav-tab-wrapper">
    <?php
    foreach ( $tabs as $tab_id => $tab_name ) {
        $tab_url = add_query_arg( array(
            'page' => $settings_page_slug,
            'tab'  => $tab_id,
        ), admin_url( 'admin.php' ) );

        $active_class = ( $active_tab === $tab_id ) ? ' nav-tab-active' : '';
        echo '<a href="' . esc_url( $tab_url ) . '" title="' . esc_attr( $tab_name ) . '" class="nav-tab' . esc_attr( $active_class ) . '">';
        echo esc_html( $tab_name );
        echo '</a>';
    }
    ?>
</h2>

<?php
// The content for the active tab will be displayed after this include
// by the main settings page callback function. For example:
//
// function display_settings_page() {
//     // ... get $tabs and $active_tab ...
//     include 'templates/admin/settings-tabs.php'; // This file
//
//     // Then, based on $active_tab, include the specific tab content partial
//     // e.g., if ($active_tab === 'api') { include 'partials/wzi-settings-api-tab.php'; }
// }
?>
