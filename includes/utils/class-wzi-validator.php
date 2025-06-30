<?php
/**
 * Validaciones
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/utils
 */

/**
 * Validaciones.
 *
 * Esta clase contiene métodos de validación para datos
 * que se intercambian entre WooCommerce y Zoho.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/utils
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Validator {

    /**
     * Reglas de validación para campos de Zoho.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $zoho_field_rules    Reglas por campo.
     */
    private static $zoho_field_rules = array(
        // Campos comunes
        'email' => array(
            'type' => 'email',
            'max_length' => 100,
            'required' => false,
        ),
        'phone' => array(
            'type' => 'phone',
            'max_length' => 30,
            'required' => false,
        ),
        'website' => array(
            'type' => 'url',
            'max_length' => 255,
            'required' => false,
        ),
        
        // Campos de CRM Contacts
        'First_Name' => array(
            'type' => 'string',
            'max_length' => 40,
            'required' => false,
        ),
        'Last_Name' => array(
            'type' => 'string',
            'max_length' => 80,
            'required' => true,
        ),
        'Email' => array(
            'type' => 'email',
            'max_length' => 100,
            'required' => false,
        ),
        'Phone' => array(
            'type' => 'phone',
            'max_length' => 30,
            'required' => false,
        ),
        'Mobile' => array(
            'type' => 'phone',
            'max_length' => 30,
            'required' => false,
        ),
        
        // Campos de dirección
        'Mailing_Street' => array(
            'type' => 'string',
            'max_length' => 250,
            'required' => false,
        ),
        'Mailing_City' => array(
            'type' => 'string',
            'max_length' => 30,
            'required' => false,
        ),
        'Mailing_State' => array(
            'type' => 'string',
            'max_length' => 30,
            'required' => false,
        ),
        'Mailing_Zip' => array(
            'type' => 'string',
            'max_length' => 30,
            'required' => false,
        ),
        'Mailing_Country' => array(
            'type' => 'string',
            'max_length' => 30,
            'required' => false,
        ),
        
        // Campos de productos
        'Product_Name' => array(
            'type' => 'string',
            'max_length' => 120,
            'required' => true,
        ),
        'Product_Code' => array(
            'type' => 'string',
            'max_length' => 40,
            'required' => false,
        ),
        'Unit_Price' => array(
            'type' => 'decimal',
            'min' => 0,
            'max' => 99999999.99,
            'decimals' => 2,
            'required' => false,
        ),
        'Qty_in_Stock' => array(
            'type' => 'integer',
            'min' => 0,
            'max' => 999999999,
            'required' => false,
        ),
        
        // Campos de órdenes
        'Subject' => array(
            'type' => 'string',
            'max_length' => 300,
            'required' => true,
        ),
        'Grand_Total' => array(
            'type' => 'decimal',
            'min' => 0,
            'max' => 99999999999.99,
            'decimals' => 2,
            'required' => false,
        ),
    );

    /**
     * Validar datos para Zoho.
     *
     * @since    1.0.0
     * @param    array     $data         Datos a validar.
     * @param    string    $entity_type  Tipo de entidad (contacts, products, etc).
     * @return   array                   Array con 'valid' y 'errors'.
     */
    public static function validate_for_zoho($data, $entity_type = '') {
        $errors = array();
        $validated_data = array();
        
        foreach ($data as $field => $value) {
            $validation = self::validate_zoho_field($field, $value);
            
            if ($validation['valid']) {
                $validated_data[$field] = $validation['value'];
            } else {
                $errors[$field] = $validation['error'];
            }
        }
        
        // Validar campos requeridos según el tipo de entidad
        $required_errors = self::validate_required_fields($validated_data, $entity_type);
        $errors = array_merge($errors, $required_errors);
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated_data,
        );
    }

    /**
     * Validar un campo específico de Zoho.
     *
     * @since    1.0.0
     * @param    string    $field    Nombre del campo.
     * @param    mixed     $value    Valor del campo.
     * @return   array              Array con 'valid', 'value' y 'error'.
     */
    public static function validate_zoho_field($field, $value) {
        // Si no hay reglas definidas, aceptar el valor
        if (!isset(self::$zoho_field_rules[$field])) {
            return array(
                'valid' => true,
                'value' => $value,
                'error' => '',
            );
        }
        
        $rules = self::$zoho_field_rules[$field];
        
        // Si el campo es requerido y está vacío
        if (isset($rules['required']) && $rules['required'] && empty($value)) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(__('El campo %s es requerido', 'woocommerce-zoho-integration'), $field),
            );
        }
        
        // Si el valor está vacío y no es requerido, es válido
        if (empty($value) && (!isset($rules['required']) || !$rules['required'])) {
            return array(
                'valid' => true,
                'value' => $value,
                'error' => '',
            );
        }
        
        // Validar según tipo
        switch ($rules['type']) {
            case 'string':
                return self::validate_string($value, $rules, $field);
                
            case 'email':
                return self::validate_email($value, $rules, $field);
                
            case 'phone':
                return self::validate_phone($value, $rules, $field);
                
            case 'url':
                return self::validate_url($value, $rules, $field);
                
            case 'integer':
                return self::validate_integer($value, $rules, $field);
                
            case 'decimal':
                return self::validate_decimal($value, $rules, $field);
                
            case 'boolean':
                return self::validate_boolean($value, $rules, $field);
                
            case 'date':
                return self::validate_date($value, $rules, $field);
                
            case 'datetime':
                return self::validate_datetime($value, $rules, $field);
                
            default:
                return array(
                    'valid' => true,
                    'value' => $value,
                    'error' => '',
                );
        }
    }

    /**
     * Validar string.
     *
     * @since    1.0.0
     * @param    string    $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_string($value, $rules, $field) {
        $value = sanitize_text_field($value);
        
        // Validar longitud máxima
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            $value = substr($value, 0, $rules['max_length']);
        }
        
        // Validar longitud mínima
        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe tener al menos %d caracteres', 'woocommerce-zoho-integration'),
                    $field,
                    $rules['min_length']
                ),
            );
        }
        
        // Validar patrón
        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s no tiene el formato correcto', 'woocommerce-zoho-integration'),
                    $field
                ),
            );
        }
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar email.
     *
     * @since    1.0.0
     * @param    string    $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_email($value, $rules, $field) {
        $value = sanitize_email($value);
        
        if (!is_email($value)) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser un email válido', 'woocommerce-zoho-integration'),
                    $field
                ),
            );
        }
        
        // Validar longitud
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El email es demasiado largo (máximo %d caracteres)', 'woocommerce-zoho-integration'),
                    $rules['max_length']
                ),
            );
        }
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar teléfono.
     *
     * @since    1.0.0
     * @param    string    $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_phone($value, $rules, $field) {
        // Limpiar número de teléfono
        $value = WZI_Helpers::clean_phone_number($value);
        
        // Validar formato básico
        if (!preg_match('/^\+?[0-9\s\-\(\)]+$/', $value)) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser un número de teléfono válido', 'woocommerce-zoho-integration'),
                    $field
                ),
            );
        }
        
        // Validar longitud
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            $value = substr($value, 0, $rules['max_length']);
        }
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar URL.
     *
     * @since    1.0.0
     * @param    string    $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_url($value, $rules, $field) {
        $value = esc_url_raw($value);
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser una URL válida', 'woocommerce-zoho-integration'),
                    $field
                ),
            );
        }
        
        // Validar longitud
        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('La URL es demasiado larga (máximo %d caracteres)', 'woocommerce-zoho-integration'),
                    $rules['max_length']
                ),
            );
        }
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar entero.
     *
     * @since    1.0.0
     * @param    mixed     $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_integer($value, $rules, $field) {
        $value = intval($value);
        
        // Validar mínimo
        if (isset($rules['min']) && $value < $rules['min']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser mayor o igual a %d', 'woocommerce-zoho-integration'),
                    $field,
                    $rules['min']
                ),
            );
        }
        
        // Validar máximo
        if (isset($rules['max']) && $value > $rules['max']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser menor o igual a %d', 'woocommerce-zoho-integration'),
                    $field,
                    $rules['max']
                ),
            );
        }
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar decimal.
     *
     * @since    1.0.0
     * @param    mixed     $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_decimal($value, $rules, $field) {
        $value = floatval($value);
        
        // Validar mínimo
        if (isset($rules['min']) && $value < $rules['min']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser mayor o igual a %s', 'woocommerce-zoho-integration'),
                    $field,
                    $rules['min']
                ),
            );
        }
        
        // Validar máximo
        if (isset($rules['max']) && $value > $rules['max']) {
            return array(
                'valid' => false,
                'value' => $value,
                'error' => sprintf(
                    __('El campo %s debe ser menor o igual a %s', 'woocommerce-zoho-integration'),
                    $field,
                    $rules['max']
                ),
            );
        }
        
        // Redondear a decimales especificados
        if (isset($rules['decimals'])) {
            $value = round($value, $rules['decimals']);
        }
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar booleano.
     *
     * @since    1.0.0
     * @param    mixed     $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_boolean($value, $rules, $field) {
        // Convertir a booleano
        if (is_string($value)) {
            $value = strtolower($value);
            if (in_array($value, array('true', '1', 'yes', 'on'))) {
                $value = true;
            } elseif (in_array($value, array('false', '0', 'no', 'off'))) {
                $value = false;
            }
        }
        
        $value = (bool) $value;
        
        // Para Zoho, convertir a string
        $value = $value ? 'true' : 'false';
        
        return array(
            'valid' => true,
            'value' => $value,
            'error' => '',
        );
    }

    /**
     * Validar fecha.
     *
     * @since    1.0.0
     * @param    mixed     $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_date($value, $rules, $field) {
        $format = isset($rules['format']) ? $rules['format'] : 'Y-m-d';
        
        // Intentar parsear la fecha
        $date = DateTime::createFromFormat($format, $value);
        
        if (!$date || $date->format($format) !== $value) {
            // Intentar con formato estándar
            try {
                $date = new DateTime($value);
                $value = $date->format($format);
            } catch (Exception $e) {
                return array(
                    'valid' => false,
                    'value' => $value,
                    'error' => sprintf(
                        __('El campo %s debe ser una fecha válida', 'woocommerce-zoho-integration'),
                        $field
                    ),
                );
            }
        }
        
        // Validar rango de fechas
        if (isset($rules['min_date'])) {
            $min_date = new DateTime($rules['min_date']);
            if ($date < $min_date) {
                return array(
                    'valid' => false,
                    'value' => $value,
                    'error' => sprintf(
                        __('El campo %s debe ser posterior a %s', 'woocommerce-zoho-integration'),
                        $field,
                        $min_date->format($format)
                    ),
                );
            }
        }
        
        if (isset($rules['max_date'])) {
            $max_date = new DateTime($rules['max_date']);
            if ($date > $max_date) {
                return array(
                    'valid' => false,
                    'value' => $value,
                    'error' => sprintf(
                        __('El campo %s debe ser anterior a %s', 'woocommerce-zoho-integration'),
                        $field,
                        $max_date->format($format)
                    ),
                );
            }
        }
        
        return array(
            'valid' => true,
            'value' => $date->format($format),
            'error' => '',
        );
    }

    /**
     * Validar fecha y hora.
     *
     * @since    1.0.0
     * @param    mixed     $value    Valor.
     * @param    array     $rules    Reglas.
     * @param    string    $field    Nombre del campo.
     * @return   array              Resultado de validación.
     */
    private static function validate_datetime($value, $rules, $field) {
        $format = isset($rules['format']) ? $rules['format'] : 'Y-m-d H:i:s';
        
        // Usar validación de fecha con formato datetime
        $rules['format'] = $format;
        return self::validate_date($value, $rules, $field);
    }

    /**
     * Validar campos requeridos.
     *
     * @since    1.0.0
     * @param    array     $data          Datos validados.
     * @param    string    $entity_type   Tipo de entidad.
     * @return   array                    Errores de campos requeridos.
     */
    private static function validate_required_fields($data, $entity_type) {
        $errors = array();
        $required_fields = self::get_required_fields($entity_type);
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = sprintf(
                    __('El campo %s es requerido para %s', 'woocommerce-zoho-integration'),
                    $field,
                    $entity_type
                );
            }
        }
        
        return $errors;
    }

    /**
     * Obtener campos requeridos por tipo de entidad.
     *
     * @since    1.0.0
     * @param    string    $entity_type    Tipo de entidad.
     * @return   array                     Campos requeridos.
     */
    private static function get_required_fields($entity_type) {
        $required_fields = array(
            'contacts' => array('Last_Name'),
            'leads' => array('Last_Name', 'Company'),
            'accounts' => array('Account_Name'),
            'deals' => array('Deal_Name', 'Stage'),
            'products' => array('Product_Name'),
            'sales_orders' => array('Subject'),
            'invoices' => array('Subject'),
            'items' => array('name'),
        );
        
        return isset($required_fields[$entity_type]) ? $required_fields[$entity_type] : array();
    }

    /**
     * Validar credenciales de API.
     *
     * @since    1.0.0
     * @param    array    $credentials    Array con client_id y client_secret.
     * @return   array                    Resultado de validación.
     */
    public static function validate_api_credentials($credentials) {
        $errors = array();
        
        // Validar Client ID
        if (empty($credentials['client_id'])) {
            $errors['client_id'] = __('El Client ID es requerido', 'woocommerce-zoho-integration');
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $credentials['client_id'])) {
            $errors['client_id'] = __('El Client ID contiene caracteres inválidos', 'woocommerce-zoho-integration');
        }
        
        // Validar Client Secret
        if (empty($credentials['client_secret'])) {
            $errors['client_secret'] = __('El Client Secret es requerido', 'woocommerce-zoho-integration');
        } elseif (strlen($credentials['client_secret']) < 20) {
            $errors['client_secret'] = __('El Client Secret parece ser inválido', 'woocommerce-zoho-integration');
        }
        
        // Validar Redirect URI
        if (isset($credentials['redirect_uri']) && !empty($credentials['redirect_uri'])) {
            if (!filter_var($credentials['redirect_uri'], FILTER_VALIDATE_URL)) {
                $errors['redirect_uri'] = __('La Redirect URI debe ser una URL válida', 'woocommerce-zoho-integration');
            }
        }
        
        // Validar Data Center
        if (isset($credentials['data_center'])) {
            $valid_centers = array('com', 'eu', 'in', 'com.cn', 'com.au');
            if (!in_array($credentials['data_center'], $valid_centers)) {
                $errors['data_center'] = __('Centro de datos inválido', 'woocommerce-zoho-integration');
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validar configuración de sincronización.
     *
     * @since    1.0.0
     * @param    array    $settings    Configuración.
     * @return   array                 Resultado de validación.
     */
    public static function validate_sync_settings($settings) {
        $errors = array();
        
        // Validar batch size
        if (isset($settings['batch_size'])) {
            $batch_size = intval($settings['batch_size']);
            if ($batch_size < 1 || $batch_size > 200) {
                $errors['batch_size'] = __('El tamaño del lote debe estar entre 1 y 200', 'woocommerce-zoho-integration');
            }
        }
        
        // Validar dirección de sincronización
        if (isset($settings['sync_direction'])) {
            $valid_directions = array('woo_to_zoho', 'zoho_to_woo', 'both');
            if (!in_array($settings['sync_direction'], $valid_directions)) {
                $errors['sync_direction'] = __('Dirección de sincronización inválida', 'woocommerce-zoho-integration');
            }
        }
        
        // Validar intervalo de sincronización
        if (isset($settings['sync_interval'])) {
            $valid_intervals = array(
                'wzi_five_minutes',
                'wzi_fifteen_minutes',
                'hourly',
                'twicedaily',
                'daily',
            );
            if (!in_array($settings['sync_interval'], $valid_intervals)) {
                $errors['sync_interval'] = __('Intervalo de sincronización inválido', 'woocommerce-zoho-integration');
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Sanitizar datos para almacenamiento.
     *
     * @since    1.0.0
     * @param    array    $data    Datos a sanitizar.
     * @return   array             Datos sanitizados.
     */
    public static function sanitize_data($data) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize_data($value);
            } elseif (is_string($value)) {
                // Sanitizar según el tipo de campo
                if (strpos($key, 'email') !== false) {
                    $sanitized[$key] = sanitize_email($value);
                } elseif (strpos($key, 'url') !== false || strpos($key, 'website') !== false) {
                    $sanitized[$key] = esc_url_raw($value);
                } elseif (strpos($key, 'html') !== false) {
                    $sanitized[$key] = wp_kses_post($value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Validar permisos de usuario.
     *
     * @since    1.0.0
     * @param    string    $action     Acción a realizar.
     * @param    int       $user_id    ID del usuario (opcional).
     * @return   bool                  Si tiene permisos.
     */
    public static function validate_user_permissions($action, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $capability_map = array(
            'manage_settings' => 'manage_wzi_settings',
            'view_logs' => 'view_wzi_logs',
            'manage_sync' => 'manage_wzi_sync',
            'manage_mappings' => 'manage_wzi_mappings',
        );
        
        $capability = isset($capability_map[$action]) ? $capability_map[$action] : 'manage_options';
        
        return user_can($user_id, $capability);
    }

    /**
     * Validar archivo para importación.
     *
     * @since    1.0.0
     * @param    array    $file    Array de archivo $_FILES.
     * @return   array             Resultado de validación.
     */
    public static function validate_import_file($file) {
        $errors = array();
        
        // Verificar si hay errores de carga
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = __('Error al cargar el archivo', 'woocommerce-zoho-integration');
            return array('valid' => false, 'errors' => $errors);
        }
        
        // Validar tipo de archivo
        $allowed_types = array('csv', 'json', 'xml');
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            $errors[] = sprintf(
                __('Tipo de archivo no permitido. Tipos permitidos: %s', 'woocommerce-zoho-integration'),
                implode(', ', $allowed_types)
            );
        }
        
        // Validar tamaño
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            $errors[] = sprintf(
                __('El archivo es demasiado grande. Tamaño máximo: %s', 'woocommerce-zoho-integration'),
                size_format($max_size)
            );
        }
        
        // Validar MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = array(
            'text/csv',
            'text/plain',
            'application/json',
            'application/xml',
            'text/xml',
        );
        
        if (!in_array($mime_type, $allowed_mimes)) {
            $errors[] = __('El tipo MIME del archivo no es válido', 'woocommerce-zoho-integration');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validar SKU de producto.
     *
     * @since    1.0.0
     * @param    string    $sku           SKU a validar.
     * @param    int       $product_id    ID del producto (para excluir en validación).
     * @return   array                    Resultado de validación.
     */
    public static function validate_product_sku($sku, $product_id = 0) {
        $errors = array();
        
        // SKU vacío es válido
        if (empty($sku)) {
            return array('valid' => true, 'errors' => array());
        }
        
        // Validar caracteres
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $sku)) {
            $errors[] = __('El SKU solo puede contener letras, números, guiones y guiones bajos', 'woocommerce-zoho-integration');
        }
        
        // Validar longitud
        if (strlen($sku) > 40) {
            $errors[] = __('El SKU no puede tener más de 40 caracteres', 'woocommerce-zoho-integration');
        }
        
        // Verificar unicidad
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id && $existing_id != $product_id) {
            $errors[] = __('El SKU ya está en uso por otro producto', 'woocommerce-zoho-integration');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }
}