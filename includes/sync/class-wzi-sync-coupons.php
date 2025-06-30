<?php
/**
 * Handles WooCommerce Coupon Synchronization with Zoho Campaigns.
 *
 * @package WooCommerceZohoIntegration/Includes/Sync
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WZI_Sync_Coupons.
 */
class WZI_Sync_Coupons {

    /**
     * Zoho Campaigns API instance.
     *
     * @var WZI_Zoho_Campaigns
     */
    private $zoho_campaigns;

    /**
     * Logger instance.
     *
     * @var WZI_Logger
     */
    private $logger;

    /**
     * WZI_Sync_Coupons constructor.
     */
    public function __construct() {
        $this->zoho_campaigns = new WZI_Zoho_Campaigns();
        $this->logger         = new WZI_Logger();
    }

    /**
     * Sync a WooCommerce coupon to Zoho Campaigns.
     *
     * This could be creating a corresponding campaign or promo code in Zoho Campaigns.
     * The exact mechanism depends on how Zoho Campaigns is used for coupons.
     *
     * @param int|WC_Coupon $coupon WooCommerce Coupon ID or object.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function sync_coupon_to_zoho( $coupon ) {
        if ( ! $coupon instanceof WC_Coupon ) {
            $coupon_id = $coupon;
            $coupon = new WC_Coupon( $coupon_id );
        }

        if ( ! $coupon->get_id() ) {
            return new WP_Error( 'invalid_coupon', __( 'Invalid coupon for sync.', 'woocommerce-zoho-integration' ) );
        }

        $this->logger->log( "Starting coupon sync for Coupon ID: {$coupon->get_id()} (Code: {$coupon->get_code()})" );

        // 1. Get coupon data and format it for Zoho Campaigns API.
        $campaign_data = $this->prepare_campaign_data_for_zoho( $coupon );
        if ( is_wp_error( $campaign_data ) ) {
            $this->logger->log( "Error preparing campaign data for Coupon ID: {$coupon->get_id()}. Error: " . $campaign_data->get_error_message() );
            return $campaign_data;
        }

        // 2. Check if a corresponding campaign/entity already exists in Zoho.
        // This might involve storing a Zoho campaign ID in coupon meta.
        $zoho_campaign_id = $coupon->get_meta( '_wzi_zoho_campaign_id', true );

        if ( $zoho_campaign_id ) {
            // Potentially update existing campaign in Zoho (if supported/needed).
            // For now, we might assume coupons are created once.
            // $result = $this->zoho_campaigns->update_coupon_campaign( $zoho_campaign_id, $campaign_data );
            // $action = 'updated';
            $this->logger->log( "Coupon ID: {$coupon->get_id()} already has a Zoho Campaign ID: {$zoho_campaign_id}. Update logic not implemented yet." );
            // For simplicity, let's assume no update action for now or that create_coupon_campaign handles it.
            // If create_coupon_campaign is idempotent or you want to recreate/update, adjust logic here.
            $result = $this->zoho_campaigns->create_coupon_campaign( $campaign_data );
            $action = 'recreated/updated'; // Or some other appropriate action
        } else {
            // Create new campaign/entity in Zoho Campaigns.
            $result = $this->zoho_campaigns->create_coupon_campaign( $campaign_data );
            $action = 'created';
        }

        // 3. Handle API response.
        if ( is_wp_error( $result ) ) {
            $this->logger->log( "Error {$action} coupon campaign in Zoho Campaigns for Coupon ID: {$coupon->get_id()}. Error: " . $result->get_error_message() );
            return $result;
        }

        // Assuming the API returns the Zoho Campaign ID in $result['campaign_id'] or similar.
        // This path might need adjustment based on the actual API response structure from create_coupon_campaign.
        $new_zoho_campaign_id = isset( $result['campaign_id'] ) ? $result['campaign_id'] : (isset($result['data']['campaignkey']) ? $result['data']['campaignkey'] : null);


        if ( ! $new_zoho_campaign_id && $action === 'created' ) {
             $this->logger->log( "Failed to retrieve Zoho Campaign ID after creation for Coupon ID: {$coupon->get_id()}." );
             // return new WP_Error('zoho_api_error', __('Failed to retrieve Zoho Campaign ID after creation.', 'woocommerce-zoho-integration'));
        }


        if ( $new_zoho_campaign_id ) {
            $coupon->update_meta_data( '_wzi_zoho_campaign_id', $new_zoho_campaign_id );
            $coupon->save_meta_data();
             if ($action === 'created') {
                $coupon->update_meta_data( '_wzi_zoho_coupon_synced_once', true );
                $coupon->save_meta_data();
            }
        }

        $this->logger->log( "Coupon campaign successfully {$action} in Zoho Campaigns for Coupon ID: {$coupon->get_id()}. Zoho Campaign ID: " . ($new_zoho_campaign_id ? $new_zoho_campaign_id : $zoho_campaign_id) );
        return true;
    }

    /**
     * Prepare campaign data from a WC_Coupon object for Zoho Campaigns API.
     *
     * @param WC_Coupon $coupon The WooCommerce coupon object.
     * @return array|WP_Error Formatted data array or WP_Error on failure.
     */
    private function prepare_campaign_data_for_zoho( WC_Coupon $coupon ) {
        // This is a placeholder. The structure will depend heavily on how you intend
        // to represent/use coupons within Zoho Campaigns.
        // It might involve creating a specific type of campaign, a merge tag for coupon codes, etc.

        $campaign_data = array(
            'campaign_name' => sprintf( __( 'WooCommerce Coupon: %s', 'woocommerce-zoho-integration' ), $coupon->get_code() ),
            'coupon_code'   => $coupon->get_code(),
            'description'   => $coupon->get_description(),
            'discount_type' => $coupon->get_discount_type(), // 'percent', 'fixed_cart', 'fixed_product'
            'amount'        => $coupon->get_amount(),
            'date_expires'  => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d H:i:s' ) : null,
            'usage_limit'   => $coupon->get_usage_limit(),
            // Add any other relevant coupon details that Zoho Campaigns can store or use.
        );

        // Example: If Zoho Campaigns has a concept of "promo codes" within a broader campaign:
        // $campaign_data = array(
        // 'target_campaign_key' => 'some_existing_zoho_campaign_key', // If linking to an existing campaign
        // 'promo_details' => array(
        // 'code' => $coupon->get_code(),
        // 'discount_value' => $coupon->get_amount(),
        // ...
        // )
        // );

        if (empty($campaign_data['campaign_name'])) {
            // Fallback if coupon code is somehow empty, though WC usually prevents this.
            return new WP_Error('missing_coupon_code', __('Coupon code is missing.', 'woocommerce-zoho-integration'));
        }

        return $campaign_data;
    }

    /**
     * Handle a webhook from Zoho Campaigns related to coupons/campaigns.
     * (Placeholder - needs to be implemented if using webhooks from Zoho Campaigns)
     *
     * @param array $webhook_data Data received from Zoho webhook.
     */
    public function handle_zoho_coupon_webhook( $webhook_data ) {
        $this->logger->log( "Received Zoho Campaigns coupon/campaign webhook: " . wp_json_encode( $webhook_data ) );
        // TODO: Process the webhook data.
        // Find the corresponding WooCommerce coupon.
        // Update coupon details or status if necessary.
        // Be careful to avoid sync loops.
    }
}
