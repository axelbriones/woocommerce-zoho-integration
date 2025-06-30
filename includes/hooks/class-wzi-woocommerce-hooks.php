<?php
/**
 * Hooks de WooCommerce
 *
 * @link       https://tudominio.com
 * @since      1.0.0
 *
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/hooks
 */

/**
 * Hooks de WooCommerce.
 *
 * Esta clase maneja todos los hooks de WooCommerce para
 * activar la sincronización cuando ocurren eventos.
 *
 * @since      1.0.0
 * @package    WooCommerce_Zoho_Integration
 * @subpackage WooCommerce_Zoho_Integration/includes/hooks
 * @author     Tu Nombre <tu@email.com>
 */
class WZI_WooCommerce_Hooks {

    /**
     * Gestor de sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Sync_Manager    $sync_manager    Instancia del gestor de sincronización.
     */
    private $sync_manager;

    /**
     * Logger.
     *
     * @since    1.0.0
     * @access   private
     * @var      WZI_Logger    $logger    Instancia del logger.
     */
    private $logger;

    /**
     * Configuración general.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $general_settings    Configuración general.
     */
    private $general_settings;

    /**
     * Configuración de sincronización.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $sync_settings    Configuración de sincronización.
     */
    private $sync_settings;

    /**
     * Constructor.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->sync_manager = new WZI_Sync_Manager();
        $this->logger = new WZI_Logger();
        
        // Cargar configuraciones
        $this->general_settings = get_option('wzi_general_settings', array());
        $this->sync_settings = get_option('wzi_sync_settings', array());
    }

    /**
     * Verificar si la sincronización está habilitada.
     *
     * @since    1.0.0
     * @return   bool    Si la sincronización está habilitada.
     */
    private function is_sync_enabled() {
        return isset($this->general_settings['enable_sync']) && 
               $this->general_settings['enable_sync'] === 'yes';
    }

    /**
     * Verificar si la sincronización en tiempo real está habilitada.
     *
     * @since    1.0.0
     * @return   bool    Si la sincronización en tiempo real está habilitada.
     */
    private function is_realtime_sync() {
        return isset($this->general_settings['sync_mode']) && 
               $this->general_settings['sync_mode'] === 'realtime';
    }

    /**
     * Verificar si un tipo de sincronización está habilitado.
     *
     * @since    1.0.0
     * @param    string    $type    Tipo de sincronización.
     * @return   bool               Si está habilitado.
     */
    private function is_type_enabled($type) {
        $key = 'sync_' . $type;
        return isset($this->sync_settings[$key]) && 
               $this->sync_settings[$key] === 'yes';
    }

    /**
     * Hook: Cliente registrado.
     *
     * @since    1.0.0
     * @param    int    $user_id    ID del usuario.
     */
    public function sync_customer_on_register($user_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('customers')) {
            return;
        }
        
        $this->logger->debug('Customer registered hook triggered', array(
            'user_id' => $user_id,
        ));
        
