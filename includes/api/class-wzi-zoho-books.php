<?php
/**
 * Zoho Books API Integration for WooCommerce Zoho Integration.
 *
 * @package WooCommerceZohoIntegration/Includes/API
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WZI_Zoho_Books.
 *
 * Handles all interactions with the Zoho Books API.
 */
class WZI_Zoho_Books extends WZI_API_Handler {

    /**
     * Organization ID for Zoho Books.
     *
     * @var string
     */
    private $organization_id;

    /**
     * WZI_Zoho_Books constructor.
     */
    public function __construct() {
        parent::__construct('books');
        $this->organization_id = get_option('wzi_books_organization_id');
        if (empty($this->organization_id)) {
            // Attempt to fetch only if API seems connected to avoid errors during initial setup
            if ($this->auth && $this->auth->is_connected('books')) {
                 $this->fetch_organization_id();
            }
        }
    }

    /**
     * Fetch and store the Zoho Books Organization ID.
     *
     * @since 1.0.0
     * @access private
     * @return string|false The organization ID or false on failure.
     */
    private function fetch_organization_id() {
        $response = parent::get('organizations'); // Use parent::get to avoid issues if org_id is not yet set for get_params_with_org_id

        if (is_wp_error($response) || !isset($response['data']['organizations']) || empty($response['data']['organizations'])) {
            $this->logger->error('Failed to fetch Zoho Books organizations or no organizations found.', array(
                'response' => $response,
            ));
            return false;
        }

        // Use the first organization by default
        $this->organization_id = $response['data']['organizations'][0]['organization_id'];
        update_option('wzi_books_organization_id', $this->organization_id);

        $this->logger->info('Zoho Books Organization ID fetched and saved.', array(
            'organization_id' => $this->organization_id,
        ));
        return $this->organization_id;
    }

    /**
     * Helper to add organization_id to params.
     * Zoho Books API often requires organization_id as a query parameter.
     *
     * @param array $params Existing parameters.
     * @return array Parameters with organization_id.
     * @throws Exception If organization_id is not set and is required.
     */
    private function get_params_with_org_id(array $params = []): array {
        if (empty($this->organization_id)) {
            // Try to fetch it if not available
            $this->fetch_organization_id();
        }

        if (empty($this->organization_id)) {
            // If still empty, this is a critical issue for most Books API calls.
            // You might throw an exception or return an error that can be caught by the calling code.
            // For methods called internally by sync processes, an exception might be appropriate.
            // For methods called from admin (like test_connection), returning WP_Error might be better.
            // For now, we'll let it proceed, and individual API calls might fail.
            // A better approach is to make organization_id strictly required.
            $this->logger->warning('Zoho Books Organization ID is not set. API calls may fail.');
        } else {
            $params['organization_id'] = $this->organization_id;
        }
        return $params;
    }

    /**
     * Test connection to Zoho Books.
     *
     * @return bool True if connection is successful, false otherwise.
     */
    public function test_connection() {
        // Example: Fetch organizations or a simple endpoint
        $response = $this->get('organizations', $this->get_params_with_org_id());
        return !is_wp_error($response) && isset($response['data']['organizations']);
    }

