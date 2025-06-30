<?php
/**
 * Zoho Campaigns API Integration for WooCommerce Zoho Integration.
 *
 * @package WooCommerceZohoIntegration/Includes/API
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WZI_Zoho_Campaigns.
 *
 * Handles all interactions with the Zoho Campaigns API.
 */
class WZI_Zoho_Campaigns extends WZI_API_Handler {

    /**
     * WZI_Zoho_Campaigns constructor.
     */
    public function __construct() {
        parent::__construct('campaigns');
    }

    /**
     * Test connection to Zoho Campaigns.
     *
     * @return bool True if connection is successful, false otherwise.
     */
    public function test_connection() {
        // Example: Fetch account details or a simple list endpoint
        $response = $this->get('lists'); // Zoho Campaigns often uses 'lists' or similar as a base check
        return !is_wp_error($response) && isset($response['data']); // Adjust based on actual response
    }

    /**
     * Add a contact to a mailing list in Zoho Campaigns.
     *
     * @param string $list_key The key of the mailing list.
     * @param array $contact_data Data for the contact.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function add_contact_to_list( $list_key, $contact_data ) {
        if (empty($list_key)) {
            return new WP_Error('missing_list_key', __('List key is required for Zoho Campaigns.', 'woocommerce-zoho-integration'));
        }
        if (empty($contact_data) || !is_array($contact_data)) {
            return new WP_Error('missing_contact_data', __('Contact data is required and must be an array.', 'woocommerce-zoho-integration'));
        }

        // Zoho Campaigns API for list subscription often looks like this:
        // Method: POST
        // Endpoint: /json/listsubscribe (o similar, relativo a la URL base del servicio de Campaigns)
        // It might require parameters in the query string AND/OR as form-data in the body.
        // Let's assume it takes form-data for this example.

        $endpoint = 'json/listsubscribe'; // Este es un endpoint común para Campaigns.
                                          // La URL base ya está en $this->api_base_url (ej. https://campaigns.zoho.com/api/v1.1)
                                          // Así que la URL completa sería $this->api_base_url . '/' . $endpoint

        // Datos a enviar como form-data.
        // El token de autenticación (oauthtoken) es añadido por WZI_API_Handler.
        $payload = array(
            'resfmt' => 'JSON', // O XML, según prefieras manejar la respuesta.
            'listkey' => $list_key,
            // Los datos del contacto a menudo se envían como un string JSON bajo una clave específica.
            // La clave común es 'contactinfo' o a veces 'xmlData' si se enviara XML.
            'contactinfo' => json_encode($contact_data)
        );

        // WZI_API_Handler::post() por defecto envía el payload como un cuerpo JSON.
        // Para Zoho Campaigns, a menudo se necesita enviar como 'application/x-www-form-urlencoded'.
        // Necesitamos pasar headers personalizados y asegurar que el cuerpo esté formateado como form data.

        $custom_headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded',
        );

        // El método make_request en WZI_API_Handler necesita ser capaz de manejar un body que no sea JSON
        // o necesitamos una forma de pasar el body ya formateado.
        // Por ahora, asumiremos que make_request puede ser adaptado o que esta API específica
        // podría aceptar JSON si Content-Type es application/json.
        // Si make_request solo hace json_encode($data), entonces el payload anterior no funcionará como form-data.

        // Opción A: Si la API de Campaigns (inesperadamente) aceptara un cuerpo JSON para este endpoint:
        // $response = $this->post($endpoint, $payload);
        // Esto enviaría: {"resfmt":"JSON", "listkey":"...", "contactinfo":"{...}"} como cuerpo JSON.

        // Opción B: La forma más común para Campaigns es enviar como form-data.
        // Esto requiere que $this->post o $this->make_request puedan manejar esto.
        // Si $this->make_request siempre hace json_encode($data) para POST, esto no funcionará directamente.
        // Se necesitaría una modificación en WZI_API_Handler o un método específico aquí.

        // Asumamos para este ejemplo que necesitamos construir la query string para el cuerpo
        // y que WZI_API_Handler puede tomar un string como cuerpo si Content-Type es x-www-form-urlencoded.
        // Esta es una simplificación. Una implementación robusta requeriría:
        // 1. Que WZI_API_Handler::make_request no haga json_encode si el body ya es un string.
        // 2. O un nuevo método en WZI_API_Handler para peticiones form-urlencoded.

        $response = $this->make_request(
            $endpoint,
            'POST',
            http_build_query($payload), // Convierte el array a una cadena de form data
            $custom_headers
        );


        if (is_wp_error($response)) {
            return $response;
        }

        // La respuesta de Zoho Campaigns para la suscripción también puede ser particular.
        // A menudo es un JSON con un código de estado y un mensaje.
        if (isset($response['data']['status']) && $response['data']['status'] === 'success') {
            $this->logger->info('Contact added to Zoho Campaigns list successfully.', array(
                'list_key' => $list_key,
                'contact_email' => $contact_data['CONTACT_EMAIL'] ?? 'N/A', // Asumiendo que CONTACT_EMAIL es una clave
                'response' => $response['data']
            ));
            return $response['data'];
        } elseif(isset($response['data']['message'])) { // A veces el éxito solo viene con un mensaje
             $this->logger->info('Zoho Campaigns list subscription response.', array(
                'list_key' => $list_key,
                'response_message' => $response['data']['message']
            ));
            return $response['data'];
        }

        $error_message = isset($response['data']['message']) ? $response['data']['message'] : __('Unknown error subscribing contact to Zoho Campaigns list.', 'woocommerce-zoho-integration');
        $this->logger->error('Failed to add contact to Zoho Campaigns list.', array(
            'list_key' => $list_key,
            'response' => $response['data'] ?? $response
        ));
        return new WP_Error('campaigns_subscription_failed', $error_message, $response['data'] ?? $response);
    }

    /**
     * Get campaign details from Zoho Campaigns.
     *
     * @param string $campaign_key The key of the campaign.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function get_campaign_details( $campaign_key ) {
        // Example: $response = $this->get("campaigns/{$campaign_key}");
        return new WP_Error( 'not_implemented', __( 'Get campaign details functionality is not yet implemented.', 'woocommerce-zoho-integration' ) );
    }

    /**
     * Create a campaign (related to coupons) in Zoho Campaigns.
     *
     * @param array $campaign_data Data for the campaign.
     * @return array|WP_Error API response or WP_Error on failure.
     */
    public function create_coupon_campaign( $campaign_data ) {
        // Example: $response = $this->post('campaigns', $campaign_data);
        return new WP_Error( 'not_implemented', __( 'Create coupon campaign functionality is not yet implemented.', 'woocommerce-zoho-integration' ) );
    }

    // Add more methods as needed for Zoho Campaigns integration (e.g., managing lists, segments, etc.)
}
