<?php
/**
 * Sync Notification Email Template.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce-zoho-integration/emails/sync-notification.php.
 *
 * HOWEVER, on occasion WooCommerce Zoho Integration will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     PLUGIN_URL_OR_DOC_LINK
 * @package WooCommerceZohoIntegration/Templates/Emails
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
    <?php
    // Example content, customize as needed.
    // You would pass variables like $sync_type, $status, $message, $item_count etc. to this template.

    // Default message if not provided
    $default_message = __( 'A synchronization process has completed.', 'woocommerce-zoho-integration');
    $email_message = isset( $message ) ? $message : $default_message;

    echo wp_kses_post( $email_message );
    ?>
</p>

<?php if ( isset( $sync_details ) && is_array( $sync_details ) && ! empty( $sync_details ) ) : ?>
    <h2><?php esc_html_e( 'Synchronization Details', 'woocommerce-zoho-integration' ); ?></h2>
    <ul>
        <?php foreach ( $sync_details as $key => $value ) : ?>
            <li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ( isset( $error_log_summary ) && ! empty( $error_log_summary ) ) : ?>
    <h3><?php esc_html_e( 'Error Summary (if any)', 'woocommerce-zoho-integration' ); ?></h3>
    <p><?php echo wp_kses_post( $error_log_summary ); ?></p>
<?php endif; ?>

<p>
    <?php esc_html_e( 'This is an automated notification from your WooCommerce store.', 'woocommerce-zoho-integration' ); ?>
</p>

<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
