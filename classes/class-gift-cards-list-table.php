<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Gift_Cards_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Gift Card', 'gift-cards-for-woocommerce' ),
            'plural'   => __( 'Gift Cards', 'gift-cards-for-woocommerce' ),
            'ajax'     => false,
        ] );
    }

    /**
     * Define the columns that are going to be used in the table
     */
    public function get_columns() {
        $columns = [
            'code'            => __( 'Code', 'gift-cards-for-woocommerce' ),
            'balance'         => __( 'Balance', 'gift-cards-for-woocommerce' ),
            'recipient_email' => __( 'Recipient Email', 'gift-cards-for-woocommerce' ),
            'issued_date'     => __( 'Issued Date', 'gift-cards-for-woocommerce' ),
            'expiration_date' => __( 'Expiration Date', 'gift-cards-for-woocommerce' ),
            'actions'         => __( 'Actions', 'gift-cards-for-woocommerce' ), // New column
        ];
        return $columns;
    }

    /**
     * Prepare the items for the table to process
     */
    public function prepare_items() {
        global $wpdb;
    
        $table_name = $wpdb->prefix . 'gift_cards';
    
        $per_page = 20;
        $current_page = $this->get_pagenum();
    
        // Define column headers
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
    
        $this->_column_headers = [ $columns, $hidden, $sortable ];
    
        // Get the search parameter
        $search = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
    
        // Prepare the WHERE clause
        $where = [];
    
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[] = $wpdb->prepare( "(code LIKE %s OR recipient_email LIKE %s OR sender_email LIKE %s)", $search_like, $search_like, $search_like );
        }
    
        // Handle filters (we'll add this in the next section)
        $gift_card_type = isset( $_REQUEST['gift_card_type'] ) ? sanitize_text_field( $_REQUEST['gift_card_type'] ) : '';
        if ( ! empty( $gift_card_type ) ) {
            $where[] = $wpdb->prepare( 'gift_card_type = %s', $gift_card_type );
        }
    
        // Combine the WHERE clauses
        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }
    
        // Handle sorting
        $allowed_orderby = [ 'code', 'balance', 'recipient_email', 'issued_date', 'expiration_date' ];
        $orderby = ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed_orderby ) ) ? $_REQUEST['orderby'] : 'issued_date';
        $order = ( ! empty( $_REQUEST['order'] ) && in_array( strtolower( $_REQUEST['order'] ), [ 'asc', 'desc' ] ) ) ? strtoupper( $_REQUEST['order'] ) : 'DESC';
    
        // Fetch the items
        $offset = ( $current_page - 1 ) * $per_page;
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $sql = $wpdb->prepare( $sql, $per_page, $offset );
        $this->items = $wpdb->get_results( $sql, ARRAY_A );
    
        // Get total items for pagination
        $count_sql = "SELECT COUNT(id) FROM $table_name $where_sql";
        $total_items = $wpdb->get_var( $count_sql );
    
        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );
    }
    
    public function get_sortable_columns() {
        $sortable_columns = array(
            'code'            => array( 'code', true ),
            'balance'         => array( 'balance', false ),
            'recipient_email' => array( 'recipient_email', false ),
            'issued_date'     => array( 'issued_date', true ),
            'expiration_date' => array( 'expiration_date', false ),
        );
        return $sortable_columns;
    }
    
    /**
     * Default column renderer
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'code':
            case 'recipient_email':
                return esc_html( $item[ $column_name ] );
            case 'balance':
                return wc_price( $item[ $column_name ] );
            case 'issued_date':
            case 'expiration_date':
                return ! empty( $item[ $column_name ] ) ? date_i18n( get_option( 'date_format' ), strtotime( $item[ $column_name ] ) ) : '-';
            default:
                return print_r( $item, true ); // Show the whole array for troubleshooting purposes
        }
    }

    protected function column_actions( $item ) {
        $delete_nonce = wp_create_nonce( 'delete_gift_card_nonce' );
        $code         = esc_attr( $item['code'] );

        $actions = sprintf(
            '<button style="line-height:24px;" class="button delete-gift-card" data-code="%s" data-nonce="%s"><span class="dashicons dashicons-trash"></span></button>',
            $code,
            $delete_nonce
        );
        
        return $actions;
    }

    public function extra_tablenav( $which ) {
        if ( 'top' === $which ) {
            // Output filter controls here
            $gift_card_type = isset( $_REQUEST['gift_card_type'] ) ? $_REQUEST['gift_card_type'] : '';
    
            echo '<div class="alignleft actions">';
            echo '<label for="filter-by-type" class="screen-reader-text">' . esc_html__( 'Filter by Type', 'gift-cards-for-woocommerce' ) . '</label>';
            echo '<select name="gift_card_type" id="filter-by-type">';
            echo '<option value="">' . esc_html__( 'All Types', 'gift-cards-for-woocommerce' ) . '</option>';
            echo '<option value="digital"' . selected( $gift_card_type, 'digital', false ) . '>' . esc_html__( 'Digital', 'gift-cards-for-woocommerce' ) . '</option>';
            echo '<option value="physical"' . selected( $gift_card_type, 'physical', false ) . '>' . esc_html__( 'Physical', 'gift-cards-for-woocommerce' ) . '</option>';
            echo '</select>';
    
            // Add other filters if needed
    
            submit_button( __( 'Filter' ), '', 'filter_action', false );
            echo '</div>';
        }
    }
    

}
