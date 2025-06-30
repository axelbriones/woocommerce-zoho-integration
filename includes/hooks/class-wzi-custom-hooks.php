<?php
/**
 * Custom Hooks for WooCommerce Zoho Integration.
 *
 * This class is intended for developers to add their own custom hooks or
 * modify plugin behavior without altering core plugin files.
 *
 * @package WooCommerceZohoIntegration/Includes/Hooks
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WZI_Custom_Hooks.
 *
 * Allows for custom actions and filters to be added.
 * Instantiated by the main plugin class or the loader.
 */
class WZI_Custom_Hooks {

    /**
     * WZI_Custom_Hooks constructor.
     */
    public function __construct() {
        // Initialize custom hooks here
        $this->add_custom_actions();
        $this->add_custom_filters();
    }

    /**
     * Add custom WordPress actions.
     */
    private function add_custom_actions() {
        // Example:
        // add_action( 'wzi_before_customer_sync', array( $this, 'my_custom_action_before_customer_sync' ), 10, 2 );
    }

    /**
     * Add custom WordPress filters.
     */
    private function add_custom_filters() {
        // Example:
        // add_filter( 'wzi_customer_data_for_zoho', array( $this, 'my_custom_filter_customer_data' ), 10, 3 );
    }

    /**
     * Example custom action callback.
     *
     * @param mixed $wc_customer WooCommerce customer object or ID.
     * @param WZI_Sync_Customers $sync_handler The customer sync handler instance.
     */
    // public function my_custom_action_before_customer_sync( $wc_customer, $sync_handler ) {
        // Your custom code here
        // Example: error_log('Custom action: Before syncing customer ' . (is_object($wc_customer) ? $wc_customer->get_id() : $wc_customer));
    // }

    /**
     * Example custom filter callback.
     *
     * @param array $zoho_data The data prepared for Zoho.
     * @param mixed $wc_customer WooCommerce customer object or ID.
     * @param WZI_Sync_Customers $sync_handler The customer sync handler instance.
     * @return array Modified $zoho_data.
     */
    // public function my_custom_filter_customer_data( $zoho_data, $wc_customer, $sync_handler ) {
        // Your custom code here
        // Example: $zoho_data['custom_field_from_filter'] = 'some_value';
        // return $zoho_data;
    // }

    // Add more methods for your custom hooks below.
}

// Example of how this class might be instantiated in the main plugin file or loader:
// if ( class_exists( 'WZI_Custom_Hooks' ) ) {
// new WZI_Custom_Hooks();
// }
