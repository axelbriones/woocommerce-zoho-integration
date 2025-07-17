<?php
/**
 * Gestor de sincronización
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 */

/**
 * Gestor de sincronización.
 *
 * Esta clase coordina todas las operaciones de sincronización
 * entre WooCommerce y Zoho.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/sync
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_Sync_Manager {

    /**
     * Instancias de sincronizadores.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $syncers    Array de sincronizadores.
     */
    private $syncers = array();

    /**
     * Logger.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Logger    $logger    Instancia del logger.
     */
    private $logger;

    /**
     * Configuración de sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $sync_config    Configuración de sincronización.
     */
    private $sync_config;

    /**
     * Estado de sincronización actual.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $sync_status    Estado actual.
     */
    private $sync_status = array(
        'is_running' => false,
        'current_type' => '',
        'progress' => 0,
        'total' => 0,
        'errors' => 0,
        'start_time' => null,
    );

    /**
     * Tabla de cola de sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $queue_table    Nombre de la tabla de cola.
     */
    private $queue_table;

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        
        $this->logger = new WZI_Logger();
        $this->sync_config = get_option('wzi_sync_settings', array());
        $this->queue_table = $wpdb->prefix . 'wzi_sync_queue';
        
        // Inicializar sincronizadores
        $this->init_syncers();
        
        // Cargar estado desde transient
        $saved_status = get_transient('wzi_sync_status');
        if ($saved_status) {
            $this->sync_status = $saved_status;
        }
    }

    /**
     * Inicializar instancias de las clases de sincronización específicas.
     *
     * Carga los sincronizadores para los módulos que están habilitados
     * en la configuración del plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function init_syncers() {
        // Solo inicializar si están habilitados
        if ($this->is_sync_enabled('customers')) {
            $this->syncers['customers'] = new WZI_Sync_Customers();
        }
        
        if ($this->is_sync_enabled('orders')) {
            $this->syncers['orders'] = new WZI_Sync_Orders();
        }
        
        if ($this->is_sync_enabled('products')) {
            $this->syncers['products'] = new WZI_Sync_Products();
        }
        
        if ($this->is_sync_enabled('invoices')) {
            $this->syncers['invoices'] = new WZI_Sync_Invoices();
        }
        
        if ($this->is_sync_enabled('coupons')) {
            $this->syncers['coupons'] = new WZI_Sync_Coupons();
        }
    }

    /**
     * Verificar si un tipo de sincronización específico está habilitado en la configuración.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $type    El tipo de sincronización (ej. 'customers', 'products').
     * @return   bool               True si está habilitado, false en caso contrario.
     */
    private function is_sync_enabled($type) {
        $key = 'sync_' . $type;
        return isset($this->sync_config[$key]) && $this->sync_config[$key] === 'yes';
    }

    /**
     * Iniciar sincronización manual.
     *
     * @since    1.0.0
     * @param    string    $sync_type    Tipo de sincronización (all, customers, orders, etc).
     * @param    string    $direction    Dirección (woo_to_zoho, zoho_to_woo, both).
     * @return   array                   Resultado de la operación.
     */
    public function start_manual_sync($sync_type = 'all', $direction = null) {
        // Verificar si se ha excedido el límite de la API
        $services = array('crm', 'inventory', 'books', 'campaigns');
        foreach ($services as $service) {
            $rate_limit = get_transient('wzi_rate_limit_' . $service);
            if ($rate_limit && $rate_limit['remaining'] <= 0) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        __('Se ha excedido el límite de la API de Zoho para el servicio %s. Por favor, espere %s antes de volver a intentarlo.', 'woocommerce-zoho-integration'),
                        ucfirst($service),
                        human_time_diff(time(), $rate_limit['reset'])
                    ),
                );
            }
        }

        // Verificar si ya hay una sincronización en curso
        if ($this->is_sync_running()) {
            return array(
                'success' => false,
                'message' => __('Ya hay una sincronización en curso', 'woocommerce-zoho-integration'),
                'status' => $this->sync_status,
            );
        }
        
        // Usar dirección de configuración si no se especifica
        if (!$direction) {
            $direction = isset($this->sync_config['sync_direction']) ? $this->sync_config['sync_direction'] : 'both';
        }
        
        // Iniciar sincronización
        $this->set_sync_status(array(
            'is_running' => true,
            'current_type' => $sync_type,
            'progress' => 0,
            'total' => 0,
            'errors' => 0,
            'start_time' => current_time('mysql'),
        ));
        
        $this->logger->info('Manual sync started', array(
            'sync_type' => $sync_type,
            'direction' => $direction,
            'user_id' => get_current_user_id(),
        ));
        
        // Programar tarea en background
        $scheduled = wp_schedule_single_event(time(), 'wzi_run_manual_sync', array($sync_type, $direction));

        if ($scheduled === false) {
            $this->logger->error('Failed to schedule manual sync.', array(
                'sync_type' => $sync_type,
                'direction' => $direction,
            ));
            return array(
                'success' => false,
                'message' => __('Failed to schedule manual sync.', 'woocommerce-zoho-integration'),
                'status' => $this->sync_status,
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Sincronización iniciada', 'woocommerce-zoho-integration'),
            'status' => $this->sync_status,
        );
    }

    /**
     * Ejecutar sincronización programada.
     *
     * @since    1.0.0
     * @return   array    Resultado de la sincronización.
     */
    public function run_scheduled_sync() {
        $general_settings = get_option('wzi_general_settings', array());
        
        // Verificar si la sincronización está habilitada
        if (!isset($general_settings['enable_sync']) || $general_settings['enable_sync'] !== 'yes') {
            $this->logger->info('Scheduled sync skipped - sync disabled');
            return array('success' => false, 'message' => 'Sync disabled');
        }
        
        // Verificar modo de sincronización
        $sync_mode = isset($general_settings['sync_mode']) ? $general_settings['sync_mode'] : 'manual';
        if ($sync_mode === 'manual') {
            $this->logger->info('Scheduled sync skipped - manual mode');
            return array('success' => false, 'message' => 'Manual mode');
        }
        
        // Ejecutar sincronización
        return $this->execute_sync('all');
    }

    /**
     * Ejecutar sincronización.
     *
     * @since    1.0.0
     * @param    string    $sync_type    Tipo de sincronización.
     * @param    string    $direction    Dirección.
     * @return   array                   Resultado.
     */
    public function execute_sync($sync_type = 'all', $direction = null) {
        // Usar dirección de configuración si no se especifica
        if (!$direction) {
            $direction = isset($this->sync_config['sync_direction']) ? $this->sync_config['sync_direction'] : 'both';
        }
        
        $results = array(
            'success' => true,
            'synced' => array(),
            'errors' => array(),
            'total_synced' => 0,
            'total_errors' => 0,
        );
        
        try {
            if ($sync_type === 'all') {
                // Sincronizar todos los tipos habilitados
                foreach ($this->syncers as $type => $syncer) {
                    $this->update_sync_progress($type, 0, 100);
                    $result = $this->sync_type($type, $direction);
                    
                    $results['synced'][$type] = $result['synced'];
                    $results['total_synced'] += $result['synced'];
                    
                    if (!empty($result['errors'])) {
                        $results['errors'][$type] = $result['errors'];
                        $results['total_errors'] += count($result['errors']);
                    }
                }
            } else {
                // Sincronizar tipo específico
                if (isset($this->syncers[$sync_type])) {
                    $result = $this->sync_type($sync_type, $direction);
                    
                    $results['synced'][$sync_type] = $result['synced'];
                    $results['total_synced'] = $result['synced'];
                    
                    if (!empty($result['errors'])) {
                        $results['errors'][$sync_type] = $result['errors'];
                        $results['total_errors'] = count($result['errors']);
                    }
                } else {
                    throw new Exception(sprintf(
                        __('Tipo de sincronización no válido: %s', 'woocommerce-zoho-integration'),
                        $sync_type
                    ));
                }
            }
            
            // Registrar resultado
            $this->logger->info('Sync completed', array(
                'sync_type' => $sync_type,
                'direction' => $direction,
                'results' => $results,
            ));
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['message'] = $e->getMessage();
            
            $this->logger->error('Sync failed', array(
                'sync_type' => $sync_type,
                'direction' => $direction,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ));
        }
        
        // Finalizar sincronización
        $this->set_sync_status(array(
            'is_running' => false,
            'current_type' => '',
            'progress' => 100,
            'total' => $results['total_synced'],
            'errors' => $results['total_errors'],
            'start_time' => null,
        ));
        
        return $results;
    }

    /**
     * Sincronizar un tipo de entidad específico (ej. clientes, productos).
     *
     * Delega la sincronización al sincronizador correspondiente para el tipo dado,
     * considerando la dirección de la sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $type        El tipo de entidad a sincronizar (ej. 'customers', 'products').
     * @param    string    $direction   La dirección de la sincronización ('woo_to_zoho', 'zoho_to_woo', 'both').
     * @return   array                  Un array con los resultados: ['synced' => int, 'errors' => array].
     */
    private function sync_type($type, $direction) {
        if (!isset($this->syncers[$type])) {
            return array(
                'success' => false,
                'synced' => 0,
                'errors' => array(__('Sincronizador no disponible', 'woocommerce-zoho-integration')),
            );
        }
        
        $syncer = $this->syncers[$type];
        $batch_size = isset($this->sync_config['batch_size']) ? intval($this->sync_config['batch_size']) : 50;
        
        $this->logger->info('Starting sync for type', array(
            'type' => $type,
            'direction' => $direction,
            'batch_size' => $batch_size,
        ));
        
        $result = array(
            'synced' => 0,
            'errors' => array(),
        );
        
        try {
            // Sincronizar según dirección
            if ($direction === 'woo_to_zoho' || $direction === 'both') {
                $woo_result = $syncer->sync_from_woocommerce($batch_size);
                $result['synced'] += $woo_result['synced'];
                $result['errors'] = array_merge($result['errors'], $woo_result['errors']);
            }
            
            if ($direction === 'zoho_to_woo' || $direction === 'both') {
                $zoho_result = $syncer->sync_from_zoho($batch_size);
                $result['synced'] += $zoho_result['synced'];
                $result['errors'] = array_merge($result['errors'], $zoho_result['errors']);
            }
            
            $result['success'] = empty($result['errors']);
            
        } catch (Exception $e) {
            $result['success'] = false;
            $result['errors'][] = $e->getMessage();
            
            $this->logger->error('Sync type failed', array(
                'type' => $type,
                'error' => $e->getMessage(),
            ));
        }
        
        return $result;
    }

    /**
     * Añadir elemento a la cola de sincronización.
     *
     * @since    1.0.0
     * @param    string    $item_type    Tipo de elemento.
     * @param    int       $item_id      ID del elemento.
     * @param    string    $action       Acción (create, update, delete).
     * @param    array     $data         Datos adicionales.
     * @param    int       $priority     Prioridad (1-10).
     * @return   int|false               ID de la cola o false.
     */
    public function add_to_queue($item_type, $item_id, $action, $data = array(), $priority = 5) {
        global $wpdb;
        
        // Verificar si ya existe en la cola
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->queue_table} 
             WHERE item_type = %s AND item_id = %d AND status = 'pending'",
            $item_type,
            $item_id
        ));
        
        if ($existing) {
            // Actualizar prioridad si es mayor
            $wpdb->update(
                $this->queue_table,
                array(
                    'priority' => $priority,
                    'data' => json_encode($data),
                ),
                array('id' => $existing),
                array('%d', '%s'),
                array('%d')
            );
            
            return $existing;
        }
        
        // Insertar nuevo elemento
        $result = $wpdb->insert(
            $this->queue_table,
            array(
                'item_type' => $item_type,
                'item_id' => $item_id,
                'action' => $action,
                'priority' => $priority,
                'status' => 'pending',
                'data' => json_encode($data),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            $this->logger->error('Failed to add to queue', array(
                'item_type' => $item_type,
                'item_id' => $item_id,
                'error' => $wpdb->last_error,
            ));
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Procesar cola de sincronización.
     *
     * @since    1.0.0
     * @param    int    $batch_size    Tamaño del lote.
     * @return   array                 Resultado del procesamiento.
     */
    public function process_queue($batch_size = null) {
        global $wpdb;
        
        if (!$batch_size) {
            $batch_size = isset($this->sync_config['batch_size']) ? intval($this->sync_config['batch_size']) : 50;
        }
        
        // Obtener elementos pendientes
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} 
             WHERE status = 'pending' AND attempts < 3 
             ORDER BY priority DESC, created_at ASC 
             LIMIT %d",
            $batch_size
        ));
        
        if (empty($items)) {
            return array(
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
            );
        }
        
        $results = array(
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
        );
        
        foreach ($items as $item) {
            // Marcar como procesando
            $wpdb->update(
                $this->queue_table,
                array(
                    'status' => 'processing',
                    'last_attempt' => current_time('mysql'),
                    'attempts' => $item->attempts + 1,
                ),
                array('id' => $item->id),
                array('%s', '%s', '%d'),
                array('%d')
            );
            
            // Procesar elemento
            $success = $this->process_queue_item($item);
            
            if ($success) {
                // Marcar como completado
                $wpdb->update(
                    $this->queue_table,
                    array('status' => 'completed'),
                    array('id' => $item->id),
                    array('%s'),
                    array('%d')
                );
                
                $results['success']++;
            } else {
                // Marcar como fallido o pendiente según intentos
                $status = $item->attempts >= 2 ? 'failed' : 'pending';
                
                $wpdb->update(
                    $this->queue_table,
                    array('status' => $status),
                    array('id' => $item->id),
                    array('%s'),
                    array('%d')
                );
                
                $results['failed']++;
            }
            
            $results['processed']++;
        }
        
        $this->logger->info('Queue processed', $results);
        
        return $results;
    }

    /**
     * Procesar un único elemento de la cola de sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @param    object    $item    El objeto del elemento de la cola desde la base de datos.
     * @return   bool               True si el procesamiento fue exitoso, false en caso contrario.
     */
    private function process_queue_item($item) {
        try {
            $data = json_decode($item->data, true);
            
            // Determinar sincronizador
            $syncer_type = $this->get_syncer_type_for_item($item->item_type);
            
            if (!isset($this->syncers[$syncer_type])) {
                throw new Exception('Syncer not available: ' . $syncer_type);
            }
            
            $syncer = $this->syncers[$syncer_type];
            
            // Procesar según acción
            switch ($item->action) {
                case 'create':
                case 'update':
                    return $syncer->sync_single($item->item_id, $data);
                    
                case 'delete':
                    return $syncer->delete_single($item->item_id, $data);
                    
                default:
                    throw new Exception('Unknown action: ' . $item->action);
            }
            
        } catch (Exception $e) {
            $this->logger->error('Queue item processing failed', array(
                'item_id' => $item->id,
                'item_type' => $item->item_type,
                'error' => $e->getMessage(),
            ));
            
            return false;
        }
    }

    /**
     * Obtener el tipo de sincronizador (key en $this->syncers) para un tipo de objeto dado.
     *
     * Por ejemplo, tanto 'customer' como 'user' pueden mapear al sincronizador 'customers'.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $item_type    El tipo de objeto (ej. 'customer', 'shop_order').
     * @return   string                  El tipo de sincronizador correspondiente (ej. 'customers', 'orders').
     */
    private function get_syncer_type_for_item($item_type) {
        $mapping = array(
            'customer' => 'customers',
            'user' => 'customers',
            'order' => 'orders',
            'shop_order' => 'orders',
            'product' => 'products',
            'product_variation' => 'products',
            'invoice' => 'invoices',
            'coupon' => 'coupons',
            'shop_coupon' => 'coupons',
        );
        
        return isset($mapping[$item_type]) ? $mapping[$item_type] : $item_type;
    }

    /**
     * Manejar webhook.
     *
     * @since    1.0.0
     * @param    string    $service    Servicio de origen.
     * @param    array     $data       Datos del webhook.
     * @return   array                 Resultado del procesamiento.
     */
    public function handle_webhook($service, $data) {
        $this->logger->info('Webhook received', array(
            'service' => $service,
            'event' => isset($data['event']) ? $data['event'] : 'unknown',
        ));
        
        try {
            // Determinar tipo de entidad y acción
            $entity_type = isset($data['entity_type']) ? $data['entity_type'] : '';
            $action = isset($data['action']) ? $data['action'] : '';
            $entity_data = isset($data['data']) ? $data['data'] : array();
            
            // Mapear a tipo de sincronización
            $sync_type = $this->map_webhook_to_sync_type($service, $entity_type);
            
            if (!$sync_type || !isset($this->syncers[$sync_type])) {
                throw new Exception('Unknown entity type: ' . $entity_type);
            }
            
            // Procesar webhook
            $syncer = $this->syncers[$sync_type];
            $result = $syncer->handle_webhook($action, $entity_data);
            
            return array(
                'success' => true,
                'message' => 'Webhook processed',
                'result' => $result,
            );
            
        } catch (Exception $e) {
            $this->logger->error('Webhook processing failed', array(
                'service' => $service,
                'error' => $e->getMessage(),
            ));
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Mapear una entidad de webhook de Zoho a un tipo de sincronizador del plugin.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $service        El servicio de Zoho que envió el webhook (ej. 'crm', 'inventory').
     * @param    string    $entity_type    El tipo de entidad de Zoho (ej. 'Contacts', 'Items').
     * @return   string                    El tipo de sincronizador del plugin o una cadena vacía si no hay mapeo.
     */
    private function map_webhook_to_sync_type($service, $entity_type) {
        $mapping = array(
            'crm' => array(
                'Contacts' => 'customers',
                'Leads' => 'customers',
                'Deals' => 'orders',
                'Sales_Orders' => 'orders',
            ),
            'inventory' => array(
                'items' => 'products',
                'salesorders' => 'orders',
                'contacts' => 'customers',
            ),
            'books' => array(
                'invoices' => 'invoices',
                'contacts' => 'customers',
                'items' => 'products',
            ),
            'campaigns' => array(
                'campaigns' => 'coupons',
                'lists' => 'customers',
            ),
        );
        
        return isset($mapping[$service][$entity_type]) ? $mapping[$service][$entity_type] : '';
    }

    /**
     * Obtener estado de sincronización.
     *
     * @since    1.0.0
     * @return   array    Estado actual.
     */
    public function get_sync_status() {
        global $wpdb;
        
        // Añadir información de cola
        $this->sync_status['queue'] = array(
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'pending'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'processing'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'failed'"),
        );
        
        // Añadir última sincronización
        $logs_table_name = $wpdb->prefix . 'wzi_sync_logs'; // Definir nombre de tabla para claridad
        $last_sync = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$logs_table_name}
             WHERE source != %s
             ORDER BY timestamp DESC
             LIMIT 1",
            'general'
        ));
        
        if ($last_sync) {
            // Usar los nombres de columna correctos del objeto $last_sync
            // Asumiendo que 'status' se refiere a 'log_level' y 'sync_type' a 'source'
            $this->sync_status['last_sync'] = array(
                'type' => $last_sync->source,       // sync_type -> source
                'status' => $last_sync->log_level,  // status -> log_level
                'time' => $last_sync->timestamp,   // created_at -> timestamp
            );
        }
        
        return $this->sync_status;
    }

    /**
     * Verificar si hay sincronización en curso.
     *
     * @since    1.0.0
     * @return   bool    Si hay sincronización en curso.
     */
    public function is_sync_running() {
        return isset($this->sync_status['is_running']) && $this->sync_status['is_running'];
    }

    /**
     * Actualizar el progreso de la sincronización actual.
     *
     * Guarda el estado actual en un transient para que pueda ser consultado.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $type        El tipo de entidad que se está sincronizando actualmente.
     * @param    int       $current     El número de ítems procesados.
     * @param    int       $total       El número total de ítems a procesar.
     * @return   void
     */
    private function update_sync_progress($type, $current, $total) {
        $this->sync_status['current_type'] = $type;
        $this->sync_status['progress'] = $total > 0 ? round(($current / $total) * 100) : 0;
        $this->sync_status['total'] = $total;
        
        set_transient('wzi_sync_status', $this->sync_status, 300);
    }

    /**
     * Establecer/actualizar el estado general de la sincronización.
     *
     * Guarda el estado en un transient.
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $status    Un array con los datos del estado a actualizar.
     * @return   void
     */
    private function set_sync_status($status) {
        $this->sync_status = array_merge($this->sync_status, $status);
        set_transient('wzi_sync_status', $this->sync_status, 300);
    }

    /**
     * Limpiar cola.
     *
     * @since    1.0.0
     * @param    string    $status    Estado a limpiar (null para todos).
     * @return   int                  Número de elementos eliminados.
     */
    public function clear_queue($status = null) {
        global $wpdb;
        
        if ($status) {
            $deleted = $wpdb->delete(
                $this->queue_table,
                array('status' => $status),
                array('%s')
            );
        } else {
            $deleted = $wpdb->query("TRUNCATE TABLE {$this->queue_table}");
        }
        
        $this->logger->info('Queue cleared', array(
            'status' => $status,
            'deleted' => $deleted,
        ));
        
        return $deleted;
    }

    /**
     * Obtener estadísticas de sincronización.
     *
     * @since    1.0.0
     * @param    string    $period    Período (today, week, month, all).
     * @return   array                Estadísticas.
     */
    public function get_sync_stats($period = 'all') {
        global $wpdb;
        
        $date_condition = '';
        $logs_table_name = $wpdb->prefix . 'wzi_sync_logs'; // Definir nombre de tabla
        
        switch ($period) {
            case 'today':
                $date_condition = " AND DATE(timestamp) = CURDATE()"; // No necesita prepare si no hay placeholders
                break;
            case 'week':
                $date_condition = " AND timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; // No necesita prepare
                break;
            case 'month':
                $date_condition = " AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; // No necesita prepare
                break;
        }
        
        // Estadísticas por tipo (source) y nivel de log (log_level)
        // Se omite sync_direction ya que no está en la tabla de logs.
        // Se asume que 'status' en la lógica original se refería al log_level (e.g., 'INFO', 'ERROR')
        $query = $wpdb->prepare(
            "SELECT source, log_level, COUNT(*) as count
             FROM {$logs_table_name}
             WHERE source != %s {$date_condition}
             GROUP BY source, log_level",
            'general'
        );
        
        $results = $wpdb->get_results($query);
        
        $stats = array(
            'by_type' => array(), // Cambiado para reflejar la nueva estructura de agrupación
            'totals' => array(
                'INFO' => 0, // Contará todos los INFO como éxito general
                'ERROR' => 0, // Contará todos los ERROR
                'WARNING' => 0, // Contará todos los WARNING
                'DEBUG' => 0, // Contará todos los DEBUG
                'total_records' => 0, // Total de registros procesados en la consulta
            ),
        );
        
        // Mapeo de log_level a un 'status' más genérico si es necesario para la lógica de 'totals'
        // Por ejemplo, 'INFO' podría considerarse un éxito general para el conteo.
        $status_map = [
            'INFO' => 'INFO',
            'ERROR' => 'ERROR',
            'WARNING' => 'WARNING',
            'DEBUG' => 'DEBUG',
            // Otros log_levels si existen
        ];

        foreach ($results as $row) {
            $current_source = $row->source;
            $current_log_level = strtoupper($row->log_level); // Normalizar a mayúsculas
            $count = intval($row->count);

            if (!isset($stats['by_type'][$current_source])) {
                $stats['by_type'][$current_source] = array(
                    // Ya no desglosamos por sync_direction aquí, solo por log_level
                    'INFO' => 0,
                    'ERROR' => 0,
                    'WARNING' => 0,
                    'DEBUG' => 0,
                    'OTHER' => 0, // Para log_levels no mapeados explícitamente
                );
            }
            
            if (isset($stats['by_type'][$current_source][$current_log_level])) {
                $stats['by_type'][$current_source][$current_log_level] += $count;
            } else {
                $stats['by_type'][$current_source]['OTHER'] += $count; // Acumular niveles no esperados en 'OTHER'
            }
            
            // Actualizar totales generales
            if (isset($status_map[$current_log_level])) {
                $stats['totals'][$status_map[$current_log_level]] += $count;
            }
            $stats['totals']['total_records'] += $count;
        }
        
        return $stats;
    }
}