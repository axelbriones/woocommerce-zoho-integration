<?php
// admin/includes/class-wzi-logs-list-table.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WZI_Logs_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Log', 'woocommerce-zoho-integration' ),
            'plural'   => __( 'Logs', 'woocommerce-zoho-integration' ),
            'ajax'     => false,
        ) );
    }

    public static function get_logs_data( $per_page = 20, $page_number = 1, $orderby = 'timestamp', $order = 'desc', $search_term = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wzi_sync_logs';

        // Check if table exists before querying
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array(); // Return empty array if table doesn't exist
        }

        $sql = "SELECT * FROM {$table_name}";
        $prepare_args = array();

        if ( ! empty( $search_term ) ) {
            $search_like = '%' . $wpdb->esc_like( $search_term ) . '%';
            $sql .= " WHERE (message LIKE %s OR source LIKE %s OR object_type LIKE %s OR object_id LIKE %s OR log_level LIKE %s)";
            array_push($prepare_args, $search_like, $search_like, $search_like, $search_like, $search_like);
        }

        $valid_orderby = array('log_id', 'timestamp', 'log_level', 'source', 'object_type', 'object_id');
        if ( !in_array(strtolower($orderby), $valid_orderby) ) {
            $orderby = 'timestamp';
        }
        if ( !in_array(strtolower($order), array('asc', 'desc')) ) {
            $order = 'desc';
        }

        $sql .= " ORDER BY `{$orderby}` " . strtoupper($order); // Use backticks for column names in ORDER BY
        $sql .= " LIMIT %d";
        $sql .= " OFFSET %d";
        array_push($prepare_args, $per_page, ( $page_number - 1 ) * $per_page);

        if (!empty($prepare_args)) {
            $query = $wpdb->prepare($sql, $prepare_args);
        } else {
            $query = $sql; // Should not happen if per_page is always set
        }

        $result = $wpdb->get_results( $query, ARRAY_A );
        return $result;
    }

    public static function get_log_count( $search_term = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wzi_sync_logs';

        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0; // Return 0 if table doesn't exist
        }

        $sql = "SELECT COUNT(*) FROM {$table_name}";
        $prepare_args = array();

        if ( ! empty( $search_term ) ) {
             $search_like = '%' . $wpdb->esc_like( $search_term ) . '%';
             $sql .= " WHERE (message LIKE %s OR source LIKE %s OR object_type LIKE %s OR object_id LIKE %s OR log_level LIKE %s)";
             array_push($prepare_args, $search_like, $search_like, $search_like, $search_like, $search_like);
        }

        if (!empty($prepare_args)) {
            $query = $wpdb->prepare($sql, $prepare_args);
        } else {
            $query = $sql;
        }
        return $wpdb->get_var( $query );
    }

    public function no_items() {
        _e( 'No logs found.', 'woocommerce-zoho-integration' );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'log_id':
            case 'timestamp':
            case 'log_level':
            case 'source':
            case 'object_type':
            case 'object_id':
                return esc_html( $item[ $column_name ] );
            case 'message':
                 $message_short = mb_strimwidth(strip_tags($item[$column_name]), 0, 100, "...");
                 $full_message_id = 'wzi-log-message-' . $item['log_id'];
                 $output = esc_html($message_short);
                 if (strlen($item[$column_name]) > 100) {
                     $output .= ' <a href="#" class="wzi-read-more" data-target="' . $full_message_id . '">' . esc_html__('Read more', 'woocommerce-zoho-integration') . '</a>';
                     $output .= '<div id="' . $full_message_id . '" style="display:none; white-space: pre-wrap; word-break: break-all;">' . esc_html($item[$column_name]) . '</div>';
                 }
                 return $output;
            case 'details':
                return !empty($item[$column_name]) ? '<pre style="white-space: pre-wrap; word-break: break-all;">' . esc_html($item[$column_name]) . '</pre>' : '';
            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : print_r( $item, true );
        }
    }

    function get_columns() {
        $columns = array(
            'timestamp'   => __( 'Timestamp', 'woocommerce-zoho-integration' ),
            'log_level'   => __( 'Level', 'woocommerce-zoho-integration' ),
            'source'      => __( 'Source', 'woocommerce-zoho-integration' ),
            'object_type' => __( 'Object Type', 'woocommerce-zoho-integration' ),
            'object_id'   => __( 'Object ID', 'woocommerce-zoho-integration' ),
            'message'     => __( 'Message', 'woocommerce-zoho-integration' ),
            'details'     => __( 'Details', 'woocommerce-zoho-integration' ),
        );
        return $columns;
    }

    public function get_sortable_columns() {
        $sortable_columns = array(
            'timestamp' => array( 'timestamp', true ),
            'log_level' => array( 'log_level', false ),
            'source'    => array( 'source', false ),
            'object_type' => array( 'object_type', false ),
        );
        return $sortable_columns;
    }

    public function prepare_items() {
        $this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

        $per_page     = $this->get_items_per_page( 'wzi_logs_per_page', 20 );
        $current_page = $this->get_pagenum();
        $search_term  = ( isset( $_REQUEST['s'] ) && is_string($_REQUEST['s']) ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

        $orderby = ( ! empty( $_REQUEST['orderby'] ) && is_string($_REQUEST['orderby']) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'timestamp';
        $order   = ( ! empty( $_REQUEST['order'] ) && is_string($_REQUEST['order']) ) ? strtoupper(sanitize_key( $_REQUEST['order'] )) : 'DESC';

        $total_items  = self::get_log_count( $search_term );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );

        $this->items = self::get_logs_data( $per_page, $current_page, $orderby, $order, $search_term );
    }

    // Puedes añadir bulk actions si lo necesitas más adelante, por ejemplo, para borrar logs seleccionados.
    // public function get_bulk_actions() {
    //    $actions = array(
    //        'bulk-delete' => 'Delete'
    //    );
    //    return $actions;
    // }
    // public function process_bulk_action() { ... }
}
?>
<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.wzi-read-more').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).slideToggle();
    });
});
</script>
