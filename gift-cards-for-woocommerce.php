<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://robertdevore.com
 * @since             1.0.0
 * @package           Back_In_Stock_Notifications
 *
 * @wordpress-plugin
 *
 * Plugin Name: Gift Cards for WooCommerce®
 * Description: Adds gift card functionality to WooCommerce®.
 * Plugin URI:  https://github.com/robertdevore/gift-cards-for-woocommerce-free-plugin/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: gift-cards-for-woocommerce
 * Domain Path: /languages
 * Update URI:  https://github.com/robertdevore/gift-cards-for-woocommerce/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Include the custom gift cards list table class.
require_once plugin_dir_path( __FILE__ ) . 'classes/class-gift-cards-list-table.php';

// Run plugin_activated from WC_Gift_Cards.
register_activation_hook( __FILE__, [ 'WC_Gift_Cards', 'plugin_activated' ] );

/**
 * Summary of WC_Gift_Cards
 */
class WC_Gift_Cards {

    /**
     * Predefined gift card amounts for generating variations.
     *
     * @var array
     */
    private $gift_card_amounts = [ 25, 50, 100 ];

    /**
     * Constructor.
     *
     * Initializes the plugin and sets up hooks.
     */
    public function __construct() {
        // Initialize the plugin and database.
        register_activation_hook( __FILE__, [ $this, 'create_gift_card_table' ] );

        // Initialize the plugin.
        add_action( 'init', [ $this, 'register_custom_post_type' ] );
        add_action( 'woocommerce_add_to_cart', [ $this, 'process_gift_card_purchase' ] );
        add_action( 'woocommerce_checkout_process', [ $this, 'apply_gift_card' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'update_balance_on_completion' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );

        add_action( 'woocommerce_cart_calculate_fees', [ $this, 'apply_gift_card_discount' ], 999 );
        add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'save_gift_card_to_order' ] );
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_gift_card_checkbox' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_gift_card_checkbox' ] );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'display_gift_card_fields_on_product' ] );
        add_action( 'woocommerce_process_product_meta_variable', [ $this, 'generate_gift_card_variations' ] );

        // Enqueue the plugin scripts.
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

        // AJAX for the plugin.
        add_action( 'wp_ajax_apply_gift_card', [ $this, 'apply_gift_card_ajax' ] );
        add_action( 'wp_ajax_nopriv_apply_gift_card', [ $this, 'apply_gift_card_ajax' ] );
        add_action( 'wp_ajax_delete_gift_card', [ $this, 'delete_gift_card_ajax' ] );

        // Add gift card data to cart.
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_gift_card_data_to_cart' ], 10, 2 );
        // Display gift card data in cart.
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_gift_card_data_in_cart' ], 10, 2 );
        // Add gift card data to order items.
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_gift_card_data_to_order_items' ], 10, 4 );

        // Add My Account endpoint.
        add_action( 'init', [ $this, 'add_my_account_endpoint' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_my_account_tab' ] );
        add_action( 'woocommerce_account_gift-cards_endpoint', [ $this, 'my_account_gift_cards_content' ] );

        // Add WooCommerce specific hooks.
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'display_gift_card_checkbox' ] );
        add_action( 'woocommerce_checkout_update_order_review', [ $this, 'update_gift_card_session' ], 20,  );
        add_action( 'woocommerce_checkout_create_order', [ $this, 'apply_gift_card_to_order' ], 20, 2 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'reduce_gift_card_balance' ] );

        // Schedule gift card emails event.
        register_activation_hook( __FILE__, [ $this, 'schedule_gift_card_email_event' ] );
        add_action( 'wc_send_gift_card_emails', [ $this, 'send_scheduled_gift_card_emails' ] );

        // Export CSV action.
        add_action( 'admin_init', [ $this, 'handle_export_action' ] );
    }

    public static function plugin_activated() {
        $instance = new self();
        $instance->create_gift_card_table();
        $instance->add_my_account_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Creates a custom database table for storing gift card data.
     *
     * This function is triggered on plugin activation.
     *
     * @return void
     */
    public function create_gift_card_table() {
        global $wpdb;
    
        $table_name      = $wpdb->prefix . 'gift_cards';
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            code VARCHAR(255) NOT NULL UNIQUE,
            balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            expiration_date DATE NULL,
            sender_email VARCHAR(100) NULL,
            recipient_email VARCHAR(100) NULL,
            message TEXT NULL,
            issued_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            delivery_date DATE NULL,
            gift_card_type VARCHAR(50) NULL,
            user_id BIGINT(20) UNSIGNED NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
    
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function admin_enqueue_scripts( $hook_suffix ) {
        if ( 'woocommerce_page_gift-cards-free' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'gift-cards-admin',
            plugins_url( 'assets/js/gift-cards-admin.js', __FILE__ ),
            [ 'jquery' ],
            '1.0',
            true
        );

        wp_localize_script( 'gift-cards-admin', 'gift_cards_ajax', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'confirm_message' => __( 'Are you sure you want to delete this gift card?', 'gift-cards-for-woocommerce' ),
            'error_message'   => __( 'An error occurred. Please try again.', 'gift-cards-for-woocommerce' ),
        ] );
        
    }

    public function delete_gift_card_ajax() {
        check_ajax_referer( 'delete_gift_card_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'gift-cards-for-woocommerce' ) );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';

        if ( empty( $code ) ) {
            wp_send_json_error( __( 'Invalid gift card code.', 'gift-cards-for-woocommerce' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        $deleted = $wpdb->delete( $table_name, [ 'code' => $code ], [ '%s' ] );

        if ( $deleted ) {
            wp_send_json_success( __( 'Gift card deleted successfully.', 'gift-cards-for-woocommerce' ) );
        } else {
            wp_send_json_error( __( 'Failed to delete gift card.', 'gift-cards-for-woocommerce' ) );
        }
    }
    
    /**
     * Registers a custom post type for Gift Certificates.
     *
     * @return void
     */
    public function register_custom_post_type() {
        register_post_type( 'gift_certificate', [
            'label'        => esc_html__( 'Gift Certificates', 'gift-cards-for-woocommerce' ),
            'public'       => false,
            'show_ui'      => true,
            'supports'     => [ 'title', 'custom-fields' ],
            'show_in_menu' => 'woocommerce',
        ] );
    }

    /**
     * Processes the gift card purchase by saving recipient and delivery date in cart item data.
     *
     * @param array $cart_item_data The cart item data array.
     * @return array
     */
    public function process_gift_card_purchase( $cart_item_data ) {
        if ( isset( $_POST['gift_recipient'] ) && isset( $_POST['delivery_date'] ) ) {
            $cart_item_data['gift_recipient'] = sanitize_email( $_POST['gift_recipient'] );
            $cart_item_data['delivery_date']  = sanitize_text_field( $_POST['delivery_date'] );
        }
        return $cart_item_data;
    }

    /**
     * Validates and applies the gift card code at checkout.
     *
     * @return void
     */
    public function apply_gift_card() {
        if ( isset( $_POST['gift_card_code'] ) ) {
            $code    = sanitize_text_field( $_POST['gift_card_code'] );
            $balance = $this->check_gift_card_balance( $code );

            if ( $balance > 0 ) {
                // Apply balance to the order
                WC()->cart->add_discount( $code );
            } else {
                wc_add_notice( esc_html__( 'Invalid or expired gift card code.', 'gift-cards-for-woocommerce' ), 'error' );
            }
        }
    }

    /**
     * Updates the balance of a gift card upon order completion.
     *
     * @param int $order_id The ID of the completed order.
     * @return void
     */
    public function update_balance_on_completion( $order_id ) {
        $order = wc_get_order( $order_id );
    
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( 'yes' === get_post_meta( $product->get_id(), '_is_gift_card', true ) ) {
                $gift_card_data = [
                    'gift_card_type'    => $item->get_meta( 'Gift Card Type' ),
                    'recipient_email'   => $item->get_meta( 'To' ),
                    'sender_name'       => $item->get_meta( 'From' ),
                    'message'           => $item->get_meta( 'Message' ),
                    'delivery_date'     => $item->get_meta( 'Delivery Date' ),
                    'balance'           => $item->get_total(),
                ];
    
                // Generate unique code
                $code = $this->generate_unique_code();
    
                // Insert gift card into database
                global $wpdb;
                $table_name = $wpdb->prefix . 'gift_cards';
    
                $wpdb->insert(
                    $table_name,
                    [
                        'code'            => $code,
                        'balance'         => $gift_card_data['balance'],
                        'expiration_date' => null,
                        'sender_email'    => $order->get_billing_email(),
                        'recipient_email' => $gift_card_data['recipient_email'],
                        'message'         => $gift_card_data['message'],
                        'issued_date'     => current_time( 'mysql' ),
                        'delivery_date'   => $gift_card_data['delivery_date'],
                        'gift_card_type'  => $gift_card_data['gift_card_type'],
                    ],
                    [ '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ]
                );

                // Prepare gift card object for email
                $gift_card = (object) [
                    'code'            => $code,
                    'balance'         => $gift_card_data['balance'],
                    'sender_email'    => $order->get_billing_email(),
                    'recipient_email' => $gift_card_data['recipient_email'],
                    'message'         => $gift_card_data['message'],
                    'gift_card_type'  => $gift_card_data['gift_card_type'],
                    'delivery_date'   => $gift_card_data['delivery_date'],
                ];

                // Associate the gift card with a user account if the recipient email matches.
                $user = get_user_by( 'email', $gift_card_data['recipient_email'] );
                if ( $user ) {
                    $wpdb->update(
                        $table_name,
                        [ 'user_id' => $user->ID ],
                        [ 'code' => $code ],
                        [ '%d' ],
                        [ '%s' ]
                    );
                }

                // Send email if delivery date is today or in the past.
                if ( empty( $gift_card_data['delivery_date'] ) || strtotime( $gift_card_data['delivery_date'] ) <= current_time( 'timestamp' ) ) {
                    $this->send_gift_card_email( $gift_card );
                }
            }
        }
    }    

    /**
     * Checks the balance of a gift card.
     *
     * @param string $code The gift card code.
     * @return float The balance of the gift card.
     */
    private function check_gift_card_balance( $code ) {
        // Placeholder balance check, replace with actual database query
        return 50.00; // Sample balance
    }

    /**
     * Generates a unique gift card code.
     *
     * @return string The generated code.
     */
    public function generate_unique_code() {
        global $wpdb;

        $code = strtoupper( wp_generate_password( 10, false, false ) );

        // Ensure code is unique by checking the database
        $table_name = $wpdb->prefix . 'gift_cards';
        $exists     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE code = %s", $code ) );

        // Keep generating a new code if a duplicate is found
        while ( $exists > 0 ) {
            $code = strtoupper( wp_generate_password( 10, false, false ) );
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name WHERE code = %s", $code ) );
        }

        return $code;
    }

    /**
     * Adds the Gift Cards admin menu under WooCommerce.
     *
     * @return void
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Gift Cards', 'gift-cards-for-woocommerce' ),
            __( 'Gift Cards', 'gift-cards-for-woocommerce' ),
            'manage_woocommerce',
            'gift-cards-free',
            [ $this, 'display_admin_page' ]
        );
    }

    /**
     * Displays the Gift Cards admin page.
     *
     * @return void
     */
    public function display_admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'gift_cards';
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Gift Cards', 'gift-cards-for-woocommerce' ); ?>
    
                <?php if ( 'gift_cards' === $active_tab ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gift-cards-free&action=export_csv' ), 'export_gift_cards' ) ); ?>" class="page-title-action">
                        <?php esc_html_e( 'Export CSV', 'gift-cards-for-woocommerce' ); ?>
                    </a>
                <?php endif; ?>
            </h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=gift-cards-free&tab=gift_cards" class="nav-tab <?php echo $active_tab == 'gift_cards' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Gift Cards', 'gift-cards-for-woocommerce' ); ?></a>
                <a href="?page=gift-cards-free&tab=activity" class="nav-tab <?php echo $active_tab == 'activity' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Activity', 'gift-cards-for-woocommerce' ); ?></a>
                <a href="?page=gift-cards-free&tab=add_card" class="nav-tab <?php echo $active_tab == 'add_card' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Add Card', 'gift-cards-for-woocommerce' ); ?></a>
            </h2>
            <?php
    
            // Display content based on the active tab
            switch ( $active_tab ) {
                case 'gift_cards':
                    $this->display_gift_cards_table();
                    break;
                case 'activity':
                    echo '<p>' . esc_html__( 'Coming soon', 'gift-cards-for-woocommerce' ) . '</p>';
                    break;
                case 'add_card':
                    $this->display_add_card_form();
                    break;
            }
    
        echo '</div>';
    }    
    public function display_gift_cards_table() {
        $gift_cards_table = new Gift_Cards_List_Table();
        $gift_cards_table->prepare_items();
        ?>
        <form method="post">
            <?php
            $gift_cards_table->display();
            ?>
        </form>
        <?php
    }

    public function display_add_card_form() {
        // Process the form submission.
        $this->process_gift_card_form();

        ?>
        <h2><?php esc_html_e( 'Issue New Gift Card', 'gift-cards-for-woocommerce' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'issue_gift_card', 'issue_gift_card_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="balance"><?php esc_html_e( 'Gift Card Balance', 'gift-cards-for-woocommerce' ); ?></label></th>
                    <td><input type="number" name="balance" id="balance" required min="0.01" step="0.01"></td>
                </tr>
                <tr>
                    <th><label for="recipient_email"><?php esc_html_e( 'Recipient Email', 'gift-cards-for-woocommerce' ); ?></label></th>
                    <td><input type="email" name="recipient_email" id="recipient_email" required></td>
                </tr>
                <tr>
                    <th><label for="expiration_date"><?php esc_html_e( 'Expiration Date', 'gift-cards-for-woocommerce' ); ?></label></th>
                    <td><input type="date" name="expiration_date" id="expiration_date"></td>
                </tr>
                <tr>
                    <th><label for="message"><?php esc_html_e( 'Personal Message', 'gift-cards-for-woocommerce' ); ?></label></th>
                    <td><textarea name="message" id="message" rows="4"></textarea></td>
                </tr>
            </table>

            <p class="submit"><input type="submit" name="issue_gift_card" id="issue_gift_card" class="button button-primary" value="<?php esc_attr_e( 'Issue Gift Card', 'gift-cards-for-woocommerce' ); ?>"></p>
        </form>
        <?php
    }

    /**
     * Processes the gift card form submission and saves the gift card to the database.
     *
     * @return void
     */
    public function process_gift_card_form() {
        if ( isset( $_POST['issue_gift_card'] ) && check_admin_referer( 'issue_gift_card', 'issue_gift_card_nonce' ) ) {
            global $wpdb;

            $balance         = isset( $_POST['balance'] ) ? floatval( $_POST['balance'] ) : 0.00;
            $recipient_email = isset( $_POST['recipient_email'] ) ? sanitize_email( $_POST['recipient_email'] ) : '';
            $expiration_date = isset( $_POST['expiration_date'] ) ? sanitize_text_field( $_POST['expiration_date'] ) : null;
            $message         = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

            if ( $balance > 0 && is_email( $recipient_email ) ) {
                // Generate a unique gift card code
                $code = $this->generate_unique_code();

                // Insert the gift card into the database
                $table_name = $wpdb->prefix . 'gift_cards';
                $wpdb->insert(
                    $table_name,
                    [
                        'code'            => $code,
                        'balance'         => $balance,
                        'expiration_date' => $expiration_date,
                        'sender_email'    => wp_get_current_user()->user_email,
                        'recipient_email' => $recipient_email,
                        'message'         => $message,
                        'issued_date'     => current_time( 'mysql' ),
                    ],
                    [ '%s', '%f', '%s', '%s', '%s', '%s', '%s' ]
                );

                // Associate the gift card with a user account if the recipient email matches
                $user = get_user_by( 'email', $recipient_email );
                if ( $user ) {
                    $wpdb->update(
                        $table_name,
                        [ 'user_id' => $user->ID ],
                        [ 'code' => $code ],
                        [ '%d' ],
                        [ '%s' ]
                    );
                }

                // Display success message.
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Gift card issued successfully!', 'gift-cards-for-woocommerce' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid input. Please make sure all required fields are filled out.', 'gift-cards-for-woocommerce' ) . '</p></div>';
            }
        }
    }

    /**
     * Enqueues JavaScript for applying the gift card at checkout.
     *
     * @return void
     */
    public function enqueue_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_script(
                'wc-gift-card-checkout',
                plugins_url( 'assets/js/wc-gift-card-checkout.js', __FILE__ ),
                [ 'jquery', 'wc-checkout' ],
                '1.0',
                true
            );
            wp_enqueue_style(
                'wc-gift-card-styles',
                plugins_url( 'assets/css/gift-cards.css', __FILE__ ),
                [],
                '1.0'
            );
        }

        // Enqueue styles on the product page
        if ( is_product() ) {
            wp_enqueue_style(
                'wc-gift-card-product-styles',
                plugins_url( 'assets/css/gift-cards.css', __FILE__ ),
                [],
                '1.0'
            );
        }
    }
        
    /**
     * Applies the gift card discount at checkout if a valid code is entered.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     * @return void
     */
    public function apply_gift_card_discount( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
    
        if ( WC()->session->get( 'apply_gift_card_balance' ) ) {
            $user_id = get_current_user_id();
    
            if ( ! $user_id ) {
                error_log( 'User not logged in.' );
                return;
            }
    
            global $wpdb;
            $table_name = $wpdb->prefix . 'gift_cards';
    
            // Get total gift card balance for the user
            $total_balance = $wpdb->get_var( $wpdb->prepare(
                "SELECT SUM(balance) FROM $table_name WHERE user_id = %d AND balance > 0", $user_id
            ) );
    
            $total_balance = floatval( $total_balance );
    
            error_log( 'Total Gift Card Balance: ' . $total_balance );
    
            if ( $total_balance > 0 ) {
                $cart_total = $cart->get_subtotal();
    
                // Exclude shipping and taxes if desired
                $cart_total = floatval( $cart_total );
    
                error_log( 'Cart Total: ' . $cart_total );
    
                $discount = min( $total_balance, $cart_total );
                $discount = floatval( $discount );
    
                error_log( 'Discount to Apply: ' . $discount );
    
                // Apply the discount
                $cart->add_fee( __( 'Gift Card Discount', 'gift-cards-for-woocommerce' ), -$discount );
    
                // Store the discount amount in the session for later use
                WC()->session->set( 'gift_card_discount_amount', $discount );
            } else {
                error_log( 'No gift card balance available.' );
            }
        } else {
            error_log( 'Gift card not applied or in admin area.' );
            WC()->session->set( 'gift_card_discount_amount', 0 );
        }
    }
    
    /**
     * Saves the applied gift card code and discount to the order meta.
     *
     * @param int $order_id The ID of the order.
     * @return void
     */
    public function save_gift_card_to_order( $order_id ) {
        if ( ! empty( $_POST['gift_card_code'] ) ) {
            update_post_meta( $order_id, '_gift_card_code', sanitize_text_field( $_POST['gift_card_code'] ) );
        }
    }

    /**
     * Adds a "Gift Card" checkbox to the product settings.
     *
     * @return void
     */
    public function add_gift_card_checkbox() {
        woocommerce_wp_checkbox( [
            'id'            => '_is_gift_card',
            'label'         => __( 'Gift Card', 'gift-cards-for-woocommerce' ),
            'description'   => __( 'Enable this option to make this product a gift card.', 'gift-cards-for-woocommerce' ),
            'desc_tip'      => true,
        ] );
    }

    /**
     * Saves the "Gift Card" checkbox value to the product meta.
     *
     * @param int $post_id The ID of the product post.
     * @return void
     */
    public function save_gift_card_checkbox( $post_id ) {
        $is_gift_card = isset( $_POST['_is_gift_card'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_is_gift_card', $is_gift_card );
    }

    /**
     * Displays gift card fields on the product page if the product is marked as a gift card.
     *
     * @return void
     */
    public function display_gift_card_fields_on_product() {
        global $product;
    
        if ( 'yes' !== get_post_meta( $product->get_id(), '_is_gift_card', true ) ) {
            return; // Not a gift card, so exit
        }
    
        echo '<div class="gift-card-fields">';
    
        // Gift Card Type
        woocommerce_form_field( 'gift_card_type', [
            'type'    => 'select',
            'label'   => __( 'Gift Card Type', 'gift-cards-for-woocommerce' ),
            'required' => true,
            'options' => [
                'digital'  => __( 'Digital', 'gift-cards-for-woocommerce' ),
                'physical' => __( 'Physical', 'gift-cards-for-woocommerce' ),
            ],
        ] );
    
        // To (email)
        woocommerce_form_field( 'gift_card_to', [
            'type'     => 'email',
            'label'    => __( 'To (Email)', 'gift-cards-for-woocommerce' ),
            'required' => true,
        ] );
    
        // From (name)
        woocommerce_form_field( 'gift_card_from', [
            'type'     => 'text',
            'label'    => __( 'From (Name)', 'gift-cards-for-woocommerce' ),
            'required' => true,
        ] );
    
        // Message
        woocommerce_form_field( 'gift_card_message', [
            'type'  => 'textarea',
            'label' => __( 'Message', 'gift-cards-for-woocommerce' ),
        ] );
    
        // Delivery Date
        woocommerce_form_field( 'gift_card_delivery_date', [
            'type'  => 'date',
            'label' => __( 'Delivery Date', 'gift-cards-for-woocommerce' ),
            'value' => date( 'Y-m-d' ),
        ] );
    
        echo '</div>';
    }
        
    /**
     * Adds gift card data to the cart item.
     *
     * @param array $cart_item_data The cart item data.
     * @param int $product_id The product ID.
     * @return array
     */
    public function add_gift_card_data_to_cart( $cart_item_data, $product_id ) {
        if ( isset( $_POST['gift_card_type'] ) ) {
            $cart_item_data['gift_card_type']         = sanitize_text_field( $_POST['gift_card_type'] );
            $cart_item_data['gift_card_to']           = sanitize_email( $_POST['gift_card_to'] );
            $cart_item_data['gift_card_from']         = sanitize_text_field( $_POST['gift_card_from'] );
            $cart_item_data['gift_card_message']      = sanitize_textarea_field( $_POST['gift_card_message'] );
            $cart_item_data['gift_card_delivery_date'] = sanitize_text_field( $_POST['gift_card_delivery_date'] );
        }
        return $cart_item_data;
    }
    

    /**
     * Displays gift card data in the cart and checkout.
     *
     * @param array $item_data The existing item data to display.
     * @param array $cart_item The cart item data.
     * @return array
     */
    public function display_gift_card_data_in_cart( $item_data, $cart_item ) {
        if ( isset( $cart_item['gift_card_type'] ) ) {
            $item_data[] = [
                'name'    => __( 'Gift Card Type', 'gift-cards-for-woocommerce' ),
                'value'   => sanitize_text_field( $cart_item['gift_card_type'] ),
            ];
            $item_data[] = [
                'name'    => __( 'To', 'gift-cards-for-woocommerce' ),
                'value'   => sanitize_text_field( $cart_item['gift_card_to'] ),
            ];
            $item_data[] = [
                'name'    => __( 'From', 'gift-cards-for-woocommerce' ),
                'value'   => sanitize_text_field( $cart_item['gift_card_from'] ),
            ];
            $item_data[] = [
                'name'    => __( 'Message', 'gift-cards-for-woocommerce' ),
                'value'   => sanitize_textarea_field( $cart_item['gift_card_message'] ),
            ];
            $item_data[] = [
                'name'    => __( 'Delivery Date', 'gift-cards-for-woocommerce' ),
                'value'   => sanitize_text_field( $cart_item['gift_card_delivery_date'] ),
            ];
        }
        return $item_data;
    }
    
    /**
     * Adds gift card data to the order line items.
     *
     * @param WC_Order_Item_Product $item The order item.
     * @param string $cart_item_key The cart item key.
     * @param array $values The cart item values.
     * @param WC_Order $order The order object.
     * @return void
     */
    public function add_gift_card_data_to_order_items( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['gift_card_type'] ) ) {
            $item->add_meta_data( __( 'Gift Card Type', 'gift-cards-for-woocommerce' ), sanitize_text_field( $values['gift_card_type'] ), true );
            $item->add_meta_data( __( 'To', 'gift-cards-for-woocommerce' ), sanitize_text_field( $values['gift_card_to'] ), true );
            $item->add_meta_data( __( 'From', 'gift-cards-for-woocommerce' ), sanitize_text_field( $values['gift_card_from'] ), true );
            $item->add_meta_data( __( 'Message', 'gift-cards-for-woocommerce' ), sanitize_textarea_field( $values['gift_card_message'] ), true );
            $item->add_meta_data( __( 'Delivery Date', 'gift-cards-for-woocommerce' ), sanitize_text_field( $values['gift_card_delivery_date'] ), true );
        }
    }
    
    /**
     * Schedules the daily event for sending gift card emails.
     *
     * @return void
     */
    public function schedule_gift_card_email_event() {
        if ( ! wp_next_scheduled( 'wc_send_gift_card_emails' ) ) {
            wp_schedule_event( strtotime( 'midnight' ), 'daily', 'wc_send_gift_card_emails' );
        }
    }

    /**
     * Sends gift card emails scheduled for today.
     *
     * @return void
     */
    public function send_scheduled_gift_card_emails() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        // Query for gift cards with today's delivery date
        $today = date( 'Y-m-d' );
        $gift_cards = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE delivery_date = %s AND gift_card_type = %s",
            $today, 'digital'
        ) );

        foreach ( $gift_cards as $gift_card ) {
            $this->send_gift_card_email( $gift_card );
        }
    }

    /**
     * Sends a single gift card email.
     *
     * @param object $gift_card The gift card data.
     * @return void
     */
    private function send_gift_card_email( $gift_card ) {
        $to      = $gift_card->recipient_email;
        $subject = __( 'You received a gift card!', 'gift-cards-for-woocommerce' );
        $message = sprintf(
            __( "Hello! You've received a gift card worth %s from %s.\n\nMessage: %s\n\nRedeem your gift card with code: %s\n\nThank you!", 'gift-cards-for-woocommerce' ),
            wc_price( $gift_card->balance ),
            sanitize_text_field( $gift_card->sender_email ),
            sanitize_textarea_field( $gift_card->message ),
            sanitize_text_field( $gift_card->code )
        );

        wp_mail( $to, $subject, $message );
    }

    /**
     * Generates gift card variations when the product is marked as a gift card.
     *
     * @param int $post_id The ID of the product post.
     * @return void
     */
    public function generate_gift_card_variations( $post_id ) {
        // Check if the product is marked as a gift card
        if ( isset( $_POST['_is_gift_card'] ) && $_POST['_is_gift_card'] === 'yes' ) {
            $product = wc_get_product( $post_id );

            // Only proceed if the product is variable
            if ( $product->is_type( 'variable' ) ) {
                $attribute_name = 'Gift Card Amount';

                // Step 1: Set up the attribute for "Gift Card Amount" if not present
                $attributes = $product->get_attributes();
                if ( ! isset( $attributes['gift_card_amount'] ) ) {
                    $attributes['gift_card_amount'] = new WC_Product_Attribute();
                    $attributes['gift_card_amount']->set_id( 0 );
                    $attributes['gift_card_amount']->set_name( 'Gift Card Amount' );
                    $attributes['gift_card_amount']->set_options( array_map( 'strval', $this->gift_card_amounts ) );
                    $attributes['gift_card_amount']->set_position( 0 );
                    $attributes['gift_card_amount']->set_visible( true );
                    $attributes['gift_card_amount']->set_variation( true );
                    $product->set_attributes( $attributes );
                    $product->save();
                }

                // Step 2: Create variations for each amount
                foreach ( $this->gift_card_amounts as $amount ) {
                    // Check if a variation with this amount already exists
                    $existing_variation_id = $this->find_existing_variation( $product, 'gift_card_amount', $amount );
                    if ( ! $existing_variation_id ) {
                        // Create a new variation
                        $variation = new WC_Product_Variation();
                        $variation->set_parent_id( $product->get_id() );
                        $variation->set_attributes( [ 'gift_card_amount' => (string) $amount ] );
                        $variation->set_regular_price( $amount );
                        $variation->save();
                    }
                }
            }
        }
    }

    /**
     * Finds an existing variation for a given attribute and value.
     *
     * @param WC_Product $product The product object.
     * @param string $attribute The attribute name.
     * @param string $value The attribute value.
     * @return int|null Variation ID if found, null otherwise.
     */
    private function find_existing_variation( $product, $attribute, $value ) {
        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( $variation && $variation->get_attribute( $attribute ) === $value ) {
                return $variation_id;
            }
        }
        return null;
    }

    /**
     * Adds a new endpoint for the "Gift Cards" tab in My Account.
     */
    public function add_my_account_endpoint() {
        add_rewrite_endpoint( 'gift-cards', EP_ROOT | EP_PAGES );
    }

    /**
     * Adds the 'gift-cards' query var.
     *
     * @param array $vars Query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'gift-cards';
        return $vars;
    }

    /**
     * Adds the "Gift Cards" tab to the My Account menu.
     *
     * @param array $items Existing menu items.
     * @return array
     */
    public function add_my_account_tab( $items ) {
        // Add the new endpoint after 'dashboard' or wherever you want
        $new_items = [];

        foreach ( $items as $key => $value ) {
            $new_items[ $key ] = $value;
            if ( 'dashboard' === $key ) {
                $new_items['gift-cards'] = __( 'Gift Cards', 'gift-cards-for-woocommerce' );
            }
        }

        return $new_items;
    }

    /**
     * Displays the content for the "Gift Cards" tab in My Account.
     */
    public function my_account_gift_cards_content() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'You need to be logged in to view your gift cards.', 'gift-cards-for-woocommerce' ) . '</p>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        // Get active gift cards associated with the user
        $gift_cards = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND balance > 0", $user_id
        ), ARRAY_A );

        if ( empty( $gift_cards ) ) {
            echo '<p>' . esc_html__( 'You have no active gift cards.', 'gift-cards-for-woocommerce' ) . '</p>';
            return;
        }

        // Calculate total balance
        $total_balance = 0;
        foreach ( $gift_cards as $gift_card ) {
            $total_balance += $gift_card['balance'];
        }

        // Display total balance
        echo '<h2>' . esc_html__( 'Your Gift Card Balance', 'gift-cards-for-woocommerce' ) . '</h2>';
        echo '<p>' . sprintf( esc_html__( 'Total Balance: %s', 'gift-cards-for-woocommerce' ), wc_price( $total_balance ) ) . '</p>';

        // Display table of gift cards
        echo '<h2>' . esc_html__( 'Active Gift Cards', 'gift-cards-for-woocommerce' ) . '</h2>';
        echo '<table class="woocommerce-orders-table woocommerce-MyAccount-gift-cards shop_table shop_table_responsive my_account_orders account-gift-cards-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Code', 'gift-cards-for-woocommerce' ) . '</th>';
        echo '<th>' . esc_html__( 'Balance', 'gift-cards-for-woocommerce' ) . '</th>';
        echo '<th>' . esc_html__( 'Expiration Date', 'gift-cards-for-woocommerce' ) . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ( $gift_cards as $gift_card ) {
            echo '<tr>';
            echo '<td>' . esc_html( $gift_card['code'] ) . '</td>';
            echo '<td>' . wc_price( $gift_card['balance'] ) . '</td>';
            echo '<td>' . ( ! empty( $gift_card['expiration_date'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $gift_card['expiration_date'] ) ) : esc_html__( 'No Expiration', 'gift-cards-for-woocommerce' ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function flush_rewrite_rules() {
        $this->add_my_account_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Displays the gift card application checkbox in the totals section of checkout.
     */
    public function display_gift_card_checkbox() {
        $user_id = get_current_user_id();
    
        // Check if the user is logged in and has gift cards
        if ( ! $user_id ) {
            return;
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';
    
        // Get total gift card balance for the user
        $total_balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(balance) FROM $table_name WHERE user_id = %d AND balance > 0", $user_id
        ) );
    
        $total_balance = floatval( $total_balance );
    
        if ( $total_balance > 0 ) {
            // Get the current state of the checkbox from the session
            $is_checked = WC()->session->get( 'apply_gift_card_balance' ) ? 'checked' : '';
    
            // Display the checkbox with a custom CSS class
            ?>
            <div class="gift-card-application" style="background-color: #f0f8ff; padding: 15px; border: 1px solid #dcdcdc; margin-bottom: 20px;">
                <p class="form-row" style="margin:0;padding:0;">
                    <label style="font-weight: bold;">
                        <input type="checkbox" id="apply_gift_card_balance" name="apply_gift_card_balance" value="1" <?php echo $is_checked; ?> style="margin-right: 10px;">
                        <?php printf( esc_html__( 'Apply Gift Card Balance (%s)', 'gift-cards-for-woocommerce' ), wc_price( $total_balance ) ); ?>
                    </label>
                </p>
            </div>
            <?php
        }
    }    
        
    /**
     * Updates the session with the gift card application status.
     *
     * @param array|string $posted_data The posted data from the checkout form.
     */
    public function update_gift_card_session( $posted_data ) {
        parse_str( $posted_data, $output );
    
        // Debugging statement
        error_log( 'Posted Data: ' . print_r( $output, true ) );
    
        if ( isset( $output['apply_gift_card_balance'] ) ) {
            WC()->session->set( 'apply_gift_card_balance', true );
            error_log( 'apply_gift_card_balance set to true' );
        } else {
            WC()->session->set( 'apply_gift_card_balance', false );
            error_log( 'apply_gift_card_balance set to false' );
        }
    }     

    /**
     * Stores the applied gift card discount in the order meta.
     *
     * @param WC_Order $order The order object.
     * @param array    $data  The posted data.
     */
    public function apply_gift_card_to_order( $order, $data ) {
        if ( WC()->session->get( 'apply_gift_card_balance' ) ) {
            $discount_amount = WC()->session->get( 'gift_card_discount_amount' );
            $discount_amount = floatval( $discount_amount );
    
            if ( $discount_amount > 0 ) {
                $order->update_meta_data( '_applied_gift_card_discount', $discount_amount );
                $order->save();
            }
        }
    }
    

    /**
     * Reduces the user's gift card balance after the order is completed.
     *
     * @param int $order_id The ID of the order.
     */
    public function reduce_gift_card_balance( $order_id ) {
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
    
        if ( ! $user_id ) {
            return;
        }
    
        $discount_amount = $order->get_meta( '_applied_gift_card_discount' );
        $discount_amount = floatval( $discount_amount );
    
        if ( $discount_amount > 0 ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gift_cards';
    
            // Get user's gift cards with balance
            $gift_cards = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND balance > 0 ORDER BY issued_date ASC", $user_id
            ) );
    
            $remaining_discount = $discount_amount;
    
            foreach ( $gift_cards as $gift_card ) {
                if ( $remaining_discount <= 0 ) {
                    break;
                }
    
                $gift_card_balance = floatval( $gift_card->balance );
    
                if ( $gift_card_balance > 0 ) {
                    if ( $gift_card_balance >= $remaining_discount ) {
                        // Deduct remaining_discount from this gift card
                        $new_balance = $gift_card_balance - $remaining_discount;
                        $remaining_discount = 0;
                    } else {
                        // Use up the whole gift card balance
                        $new_balance = 0;
                        $remaining_discount -= $gift_card_balance;
                    }
    
                    // Update the gift card balance in the database
                    $wpdb->update(
                        $table_name,
                        [ 'balance' => $new_balance ],
                        [ 'id' => $gift_card->id ],
                        [ '%f' ],
                        [ '%d' ]
                    );
                }
            }
        }
    }

    public function export_gift_cards_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have permission to export gift cards.', 'gift-cards-for-woocommerce' ), '', [ 'response' => 403 ] );
        }
    
        if ( ! check_admin_referer( 'export_gift_cards' ) ) {
            wp_die( __( 'Invalid request.', 'gift-cards-for-woocommerce' ), '', [ 'response' => 403 ] );
        }
    
        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';
    
        $gift_cards = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
    
        if ( empty( $gift_cards ) ) {
            // No data to export, redirect back with a notice
            wp_redirect( add_query_arg( 'export_error', 'no_data', admin_url( 'admin.php?page=gift-cards-free' ) ) );
            exit;
        }
    
        // Set the headers for CSV download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=gift-cards-' . date( 'Y-m-d' ) . '.csv' );
    
        // Open the output stream
        $output = fopen( 'php://output', 'w' );
    
        // Define the column headings with translations
        $headers = array(
            'id'              => __( 'ID', 'gift-cards-for-woocommerce' ),
            'code'            => __( 'Code', 'gift-cards-for-woocommerce' ),
            'balance'         => __( 'Balance', 'gift-cards-for-woocommerce' ),
            'expiration_date' => __( 'Expiration Date', 'gift-cards-for-woocommerce' ),
            'sender_email'    => __( 'Sender Email', 'gift-cards-for-woocommerce' ),
            'recipient_email' => __( 'Recipient Email', 'gift-cards-for-woocommerce' ),
            'message'         => __( 'Message', 'gift-cards-for-woocommerce' ),
            'issued_date'     => __( 'Issued Date', 'gift-cards-for-woocommerce' ),
            'delivery_date'   => __( 'Delivery Date', 'gift-cards-for-woocommerce' ),
            'gift_card_type'  => __( 'Gift Card Type', 'gift-cards-for-woocommerce' ),
            'user_id'         => __( 'User ID', 'gift-cards-for-woocommerce' ),
        );
    
        // Output the column headings
        fputcsv( $output, $headers );
    
        // Loop over the rows and output them
        foreach ( $gift_cards as $gift_card ) {
            // Create an array that matches the order of headers
            $data = array();
            foreach ( array_keys( $headers ) as $key ) {
                $data[] = isset( $gift_card[ $key ] ) ? $gift_card[ $key ] : '';
            }
            fputcsv( $output, $data );
        }
    
        fclose( $output );
    
        exit;
    }
    
    public function handle_export_action() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'gift-cards-free' && isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' ) {
            $this->export_gift_cards_csv();
        }
    }

}

new WC_Gift_Cards();