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

// Ensure WooCommerce is active.
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && ! class_exists( 'WooCommerce' ) ) {
    add_action( 'admin_notices', 'wc_gift_cards_woocommerce_inactive_notice' );
    return;
}

/**
 * Current plugin version.
 */
define( 'GIFT_CARDS_FOR_WOOCOMMERCE_VERSION', '1.0.0' );

/**
 * Displays an admin notice if WooCommerce is inactive.
 *
 * This notice alerts the user that the Gift Cards for WooCommerce® plugin
 * requires WooCommerce to be installed and active.
 *
 * @return void
 */
function wc_gift_cards_woocommerce_inactive_notice() {
    echo '<div class="error"><p>';
    esc_html_e( 'Gift Cards for WooCommerce® (free) requires WooCommerce to be installed and active.', 'gift-cards-for-woocommerce' );
    echo '</p></div>';
}

// Include the custom gift cards list table class.
require_once plugin_dir_path( __FILE__ ) . 'classes/class-gift-cards-list-table.php';

// Run plugin_activated from WC_Gift_Cards.
register_activation_hook( __FILE__, [ 'WC_Gift_Cards', 'plugin_activated' ] );

/**
 * Main class for managing gift card functionality in WooCommerce.
 *
 * This class handles the creation, management, and redemption of gift cards within
 * a WooCommerce environment. It includes hooks for admin and front-end actions,
 * AJAX requests, custom database tables, and integration with WooCommerce order processing.
 * 
 * Features include:
 * - Enqueuing admin and front-end scripts/styles.
 * - Handling gift card purchases, discount application, and balance tracking.
 * - Managing gift card creation, variations, and custom data.
 * - Providing AJAX endpoints for gift card administration.
 * - Sending automated emails for gift card delivery and expiration reminders.
 * 
 * @since 1.0.0
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
        add_action( 'wc_send_gift_card_expiry_reminders', [ $this, 'send_gift_card_expiry_reminder_emails' ] );

        // Export CSV actions.
        add_action( 'admin_init', [ $this, 'handle_export_action' ] );
        add_action( 'wp_ajax_export_gift_cards_in_batches', [ $this, 'batch_export_gift_cards' ] );
        
        // Import CSV actions.
        add_action( 'admin_init', [$this, 'handle_import_action'] );
        add_action( 'wp_ajax_import_gift_cards_in_batches', [ $this, 'import_gift_cards_in_batches' ] );

        // Register the email class with WooCommerce.
        add_filter( 'woocommerce_email_classes', [ $this, 'add_gift_card_email_class' ] );

        // AJAX actions for editing gift cards.
        add_action( 'wp_ajax_get_gift_card_data', [ $this, 'get_gift_card_data_ajax' ] );
        add_action( 'wp_ajax_update_gift_card', [ $this, 'update_gift_card_ajax' ] );
    }

    /**
     * Handles actions to perform when the plugin is activated.
     * 
     * This method initializes the plugin by creating necessary database tables,
     * adding custom endpoints, and flushing rewrite rules.
     * 
     * @return void
     */
    public static function plugin_activated() {
        $instance = new self();

        // Create the gift card database table.
        $instance->create_gift_card_table();
        $instance->create_activity_table();

        // Add custom "My Account" endpoint for the plugin.
        $instance->add_my_account_endpoint();

        // Flush rewrite rules for proper endpoint registration.
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
            sender_name VARCHAR(100) NULL,
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

    /**
     * Creates the activity log database table on plugin activation.
     * 
     * @since  1.0.0
     * @return void
     */
    public function create_activity_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'gift_card_activities';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            action_type VARCHAR(20) NOT NULL,
            code VARCHAR(255) NOT NULL,
            amount DECIMAL(10, 2) NULL,
            user_id BIGINT(20) UNSIGNED NULL,
            action_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Enqueues admin-specific scripts and styles for the Gift Cards plugin.
     * 
     * This method loads custom styles and scripts only on the Gift Cards admin page,
     * including WooCommerce® admin styles, jQuery UI Dialog, and custom AJAX functionality.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * 
     * @since  1.0.0
     * @return void
     */
    public function admin_enqueue_scripts( $hook_suffix ) {
        if ( 'woocommerce_page_gift-cards-free' !== $hook_suffix ) {
            return;
        }

        // Enqueue the WooCommerce admin styles.
        wp_enqueue_style( 'woocommerce_admin_styles' );

        // Enqueue custom admin styles for the Gift Cards page.
        wp_enqueue_style(
            'gift-cards-admin-custom-styles',
            plugins_url( 'assets/css/gift-cards-admin.css', __FILE__ ),
            [],
            GIFT_CARDS_FOR_WOOCOMMERCE_VERSION
        );

        // Enqueue jQuery UI Dialog for modal functionality.
        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_style( 'wp-jquery-ui-dialog' );

        // Enqueue custom admin JavaScript for the Gift Cards page.
        wp_enqueue_script(
            'gift-cards-admin',
            plugins_url( 'assets/js/gift-cards-admin.js', __FILE__ ),
            [ 'jquery', 'jquery-ui-dialog' ],
            GIFT_CARDS_FOR_WOOCOMMERCE_VERSION,
            true
        );

        // Localize script with AJAX URL and confirmation/error messages.
        wp_localize_script( 'gift-cards-admin', 'gift_cards_ajax', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'confirm_message' => esc_html__( 'Are you sure you want to delete this gift card?', 'gift-cards-for-woocommerce' ),
            'error_message'   => esc_html__( 'An error occurred. Please try again.', 'gift-cards-for-woocommerce' ),
        ] );

        // Inline script to handle the "Import CSV" button click event.
        wp_add_inline_script( 'gift-cards-admin', "
            document.getElementById('import-csv-button').addEventListener('click', function() {
                document.getElementById('gift_card_csv').click();
            });
        " );
    }

    /**
     * Handles the AJAX request to delete a gift card.
     *
     * This function verifies the nonce, checks user permissions, retrieves the gift card
     * code from the request, and attempts to delete the corresponding gift card from
     * the database. It returns a JSON response indicating success or failure.
     *
     * @since  1.0.0
     * @return void Outputs a JSON response on success or error.
     */
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
            // Clear cached data for the list table.
            $this->clear_gift_cards_list_cache();

            wp_send_json_success( __( 'Gift card deleted successfully.', 'gift-cards-for-woocommerce' ) );
        } else {
            wp_send_json_error( __( 'Failed to delete gift card.', 'gift-cards-for-woocommerce' ) );
        }
    }

    /**
     * Processes the gift card purchase by saving recipient and delivery date in cart item data.
     *
     * @param array $cart_item_data The cart item data array.
     * 
     * @since  1.0.0
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
                // Apply balance to the order.
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
     * 
     * @since  1.0.0
     * @return void
     */
    public function update_balance_on_completion( $order_id ) {
        $order = wc_get_order( $order_id );
    
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( 'yes' === get_post_meta( $product->get_id(), '_is_gift_card', true ) ) {
                $gift_card_data = [
                    'gift_card_type'    => $item->get_meta( 'gift_card_type' ),
                    'recipient_email'   => $item->get_meta( 'gift_card_to' ),
                    'sender_name'       => $item->get_meta( 'gift_card_from' ),
                    'message'           => $item->get_meta( 'gift_card_message' ),
                    'delivery_date'     => $item->get_meta( 'gift_card_delivery_date' ),
                    'balance'           => $item->get_total(),
                ];

                // Generate unique code.
                $code = $this->generate_unique_code();

                // Insert gift card into database.
                global $wpdb;
                $table_name = $wpdb->prefix . 'gift_cards';
    
                $wpdb->insert(
                    $table_name,
                    [
                        'code'            => $code,
                        'balance'         => $gift_card_data['balance'],
                        'expiration_date' => null,
                        'sender_name'     => $gift_card_data['sender_name'],
                        'sender_email'    => $order->get_billing_email(),
                        'recipient_email' => $gift_card_data['recipient_email'],
                        'message'         => $gift_card_data['message'],
                        'issued_date'     => current_time( 'mysql' ),
                        'delivery_date'   => $gift_card_data['delivery_date'],
                        'gift_card_type'  => $gift_card_data['gift_card_type'],
                    ],
                    [ '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
                );

                // Prepare gift card object for email.
                $gift_card = (object) [
                    'code'            => $code,
                    'balance'         => $gift_card_data['balance'],
                    'sender_name'     => $gift_card_data['sender_name'], // Add sender_name here
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
                if ( 'digital' === $gift_card_data['gift_card_type'] ) {
                    if ( empty( $gift_card_data['delivery_date'] ) || strtotime( $gift_card_data['delivery_date'] ) <= current_time( 'timestamp' ) ) {
                        $this->send_gift_card_email( $gift_card );
                    }
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
     * @since  1.0.0
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
            esc_html__( 'Gift Cards', 'gift-cards-for-woocommerce' ),
            esc_html__( 'Gift Cards', 'gift-cards-for-woocommerce' ),
            'manage_woocommerce',
            'gift-cards-free',
            [ $this, 'display_admin_page' ]
        );
    }

    /**
     * Displays the Gift Cards admin page with tabs for different sections.
     *
     * This function outputs the main Gift Cards admin page, with options to export/import CSV files,
     * and navigate between "Gift Cards", "Activity", and "Add Card" sections.
     *
     * @return void
     */
    public function display_admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'gift_cards';

        // Check for import status and display messages
        if ( isset( $_GET['import_success'] ) ) {
            if ( 'true' === $_GET['import_success'] ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'CSV imported successfully!', 'gift-cards-for-woocommerce' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error importing CSV. Please check the file format and try again.', 'gift-cards-for-woocommerce' ) . '</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Gift Cards', 'gift-cards-for-woocommerce' ); ?>
                <?php if ( 'gift_cards' === $active_tab ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=gift-cards-free&action=export_csv' ), 'export_gift_cards' ) ); ?>" class="page-title-action">
                        <?php esc_html_e( 'Export CSV', 'gift-cards-for-woocommerce' ); ?>
                    </a>

                    <button type="button" class="page-title-action" id="import-csv-button">
                        <?php esc_html_e( 'Import CSV', 'gift-cards-for-woocommerce' ); ?>
                    </button>

                    <form id="import-csv-form" method="post" enctype="multipart/form-data" style="display: none;">
                        <?php wp_nonce_field( 'import_gift_cards', 'import_gift_cards_nonce' ); ?>
                        <input type="file" name="gift_card_csv" id="gift_card_csv" accept=".csv" required onchange="document.getElementById('import-csv-form').submit();">
                    </form>
                <?php endif; ?>
            </h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=gift-cards-free&tab=gift_cards" class="nav-tab <?php echo ( $active_tab === 'gift_cards' ) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Gift Cards', 'gift-cards-for-woocommerce' ); ?>
                </a>
                <a href="?page=gift-cards-free&tab=activity" class="nav-tab <?php echo ( $active_tab === 'activity' ) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Activity', 'gift-cards-for-woocommerce' ); ?>
                </a>
                <a href="?page=gift-cards-free&tab=add_card" class="nav-tab <?php echo ( $active_tab === 'add_card' ) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Add Card', 'gift-cards-for-woocommerce' ); ?>
                </a>
                <a href="?page=gift-cards-free&tab=settings" class="nav-tab <?php echo ( $active_tab === 'settings' ) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'gift-cards-for-woocommerce' ); ?>
                </a>
            </h2>
            <?php
            // Display content based on the active tab.
            switch ( $active_tab ) {
                case 'gift_cards':
                    $this->display_gift_cards_table();
                    break;
                case 'activity':
                    $this->display_activity_table();
                    break;
                case 'add_card':
                    $this->display_add_card_form();
                    break;
                case 'settings':
                    $this->display_settings_page();
                    break;
            }
            // After displaying the gift cards table, add the modal HTML.
            if ( 'gift_cards' === $active_tab ) {
                // Modal HTML.
                ?>
                <div id="gift-card-edit-modal" style="display:none;">
                    <p class="message" style="display: none;"></p>
                    <form id="gift-card-edit-form" class="wc-gift-card-form">
                        <?php wp_nonce_field( 'update_gift_card', 'update_gift_card_nonce' ); ?>
                        <input type="hidden" name="code" id="gift-card-code">

                        <p class="form-field form-row form-row-wide">
                            <label for="gift-card-balance"><?php esc_html_e( 'Gift Card Balance', 'gift-cards-for-woocommerce' ); ?> <span class="required">*</span></label>
                            <input type="number" class="input-text" name="balance" id="gift-card-balance" required min="0.01" step="0.01">
                        </p>

                        <p class="form-field form-row form-row-wide">
                            <label for="gift-card-expiration-date"><?php esc_html_e( 'Expiration Date', 'gift-cards-for-woocommerce' ); ?></label>
                            <input type="date" class="input-text" name="expiration_date" id="gift-card-expiration-date">
                        </p>

                        <p class="form-field form-row form-row-wide">
                            <label for="gift-card-recipient-email"><?php esc_html_e( 'Recipient Email', 'gift-cards-for-woocommerce' ); ?> <span class="required">*</span></label>
                            <input type="email" class="input-text" name="recipient_email" id="gift-card-recipient-email" required>
                        </p>

                        <p class="form-field form-row form-row-wide">
                            <label for="gift-card-sender-name"><?php esc_html_e( 'Sender Name', 'gift-cards-for-woocommerce' ); ?></label>
                            <input type="text" class="input-text" name="sender_name" id="gift-card-sender-name">
                        </p>

                        <p class="form-field form-row form-row-wide">
                            <label for="gift-card-message"><?php esc_html_e( 'Message', 'gift-cards-for-woocommerce' ); ?></label>
                            <textarea name="message" id="gift-card-message" class="input-text" rows="4"></textarea>
                        </p>

                        <p class="form-row">
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'gift-cards-for-woocommerce' ); ?></button>
                        </p>
                    </form>
                </div>
                <?php
            } else {
                // Do nothing.
            }
            ?>
        </div>
        <?php
    }

    /**
     * Displays the Gift Cards list table within the admin page.
     *
     * This function renders the list table, providing an interface to view and manage gift cards.
     *
     * @since  1.0.0
     * @return void
     */
    public function display_gift_cards_table() {
        $gift_cards_table = new Gift_Cards_List_Table();
        $gift_cards_table->prepare_items();
        ?>
        <form method="get">
            <?php
            // Preserve the page and tab parameters
            echo '<input type="hidden" name="page" value="gift-cards-free" />';
            echo '<input type="hidden" name="tab" value="gift_cards" />';
            $gift_cards_table->search_box( __( 'Search Gift Cards', 'gift-cards-for-woocommerce' ), 'gift-card-search' );
            $gift_cards_table->display();
            ?>
        </form>
        <?php
    }

    /**
     * Displays the activity logs in the admin "Activity" tab.
     * 
     * @since  1.0.0
     * @return void
     */
    public function display_activity_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_card_activities';

        $logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY action_date DESC LIMIT 50", ARRAY_A );

        echo '<h2>' . esc_html__( 'Gift Card Activity Log', 'gift-cards-for-woocommerce' ) . '</h2>';
        if ( empty( $logs ) ) {
            echo '<p>' . esc_html__( 'No activity found.', 'gift-cards-for-woocommerce' ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__( 'Date', 'gift-cards-for-woocommerce' ) . '</th><th>' . esc_html__( 'Action', 'gift-cards-for-woocommerce' ) . '</th><th>' . esc_html__( 'Code', 'gift-cards-for-woocommerce' ) . '</th><th>' . esc_html__( 'Amount', 'gift-cards-for-woocommerce' ) . '</th><th>' . esc_html__( 'User ID', 'gift-cards-for-woocommerce' ) . '</th></tr></thead>';
        echo '<tbody>';
        foreach ( $logs as $log ) {
            echo '<tr>';
            echo '<td>' . esc_html( $log['action_date'] ) . '</td>';
            echo '<td>' . esc_html( $log['action_type'] ) . '</td>';
            echo '<td>' . esc_html( $log['code'] ) . '</td>';
            echo '<td>' . ( isset( $log['amount'] ) ? wc_price( $log['amount'] ) : '-' ) . '</td>';
            echo '<td>' . esc_html( $log['user_id'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Displays the form to issue a new gift card.
     *
     * This form allows the admin to input gift card details, such as balance, type,
     * sender and recipient information, delivery and expiration dates, and a personal message.
     * Upon submission, the form data is processed and saved.
     *
     * @since  1.0.0
     * @return void
     */
    public function display_add_card_form() {
        // Process the form submission.
        $this->process_gift_card_form();
        ?>
        <h2><?php esc_html_e( 'Issue New Gift Card', 'gift-cards-for-woocommerce' ); ?></h2>
        <form method="post" action="" class="wc_gift_card_form">
            <?php wp_nonce_field( 'issue_gift_card', 'issue_gift_card_nonce' ); ?>
            <div class="form-field">
                <label for="balance"><?php esc_html_e( 'Gift Card Balance', 'gift-cards-for-woocommerce' ); ?></label>
                <input type="number" name="balance" id="balance" required min="0.01" step="0.01">
                <span class="description"><?php esc_html_e( 'Enter the balance for the gift card.', 'gift-cards-for-woocommerce' ); ?></span>
            </div>
            <div class="form-field">
                <label for="gift_card_type"><?php esc_html_e( 'Gift Card Type', 'gift-cards-for-woocommerce' ); ?></label>
                <select name="gift_card_type" id="gift_card_type" required>
                    <option value="digital"><?php esc_html_e( 'Digital', 'gift-cards-for-woocommerce' ); ?></option>
                    <option value="physical"><?php esc_html_e( 'Physical', 'gift-cards-for-woocommerce' ); ?></option>
                </select>
            </div>
            <div class="form-field">
                <label for="sender_name"><?php esc_html_e( 'Sender Name', 'gift-cards-for-woocommerce' ); ?></label>
                <input type="text" name="sender_name" id="sender_name" required>
            </div>
            <div class="form-field">
                <label for="recipient_email"><?php esc_html_e( 'Recipient Email', 'gift-cards-for-woocommerce' ); ?></label>
                <input type="email" name="recipient_email" id="recipient_email" required>
            </div>
            <div class="form-field">
                <label for="delivery_date"><?php esc_html_e( 'Delivery Date', 'gift-cards-for-woocommerce' ); ?></label>
                <input type="date" name="delivery_date" id="delivery_date" value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
            </div>
            <div class="form-field">
                <label for="expiration_date"><?php esc_html_e( 'Expiration Date', 'gift-cards-for-woocommerce' ); ?></label>
                <input type="date" name="expiration_date" id="expiration_date">
            </div>
            <div class="form-field">
                <label for="message"><?php esc_html_e( 'Personal Message', 'gift-cards-for-woocommerce' ); ?></label>
                <textarea name="message" id="message" rows="4" placeholder="<?php esc_attr_e( 'Enter your message here...', 'gift-cards-for-woocommerce' ); ?>"></textarea>
            </div>
            <p class="submit">
                <input type="submit" name="issue_gift_card" id="issue_gift_card" class="button button-primary" value="<?php esc_attr_e( 'Issue Gift Card', 'gift-cards-for-woocommerce' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Displays the settings page for customizing email templates.
     *
     * @since  1.0.0
     * @return void
     */
    public function display_settings_page() {
        // Process form submission
        if ( isset( $_POST['save_gift_card_settings'] ) && check_admin_referer( 'save_gift_card_settings', 'gift_card_settings_nonce' ) ) {
            // Sanitize and save settings.
            $custom_email_image          = isset( $_POST['custom_email_image'] ) ? esc_url_raw( $_POST['custom_email_image'] ) : '';
            $custom_email_text           = isset( $_POST['custom_email_text'] ) ? wp_kses_post( $_POST['custom_email_text'] ) : '';
            $reminder_days_before_expiry = isset( $_POST['reminder_days_before_expiry'] ) ? absint( $_POST['reminder_days_before_expiry'] ) : 7;

            update_option( 'gift_card_custom_email_image', $custom_email_image );
            update_option( 'gift_card_custom_email_text', $custom_email_text );
            update_option( 'gift_card_reminder_days_before_expiry', $reminder_days_before_expiry );

            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'gift-cards-for-woocommerce' ) . '</p></div>';
        }

        // Retrieve existing settings.
        $custom_email_image          = get_option( 'gift_card_custom_email_image', '' );
        $custom_email_text           = get_option( 'gift_card_custom_email_text', '' );
        $reminder_days_before_expiry = get_option( 'gift_card_reminder_days_before_expiry', 7 );

        ?>
        <h2><?php esc_html_e( 'Gift Card Email Settings', 'gift-cards-for-woocommerce' ); ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'save_gift_card_settings', 'gift_card_settings_nonce' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <label for="custom_email_image"><?php esc_html_e( 'Custom Email Image', 'gift-cards-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="custom_email_image" id="custom_email_image" value="<?php echo esc_attr( $custom_email_image ); ?>" style="width:300px;" />
                        <button class="button" id="upload_custom_email_image"><?php esc_html_e( 'Upload Image', 'gift-cards-for-woocommerce' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Upload an image to use in the gift card emails.', 'gift-cards-for-woocommerce' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="custom_email_text"><?php esc_html_e( 'Custom Email Text', 'gift-cards-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor( $custom_email_text, 'custom_email_text', [
                            'textarea_name' => 'custom_email_text',
                            'textarea_rows' => 10,
                        ] );
                        ?>
                        <p class="description"><?php esc_html_e( 'Enter custom text to include in the gift card emails.', 'gift-cards-for-woocommerce' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="reminder_days_before_expiry"><?php esc_html_e( 'Days Before Expiration for Reminder Email', 'gift-cards-for-woocommerce' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="reminder_days_before_expiry" id="reminder_days_before_expiry" value="<?php echo esc_attr( $reminder_days_before_expiry ); ?>" min="1" style="width:100px;" />
                        <p class="description"><?php esc_html_e( 'Enter the number of days before a gift card expires to send a reminder email.', 'gift-cards-for-woocommerce' ); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_gift_card_settings" id="save_gift_card_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'gift-cards-for-woocommerce' ); ?>">
            </p>
        </form>
        <?php

        // Enqueue the media uploader script.
        wp_enqueue_media();

        // Add inline script to handle the media uploader.
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#upload_custom_email_image').on('click', function(e) {
                e.preventDefault();
                var image_frame;
                if (image_frame) {
                    image_frame.open();
                }
                // Define image_frame as wp.media object.
                image_frame = wp.media({
                    title: '<?php esc_html_e( 'Select Image', 'gift-cards-for-woocommerce' ); ?>',
                    multiple : false,
                    library : {
                        type : 'image',
                    }
                });

                image_frame.on('select', function(){
                    var attachment = image_frame.state().get('selection').first().toJSON();
                    $('#custom_email_image').val( attachment.url );
                });

                image_frame.open();
            });
        });
        </script>
        <?php
    }

    /**
     * Processes the gift card form submission, saves the gift card to the database, and logs the creation.
     *
     * Validates and sanitizes input fields from the "Add Card" form, generates a unique gift card code,
     * inserts the new gift card record into the database, and logs the creation action.
     *
     * @since 1.0.0
     * @return void
     */
    public function process_gift_card_form() {
        if ( isset( $_POST['issue_gift_card'] ) && check_admin_referer( 'issue_gift_card', 'issue_gift_card_nonce' ) ) {
            global $wpdb;

            // Retrieve and sanitize form inputs.
            $balance         = isset( $_POST['balance'] ) ? floatval( $_POST['balance'] ) : 0.00;
            $gift_card_type  = isset( $_POST['gift_card_type'] ) ? sanitize_text_field( $_POST['gift_card_type'] ) : 'digital';
            $sender_name     = isset( $_POST['sender_name'] ) ? sanitize_text_field( $_POST['sender_name'] ) : '';
            $recipient_email = isset( $_POST['recipient_email'] ) ? sanitize_email( $_POST['recipient_email'] ) : '';
            $delivery_date   = isset( $_POST['delivery_date'] ) ? sanitize_text_field( $_POST['delivery_date'] ) : date( 'Y-m-d' );
            $expiration_date = ! empty( $_POST['expiration_date'] ) ? sanitize_text_field( $_POST['expiration_date'] ) : null;
            $message         = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

            // Validate balance and recipient email.
            if ( $balance > 0 && is_email( $recipient_email ) ) {

                // Generate a unique gift card code.
                $code = $this->generate_unique_code();

                // Prepare data for insertion.
                $data = [
                    'code'            => $code,
                    'balance'         => $balance,
                    'sender_name'     => $sender_name,
                    'sender_email'    => wp_get_current_user()->user_email,
                    'recipient_email' => $recipient_email,
                    'message'         => $message,
                    'issued_date'     => current_time( 'mysql' ),
                    'delivery_date'   => $delivery_date,
                    'gift_card_type'  => $gift_card_type,
                ];

                $formats = [ '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

                // Add expiration date to data if provided.
                if ( ! is_null( $expiration_date ) ) {
                    $data['expiration_date'] = $expiration_date;
                    $formats[] = '%s';
                }

                // Insert the gift card into the database.
                $table_name = $wpdb->prefix . 'gift_cards';
                $wpdb->insert( $table_name, $data, $formats );

                // Associate the gift card with a user account if the recipient email matches.
                $user = get_user_by( 'email', $recipient_email );
                if ( $user ) {
                    $wpdb->update(
                        $table_name,
                        [ 'user_id' => $user->ID ],
                        [ 'code'    => $code ],
                        [ '%d' ],
                        [ '%s' ]
                    );
                }

                // Log the creation of this gift card.
                $this->log_creation( $code, $balance, $user ? $user->ID : null );

                // Prepare gift card object for email
                $gift_card = (object) [
                    'code'            => $code,
                    'balance'         => $balance,
                    'sender_name'     => $sender_name,
                    'sender_email'    => wp_get_current_user()->user_email,
                    'recipient_email' => $recipient_email,
                    'message'         => $message,
                    'gift_card_type'  => $gift_card_type,
                    'delivery_date'   => $delivery_date,
                ];

                // Send email if delivery date is today or in the past.
                if ( empty( $delivery_date ) || strtotime( $delivery_date ) <= current_time( 'timestamp' ) ) {
                    $this->send_gift_card_email( $gift_card );
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
                GIFT_CARDS_FOR_WOOCOMMERCE_VERSION,
                true
            );
            wp_enqueue_style(
                'wc-gift-card-styles',
                plugins_url( 'assets/css/gift-cards.css', __FILE__ ),
                [],
                GIFT_CARDS_FOR_WOOCOMMERCE_VERSION
            );
        }

        if ( is_product() ) {
            wp_enqueue_style(
                'wc-gift-card-product-styles',
                plugins_url( 'assets/css/gift-cards.css', __FILE__ ),
                [],
                GIFT_CARDS_FOR_WOOCOMMERCE_VERSION
            );
        }
    }

    /**
     * Applies the gift card discount at checkout if a valid code is entered.
     *
     * This method retrieves the user's gift card balance and applies it as a discount
     * at checkout, up to the order subtotal. It also saves the discount amount in the session.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     * @return void
     */
    public function apply_gift_card_discount( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        global $wpdb;
        $table_name    = $wpdb->prefix . 'gift_cards';
        $total_balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(balance) FROM $table_name WHERE user_id = %d AND balance > 0", $user_id
        ) );

        $requested_amount = floatval( WC()->session->get( 'apply_gift_card_balance' ) ?: $total_balance );
        $cart_total       = floatval( $cart->get_subtotal() );

        // Determine the discount amount, limited by available balance and cart total.
        $discount = min( $requested_amount, $total_balance, $cart_total );

        if ( $discount > 0 ) {
            $cart->add_fee( esc_html__( 'Gift Card Discount', 'gift-cards-for-woocommerce' ), -$discount );
            WC()->session->set( 'gift_card_discount_amount', $discount );
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
     * This method checks if the "Gift Card" option is selected in the product edit page
     * and updates the product meta accordingly.
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

        // Not a gift card, so exit.
        if ( 'yes' !== get_post_meta( $product->get_id(), '_is_gift_card', true ) ) {
            return;
        }

        echo '<div class="gift-card-fields">';

        // Gift Card Type
        woocommerce_form_field( 'gift_card_type', [
            'type'     => 'select',
            'label'    => __( 'Gift Card Type', 'gift-cards-for-woocommerce' ),
            'required' => true,
            'options'  => [
                'digital'  => __( 'Digital', 'gift-cards-for-woocommerce' ),
                'physical' => __( 'Physical', 'gift-cards-for-woocommerce' ),
            ],
        ] );

        // To (email).
        woocommerce_form_field( 'gift_card_to', [
            'type'     => 'email',
            'label'    => __( 'To (Email)', 'gift-cards-for-woocommerce' ),
            'required' => true,
        ] );

        // From (name).
        woocommerce_form_field( 'gift_card_from', [
            'type'     => 'text',
            'label'    => __( 'From (Name)', 'gift-cards-for-woocommerce' ),
            'required' => true,
        ] );

        // Message.
        woocommerce_form_field( 'gift_card_message', [
            'type'  => 'textarea',
            'label' => __( 'Message', 'gift-cards-for-woocommerce' ),
        ] );

        // Delivery Date.
        woocommerce_form_field( 'gift_card_delivery_date', [
            'type'              => 'date',
            'label'             => __( 'Delivery Date', 'gift-cards-for-woocommerce' ),
            'default'           => date( 'Y-m-d', current_time( 'timestamp' ) ),
            'required'          => true,
            'custom_attributes' => [
                'min' => date( 'Y-m-d', current_time( 'timestamp' ) ),
            ],
        ] );

        echo '</div>';
    }

    /**
     * Adds gift card data to the cart item.
     *
     * @param array $cart_item_data The cart item data.
     * @param int $product_id The product ID.
     * 
     * @since  1.0.0
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
            $item->add_meta_data( 'gift_card_type', sanitize_text_field( $values['gift_card_type'] ), true );
            $item->add_meta_data( 'gift_card_to', sanitize_text_field( $values['gift_card_to'] ), true );
            $item->add_meta_data( 'gift_card_from', sanitize_text_field( $values['gift_card_from'] ), true );
            $item->add_meta_data( 'gift_card_message', sanitize_textarea_field( $values['gift_card_message'] ), true );
            $item->add_meta_data( 'gift_card_delivery_date', sanitize_text_field( $values['gift_card_delivery_date'] ), true );
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

        if ( ! wp_next_scheduled( 'wc_send_gift_card_expiry_reminders' ) ) {
            wp_schedule_event( strtotime( 'midnight' ), 'daily', 'wc_send_gift_card_expiry_reminders' );
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
        $today = current_time( 'Y-m-d' );
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
        // Ensure that the email classes are initialized.
        WC()->mailer()->emails;

        // Trigger the email.
        do_action( 'wc_gift_card_email_notification', $gift_card );
    }

    /**
     * Sends reminder emails for gift cards that are about to expire.
     *
     * @return void
     */
    public function send_gift_card_expiry_reminder_emails() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        // Get the number of days before expiration to send the reminder.
        $days_before_expiry = get_option( 'gift_card_reminder_days_before_expiry', 7 );

        // Calculate the date range.
        $start_date = date( 'Y-m-d', current_time( 'timestamp' ) );
        $end_date   = date( 'Y-m-d', strtotime( '+' . $days_before_expiry . ' days', current_time( 'timestamp' ) ) );

        // Query for gift cards that expire between now and the target date.
        $gift_cards = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE expiration_date BETWEEN %s AND %s AND expiration_date IS NOT NULL AND expiration_date != '0000-00-00'",
            $start_date,
            $end_date
        ) );

        if ( ! empty( $gift_cards ) ) {
            foreach ( $gift_cards as $gift_card ) {
                $user_id = $gift_card->user_id ? intval( $gift_card->user_id ) : null;
                $this->log_expiration_reminder_sent( $gift_card->code, $user_id );
                $this->send_gift_card_expiry_reminder_email( $gift_card );
            }
        }
    }

    /**
     * Sends a reminder email that a gift card is about to expire.
     *
     * @param object $gift_card The gift card data.
     * @return void
     */
    private function send_gift_card_expiry_reminder_email( $gift_card ) {
        // Ensure that the email classes are initialized.
        WC()->mailer()->emails;

        // Trigger the reminder email.
        do_action( 'wc_gift_card_expiry_reminder_email_notification', $gift_card );
    }

    /**
     * Generates gift card variations when the product is marked as a gift card.
     *
     * @param int $post_id The ID of the product post.
     * @return void
     */
    public function generate_gift_card_variations( $post_id ) {
        // Check if the product is marked as a gift card.
        if ( isset( $_POST['_is_gift_card'] ) && $_POST['_is_gift_card'] === 'yes' ) {
            $product = wc_get_product( $post_id );

            // Only proceed if the product is variable.
            if ( $product->is_type( 'variable' ) ) {
                $attribute_name = 'Gift Card Amount';

                // Set up the attribute for "Gift Card Amount" if not present.
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

                // Create variations for each amount.
                foreach ( $this->gift_card_amounts as $amount ) {
                    // Check if a variation with this amount already exists.
                    $existing_variation_id = $this->find_existing_variation( $product, 'gift_card_amount', $amount );
                    if ( ! $existing_variation_id ) {
                        // Create a new variation.
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
     * 
     * @since  1.0.0
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
     * 
     * @since  1.0.0
     * @return void
     */
    public function add_my_account_endpoint() {
        add_rewrite_endpoint( 'gift-cards', EP_ROOT | EP_PAGES );
    }

    /**
     * Adds the 'gift-cards' query var.
     *
     * @param array $vars Query vars.
     * 
     * @since  1.0.0
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
     * 
     * @since  1.0.0
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
     * 
     * @since  1.0.0
     * @return void
     */
    public function my_account_gift_cards_content() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'You need to be logged in to view your gift cards.', 'gift-cards-for-woocommerce' ) . '</p>';
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        // Get active gift cards associated with the user.
        $gift_cards = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND balance > 0", $user_id
        ), ARRAY_A );

        if ( empty( $gift_cards ) ) {
            echo '<p>' . esc_html__( 'You have no active gift cards.', 'gift-cards-for-woocommerce' ) . '</p>';
            return;
        }

        // Calculate total balance.
        $total_balance = 0;
        foreach ( $gift_cards as $gift_card ) {
            $total_balance += $gift_card['balance'];
        }

        // Display total balance.
        echo '<h2>' . esc_html__( 'Your Gift Card Balance', 'gift-cards-for-woocommerce' ) . '</h2>';
        echo '<p>' . sprintf( esc_html__( 'Total Balance: %s', 'gift-cards-for-woocommerce' ), wc_price( $total_balance ) ) . '</p>';

        // Display table of gift cards.
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

            $expiration_date = $gift_card['expiration_date'];

            if ( ! empty( $expiration_date ) && $expiration_date !== '0000-00-00' && strtotime( $expiration_date ) ) {
                echo '<td>' . date_i18n( get_option( 'date_format' ), strtotime( $expiration_date ) ) . '</td>';
            } else {
                echo '<td>' . esc_html__( 'No Expiration', 'gift-cards-for-woocommerce' ) . '</td>';
            }

            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Flushes rewrite rules and adds the custom My Account endpoint.
     *
     * This method ensures that custom rewrite rules are registered and then flushed,
     * allowing the custom "Gift Cards" endpoint in the My Account section to function correctly.
     *
     * @since  1.0.0
     * @return void
     */
    public function flush_rewrite_rules() {
        $this->add_my_account_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Displays the gift card application checkbox in the totals section of checkout.
     * 
     * @since  1.0.0
     * @return void
     */
    public function display_gift_card_checkbox() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        $total_balance = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(balance) FROM $table_name WHERE user_id = %d AND balance > 0", $user_id
        ) );

        $total_balance   = floatval( $total_balance );
        $applied_balance = WC()->session->get( 'apply_gift_card_balance' ) ?: $total_balance;

        if ( $total_balance > 0 ) {
            ?>
            <div class="gift-card-application" style="background-color: #f0f8ff; padding: 15px; border: 1px solid #dcdcdc; margin-bottom: 20px;">
                <p style="margin: 0; padding: 0;">
                    <strong><?php esc_html_e( 'Gift Card Balance:', 'gift-cards-for-woocommerce' ); ?></strong>
                    <?php printf( __( '%s', 'gift-cards-for-woocommerce' ), wc_price( $total_balance ) ); ?>
                    <a href="#" id="edit-gift-card-amount" style="margin-left: 10px;"><?php esc_html_e( 'Edit', 'gift-cards-for-woocommerce' ); ?></a>
                </p>
                <input type="number" id="gift_card_amount_input" name="gift_card_amount" value="<?php echo esc_attr($applied_balance); ?>" max="<?php echo esc_attr($total_balance); ?>" min="0" style="display: none; width: 100px;">
            </div>
            <?php
        }
    }

    /**
     * Updates the session with the gift card application status.
     *
     * This method parses the posted checkout data to check if a gift card amount
     * has been entered. If so, it saves the amount in the session for use as a discount
     * at checkout; otherwise, it resets the session balance to zero.
     *
     * @param array|string $posted_data The posted data from the checkout form.
     * 
     * @since  1.0.0
     * @return void
     */
    public function update_gift_card_session( $posted_data ) {
        parse_str( $posted_data, $output );

        if ( isset( $output['gift_card_amount'] ) ) {
            $amount = floatval( $output['gift_card_amount'] );
            WC()->session->set( 'apply_gift_card_balance', $amount );
        } else {
            WC()->session->set( 'apply_gift_card_balance', 0 );
        }
    }

    /**
     * Stores the applied gift card discount in the order meta.
     *
     * @param WC_Order $order The order object.
     * @param array    $data  The posted data.
     * 
     * @since  1.0.0
     * @return void
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
     * This method deducts the applied gift card discount amount from the user's gift card balances.
     * It follows a "first-in, first-out" approach by reducing balances in the order they were issued.
     *
     * @param int $order_id The ID of the completed order.
     * 
     * @since  1.0.0
     * @return void
     */
    public function reduce_gift_card_balance( $order_id ) {
        $order   = wc_get_order( $order_id );
        $user_id = $order->get_user_id();

        if ( ! $user_id ) return;

        $discount_amount = floatval( $order->get_meta( '_applied_gift_card_discount' ) );

        if ( $discount_amount > 0 ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gift_cards';
            $gift_cards = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND balance > 0 ORDER BY issued_date ASC", $user_id
            ) );

            $remaining_discount = $discount_amount;

            foreach ( $gift_cards as $gift_card ) {
                if ( $remaining_discount <= 0 ) break;

                $deduction   = min( $gift_card->balance, $remaining_discount );
                $new_balance = $gift_card->balance - $deduction;
                $remaining_discount -= $deduction;

                $wpdb->update(
                    $table_name,
                    [ 'balance' => $new_balance ],
                    [ 'id' => $gift_card->id ],
                    [ '%f' ],
                    [ '%d' ]
                );

                // Log the usage of this gift card.
                $this->log_usage($gift_card->code, $deduction, $user_id);
            }
        }
    }

    /**
     * Exports gift cards data to a CSV file for download.
     *
     * This function checks user permissions and verifies the nonce for security.
     * If gift card data is available, it generates a CSV file with relevant columns
     * such as code, balance, expiration date, and more. The CSV file is then output
     * for download by the browser. If no data is available, the user is redirected
     * back to the admin page with an error message.
     *
     * @since  1.0.0
     * @return void Outputs a CSV file for download or redirects with an error message.
     */
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
            // No data to export, redirect back with a notice.
            wp_redirect( add_query_arg( 'export_error', 'no_data', admin_url( 'admin.php?page=gift-cards-free' ) ) );
            exit;
        }

        // Set the headers for CSV download.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=gift-cards-' . current_time( 'Y-m-d' ) . '.csv' );

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

        // Loop over the rows and output them.
        foreach ( $gift_cards as $gift_card ) {
            // Create an array that matches the order of headers.
            $data = [];
            foreach ( array_keys( $headers ) as $key ) {
                $data[] = isset( $gift_card[ $key ] ) ? $gift_card[ $key ] : '';
            }
            fputcsv( $output, $data );
        }

        fclose( $output );

        exit;
    }

    /**
     * Handles the import of gift card data from a CSV file.
     *
     * Validates permissions, nonce, and the uploaded CSV file.
     * Parses each row in the CSV, sanitizes data, and inserts it into the database.
     * Redirects to the admin page with success or failure messages based on import results.
     *
     * @return void
     */
    public function handle_import_action() {
        // Verify file upload and nonce
        if ( isset( $_FILES['gift_card_csv'] ) && check_admin_referer( 'import_gift_cards', 'import_gift_cards_nonce' ) ) {

            // Ensure the user has the right permissions
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'You do not have permission to import gift cards.', 'gift-cards-for-woocommerce' ), '', [ 'response' => 403 ] );
            }

            // Check that the file is not empty
            if ( ! empty( $_FILES['gift_card_csv']['tmp_name'] ) ) {
                $file   = $_FILES['gift_card_csv']['tmp_name'];
                $handle = fopen( $file, 'r' );

                $imported_count = 0;

                if ( $handle !== false ) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'gift_cards';

                    fgetcsv( $handle, 1000, ',' ); // Skip header

                    while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
                        $result = $wpdb->insert(
                            $table_name,
                            [
                                'code'            => sanitize_text_field( $data[0] ),
                                'balance'         => floatval( $data[1] ),
                                'expiration_date' => sanitize_text_field( $data[2] ),
                                'sender_name'     => sanitize_text_field( $data[3] ),
                                'sender_email'    => sanitize_email( $data[4] ),
                                'recipient_email' => sanitize_email( $data[5] ),
                                'message'         => sanitize_textarea_field( $data[6] ),
                                'issued_date'     => sanitize_text_field( $data[7] ),
                                'delivery_date'   => sanitize_text_field( $data[8] ),
                                'gift_card_type'  => sanitize_text_field( $data[9] ),
                                'user_id'         => intval( $data[10] ),
                            ]
                        );

                        if ( $result ) {
                            $imported_count++;
                        }
                    }

                    fclose( $handle );

                    $this->log_import_export( 'import', get_current_user_id(), $imported_count );

                    $redirect_url = add_query_arg(
                        'import_success',
                        $imported_count > 0 ? 'true' : 'false',
                        admin_url( 'admin.php?page=gift-cards-free' )
                    );
                    wp_redirect( esc_url_raw( $redirect_url ) );
                    exit;
                }
            } else {
                $redirect_url = add_query_arg( 'import_success', 'false', admin_url( 'admin.php?page=gift-cards-free' ) );
                wp_redirect( esc_url_raw( $redirect_url ) );
                exit;
            }
        }
    }

    /**
     * Handles the export action for gift cards.
     *
     * Checks if the current page and action are set to export the gift cards CSV,
     * then calls the `export_gift_cards_csv()` function to generate and output the CSV file.
     *
     * @since  1.0.0
     * @return void
     */
    public function handle_export_action() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'gift-cards-free' && isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' ) {
            $this->export_gift_cards_csv();
        }
    }

    /**
     * Adds a custom gift card email class to WooCommerce's email classes.
     *
     * Loads the custom gift card email class file and adds it to the array of WooCommerce
     * email classes, enabling WooCommerce to send gift card-related emails.
     *
     * @param array $email_classes Existing WooCommerce email classes.
     * 
     * @since  1.0.0
     * @return array Modified email classes with the custom gift card email class added.
     */
    public function add_gift_card_email_class( $email_classes ) {
        // Include the custom email class.
        require_once plugin_dir_path( __FILE__ ) . 'classes/class-wc-gift-card-email.php';
        require_once plugin_dir_path( __FILE__ ) . 'classes/class-wc-gift-card-expiry-reminder-email.php';

        // Add the email classes to the list of email classes that WooCommerce® loads.
        $email_classes['WC_Gift_Card_Email'] = new WC_Gift_Card_Email();
        $email_classes['WC_Gift_Card_Expiry_Reminder_Email'] = new WC_Gift_Card_Expiry_Reminder_Email();

        return $email_classes;
    }

    /**
     * Retrieves gift card data via AJAX.
     *
     * This method handles the AJAX request to retrieve gift card details by code.
     * It checks permissions, verifies the nonce, sanitizes the code, and formats
     * the gift card data for use in the edit form.
     *
     * @return void Outputs JSON response with gift card data or error message.
     */
    public function get_gift_card_data_ajax() {
        check_ajax_referer( 'edit_gift_card_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'gift-cards-for-woocommerce' ) );
        }

        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';

        if ( empty( $code ) ) {
            wp_send_json_error( __( 'Invalid gift card code.', 'gift-cards-for-woocommerce' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        $gift_card = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE code = %s", $code ), ARRAY_A );

        if ( $gift_card ) {
            // Format date fields for input[type="date"].
            $gift_card['expiration_date'] = ! empty( $gift_card['expiration_date'] ) && '0000-00-00' !== $gift_card['expiration_date'] ? date( 'Y-m-d', strtotime( $gift_card['expiration_date'] ) ) : '';
            $gift_card['delivery_date']   = ! empty( $gift_card['delivery_date'] ) && '0000-00-00' !== $gift_card['delivery_date'] ? date( 'Y-m-d', strtotime( $gift_card['delivery_date'] ) ) : '';

            wp_send_json_success( $gift_card );
        } else {
            wp_send_json_error( __( 'Gift card not found.', 'gift-cards-for-woocommerce' ) );
        }
    }

    /**
     * Handles the AJAX request to update gift card details.
     *
     * Verifies permissions, validates the nonce, and updates specified fields.
     * Logs balance adjustments and expiration date updates only when changes occur.
     *
     * @since  1.0.0
     * @return void Outputs JSON response indicating success or failure.
     */
    public function update_gift_card_ajax() {
        // Verify the nonce for security.
        check_ajax_referer( 'update_gift_card', 'update_gift_card_nonce' );

        // Check if the current user has the required permissions.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'gift-cards-for-woocommerce' ) );
        }

        // Retrieve and sanitize the gift card code from the POST request.
        $code = isset( $_POST['code'] ) ? sanitize_text_field( $_POST['code'] ) : '';
        if ( empty( $code ) ) {
            wp_send_json_error( esc_html__( 'Invalid gift card code.', 'gift-cards-for-woocommerce' ) );
        }

        // Collect and sanitize other fields from the POST request.
        $balance             = isset( $_POST['balance'] ) ? floatval( $_POST['balance'] ) : 0.00;
        $new_expiration_date = isset( $_POST['expiration_date'] ) ? sanitize_text_field( $_POST['expiration_date'] ) : null;
        $recipient_email     = isset( $_POST['recipient_email'] ) ? sanitize_email( $_POST['recipient_email'] ) : '';
        $sender_name         = isset( $_POST['sender_name'] ) ? sanitize_text_field( $_POST['sender_name'] ) : '';
        $message             = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

        // Validate that the balance is not negative.
        if ( $balance < 0 ) {
            wp_send_json_error( esc_html__( 'Balance cannot be negative.', 'gift-cards-for-woocommerce' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        // Retrieve the current expiration_date from the database.
        $current_gift_card = $wpdb->get_row( $wpdb->prepare( "SELECT expiration_date FROM $table_name WHERE code = %s", $code ), ARRAY_A );

        if ( ! $current_gift_card ) {
            wp_send_json_error( esc_html__( 'Gift card not found.', 'gift-cards-for-woocommerce' ) );
        }

        $current_expiration_date = $current_gift_card['expiration_date'];

        // Normalize dates for accurate comparison.
        $current_expiration_date_normalized = ( ! empty( $current_expiration_date ) && '0000-00-00' !== $current_expiration_date ) ? date( 'Y-m-d', strtotime( $current_expiration_date ) ) : '';
        $new_expiration_date_normalized     = ( ! empty( $new_expiration_date ) && '0000-00-00' !== $new_expiration_date ) ? date( 'Y-m-d', strtotime( $new_expiration_date ) ) : '';

        // Determine if the expiration date has changed.
        $is_expiration_changed = ( $current_expiration_date_normalized !== $new_expiration_date_normalized );

        // Prepare the data for updating the gift card.
        $update_data = [
            'balance'         => $balance,
            'recipient_email' => $recipient_email,
            'sender_name'     => $sender_name,
            'message'         => $message,
        ];
        $update_format = [ '%f', '%s', '%s', '%s' ];

        // Conditionally include 'expiration_date' if it has changed.
        if ( $is_expiration_changed ) {
            $update_data['expiration_date'] = ! empty( $new_expiration_date_normalized ) ? $new_expiration_date_normalized : null;
            $update_format[]                = '%s';
        }

        // Execute the update query.
        $updated = $wpdb->update(
            $table_name,
            $update_data,
            [ 'code' => $code ],
            $update_format,
            [ '%s' ]
        );

        // Check if the update was successful.
        if ( false !== $updated ) {
            // Clear cached data for the list table to ensure fresh data is displayed.
            $this->clear_gift_cards_list_cache();

            // Log the balance adjustment.
            $this->log_balance_adjustment( $code, $balance, get_current_user_id() );

            // Conditionally log the expiration date update only if it has changed.
            if ( $is_expiration_changed ) {
                $this->log_expiration_update( $code, $new_expiration_date_normalized, get_current_user_id() );
            }

            // Send a success response back to the AJAX call.
            wp_send_json_success( esc_html__( 'Gift card updated successfully.', 'gift-cards-for-woocommerce' ) );
        } else {
            // Send an error response if the update failed.
            wp_send_json_error( esc_html__( 'Failed to update gift card.', 'gift-cards-for-woocommerce' ) );
        }
    }

    /**
     * Exports gift cards data in batches to a CSV file.
     *
     * @return void Outputs JSON response indicating batch progress or completion.
     */
    public function batch_export_gift_cards() {
        check_ajax_referer( 'gift_cards_nonce', '_ajax_nonce' );

        // Define offset and batch size from AJAX data
        $offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';

        // Retrieve a batch of gift card records
        $gift_cards = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_name LIMIT %d OFFSET %d",
            $batch_size,
            $offset
        ), ARRAY_A );

        // Define CSV file path
        $file_path = wp_upload_dir()['basedir'] . '/gift-cards-export.csv';
        $file = fopen( $file_path, $offset === 0 ? 'w' : 'a' );

        // Write gift card data to CSV
        if ( $offset === 0 ) {
            fputcsv( $file, array_keys( $gift_cards[0] ) ); // Header row
        }

        foreach ( $gift_cards as $gift_card ) {
            fputcsv( $file, $gift_card );
        }
        fclose( $file );

        // If this is the last batch, return completion status
        if ( count( $gift_cards ) < $batch_size ) {
            wp_send_json_success( [ 'complete' => true, 'file_url' => wp_upload_dir()['baseurl'] . '/gift-cards-export.csv' ] );
        } else {
            wp_send_json_success( [ 'complete' => false ] );
        }
    }

    /**
     * Imports gift card data from a CSV file in batches.
     *
     * @return void Outputs JSON response indicating batch progress or completion.
     */
    public function import_gift_cards_in_batches() {
        check_ajax_referer( 'gift_cards_nonce', '_ajax_nonce' );

        $offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;

        if ( ! isset( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
            wp_send_json_error( [ 'error' => __( 'File upload error.', 'gift-cards-for-woocommerce' ) ] );
        }

        $file = fopen( $_FILES['file']['tmp_name'], 'r' );
        if ( $file === false ) {
            wp_send_json_error( [ 'error' => __( 'Cannot open uploaded file.', 'gift-cards-for-woocommerce' ) ] );
        }

        // Skip headers and previous rows
        if ( $offset > 0 ) {
            for ( $i = 0; $i < $offset + 1; $i++ ) {
                fgetcsv( $file );
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_cards';
        $rows_imported = 0;

        while ( ( $data = fgetcsv( $file ) ) !== false && $rows_imported < $batch_size ) {
            $wpdb->insert(
                $table_name,
                [
                    'code'            => sanitize_text_field( $data[0] ),
                    'balance'         => floatval( $data[1] ),
                    'expiration_date' => sanitize_text_field( $data[2] ),
                    'sender_name'     => sanitize_text_field( $data[3] ),
                    'sender_email'    => sanitize_email( $data[4] ),
                    'recipient_email' => sanitize_email( $data[5] ),
                    'message'         => sanitize_textarea_field( $data[6] ),
                    'issued_date'     => sanitize_text_field( $data[7] ),
                    'delivery_date'   => sanitize_text_field( $data[8] ),
                    'gift_card_type'  => sanitize_text_field( $data[9] ),
                    'user_id'         => intval( $data[10] ),
                ]
            );
            $rows_imported++;
        }

        fclose( $file );

        // Return completion status
        if ( $rows_imported < $batch_size ) {
            wp_send_json_success( [ 'complete' => true ] );
        } else {
            wp_send_json_success( [ 'complete' => false ] );
        }
    }

    /**
     * Logs an activity related to a gift card.
     *
     * @param string $action_type The type of action (e.g., 'created', 'used').
     * @param string $code        The gift card code.
     * @param float  $amount      The amount associated with the action.
     * @param int    $user_id     The user ID related to the action.
     */
    private function log_activity($action_type, $code, $amount = null, $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gift_card_activities';

        $wpdb->insert(
            $table_name,
            [
                'action_type' => $action_type,
                'code'        => $code,
                'amount'      => $amount,
                'user_id'     => $user_id,
                'action_date' => current_time('mysql'),
            ],
            [ '%s', '%s', '%f', '%d', '%s' ]
        );
    }

    /**
     * Logs the creation of a gift card.
     *
     * @param string $code        The code of the created gift card.
     * @param float  $balance     The initial balance of the gift card.
     * @param int    $user_id     The user ID associated with the card.
     */
    private function log_creation($code, $balance, $user_id = null) {
        $this->log_activity('created', $code, $balance, $user_id);
    }

    /**
     * Logs the usage of a gift card.
     *
     * @param string $code        The code of the used gift card.
     * @param float  $amount_used The amount used.
     * @param int    $user_id     The user ID who used the gift card.
     */
    private function log_usage($code, $amount_used, $user_id = null) {
        $this->log_activity('used', $code, $amount_used, $user_id);
    }

    /**
     * Logs a balance adjustment action for a gift card.
     *
     * @param string $code        The code of the adjusted gift card.
     * @param float  $new_balance The new balance after adjustment.
     * @param int    $user_id     The ID of the admin making the adjustment.
     */
    private function log_balance_adjustment( $code, $new_balance, $user_id ) {
        $this->log_activity( 'balance_adjusted', $code, $new_balance, $user_id );
    }

    /**
     * Logs an update to the expiration date of a gift card.
     *
     * @param string $code            The code of the gift card with updated expiration.
     * @param string $expiration_date The new expiration date.
     * @param int    $user_id         The ID of the admin making the update.
     */
    private function log_expiration_update( $code, $expiration_date, $user_id ) {
        $this->log_activity( 'expiration_updated', $code, null, $user_id );
    }

    /**
     * Logs the deletion of a gift card.
     *
     * @param string $code    The code of the deleted gift card.
     * @param int    $user_id The ID of the admin who deleted the card.
     */
    private function log_deletion( $code, $user_id ) {
        $this->log_activity( 'deleted', $code, null, $user_id );
    }

    /**
     * Logs when an expiration reminder email is sent.
     *
     * @param string $code    The code of the gift card.
     * @param int    $user_id The ID of the recipient user.
     */
    private function log_expiration_reminder_sent( $code, $user_id ) {
        $this->log_activity( 'expiration_reminder_sent', $code, null, $user_id );
    }

    /**
     * Logs a gift card import or export action.
     *
     * @param string $action_type Action type: 'import' or 'export'.
     * @param int    $user_id     The admin ID who initiated the action.
     * @param int    $count       The number of cards processed.
     */
    private function log_import_export( $action_type, $user_id, $count ) {
        $this->log_activity( $action_type . '_csv', null, $count, $user_id );
    }

    /**
     * Clears all cached gift cards list table transients.
     *
     * @return void
     */
    private function clear_gift_cards_list_cache() {
        global $wpdb;

        // Retrieve all option names that start with '_transient_gift_cards_list_'
        $transient_names = $wpdb->get_col( "
            SELECT option_name FROM {$wpdb->options}
            WHERE option_name LIKE '\\_transient\\_gift_cards_list\\_%'
        " );

        // Loop through each transient and delete it
        foreach ( $transient_names as $transient ) {
            // Remove the '_transient_' prefix to get the actual transient name
            $transient_clean = str_replace( '_transient_', '', $transient );
            delete_transient( $transient_clean );
        }

        // Additionally, delete the total count transient if it exists
        delete_transient( 'gift_cards_total_count' );
    }


}

new WC_Gift_Cards();