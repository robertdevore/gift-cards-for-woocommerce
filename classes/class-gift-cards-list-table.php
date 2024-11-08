<?php
/**
 * Gift Cards List Table Class
 *
 * Provides a custom table to display, filter, and manage gift cards in the WooCommerce admin.
 *
 * @package    Gift_Cards_For_WooCommerce
 * @subpackage Admin
 * @since      1.0.0
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Gift_Cards_List_Table
 *
 * Extends the WP_List_Table class to display gift card data.
 */
class Gift_Cards_List_Table extends WP_List_Table {

    /**
     * Constructor to set up the list table.
     */
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'Gift Card', 'gift-cards-for-woocommerce' ),
            'plural'   => __( 'Gift Cards', 'gift-cards-for-woocommerce' ),
            'ajax'     => true,
        ] );
    }

    /**
     * Defines the columns used in the table.
     *
     * @return array Column headers for the gift card table.
     */
    public function get_columns() {
        $columns = [
            'code'            => __( 'Code', 'gift-cards-for-woocommerce' ),
            'balance'         => __( 'Balance', 'gift-cards-for-woocommerce' ),
            'recipient_email' => __( 'Recipient Email', 'gift-cards-for-woocommerce' ),
            'issued_date'     => __( 'Issued Date', 'gift-cards-for-woocommerce' ),
            'expiration_date' => __( 'Expiration Date', 'gift-cards-for-woocommerce' ),
            'actions'         => __( 'Actions', 'gift-cards-for-woocommerce' ),
        ];
        return $columns;
    }

    /**
     * Prepares the items for display in the gift cards list table.
     *
     * Retrieves data from the database and sets up pagination, sorting, and filtering.
     *
     * @since  1.0.0
     * @return void
     */
    public function prepare_items() {
        global $wpdb;

        $table_name   = $wpdb->prefix . 'gift_cards';
        $per_page     = apply_filters( 'gift_cards_prepare_items_per_page', 20 );
        $current_page = $this->get_pagenum();

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $search         = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
        $gift_card_type = isset( $_REQUEST['gift_card_type'] ) ? sanitize_text_field( $_REQUEST['gift_card_type'] ) : '';

        $where  = [];

        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]     = $wpdb->prepare( "( code LIKE %s OR recipient_email LIKE %s )", $search_like, $search_like );
        }

        if ( ! empty( $gift_card_type ) && in_array( $gift_card_type, [ 'digital', 'physical' ], true ) ) {
            $where[] = $wpdb->prepare( "gift_card_type = %s", $gift_card_type );
        }

        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $allowed_orderby = [ 'code', 'balance', 'recipient_email', 'issued_date', 'expiration_date' ];
        $orderby         = ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $allowed_orderby, true ) ) ? $_REQUEST['orderby'] : 'issued_date';
        $order           = ( ! empty( $_REQUEST['order'] ) && in_array( strtolower( $_REQUEST['order'] ), [ 'asc', 'desc' ], true ) ) ? strtoupper( $_REQUEST['order'] ) : 'DESC';

        // Bypass cache if updating
        if ( isset( $_POST['action'] ) && 'update_gift_card' === $_POST['action'] ) {
            $this->items = false;
        } else {
            $cache_key   = 'gift_cards_list_' . md5( serialize( [ $current_page, $search, $gift_card_type, $orderby, $order ] ) );
            $this->items = get_transient( $cache_key );
        }

        if ( false === $this->items ) {
            $offset = ( $current_page - 1 ) * $per_page;
            $sql    = "SELECT id, code, balance, recipient_email, issued_date, expiration_date FROM $table_name $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
            $sql    = $wpdb->prepare( $sql, $per_page, $offset );

            $this->items = $wpdb->get_results( $sql, ARRAY_A );
            set_transient( $cache_key, $this->items, HOUR_IN_SECONDS );
        }

        $count_cache_key = 'gift_cards_total_count_' . md5( serialize( [ $search, $gift_card_type ] ) );
        $total_items     = get_transient( $count_cache_key );

        if ( false === $total_items ) {
            $count_sql   = "SELECT COUNT(id) FROM $table_name $where_sql";
            $total_items = $wpdb->get_var( $count_sql );
            set_transient( $count_cache_key, $total_items, HOUR_IN_SECONDS );
        }

        $this->set_pagination_args( [
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ] );
    }

    /**
     * Defines the sortable columns for the table.
     *
     * @return array Associative array of sortable columns.
     */
    public function get_sortable_columns() {
        $sortable_columns = [
            'code'            => [ 'code', true ],
            'balance'         => [ 'balance', false ],
            'recipient_email' => [ 'recipient_email', false ],
            'issued_date'     => [ 'issued_date', true ],
            'expiration_date' => [ 'expiration_date', false ],
        ];
        return $sortable_columns;
    }

    /**
     * Default column renderer for displaying data.
     *
     * @param array  $item        Row data.
     * @param string $column_name Column name.
     * @return string|void Escaped data for the specified column.
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
                return print_r( $item, true );
        }
    }

    /**
     * Renders the Actions column with edit and delete buttons.
     *
     * @param array $item Row data.
     * @return string HTML for action buttons.
     */
    protected function column_actions( $item ) {
        $delete_nonce = wp_create_nonce( 'delete_gift_card_nonce' );
        $edit_nonce   = wp_create_nonce( 'edit_gift_card_nonce' );
        $code         = esc_attr( $item['code'] );

        $actions  = sprintf(
            '<button style="line-height:24px;" class="button edit-gift-card" data-code="%s" data-nonce="%s"><span class="dashicons dashicons-edit"></span></button> ',
            $code,
            $edit_nonce
        );
        $actions .= sprintf(
            '<button style="line-height:24px;" class="button delete-gift-card" data-code="%s" data-nonce="%s"><span class="dashicons dashicons-trash"></span></button>',
            $code,
            $delete_nonce
        );

        return $actions;
    }

    /**
     * Adds extra filter controls above the table.
     *
     * @param string $which Top or bottom of the table.
     */
    public function extra_tablenav( $which ) {
        if ( 'top' === $which ) {
            // Output filter controls.
            $gift_card_type = isset( $_REQUEST['gift_card_type'] ) ? sanitize_text_field( $_REQUEST['gift_card_type'] ) : '';

            echo '<div class="alignleft actions">';
            echo '<label for="filter-by-type" class="screen-reader-text">' . esc_html__( 'Filter by Type', 'gift-cards-for-woocommerce' ) . '</label>';
            echo '<select name="gift_card_type" id="filter-by-type">';
            echo '<option value="">' . esc_html__( 'All Types', 'gift-cards-for-woocommerce' ) . '</option>';
            echo '<option value="digital"' . selected( $gift_card_type, 'digital', false ) . '>' . esc_html__( 'Digital', 'gift-cards-for-woocommerce' ) . '</option>';
            echo '<option value="physical"' . selected( $gift_card_type, 'physical', false ) . '>' . esc_html__( 'Physical', 'gift-cards-for-woocommerce' ) . '</option>';
            echo '</select>';

            submit_button( __( 'Filter', 'gift-cards-for-woocommerce' ), '', 'filter_action', false );
            echo '</div>';
        }
    }
}
