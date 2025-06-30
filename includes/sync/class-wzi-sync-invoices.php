<?php
/**
 * Handles WooCommerce Invoice Synchronization with Zoho.
 *
 * @package WooCommerceZohoIntegration/Includes/Sync
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WZI_Sync_Invoices.
 */
class WZI_Sync_Invoices {

    /**
     * Zoho Books API instance.
     *
     * @var WZI_Zoho_Books
     */
    private $zoho_books;

    /**
     * Logger instance.
     *
     * @var WZI_Logger
     */
    private $logger;

    /**
     * WZI_Sync_Invoices constructor.
     */
    public function __construct() {
        $this->zoho_books = new WZI_Zoho_Books();
        $this->logger     = new WZI_Logger();
    }

    /**
     * Sync a WooCommerce invoice to Zoho Books.
     *
     * This is typically triggered when a WooCommerce order is paid or an invoice is generated.
     *
     * @param int|WC_Order $order WooCommerce Order ID or object.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function sync_invoice_to_zoho( $order ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Invalid order for invoice sync.', 'woocommerce-zoho-integration' ) );
        }

        $this->logger->log( "Starting invoice sync for Order ID: {$order->get_id()}" );

        // 1. Get order data and format it for Zoho Books API.
        $invoice_data = $this->prepare_invoice_data_for_zoho( $order );
        if ( is_wp_error( $invoice_data ) ) {
            $this->logger->log( "Error preparing invoice data for Order ID: {$order->get_id()}. Error: " . $invoice_data->get_error_message() );
            return $invoice_data;
        }

        // 2. Check if invoice already exists in Zoho (based on a meta field or mapping).
        $zoho_invoice_id = get_post_meta( $order->get_id(), '_wzi_zoho_invoice_id', true );

        if ( $zoho_invoice_id ) {
            // Update existing invoice.
            $result = $this->zoho_books->update_invoice( $zoho_invoice_id, $invoice_data );
            $action = 'updated';
        } else {
            // Create new invoice.
            $result = $this->zoho_books->create_invoice( $invoice_data );
            $action = 'created';
        }

        // 3. Handle API response.
        if ( is_wp_error( $result ) ) {
            $this->logger->log( "Error {$action} invoice in Zoho Books for Order ID: {$order->get_id()}. Error: " . $result->get_error_message() );
            return $result;
        }

        // Assuming the API returns the Zoho Invoice ID in $result['invoice']['invoice_id'] or similar.
        // This path might need adjustment based on the actual API response structure.
        $new_zoho_invoice_id = isset( $result['invoice']['invoice_id'] ) ? $result['invoice']['invoice_id'] : null;

        if ( ! $new_zoho_invoice_id && $action === 'created' ) {
             $this->logger->log( "Failed to retrieve Zoho Invoice ID after creation for Order ID: {$order->get_id()}." );
             return new WP_Error('zoho_api_error', __('Failed to retrieve Zoho Invoice ID after creation.', 'woocommerce-zoho-integration'));
        }

        if ( $new_zoho_invoice_id ) {
            update_post_meta( $order->get_id(), '_wzi_zoho_invoice_id', $new_zoho_invoice_id );
            if ($action === 'created') {
                update_post_meta( $order->get_id(), '_wzi_zoho_invoice_synced_once', true );
            }
        }

        $this->logger->log( "Invoice successfully {$action} in Zoho Books for Order ID: {$order->get_id()}. Zoho Invoice ID: " . ($new_zoho_invoice_id ? $new_zoho_invoice_id : $zoho_invoice_id) );
        return true;
    }

    /**
     * Prepare invoice data from a WC_Order object for Zoho Books API.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return array|WP_Error Formatted data array or WP_Error on failure.
     */
    private function prepare_invoice_data_for_zoho( WC_Order $order ) {
        // This is a placeholder. You'll need to map WooCommerce order fields
        // to Zoho Books invoice fields according to Zoho's API documentation.
        // This includes customer details, line items, taxes, shipping, etc.

        $customer_id = $order->get_customer_id();
        $zoho_customer_id = null;
        if ( $customer_id ) {
            // Предполагается, что ID клиента Zoho хранится в метаполях пользователя
            $zoho_customer_id = get_user_meta( $customer_id, '_wzi_zoho_crm_contact_id', true );
            if ( ! $zoho_customer_id ) {
                 // Or, if you sync customers to Zoho Books directly, use that ID.
                 $zoho_customer_id = get_user_meta( $customer_id, '_wzi_zoho_books_contact_id', true );
            }
        }

        if ( ! $zoho_customer_id ) {
            // Option: Create customer in Zoho Books first, or link by email, or require manual linking.
            // For now, returning an error if customer is not synced.
            return new WP_Error( 'zoho_customer_missing', __( 'Zoho customer ID not found for this order. Sync customer first.', 'woocommerce-zoho-integration' ) );
        }

        $line_items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $zoho_item_id = $product ? get_post_meta( $product->get_id(), '_wzi_zoho_inventory_item_id', true ) : null;
            // Or, if you use Zoho Books items directly:
            // $zoho_item_id = $product ? get_post_meta( $product->get_id(), '_wzi_zoho_books_item_id', true ) : null;

            if (!$zoho_item_id && $product && $product->is_type('variation')) {
                $zoho_item_id = get_post_meta( $product->get_parent_id(), '_wzi_zoho_inventory_item_id', true );
            }


            $line_items[] = array(
                // 'item_id' => $zoho_item_id, // Use this if products are synced as items in Zoho Books/Inventory
                'name'        => $item->get_name(),
                'description' => $item->get_name(), // Or more detailed description
                'rate'        => $order->get_item_subtotal( $item, false, false ), // Price per unit without tax
                'quantity'    => $item->get_quantity(),
                'item_total'  => $order->get_line_subtotal( $item, false, false ), // Total for line item without tax
                // Add tax details if applicable and configured
            );
        }

