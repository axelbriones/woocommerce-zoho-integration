<?php
/**
 * Sincronización de clientes
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 */

/**
 * Sincronización de clientes.
 *
 * Esta clase maneja la sincronización bidireccional de clientes
 * entre WooCommerce y Zoho CRM.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Sync_Customers {

    /**
     * API de Zoho CRM.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Zoho_CRM    $crm_api    Instancia de la API de CRM.
     */
    private $crm_api;

    /**
     * Logger.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Logger    $logger    Instancia del logger.
     */
    private $logger;

    /**
     * Mapeo de campos.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $field_mapping    Mapeo de campos entre WooCommerce y Zoho.
     */
    private $field_mapping = array();

    /**
     * Meta key para almacenar ID de Zoho.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $zoho_id_meta_key    Meta key.
     */
    private $zoho_id_meta_key = '_wzi_zoho_contact_id';

    /**
     * Meta key para almacenar fecha de última sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $last_sync_meta_key    Meta key.
     */
    private $last_sync_meta_key = '_wzi_last_sync';

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->crm_api = new WZI_Zoho_CRM();
        $this->logger = new WZI_Logger();
        
        // Cargar mapeo de campos
        $this->load_field_mapping();
    }

    /**
     * Cargar mapeo de campos desde la base de datos.
     *
     * @since    1.0.0
     */
    private function load_field_mapping() {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'wzi_field_mapping'; // Unificado a singular
        
        $mappings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$mapping_table} 
             WHERE entity_type = %s AND is_active = 1",
            'customer'
        ));
        
        foreach ($mappings as $mapping) {
            $this->field_mapping[$mapping->woo_field] = array(
                'zoho_field' => $mapping->zoho_field,
                'sync_direction' => $mapping->sync_direction,
                'transform_function' => $mapping->transform_function,
            );
        }
        
        // Mapeo por defecto si no hay configuración
        if (empty($this->field_mapping)) {
            $this->field_mapping = $this->get_default_field_mapping();
        }
    }

    /**
     * Obtener mapeo de campos por defecto.
     *
     * @since    1.0.0
     * @return   array    Mapeo por defecto.
     */
    private function get_default_field_mapping() {
        return array(
            'email' => array(
                'zoho_field' => 'Email',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'first_name' => array(
                'zoho_field' => 'First_Name',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'last_name' => array(
                'zoho_field' => 'Last_Name',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_phone' => array(
                'zoho_field' => 'Phone',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_company' => array(
                'zoho_field' => 'Account_Name',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_address_1' => array(
                'zoho_field' => 'Mailing_Street',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_city' => array(
                'zoho_field' => 'Mailing_City',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_state' => array(
                'zoho_field' => 'Mailing_State',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_postcode' => array(
                'zoho_field' => 'Mailing_Zip',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
            'billing_country' => array(
                'zoho_field' => 'Mailing_Country',
                'sync_direction' => 'both',
                'transform_function' => null,
            ),
        );
    }

    /**
     * Sincronizar desde WooCommerce a Zoho.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 Resultado de la sincronización.
     */
    public function sync_from_woocommerce($batch_size = 50) {
        $result = array(
            'synced' => 0,
            'errors' => array(),
        );
        
        $this->logger->info('Starting customer sync from WooCommerce', array(
            'sync_type' => 'customers',
            'sync_direction' => 'woo_to_zoho',
            'batch_size' => $batch_size,
        ));
        
        // Obtener clientes no sincronizados o modificados
        $customers = $this->get_customers_to_sync($batch_size);
        
        foreach ($customers as $customer_id) {
            try {
                $customer = new WC_Customer($customer_id);
                
                if (!$customer->get_id()) {
                    continue;
                }
                
                // Sincronizar cliente
                $zoho_contact = $this->sync_customer_to_zoho($customer);
                
                if (is_wp_error($zoho_contact)) {
                    $result['errors'][] = sprintf(
                        __('Error sincronizando cliente %d: %s', 'woocommerce-zoho-integration'),
                        $customer_id,
                        $zoho_contact->get_error_message()
                    );
                    
                    $this->logger->error('Failed to sync customer', array(
                        'customer_id' => $customer_id,
                        'error' => $zoho_contact->get_error_message(),
                    ));
                } else {
                    $result['synced']++;
                    
                    // Guardar ID de Zoho
                    update_user_meta($customer_id, $this->zoho_id_meta_key, $zoho_contact['id']);
                    update_user_meta($customer_id, $this->last_sync_meta_key, current_time('mysql'));
                    
                    $this->logger->info('Customer synced successfully', array(
                        'customer_id' => $customer_id,
                        'zoho_id' => $zoho_contact['id'],
                    ));
                }
                
            } catch (Exception $e) {
                $result['errors'][] = sprintf(
                    __('Error procesando cliente %d: %s', 'woocommerce-zoho-integration'),
                    $customer_id,
                    $e->getMessage()
                );
                
                $this->logger->error('Exception syncing customer', array(
                    'customer_id' => $customer_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ));
            }
        }
        
        $this->logger->info('Customer sync from WooCommerce completed', array(
            'synced' => $result['synced'],
            'errors' => count($result['errors']),
        ));
        
        return $result;
    }

    /**
     * Sincronizar desde Zoho a WooCommerce.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 Resultado de la sincronización.
     */
    public function sync_from_zoho($batch_size = 50) {
        $result = array(
            'synced' => 0,
            'errors' => array(),
        );
        
        $this->logger->info('Starting customer sync from Zoho', array(
            'sync_type' => 'customers',
            'sync_direction' => 'zoho_to_woo',
            'batch_size' => $batch_size,
        ));
        
        try {
            // Obtener última fecha de sincronización
            $last_sync = get_option('wzi_last_customer_sync_from_zoho', '1970-01-01 00:00:00');
            
            // Obtener contactos modificados desde la última sincronización
            $params = array(
                'sort_by' => 'Modified_Time',
                'sort_order' => 'asc',
                'per_page' => $batch_size,
            );
            
            // Añadir filtro de fecha si es posible
            if ($last_sync !== '1970-01-01 00:00:00') {
                $params['modified_since'] = date('c', strtotime($last_sync));
            }
            
            $contacts = $this->crm_api->get_all_records('Contacts', $params);
            
            if (is_wp_error($contacts)) {
                throw new Exception($contacts->get_error_message());
            }
            
            foreach ($contacts as $contact) {
                try {
                    // Sincronizar contacto
                    $customer = $this->sync_contact_to_woocommerce($contact);
                    
                    if (is_wp_error($customer)) {
                        $result['errors'][] = sprintf(
                            __('Error sincronizando contacto %s: %s', 'woocommerce-zoho-integration'),
                            $contact['id'],
                            $customer->get_error_message()
                        );
                    } else {
                        $result['synced']++;
                        
                        // Actualizar fecha de última sincronización
                        update_user_meta($customer->get_id(), $this->zoho_id_meta_key, $contact['id']);
                        update_user_meta($customer->get_id(), $this->last_sync_meta_key, current_time('mysql'));
                    }
                    
                } catch (Exception $e) {
                    $result['errors'][] = sprintf(
                        __('Error procesando contacto %s: %s', 'woocommerce-zoho-integration'),
                        $contact['id'],
                        $e->getMessage()
                    );
                }
            }
            
            // Actualizar fecha de última sincronización
            if ($result['synced'] > 0) {
                update_option('wzi_last_customer_sync_from_zoho', current_time('mysql'));
            }
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            
            $this->logger->error('Failed to sync from Zoho', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
        
        $this->logger->info('Customer sync from Zoho completed', array(
            'synced' => $result['synced'],
            'errors' => count($result['errors']),
        ));
        
        return $result;
    }

    /**
     * Sincronizar un cliente individual.
     *
     * @since    1.0.0
     * @param    int      $customer_id    ID del cliente.
     * @param    array    $data           Datos adicionales.
     * @return   bool                     Resultado de la sincronización.
     */
    public function sync_single($customer_id, $data = array()) {
        try {
            $customer = new WC_Customer($customer_id);
            
            if (!$customer->get_id()) {
                throw new Exception(__('Cliente no encontrado', 'woocommerce-zoho-integration'));
            }
            
            $zoho_contact = $this->sync_customer_to_zoho($customer);
            
            if (is_wp_error($zoho_contact)) {
                throw new Exception($zoho_contact->get_error_message());
            }
            
            // Guardar ID de Zoho
            update_user_meta($customer_id, $this->zoho_id_meta_key, $zoho_contact['id']);
            update_user_meta($customer_id, $this->last_sync_meta_key, current_time('mysql'));
            
            $this->logger->info('Single customer synced', array(
                'customer_id' => $customer_id,
                'zoho_id' => $zoho_contact['id'],
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to sync single customer', array(
                'customer_id' => $customer_id,
                'error' => $e->getMessage(),
            ));
            
            return false;
        }
    }

    /**
     * Eliminar cliente en Zoho.
     *
     * @since    1.0.0
     * @param    int      $customer_id    ID del cliente.
     * @param    array    $data           Datos adicionales.
     * @return   bool                     Resultado de la eliminación.
     */
    public function delete_single($customer_id, $data = array()) {
        try {
            $zoho_id = get_user_meta($customer_id, $this->zoho_id_meta_key, true);
            
            if (empty($zoho_id)) {
                return true; // No existe en Zoho
            }
            
            $result = $this->crm_api->delete_record('Contacts', $zoho_id);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Limpiar metadata
            delete_user_meta($customer_id, $this->zoho_id_meta_key);
            delete_user_meta($customer_id, $this->last_sync_meta_key);
            
            $this->logger->info('Customer deleted from Zoho', array(
                'customer_id' => $customer_id,
                'zoho_id' => $zoho_id,
            ));
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to delete customer from Zoho', array(
                'customer_id' => $customer_id,
                'error' => $e->getMessage(),
            ));
            
            return false;
        }
    }

    /**
     * Manejar webhook de Zoho.
     *
     * @since    1.0.0
     * @param    string    $action    Acción del webhook.
     * @param    array     $data      Datos del webhook.
     * @return   bool                 Resultado del procesamiento.
     */
    public function handle_webhook($action, $data) {
        try {
            switch ($action) {
                case 'create':
                case 'update':
                    if (isset($data['id'])) {
                        // Obtener contacto completo
                        $contact = $this->crm_api->get_record('Contacts', $data['id']);
                        
                        if (!is_wp_error($contact)) {
                            $customer = $this->sync_contact_to_woocommerce($contact);
                            return !is_wp_error($customer);
                        }
                    }
                    break;
                    
                case 'delete':
                    if (isset($data['id'])) {
                        // Buscar cliente por ID de Zoho
                        $customer_id = $this->get_customer_by_zoho_id($data['id']);
                        
                        if ($customer_id) {
                            // Opcionalmente eliminar cliente o solo desvincular
                            delete_user_meta($customer_id, $this->zoho_id_meta_key);
                            delete_user_meta($customer_id, $this->last_sync_meta_key);
                            
                            return true;
                        }
                    }
                    break;
            }
            
        } catch (Exception $e) {
            $this->logger->error('Failed to handle webhook', array(
                'action' => $action,
                'error' => $e->getMessage(),
            ));
        }
        
        return false;
    }

    /**
     * Obtener clientes para sincronizar.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 IDs de clientes.
     */
    private function get_customers_to_sync($batch_size) {
        global $wpdb;
        
        // Primero, clientes sin ID de Zoho
        $query = "SELECT u.ID 
                  FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
                  WHERE um.meta_value IS NULL
                  AND u.ID IN (
                      SELECT DISTINCT user_id 
                      FROM {$wpdb->usermeta} 
                      WHERE meta_key = %s
                  )
                  LIMIT %d";
        
        $customers_without_zoho_id = $wpdb->get_col($wpdb->prepare(
            $query,
            $this->zoho_id_meta_key,
            'wp_capabilities',
            $batch_size
        ));
        
        // Si no llenamos el batch, buscar clientes modificados
        $remaining = $batch_size - count($customers_without_zoho_id);
        $modified_customers = array();
        
        if ($remaining > 0) {
            // Clientes modificados después de la última sincronización
            $query = "SELECT u.ID 
                      FROM {$wpdb->users} u
                      INNER JOIN {$wpdb->usermeta} um1 ON u.ID = um1.user_id AND um1.meta_key = %s
                      LEFT JOIN {$wpdb->usermeta} um2 ON u.ID = um2.user_id AND um2.meta_key = %s
                      WHERE um1.meta_value IS NOT NULL
                      AND (um2.meta_value IS NULL OR um2.meta_value < u.user_registered)
                      LIMIT %d";
            
            $modified_customers = $wpdb->get_col($wpdb->prepare(
                $query,
                $this->zoho_id_meta_key,
                $this->last_sync_meta_key,
                $remaining
            ));
        }
        
        return array_merge($customers_without_zoho_id, $modified_customers);
    }

    /**
     * Sincronizar cliente a Zoho.
     *
     * @since    1.0.0
     * @param    WC_Customer    $customer    Cliente de WooCommerce.
     * @return   array|WP_Error              Contacto de Zoho o error.
     */
    private function sync_customer_to_zoho($customer) {
        $zoho_data = array();
        $customer_id = $customer->get_id();

        $this->logger->debug(sprintf('Starting data preparation for WC User ID: %d', $customer_id), array('field_mapping_count' => count($this->field_mapping)));

        foreach ($this->field_mapping as $woo_field_key => $mapping_details) {
            if (!in_array($mapping_details['sync_direction'], array('woo_to_zoho', 'both'))) {
                $this->logger->debug(sprintf('Skipping field %s for WC User ID: %d due to sync direction (%s).', $woo_field_key, $customer_id, $mapping_details['sync_direction']));
                continue;
            }

            $value = $this->get_customer_field_value($customer, $woo_field_key);
            $this->logger->debug(sprintf('WC User ID: %d, WC Field: %s, Raw Value: %s', $customer_id, $woo_field_key, is_array($value) ? json_encode($value) : $value));


            if (!empty($mapping_details['transform_function'])) {
                if (is_callable($mapping_details['transform_function'])) {
                    try {
                        $value = call_user_func($mapping_details['transform_function'], $value, 'wc_to_zoho', $customer, $mapping_details);
                        $this->logger->debug(sprintf('WC User ID: %d, Field: %s, Transformed Value: %s', $customer_id, $woo_field_key, is_array($value) ? json_encode($value) : $value));
                    } catch (Exception $e) {
                        $this->logger->error(sprintf('Error transforming field %s for WC User ID: %d. Function: %s. Error: %s', $woo_field_key, $customer_id, $mapping_details['transform_function'], $e->getMessage()));
                    }
                } elseif (method_exists('WZI_Helpers', $mapping_details['transform_function'])) {
                     try {
                        $value = WZI_Helpers::{$mapping_details['transform_function']}($value, 'wc_to_zoho', $customer, $mapping_details);
                        $this->logger->debug(sprintf('WC User ID: %d, Field: %s, Transformed Value (Helper): %s', $customer_id, $woo_field_key, is_array($value) ? json_encode($value) : $value));
                    } catch (Exception $e) {
                        $this->logger->error(sprintf('Error transforming field %s with WZI_Helpers for WC User ID: %d. Function: %s. Error: %s', $woo_field_key, $customer_id, $mapping_details['transform_function'], $e->getMessage()));
                    }
                }else {
                    $this->logger->warning(sprintf('Transform function %s not found for field %s, WC User ID: %d.', $mapping_details['transform_function'], $woo_field_key, $customer_id));
                }
            }
            
            // Solo añadir si el valor no es nulo. Zoho puede rechazar claves con valores nulos.
            // Algunos campos vacíos ('') pueden ser necesarios para borrar un valor en Zoho.
            if ($value !== null) {
                $zoho_data[$mapping_details['zoho_field']] = $value;
            }
        }
        
        // Asegurar que Last_Name (apellido) no esté vacío, ya que es requerido por Zoho CRM Contacts.
        // Buscar si 'Last_Name' fue mapeado.
        $last_name_zoho_key = 'Last_Name'; // Asumiendo que este es el API name estándar en Zoho.
        $is_last_name_mapped_and_filled = false;
        foreach($this->field_mapping as $woo_field => $map_details) {
            if ($map_details['zoho_field'] === $last_name_zoho_key && !empty($zoho_data[$last_name_zoho_key])) {
                $is_last_name_mapped_and_filled = true;
                break;
            }
        }

        if (!$is_last_name_mapped_and_filled) {
            // Si Last_Name no está, o está vacío, usar un placeholder o el email.
            $placeholder_last_name = $customer->get_billing_last_name() ?: $customer->get_last_name();
            if(empty($placeholder_last_name)) {
                $user_info = get_userdata($customer_id);
                $placeholder_last_name = $user_info ? $user_info->user_login : 'N/A';
            }
             // Si el campo 'Last_Name' no fue mapeado en absoluto, lo añadimos.
            // Si fue mapeado pero resultó en un valor vacío, lo sobrescribimos aquí si es necesario.
            if (empty($zoho_data[$last_name_zoho_key])) {
                 $zoho_data[$last_name_zoho_key] = $placeholder_last_name;
                 $this->logger->info(sprintf('Last_Name for Zoho was empty or not mapped for WC User ID: %d. Using placeholder: %s', $customer_id, $placeholder_last_name));
            }
        }
        
        // Añadir datos fijos o adicionales
        $zoho_data['Lead_Source'] = 'WooCommerce';
        // La descripción puede ser más genérica o construirse con datos específicos si es necesario.
        // $zoho_data['Description'] = sprintf(
        //    __('Cliente sincronizado desde WooCommerce (ID: %d)', 'woocommerce-zoho-integration'),
        //    $customer_id
        // );

        // Aplicar filtro para personalización final
        $zoho_data = apply_filters('wzi_customer_to_zoho_data', $zoho_data, $customer, $this->field_mapping);

        // Eliminar cualquier clave con valor nulo explícito, ya que algunas APIs de Zoho no los aceptan.
        // Las cadenas vacías suelen ser aceptables para borrar un campo.
        $zoho_data = array_filter($zoho_data, function($value) {
            return $value !== null;
        });

        $this->logger->debug(sprintf('Zoho data payload for WC User ID: %d before validation and final filter', $customer_id), $zoho_data);

        // Validar los datos preparados antes de enviarlos a Zoho
        $validation_result = WZI_Validator::validate_for_zoho($zoho_data, 'Contacts'); // Asumiendo 'Contacts' como tipo de entidad para el validador

        if (!$validation_result['valid']) {
            $this->logger->error(
                sprintf('Validation failed for Zoho Contact data (WC User ID: %d).', $customer_id),
                array(
                    'errors' => $validation_result['errors'],
                    'data_prepared' => $zoho_data
                )
            );
            // Devolver un WP_Error con los detalles de la validación
            // Es importante que el código de error sea único y descriptivo
            $error_messages = implode('; ', array_map(function($field_errors) {
                return implode(', ', (array)$field_errors);
            }, $validation_result['errors']));
            return new WP_Error(
                'zoho_contact_validation_failed',
                sprintf(__('Los datos preparados para el contacto de Zoho no pasaron la validación: %s', 'woocommerce-zoho-integration'), $error_messages),
                $validation_result['errors']
            );
        }

        // Usar los datos potencialmente sanitizados/modificados por el validador
        // Aunque WZI_Validator actualmente no modifica mucho, es una buena práctica.
        $zoho_data = $validation_result['data'];

        $this->logger->debug(sprintf('Final Zoho data payload for WC User ID: %d after validation', $customer_id), $zoho_data);

        $zoho_id = get_user_meta($customer_id, $this->zoho_id_meta_key, true);
        $search_field_for_upsert = 'Email'; // Campo principal para buscar duplicados
        $email_value = $customer->get_email();

        $upsert_criteria = [$search_field_for_upsert => $email_value];

        if ($zoho_id) {
            $this->logger->info(sprintf('Updating Zoho Contact ID: %s for WC User ID: %d', $zoho_id, $customer_id));
            // Para update, el ID debe estar en el payload según la doc de Zoho v2+
            // $zoho_data['id'] = $zoho_id; // No, update_record lo añade. Upsert lo maneja.
            $response = $this->crm_api->update_record('Contacts', $zoho_id, $zoho_data);
        } else {
            // Intentar upsert para evitar duplicados si el zoho_id no está pero el email sí existe en Zoho
            $this->logger->info(sprintf('Attempting to upsert Zoho Contact for WC User ID: %d (Email: %s)', $customer_id, $email_value));
            $response = $this->crm_api->upsert_record('Contacts', $zoho_data, $upsert_criteria);
        }

        if (is_wp_error($response)) {
            $this->logger->error(
                sprintf('Error syncing WC User ID: %d to Zoho. Zoho ID: %s', $customer_id, $zoho_id ?: 'N/A (attempted upsert)'),
                array('error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message(), 'payload_sent' => $zoho_data)
            );
            return $response;
        }

        // La respuesta de create/update/upsert en Zoho CRM v2+ suele estar en $response['data'][0]
        // y el ID del registro en $response['data'][0]['details']['id']
        $processed_record = null;
        if (isset($response['data'][0]['code']) && $response['data'][0]['code'] == 'SUCCESS') {
            $processed_record = $response['data'][0]['details'];
        } elseif (isset($response['id'])) { // Fallback para algunas estructuras de API más antiguas o respuestas directas de upsert
            $processed_record = $response;
        }


        if (isset($processed_record['id'])) {
            update_user_meta($customer_id, $this->zoho_id_meta_key, $processed_record['id']);
            update_user_meta($customer_id, $this->last_sync_meta_key, current_time('mysql'));
            $this->logger->info(sprintf('Successfully synced WC User ID: %d to Zoho Contact ID: %s', $customer_id, $processed_record['id']));
            return $processed_record;
        } else {
            $this->logger->warning(sprintf('Zoho API response did not contain an ID or success code for WC User ID: %d.', $customer_id), $response);
            return new WP_Error('zoho_id_missing_or_error', __('Zoho API response did not contain an ID or success code.', 'woocommerce-zoho-integration'), $response);
        }
    }

    /**
     * Sincronizar contacto a WooCommerce.
     *
     * @since    1.0.0
     * @param    array    $contact    Contacto de Zoho.
     * @return   WC_Customer|WP_Error  Cliente de WooCommerce o error.
     */
    private function sync_contact_to_woocommerce($contact) {
        // Buscar cliente existente por email
        $email = isset($contact['Email']) ? sanitize_email($contact['Email']) : '';
        
        if (empty($email)) {
            return new WP_Error('no_email', __('El contacto no tiene email', 'woocommerce-zoho-integration'));
        }
        
        // Buscar por email o por ID de Zoho
        $customer_id = email_exists($email);
        
        if (!$customer_id) {
            $customer_id = $this->get_customer_by_zoho_id($contact['id']);
        }
        
        if ($customer_id) {
            $customer = new WC_Customer($customer_id);
        } else {
            // Crear nuevo cliente
            $customer = new WC_Customer();
            $customer->set_email($email);
            
            // Generar username si es necesario
            $username = isset($contact['First_Name']) && isset($contact['Last_Name']) 
                ? sanitize_user($contact['First_Name'] . '_' . $contact['Last_Name']) 
                : sanitize_user($email);
            
            $username = $this->generate_unique_username($username);
            $customer->set_username($username);
        }
        
        // Mapear campos desde Zoho
        foreach ($this->field_mapping as $woo_field => $mapping) {
            // Verificar dirección de sincronización
            if (!in_array($mapping['sync_direction'], array('zoho_to_woo', 'both'))) {
                continue;
            }
            
            $zoho_field = $mapping['zoho_field'];
            
            if (isset($contact[$zoho_field])) {
                $value = $contact[$zoho_field];
                
                // Aplicar transformación si existe
                if (!empty($mapping['transform_function']) && function_exists($mapping['transform_function'])) {
                    $value = call_user_func($mapping['transform_function'], $value, 'zoho_to_woo');
                }
                
                $this->set_customer_field_value($customer, $woo_field, $value);
            }
        }
        
        // Aplicar filtro para personalización
        $customer = apply_filters('wzi_zoho_to_customer_data', $customer, $contact);
        
        try {
            // Guardar cliente
            $customer->save();
            
            // Guardar ID de Zoho
            update_user_meta($customer->get_id(), $this->zoho_id_meta_key, $contact['id']);
            
            $this->logger->info('Contact synced to WooCommerce', array(
                'zoho_id' => $contact['id'],
                'customer_id' => $customer->get_id(),
            ));
            
            return $customer;
            
        } catch (Exception $e) {
            return new WP_Error('save_failed', $e->getMessage());
        }
    }

    /**
     * Obtener valor de campo del cliente.
     *
     * @since    1.0.0
     * @param    WC_Customer    $customer    Cliente.
     * @param    string         $field       Campo.
     * @return   mixed                       Valor del campo.
     */
    private function get_customer_field_value($customer, $field) {
        switch ($field) {
            case 'email':
                return $customer->get_email();
            case 'first_name':
                return $customer->get_first_name();
            case 'last_name':
                return $customer->get_last_name();
            case 'billing_phone':
                return $customer->get_billing_phone();
            case 'billing_company':
                return $customer->get_billing_company();
            case 'billing_address_1':
                return $customer->get_billing_address_1();
            case 'billing_address_2':
                return $customer->get_billing_address_2();
            case 'billing_city':
                return $customer->get_billing_city();
            case 'billing_state':
                return $customer->get_billing_state();
            case 'billing_postcode':
                return $customer->get_billing_postcode();
            case 'billing_country':
                return $customer->get_billing_country();
            case 'shipping_address_1':
                return $customer->get_shipping_address_1();
            case 'shipping_address_2':
                return $customer->get_shipping_address_2();
            case 'shipping_city':
                return $customer->get_shipping_city();
            case 'shipping_state':
                return $customer->get_shipping_state();
            case 'shipping_postcode':
                return $customer->get_shipping_postcode();
            case 'shipping_country':
                return $customer->get_shipping_country();
            default:
                // Intentar obtener como meta
                return $customer->get_meta($field);
        }
    }

    /**
     * Establecer valor de campo del cliente.
     *
     * @since    1.0.0
     * @param    WC_Customer    $customer    Cliente.
     * @param    string         $field       Campo.
     * @param    mixed          $value       Valor.
     */
    private function set_customer_field_value($customer, $field, $value) {
        switch ($field) {
            case 'email':
                $customer->set_email($value);
                break;
            case 'first_name':
                $customer->set_first_name($value);
                break;
            case 'last_name':
                $customer->set_last_name($value);
                break;
            case 'billing_phone':
                $customer->set_billing_phone($value);
                break;
            case 'billing_company':
                $customer->set_billing_company($value);
                break;
            case 'billing_address_1':
                $customer->set_billing_address_1($value);
                break;
            case 'billing_address_2':
                $customer->set_billing_address_2($value);
                break;
            case 'billing_city':
                $customer->set_billing_city($value);
                break;
            case 'billing_state':
                $customer->set_billing_state($value);
                break;
            case 'billing_postcode':
                $customer->set_billing_postcode($value);
                break;
            case 'billing_country':
                $customer->set_billing_country($value);
                break;
            case 'shipping_address_1':
                $customer->set_shipping_address_1($value);
                break;
            case 'shipping_address_2':
                $customer->set_shipping_address_2($value);
                break;
            case 'shipping_city':
                $customer->set_shipping_city($value);
                break;
            case 'shipping_state':
                $customer->set_shipping_state($value);
                break;
            case 'shipping_postcode':
                $customer->set_shipping_postcode($value);
                break;
            case 'shipping_country':
                $customer->set_shipping_country($value);
                break;
            default:
                // Establecer como meta
                $customer->update_meta_data($field, $value);
        }
    }

    /**
     * Obtener cliente por ID de Zoho.
     *
     * @since    1.0.0
     * @param    string    $zoho_id    ID de Zoho.
     * @return   int|false             ID del cliente o false.
     */
    private function get_customer_by_zoho_id($zoho_id) {
        global $wpdb;
        
        $customer_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = %s AND meta_value = %s 
             LIMIT 1",
            $this->zoho_id_meta_key,
            $zoho_id
        ));
        
        return $customer_id ? intval($customer_id) : false;
    }

    /**
     * Generar username único.
     *
     * @since    1.0.0
     * @param    string    $username    Username base.
     * @return   string                 Username único.
     */
    private function generate_unique_username($username) {
        $username = sanitize_user($username);
        $original_username = $username;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $original_username . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Obtener estadísticas de sincronización.
     *
     * @since    1.0.0
     * @return   array    Estadísticas.
     */
    public function get_sync_stats() {
        global $wpdb;
        
        // Total de clientes
        $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        
        // Clientes sincronizados
        $synced_customers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $this->zoho_id_meta_key
        ));
        
        // Clientes pendientes
        $pending_customers = $total_customers - $synced_customers;
        
        return array(
            'total' => $total_customers,
            'synced' => $synced_customers,
            'pending' => $pending_customers,
            'percentage' => $total_customers > 0 ? round(($synced_customers / $total_customers) * 100, 2) : 0,
        );
    }
}