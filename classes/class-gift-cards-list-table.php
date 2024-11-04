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
        $sortable = [];

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        // Fetch the items
        $offset = ( $current_page - 1 ) * $per_page;
        $this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );

        // Get total items for pagination
        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );
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
        $code = esc_attr( $item['code'] );
        
        $actions = sprintf(
            '<button class="button delete-gift-card" data-code="%s" data-nonce="%s"><span class="dashicons dashicons-trash"></span></button>',
            $code,
            $delete_nonce
        );
        
        return $actions;
    }    

    /**
     * Optional. If you need to render specific columns differently, you can define methods like this:
     */
    /*
    protected function column_code( $item ) {
        // Custom rendering for 'code' column
        return esc_html( $item['code'] );
    }
    */
}
