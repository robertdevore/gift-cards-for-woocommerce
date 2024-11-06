<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gift_Card_Expiry_Reminder_Email extends WC_Email {

    public function __construct() {
        $this->id             = 'wc_gift_card_expiry_reminder_email';
        $this->title          = __( 'Gift Card Expiry Reminder Email', 'gift-cards-for-woocommerce' );
        $this->description    = __( 'This email is sent to the recipient as a reminder before the gift card expires.', 'gift-cards-for-woocommerce' );
        $this->heading        = __( 'Your gift card is about to expire!', 'gift-cards-for-woocommerce' );
        $this->subject        = __( 'Your gift card will expire soon', 'gift-cards-for-woocommerce' );

        $this->template_html  = 'emails/gift-card-expiry-reminder-email.php';
        $this->template_plain = 'emails/plain/gift-card-expiry-reminder-email.php';
        $this->template_base  = plugin_dir_path( __FILE__ ) . '../templates/';

        // Triggers for this email.
        add_action( 'wc_gift_card_expiry_reminder_email_notification', [ $this, 'trigger' ], 10, 1 );

        // Call parent constructor.
        parent::__construct();

        // Other settings.
        $this->recipient = '';

        // Enable the email by default.
        $this->enabled = 'yes';
    }

    public function trigger( $gift_card ) {

        if ( ! $gift_card ) {
            return;
        }

        $this->gift_card = $gift_card;

        $this->recipient = $gift_card->recipient_email;

        $this->placeholders['{gift_card_code}'] = $gift_card->code;
        $this->placeholders['{gift_card_balance}'] = wc_price( $gift_card->balance );
        $this->placeholders['{expiration_date}'] = date_i18n( wc_date_format(), strtotime( $gift_card->expiration_date ) );

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    public function get_content_html() {
        return wc_get_template_html( $this->template_html, array(
            'gift_card'     => $this->gift_card,
            'email_heading' => $this->get_heading(),
            'email'         => $this,
        ), '', $this->template_base );
    }
    
    public function get_content_plain() {
        return wc_get_template_html( $this->template_plain, array(
            'gift_card'     => $this->gift_card,
            'email_heading' => $this->get_heading(),
            'email'         => $this,
        ), '', $this->template_base );
    }
}