        // Verificar si es un cliente (no otro tipo de usuario)
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('customer', $user->roles)) {
            return;
        }
        
        if ($this->is_realtime_sync()) {
            // Sincronización inmediata
            $this->sync_manager->add_to_queue('customer', $user_id, 'create', array(), 10);
            $this->sync_manager->process_queue(1);
        } else {
            // Añadir a la cola para sincronización posterior
            $this->sync_manager->add_to_queue('customer', $user_id, 'create');
        }
    }

    /**
     * Hook: Perfil de cliente actualizado.
     *
     * @since    1.0.0
     * @param    int       $user_id       ID del usuario.
     * @param    WP_User   $old_user_data Datos antiguos del usuario.
     */
    public function sync_customer_on_update($user_id, $old_user_data = null) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('customers')) {
            return;
        }
        
        $this->logger->debug('Customer updated hook triggered', array(
            'user_id' => $user_id,
        ));
        
        // Verificar si es un cliente
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('customer', $user->roles)) {
            return;
        }
        
        // Verificar si ya está sincronizado
        $zoho_id = get_user_meta($user_id, '_wzi_zoho_contact_id', true);
        $action = $zoho_id ? 'update' : 'create';
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('customer', $user_id, $action, array(), 10);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('customer', $user_id, $action);
        }
    }

    /**
     * Hook: Dirección de cliente actualizada.
     *
     * @since    1.0.0
     * @param    int       $user_id    ID del usuario.
     * @param    string    $load_address Tipo de dirección (billing/shipping).
     */
    public function sync_customer_on_address_update($user_id, $load_address) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('customers')) {
            return;
        }
        
        $this->logger->debug('Customer address updated hook triggered', array(
            'user_id' => $user_id,
            'address_type' => $load_address,
        ));
        
        // Reutilizar la función de actualización
        $this->sync_customer_on_update($user_id);
    }

    /**
     * Hook: Pedido procesado en checkout.
     *
     * @since    1.0.0
     * @param    int       $order_id    ID del pedido.
     * @param    array     $posted_data Datos enviados.
     * @param    WC_Order  $order       Objeto del pedido.
     */
    public function sync_order_on_checkout($order_id, $posted_data, $order) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('orders')) {
            return;
        }
        
        $this->logger->debug('Order checkout hook triggered', array(
            'order_id' => $order_id,
            'order_status' => $order->get_status(),
        ));
        
        // También sincronizar el cliente si es nuevo
        $customer_id = $order->get_customer_id();
        if ($customer_id && $this->is_type_enabled('customers')) {
            $zoho_contact_id = get_user_meta($customer_id, '_wzi_zoho_contact_id', true);
            if (!$zoho_contact_id) {
                $this->sync_manager->add_to_queue('customer', $customer_id, 'create', array(), 10);
            }
        }
        
        // Sincronizar pedido
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('order', $order_id, 'create', array(), 10);
            $this->sync_manager->process_queue(2); // Procesar cliente y pedido
        } else {
            $this->sync_manager->add_to_queue('order', $order_id, 'create');
        }
    }

    /**
     * Hook: Estado de pedido cambiado.
     *
     * @since    1.0.0
     * @param    int       $order_id    ID del pedido.
     * @param    string    $from        Estado anterior.
     * @param    string    $to          Nuevo estado.
     * @param    WC_Order  $order       Objeto del pedido.
     */
    public function sync_order_on_status_change($order_id, $from, $to, $order) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('orders')) {
            return;
        }
        
        $this->logger->debug('Order status changed hook triggered', array(
            'order_id' => $order_id,
            'from_status' => $from,
            'to_status' => $to,
        ));
        
        // Verificar si ya está sincronizado
        $zoho_deal_id = $order->get_meta('_wzi_zoho_deal_id');
        $zoho_so_id = $order->get_meta('_wzi_zoho_sales_order_id');
        
        // Si no está sincronizado y es un estado válido, crear
        if (!$zoho_deal_id && !$zoho_so_id && in_array($to, array('processing', 'completed', 'on-hold'))) {
            $action = 'create';
        } else {
            $action = 'update';
        }
        
        // Datos adicionales para el cambio de estado
        $data = array(
            'previous_status' => $from,
            'new_status' => $to,
            'status_change' => true,
        );
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('order', $order_id, $action, $data, 10);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('order', $order_id, $action, $data);
        }
    }

    /**
     * Hook: Producto creado.
     *
     * @since    1.0.0
     * @param    int    $product_id    ID del producto.
     */
    public function sync_product_on_create($product_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('products')) {
            return;
        }
        
        $this->logger->debug('Product created hook triggered', array(
            'product_id' => $product_id,
        ));
        
        // Verificar si es un producto (no variación)
        $product = wc_get_product($product_id);
        if (!$product || $product->is_type('variation')) {
            return;
        }
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('product', $product_id, 'create', array(), 8);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('product', $product_id, 'create');
        }
    }

    /**
     * Hook: Producto actualizado.
     *
     * @since    1.0.0
     * @param    int    $product_id    ID del producto.
     */
    public function sync_product_on_update($product_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('products')) {
            return;
        }
        
        $this->logger->debug('Product updated hook triggered', array(
            'product_id' => $product_id,
        ));
        
        // Verificar si es un producto (no variación)
        $product = wc_get_product($product_id);
        if (!$product || $product->is_type('variation')) {
            return;
        }
        
        // Verificar si ya está sincronizado
        $zoho_id = get_post_meta($product_id, '_wzi_zoho_product_id', true);
        $action = $zoho_id ? 'update' : 'create';
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('product', $product_id, $action, array(), 8);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('product', $product_id, $action);
        }
    }

    /**
     * Hook: Antes de eliminar producto.
     *
     * @since    1.0.0
     * @param    int    $post_id    ID del post.
     */
    public function sync_product_on_delete($post_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('products')) {
            return;
        }
        
        // Verificar si es un producto
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        $this->logger->debug('Product delete hook triggered', array(
            'product_id' => $post_id,
        ));
        
        // Verificar si está sincronizado
        $zoho_id = get_post_meta($post_id, '_wzi_zoho_product_id', true);
        
        if ($zoho_id) {
            $data = array(
                'zoho_id' => $zoho_id,
            );
            
            if ($this->is_realtime_sync()) {
                $this->sync_manager->add_to_queue('product', $post_id, 'delete', $data, 10);
                $this->sync_manager->process_queue(1);
            } else {
                $this->sync_manager->add_to_queue('product', $post_id, 'delete', $data);
            }
        }
    }

    /**
     * Hook: Stock de producto actualizado.
     *
     * @since    1.0.0
     * @param    WC_Product    $product    Objeto del producto.
     */
    public function sync_product_stock($product) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('products')) {
            return;
        }
        
        $this->logger->debug('Product stock updated hook triggered', array(
            'product_id' => $product->get_id(),
            'stock_quantity' => $product->get_stock_quantity(),
        ));
        
        // Solo sincronizar si el producto gestiona stock
        if (!$product->managing_stock()) {
            return;
        }
        
        $data = array(
            'stock_update' => true,
            'new_stock' => $product->get_stock_quantity(),
        );
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('product', $product->get_id(), 'update', $data, 9);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('product', $product->get_id(), 'update', $data);
        }
    }

    /**
     * Hook: Cupón creado.
     *
     * @since    1.0.0
     * @param    int    $coupon_id    ID del cupón.
     */
    public function sync_coupon_on_create($coupon_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('coupons')) {
            return;
        }
        
        $this->logger->debug('Coupon created hook triggered', array(
            'coupon_id' => $coupon_id,
        ));
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('coupon', $coupon_id, 'create', array(), 5);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('coupon', $coupon_id, 'create');
        }
    }

    /**
     * Hook: Cupón actualizado.
     *
     * @since    1.0.0
     * @param    int    $coupon_id    ID del cupón.
     */
    public function sync_coupon_on_update($coupon_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('coupons')) {
            return;
        }
        
        $this->logger->debug('Coupon updated hook triggered', array(
            'coupon_id' => $coupon_id,
        ));
        
        // Verificar si ya está sincronizado
        $zoho_id = get_post_meta($coupon_id, '_wzi_zoho_campaign_id', true);
        $action = $zoho_id ? 'update' : 'create';
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('coupon', $coupon_id, $action, array(), 5);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('coupon', $coupon_id, $action);
        }
    }

    /**
     * Hook: Cliente eliminado.
     *
     * @since    1.0.0
     * @param    int    $user_id    ID del usuario.
     */
    public function sync_customer_on_delete($user_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('customers')) {
            return;
        }
        
        // Verificar si tiene ID de Zoho
        $zoho_id = get_user_meta($user_id, '_wzi_zoho_contact_id', true);
        
        if ($zoho_id) {
            $this->logger->debug('Customer delete hook triggered', array(
                'user_id' => $user_id,
                'zoho_id' => $zoho_id,
            ));
            
            $data = array(
                'zoho_id' => $zoho_id,
            );
            
            if ($this->is_realtime_sync()) {
                $this->sync_manager->add_to_queue('customer', $user_id, 'delete', $data, 10);
                $this->sync_manager->process_queue(1);
            } else {
                $this->sync_manager->add_to_queue('customer', $user_id, 'delete', $data);
            }
        }
    }

    /**
     * Hook: Pedido eliminado.
     *
     * @since    1.0.0
     * @param    int    $order_id    ID del pedido.
     */
    public function sync_order_on_delete($order_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('orders')) {
            return;
        }
        
        // Obtener IDs de Zoho antes de que se elimine
        $zoho_deal_id = get_post_meta($order_id, '_wzi_zoho_deal_id', true);
        $zoho_so_id = get_post_meta($order_id, '_wzi_zoho_sales_order_id', true);
        
        if ($zoho_deal_id || $zoho_so_id) {
            $this->logger->debug('Order delete hook triggered', array(
                'order_id' => $order_id,
                'zoho_deal_id' => $zoho_deal_id,
                'zoho_so_id' => $zoho_so_id,
            ));
            
            $data = array(
                'zoho_deal_id' => $zoho_deal_id,
                'zoho_so_id' => $zoho_so_id,
            );
            
            if ($this->is_realtime_sync()) {
                $this->sync_manager->add_to_queue('order', $order_id, 'delete', $data, 10);
                $this->sync_manager->process_queue(1);
            } else {
                $this->sync_manager->add_to_queue('order', $order_id, 'delete', $data);
            }
        }
    }

    /**
     * Hook: Reembolso creado.
     *
     * @since    1.0.0
     * @param    int    $refund_id    ID del reembolso.
     * @param    array  $args         Argumentos del reembolso.
     */
    public function sync_order_on_refund($refund_id, $args) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('orders')) {
            return;
        }
        
        $order_id = isset($args['order_id']) ? $args['order_id'] : 0;
        
        if (!$order_id) {
            return;
        }
        
        $this->logger->debug('Order refund hook triggered', array(
            'order_id' => $order_id,
            'refund_id' => $refund_id,
            'amount' => isset($args['amount']) ? $args['amount'] : 0,
        ));
        
        $data = array(
            'refund' => true,
            'refund_id' => $refund_id,
            'refund_amount' => isset($args['amount']) ? $args['amount'] : 0,
            'refund_reason' => isset($args['reason']) ? $args['reason'] : '',
        );
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('order', $order_id, 'update', $data, 10);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('order', $order_id, 'update', $data);
        }
    }

    /**
     * Hook: Nota de pedido añadida.
     *
     * @since    1.0.0
     * @param    int    $comment_id    ID del comentario.
     * @param    WC_Order_Note $order  Objeto de la nota del pedido.
     */
    public function sync_order_on_note_added($comment_id, $order) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('orders')) {
            return;
        }
        
        // Solo sincronizar notas para clientes
        $comment = get_comment($comment_id);
        if (!$comment || $comment->comment_type !== 'order_note') {
            return;
        }
        
        $order_id = $comment->comment_post_ID;
        
        $this->logger->debug('Order note added hook triggered', array(
            'order_id' => $order_id,
            'note_id' => $comment_id,
        ));
        
        $data = array(
            'note_added' => true,
            'note_content' => $comment->comment_content,
            'note_date' => $comment->comment_date,
        );
        
        if ($this->is_realtime_sync()) {
            $this->sync_manager->add_to_queue('order', $order_id, 'update', $data, 5);
            $this->sync_manager->process_queue(1);
        } else {
            $this->sync_manager->add_to_queue('order', $order_id, 'update', $data);
        }
    }

    /**
     * Hook: Variación de producto creada.
     *
     * @since    1.0.0
     * @param    int    $variation_id    ID de la variación.
     */
    public function sync_variation_on_create($variation_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('products')) {
            return;
        }
        
        $variation = wc_get_product($variation_id);
        if (!$variation || !$variation->is_type('variation')) {
            return;
        }
        
        $this->logger->debug('Product variation created hook triggered', array(
            'variation_id' => $variation_id,
            'parent_id' => $variation->get_parent_id(),
        ));
        
        // Por ahora, sincronizar el producto padre
        $this->sync_product_on_update($variation->get_parent_id());
    }

    /**
     * Hook: Categoría de producto actualizada.
     *
     * @since    1.0.0
     * @param    int    $term_id    ID del término.
     * @param    int    $tt_id      ID de la taxonomía del término.
     */
    public function sync_products_on_category_update($term_id, $tt_id) {
        if (!$this->is_sync_enabled() || !$this->is_type_enabled('products')) {
            return;
        }
        
        $this->logger->debug('Product category updated hook triggered', array(
            'term_id' => $term_id,
        ));
        
        // Obtener productos en esta categoría
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $term_id,
                ),
            ),
            'fields' => 'ids',
        );
        
        $products = get_posts($args);
        
        foreach ($products as $product_id) {
            $this->sync_manager->add_to_queue('product', $product_id, 'update', array(
                'category_update' => true,
                'category_id' => $term_id,
            ));
        }
    }

    /**
     * Obtener instancia del sync manager.
     *
     * @since    1.0.0
     * @return   WZI_Sync_Manager    Instancia del sync manager.
     */
    public function get_sync_manager() {
        return $this->sync_manager;
    }
}