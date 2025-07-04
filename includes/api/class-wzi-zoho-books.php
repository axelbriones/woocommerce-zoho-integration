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
     * @since    1.0.0
     * @access   private
     * @var      string
     */
    private $organization_id;

    /**
     * WZI_Zoho_Books constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        parent::__construct('books');
        $this->organization_id = get_option('wzi_books_organization_id');
        if (empty($this->organization_id)) {
            if (isset($this->auth) && $this->auth instanceof WZI_Zoho_Auth && $this->auth->is_connected('books')) {
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
        $response = parent::get('organizations');

        if (is_wp_error($response) || !isset($response['data']['organizations']) || empty($response['data']['organizations'])) {
            if(isset($this->logger) && $this->logger instanceof WZI_Logger) {
                $this->logger->error('Failed to fetch Zoho Books organizations or no organizations found.', array(
                    'response' => $response,
                ));
            }
            return false;
        }

        $this->organization_id = $response['data']['organizations'][0]['organization_id'];
        update_option('wzi_books_organization_id', $this->organization_id);

        if(isset($this->logger) && $this->logger instanceof WZI_Logger) {
            $this->logger->info('Zoho Books Organization ID fetched and saved.', array(
                'organization_id' => $this->organization_id,
            ));
        }
        return $this->organization_id;
    }

    /**
     * Helper to add organization_id to params.
     * Zoho Books API often requires organization_id as a query parameter.
     *
     * @since 1.0.0
     * @access private
     * @param array $params Existing parameters.
     * @return array Parameters with organization_id.
     */
    private function get_params_with_org_id(array $params = []): array {
        if (empty($this->organization_id)) {
            if (isset($this->auth) && $this->auth instanceof WZI_Zoho_Auth && $this->auth->is_connected('books')) {
                $this->fetch_organization_id();
            }
        }

        if (empty($this->organization_id)) {
            if(isset($this->logger) && $this->logger instanceof WZI_Logger) {
                $this->logger->warning('Zoho Books Organization ID is not set. API calls may fail.');
            }
        } else {
            $params['organization_id'] = $this->organization_id;
        }
        return $params;
    }

    /**
     * Test connection to Zoho Books.
     *
     * @since 1.0.0
     * @return bool True if connection is successful, false otherwise.
     */
    public function test_connection() {
        $response = parent::get('organizations');
        if (is_wp_error($response)) {
            return false;
        }
        if (isset($response['data']['organizations']) && !empty($response['data']['organizations'])) {
            if(empty($this->organization_id) && isset($response['data']['organizations'][0]['organization_id'])) {
                $this->organization_id = $response['data']['organizations'][0]['organization_id'];
                update_option('wzi_books_organization_id', $this->organization_id);
            }
            return true;
        }
        return false;
    }

    /**
     * Create an invoice in Zoho Books.
     *
     * @since 1.0.0
     * @param array $invoice_data Data for the invoice.
     * @return array|WP_Error API response (invoice object) or WP_Error on failure.
     */
    public function create_invoice( $invoice_data ) {
        if (empty($this->organization_id)) {
            if (!$this->fetch_organization_id()) {
                 return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured or could not be fetched.', 'woocommerce-zoho-integration'));
            }
        }

        $endpoint = 'invoices';
        $params = $this->get_params_with_org_id();
        $full_endpoint = add_query_arg($params, $endpoint);

        $response = $this->post($full_endpoint, $invoice_data);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['data']['invoice'])) {
            if(isset($this->logger)) $this->logger->info('Invoice created successfully in Zoho Books.', array(
                'invoice_id' => $response['data']['invoice']['invoice_id'],
                'invoice_number' => $response['data']['invoice']['invoice_number']
            ));
            return $response['data']['invoice'];
        } elseif (isset($response['data']['message']) && stripos($response['data']['message'], 'success') !== false) {
             if(isset($this->logger)) $this->logger->info('Invoice creation reported success by Zoho Books, but full invoice data not in response.', $response['data']);
            return $response['data'];
        }

        if(isset($this->logger)) $this->logger->error('Failed to create invoice in Zoho Books or unexpected response structure.', array(
            'response' => $response['data'] ?? $response
        ));
        return new WP_Error('invoice_creation_failed', __('Failed to create invoice in Zoho Books or unexpected response structure.', 'woocommerce-zoho-integration'), $response['data'] ?? $response);
    }

    /**
     * Get an invoice from Zoho Books.
     *
     * @since 1.0.0
     * @param string $invoice_id The ID of the invoice to retrieve.
     * @return array|WP_Error API response (invoice object) or WP_Error on failure.
     */
    public function get_invoice( $invoice_id ) {
        if (empty($invoice_id)) {
            return new WP_Error('missing_invoice_id', __('Invoice ID is required.', 'woocommerce-zoho-integration'));
        }
        if (empty($this->organization_id) && !$this->fetch_organization_id()) {
            return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured or could not be fetched.', 'woocommerce-zoho-integration'));
        }

        $endpoint = sprintf('invoices/%s', $invoice_id);
        $params = $this->get_params_with_org_id();

        $response = $this->get($endpoint, $params);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['data']['invoice'])) {
            if(isset($this->logger)) $this->logger->info('Invoice retrieved successfully from Zoho Books.', array('invoice_id' => $invoice_id));
            return $response['data']['invoice'];
        }

        if(isset($this->logger)) $this->logger->error('Failed to retrieve invoice from Zoho Books or unexpected response structure.', array(
            'invoice_id' => $invoice_id, 'response' => $response['data'] ?? $response
        ));
        return new WP_Error('get_invoice_failed', __('Failed to retrieve invoice from Zoho Books or unexpected response structure.', 'woocommerce-zoho-integration'), $response['data'] ?? $response);
    }

    /**
     * Update an invoice in Zoho Books.
     *
     * @since 1.0.0
     * @param string $invoice_id The ID of the invoice to update.
     * @param array $invoice_data Data to update the invoice.
     * @return array|WP_Error API response (invoice object) or WP_Error on failure.
     */
    public function update_invoice( $invoice_id, $invoice_data ) {
        if (empty($invoice_id)) {
            return new WP_Error('missing_invoice_id', __('Invoice ID is required for updating.', 'woocommerce-zoho-integration'));
        }
        if (empty($invoice_data) || !is_array($invoice_data)) {
            return new WP_Error('missing_invoice_data', __('Invoice data is required and must be an array for updating.', 'woocommerce-zoho-integration'));
        }
        if (empty($this->organization_id) && !$this->fetch_organization_id()) {
            return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured or could not be fetched.', 'woocommerce-zoho-integration'));
        }

        $endpoint = sprintf('invoices/%s', $invoice_id);
        $params = $this->get_params_with_org_id();

        $response = $this->put(add_query_arg($params, $endpoint), $invoice_data);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['data']['invoice'])) {
            if(isset($this->logger)) $this->logger->info('Invoice updated successfully in Zoho Books.', array(
                'invoice_id' => $response['data']['invoice']['invoice_id']
            ));
            return $response['data']['invoice'];
        } elseif (isset($response['data']['message']) && stripos($response['data']['message'], 'success') !== false) {
            if(isset($this->logger)) $this->logger->info('Invoice update reported success by Zoho Books, but full invoice data not in response.', $response['data']);
            return $response['data'];
        }

        if(isset($this->logger)) $this->logger->error('Failed to update invoice in Zoho Books or unexpected response structure.', array(
            'invoice_id' => $invoice_id, 'response' => $response['data'] ?? $response
        ));
        return new WP_Error('invoice_update_failed', __('Failed to update invoice in Zoho Books or unexpected response structure.', 'woocommerce-zoho-integration'), $response['data'] ?? $response);
    }

    /**
     * Delete an invoice from Zoho Books.
     *
     * @since 1.0.0
     * @param string $invoice_id The ID of the invoice to delete.
     * @return bool|WP_Error True on success, or WP_Error on failure.
     */
    public function delete_invoice( $invoice_id ) {
        if (empty($invoice_id)) {
            return new WP_Error('missing_invoice_id', __('Invoice ID is required for deletion.', 'woocommerce-zoho-integration'));
        }
         if (empty($this->organization_id) && !$this->fetch_organization_id()) {
            return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured or could not be fetched.', 'woocommerce-zoho-integration'));
        }

        $endpoint = sprintf('invoices/%s', $invoice_id);
        $params = $this->get_params_with_org_id();
        $response = $this->delete(add_query_arg($params, $endpoint));

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['data']['code']) && $response['data']['code'] == 0 && isset($response['data']['message']) && stripos($response['data']['message'], 'deleted') !== false) {
            if(isset($this->logger)) $this->logger->info('Invoice deleted successfully from Zoho Books.', array('invoice_id' => $invoice_id));
            return true;
        }

        if(isset($this->logger)) $this->logger->error('Failed to delete invoice from Zoho Books or unexpected response.', array(
            'invoice_id' => $invoice_id, 'response' => $response['data'] ?? $response
        ));
        return new WP_Error('invoice_delete_failed', __('Failed to delete invoice from Zoho Books.', 'woocommerce-zoho-integration'), $response['data'] ?? $response);
    }

    /**
     * Create a payment in Zoho Books.
     *
     * @since 1.0.0
     * @param array $payment_data Data for the payment. Expected keys: customer_id, amount, date, invoices (array of invoice_id & amount_applied).
     * @return array|WP_Error API response (payment object) or WP_Error on failure.
     */
    public function create_payment( $payment_data ) {
        if (empty($payment_data) || !is_array($payment_data) ||
            !isset($payment_data['customer_id']) ||
            !isset($payment_data['invoices']) || !is_array($payment_data['invoices']) || empty($payment_data['invoices']) ||
            !isset($payment_data['amount']) || !isset($payment_data['date'])) {
            return new WP_Error('missing_payment_data', __('Payment data must be an array and include customer_id, amount, date, and at least one invoice application.', 'woocommerce-zoho-integration'));
        }

        if (empty($this->organization_id) && !$this->fetch_organization_id()) {
            return new WP_Error('missing_organization_id', __('Zoho Books Organization ID is not configured or could not be fetched.', 'woocommerce-zoho-integration'));
        }

        $endpoint = 'customerpayments';
        $params = $this->get_params_with_org_id();
        $full_endpoint = add_query_arg($params, $endpoint);

        // Zoho Books API for customerpayments expects the payload directly as JSON.
        $response = $this->post($full_endpoint, $payment_data);

        if (is_wp_error($response)) {
            return $response;
        }

        // Expected success response: {"code":0,"message":"Payment has been added.","payment":{...}}
        if (isset($response['data']['code']) && $response['data']['code'] == 0 && isset($response['data']['payment'])) {
             if(isset($this->logger)) $this->logger->info('Payment created successfully in Zoho Books.', array(
                'payment_id' => $response['data']['payment']['payment_id'],
                'customer_id' => $payment_data['customer_id']
             ));
            return $response['data']['payment'];
        } elseif (isset($response['data']['message'])) {
            if (stripos($response['data']['message'], 'success') !== false || stripos($response['data']['message'], 'added') !== false) {
                 if(isset($this->logger)) $this->logger->info('Payment creation reported success by Zoho Books, but full payment data might not be in response.', $response['data']);
                return $response['data'];
            } else {
                if(isset($this->logger)) $this->logger->error('Failed to create payment in Zoho Books: API message.', array('response' => $response['data'], 'sent_data' => $payment_data));
                return new WP_Error('payment_creation_api_error', $response['data']['message'], $response['data']);
            }
        }

        if(isset($this->logger)) $this->logger->error('Failed to create payment in Zoho Books or unexpected response structure.', array(
            'response' => $response['data'] ?? $response,
            'sent_data' => $payment_data
        ));
        return new WP_Error('payment_creation_failed', __('Failed to create payment in Zoho Books or unexpected response structure.', 'woocommerce-zoho-integration'), $response['data'] ?? $response);
    }

    /**
     * Get available fields for a Zoho Books module (e.g., invoices, customers).
     *
     * NOTE: Zoho Books API for field metadata might be different from CRM.
     * This is a conceptual placeholder and needs verification against Books API docs.
     * Often, for Finance APIs, fields are derived from a sample record or settings endpoints.
     *
     * @since 1.0.0
     * @param string $module_name The module name (e.g., 'invoices', 'customers').
     * @return array|WP_Error Array of field definitions or WP_Error.
     */
    public function get_available_module_fields($module_name) {
        $cache_key = 'books_fields_' . $module_name;
        $cached_fields = method_exists($this, 'cache') ? $this->cache($cache_key, null, DAY_IN_SECONDS) : false;

        if ($cached_fields !== false && is_array($cached_fields)) {
            if(isset($this->logger) && $this->logger instanceof WZI_Logger) {
                $this->logger->debug("Fields for Books module {$module_name} loaded from cache.", array('count' => count($cached_fields)));
            }
            return $cached_fields;
        }

        if (empty($this->organization_id) && !$this->fetch_organization_id()) {
            return new WP_Error('missing_organization_id_for_fields', __('Zoho Books Organization ID is required to fetch module fields.', 'woocommerce-zoho-integration'));
        }

        // --- LÓGICA HIPOTÉTICA PARA OBTENER CAMPOS ---
        // La API de Zoho Books no tiene un endpoint genérico 'settings/fields?module=...' como CRM.
        // A menudo, los campos se obtienen de la configuración de la plantilla o de un registro de ejemplo.
        // Para 'invoices', 'customerpayments', 'customers', 'items'.

        $sample_record = null;
        $fields_from_record = [];

        switch ($module_name) {
            case 'invoices':
                // $response = $this->get('invoices', ['per_page' => 1]); // Necesita get_params_with_org_id
                // if (!is_wp_error($response) && isset($response['data']['invoices'][0])) {
                //     $sample_record = $response['data']['invoices'][0];
                // }
                // Para una implementación real, se necesitaría un endpoint de metadatos o una forma de obtener la plantilla de campos.
                // Por ahora, devolvemos un conjunto predefinido de campos comunes para facturas.
                $fields_from_record = [
                    'customer_id' => ['label' => 'Customer ID', 'type' => 'string'],
                    'invoice_number' => ['label' => 'Invoice Number', 'type' => 'string'],
                    'date' => ['label' => 'Date', 'type' => 'date'],
                    'due_date' => ['label' => 'Due Date', 'type' => 'date'],
                    'currency_code' => ['label' => 'Currency Code', 'type' => 'string'],
                    'total' => ['label' => 'Total', 'type' => 'decimal'],
                    'balance' => ['label' => 'Balance', 'type' => 'decimal'],
                    'notes' => ['label' => 'Notes', 'type' => 'text'],
                    'terms' => ['label' => 'Terms', 'type' => 'text'],
                    // Line items es un array de objetos, más complejo de mapear directamente.
                ];
                break;
            case 'customerpayments':
                 $fields_from_record = [
                    'customer_id' => ['label' => 'Customer ID', 'type' => 'string'],
                    'payment_mode' => ['label' => 'Payment Mode', 'type' => 'string'],
                    'amount' => ['label' => 'Amount', 'type' => 'decimal'],
                    'date' => ['label' => 'Date', 'type' => 'date'],
                    'reference_number' => ['label' => 'Reference Number', 'type' => 'string'],
                    // 'invoices' es un array de aplicaciones de pago.
                ];
                break;
            // Añadir casos para 'customers', 'items' si es necesario, similar a Inventory o CRM.
            default:
                 if(isset($this->logger)) $this->logger->warning("Field discovery for Zoho Books module '{$module_name}' is based on predefined common fields.", array('module' => $module_name));
        }

        if (!empty($fields_from_record)) {
            $formatted_fields = [];
            foreach ($fields_from_record as $api_name => $details) {
                $formatted_fields[] = [
                    'api_name'    => $api_name,
                    'field_label' => $details['label'],
                    'data_type'   => $details['type']
                ];
            }
            if (method_exists($this, 'cache')) $this->cache($cache_key, $formatted_fields, DAY_IN_SECONDS);
            return $formatted_fields;
        }
        // --- FIN DE LÓGICA HIPOTÉTICA ---

        if(isset($this->logger)) $this->logger->warning("Field discovery for Zoho Books module '{$module_name}' is not fully implemented or no sample record found.", array('module' => $module_name));
        return new WP_Error('books_fields_not_implemented', __('Field discovery for Zoho Books module ' . $module_name . ' is not fully implemented or no sample record available.', 'woocommerce-zoho-integration'));
    }
}