    /**
     * Create an invoice in Zoho Books.
     *
     * @param array $invoice_data Data for the invoice.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function create_invoice( $invoice_data ) {
        if (empty($this->organization_id)) {
            $this->fetch_organization_id(); // Intenta obtenerlo si aún no está seteado
            if (empty($this->organization_id)) {
                return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured.', 'woocommerce-zoho-integration'));
            }
        }

        // La API de Zoho Books espera los datos dentro de un JSONString para POST/PUT a muchos endpoints.
        // Y también el organization_id como parámetro de query.
        $params = $this->get_params_with_org_id(); // Solo contendrá organization_id

        // El endpoint es 'invoices'. Los parámetros de query van en la URL.
        // El cuerpo de la solicitud es el JSONString.
        $endpoint = 'invoices';
        if (!empty($params)) {
            $endpoint = add_query_arg($params, $endpoint);
        }

        $response = $this->post($endpoint, $invoice_data); // WZI_API_Handler::post ya hace json_encode del body.

        if (is_wp_error($response)) {
            return $response;
        }

        // Verificar la estructura de la respuesta exitosa de Zoho Books para facturas
        // Normalmente, devuelve un objeto 'invoice'.
        if (isset($response['data']['invoice'])) {
            $this->logger->info('Invoice created successfully in Zoho Books.', array(
                'invoice_id' => $response['data']['invoice']['invoice_id'],
                'invoice_number' => $response['data']['invoice']['invoice_number']
            ));
            return $response['data']['invoice'];
        } elseif (isset($response['data']['message']) && strpos($response['data']['message'], 'success') !== false) {
            // Algunas APIs de Zoho Books devuelven un mensaje de éxito genérico y no el objeto completo.
            // En ese caso, podríamos necesitar hacer un GET para obtener el objeto completo si es necesario.
            // O simplemente devolver el ID si está en la respuesta.
             $this->logger->info('Invoice creation reported success by Zoho Books, but full invoice data not in response.', $response['data']);
            // Tratar de extraer un ID si es posible, o marcar para una re-sincronización/verificación.
            return $response['data']; // Devolver la respuesta cruda si no se puede parsear el objeto factura.
        }

        $this->logger->error('Failed to create invoice in Zoho Books or unexpected response structure.', array(
            'response' => $response['data']
        ));
        return new WP_Error('invoice_creation_failed', __('Failed to create invoice in Zoho Books or unexpected response structure.', 'woocommerce-zoho-integration'), $response['data']);
    }

    /**
     * Get an invoice from Zoho Books.
     *
     * @param string $invoice_id The ID of the invoice to retrieve.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function get_invoice( $invoice_id ) {
        if (empty($invoice_id)) {
            return new WP_Error('missing_invoice_id', __('Invoice ID is required.', 'woocommerce-zoho-integration'));
        }

        if (empty($this->organization_id)) {
            $this->fetch_organization_id();
            if (empty($this->organization_id)) {
                return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured.', 'woocommerce-zoho-integration'));
            }
        }

        $endpoint = sprintf('invoices/%s', $invoice_id);
        $params = $this->get_params_with_org_id(); // Asegura que organization_id esté en los params si es necesario para GET

        $response = $this->get($endpoint, $params);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['data']['invoice'])) {
            $this->logger->info('Invoice retrieved successfully from Zoho Books.', array(
                'invoice_id' => $invoice_id
            ));
            return $response['data']['invoice'];
        }

        $this->logger->error('Failed to retrieve invoice from Zoho Books or unexpected response structure.', array(
            'invoice_id' => $invoice_id,
            'response' => $response['data'] ?? $response
        ));
        return new WP_Error('get_invoice_failed', __('Failed to retrieve invoice from Zoho Books or unexpected response structure.', 'woocommerce-zoho-integration'), $response['data'] ?? $response);
    }

    /**
     * Update an invoice in Zoho Books.
     *
     * @param string $invoice_id The ID of the invoice to update.
     * @param array $invoice_data Data to update the invoice.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function update_invoice( $invoice_id, $invoice_data ) {
        // Example: $response = $this->put("invoices/{$invoice_id}", $this->get_params_with_org_id(['JSONString' => json_encode($invoice_data)]));
        return new WP_Error( 'not_implemented', __( 'Update invoice functionality is not yet implemented.', 'woocommerce-zoho-integration' ) );
    }

    /**
     * Delete an invoice from Zoho Books.
     *
     * @param string $invoice_id The ID of the invoice to delete.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function delete_invoice( $invoice_id ) {
        // Example: $response = $this->delete("invoices/{$invoice_id}", $this->get_params_with_org_id());
        return new WP_Error( 'not_implemented', __( 'Delete invoice functionality is not yet implemented.', 'woocommerce-zoho-integration' ) );
    }

    /**
     * Create a payment in Zoho Books.
     *
     * @param array $payment_data Data for the payment.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function create_payment( $payment_data ) {
        // Example: $response = $this->post('customerpayments', $this->get_params_with_org_id(['JSONString' => json_encode($payment_data)]));
        return new WP_Error( 'not_implemented', __( 'Create payment functionality is not yet implemented.', 'woocommerce-zoho-integration' ) );
    }

    // Add more methods as needed for Zoho Books integration (e.g., customers, items, etc.)
}