        // Shipping
        if ( $order->get_shipping_total() > 0 ) {
            $line_items[] = array(
                'name'        => __( 'Shipping', 'woocommerce-zoho-integration' ),
                'description' => $order->get_shipping_method(),
                'rate'        => $order->get_shipping_total(),
                'quantity'    => 1,
            );
        }

        // Fees
        foreach ( $order->get_fees() as $fee_id => $fee ) {
            $line_items[] = array(
                'name'        => $fee->name,
                'description' => $fee->name,
                'rate'        => $fee->amount,
                'quantity'    => 1,
            );
        }


        $invoice_data = array(
            'customer_id'    => $zoho_customer_id,
            'invoice_number' => $order->get_order_number(), // Or use Zoho's auto-generated number
            'date'           => $order->get_date_created()->date( 'Y-m-d' ),
            'due_date'       => $order->get_date_paid() ? $order->get_date_paid()->date( 'Y-m-d' ) : $order->get_date_created()->date( 'Y-m-d' ), // Example due date
            'line_items'     => $line_items,
            'notes'          => $order->get_customer_note(),
            'terms'          => '', // Optional: invoice terms
            'currency_code'  => $order->get_currency(),
            // Add discount, tax, shipping details as per Zoho API requirements
        );

        // Handle taxes - this can be complex depending on WC tax setup and Zoho requirements
        // $invoice_data['taxes'] = ... ;

        // Handle discounts
        if ( $order->get_discount_total() > 0 ) {
            // Zoho Books might handle discounts at line item level or invoice level.
            // This is a placeholder for invoice level discount.
            // $invoice_data['discount'] = $order->get_discount_total();
            // $invoice_data['discount_type'] = 'entity_level'; // or 'item_level'
        }

        return $invoice_data;
    }

    /**
     * Handle a webhook from Zoho Books for an invoice update.
     * (Placeholder - needs to be implemented if using webhooks from Zoho Books)
     *
     * @param array $webhook_data Data received from Zoho webhook.
     */
    public function handle_zoho_invoice_webhook( $webhook_data ) {
        $this->logger->log( "Received Zoho Books invoice webhook: " . wp_json_encode( $webhook_data ) );
        // TODO: Process the webhook data.
        // Find the corresponding WooCommerce order.
        // Update order status or details if necessary.
        // Be careful to avoid sync loops.
    }
}
