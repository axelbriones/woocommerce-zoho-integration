<?php
/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for actions and filters.
 * Also Defines all hooks for the public side of the site.
 *
 * @link       YOUR_PLUGIN_URL
 * @since      1.0.0
 *
 * @package    WooCommerceZohoIntegration
 * @subpackage WooCommerceZohoIntegration/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * @package    WooCommerceZohoIntegration
 * @subpackage WooCommerceZohoIntegration/public
 * @author     YOUR NAME <your-email@example.com>
 */
class WZI_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WZI_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The WZI_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wzi-public.css', array(), $this->version, 'all' );

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in WZI_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The WZI_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wzi-public.js', array( 'jquery' ), $this->version, false );

    }

    /**
     * Example of a public-facing action hook.
     *
     * This function could be hooked into an action like 'wp_head' or 'wp_footer'.
     * For example, you might use this to add tracking scripts or other elements.
     *
     * @since 1.0.0
     */
    public function public_action_example() {
        // echo "<p>WooCommerce Zoho Integration Public Action Hook Example Output!</p>";
    }

    /**
     * Example of a public-facing filter hook.
     *
     * This function could be hooked into a filter like 'the_content' or 'body_class'.
     * For example, you might use this to modify content or add CSS classes.
     *
     * @since 1.0.0
     * @param string $content The content being filtered.
     * @return string The modified content.
     */
    public function public_filter_example( $content ) {
        // $modified_content = $content . "<p>Content modified by WooCommerce Zoho Integration Public Filter!</p>";
        // return $modified_content;
        return $content;
    }

    // Add any public-facing methods here. For example:
    // - Modifying product display to show Zoho stock levels (if applicable and configured)
    // - Adding tracking scripts for Zoho SalesIQ or Zoho Campaigns
    // - Displaying customer-specific information from Zoho on their account page

    /**
     * Example: Display custom field from Zoho on product page.
     * This is a conceptual example. Actual implementation would depend on
     * how and where you store Zoho data related to products.
     */
    public function display_zoho_product_info() {
        global $product;
        if ( is_product() && $product instanceof WC_Product ) {
            // Assume you have synced some data from Zoho to product meta
            // $zoho_custom_field = get_post_meta( $product->get_id(), '_wzi_zoho_product_custom_field', true );
            // if ( ! empty( $zoho_custom_field ) ) {
            //     echo '<div class="wzi-zoho-product-info">';
            //     echo '<strong>' . esc_html__( 'Zoho Info:', 'woocommerce-zoho-integration' ) . '</strong> ' . esc_html( $zoho_custom_field );
            //     echo '</div>';
            // }
        }
    }
}
