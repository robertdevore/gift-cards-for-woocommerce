<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gift_Card_Email extends WC_Email {

    public function __construct() {
        $this->id             = 'wc_gift_card_email';
        $this->title          = __( 'Gift Card Email', 'gift-cards-for-woocommerce' );
        $this->description    = __( 'This email is sent to the recipient when a gift card is issued.', 'gift-cards-for-woocommerce' );
        $this->heading        = __( 'You have received a gift card!', 'gift-cards-for-woocommerce' );
        $this->subject        = __( 'You have received a gift card from {sender_name}', 'gift-cards-for-woocommerce' );

        $this->template_html  = 'emails/gift-card-email.php';
        $this->template_plain = 'emails/plain/gift-card-email.php';
        $this->template_base  = plugin_dir_path( __FILE__ ) . '../templates/';

        // Triggers for this email.
        add_action( 'wc_gift_card_email_notification', [ $this, 'trigger' ], 10, 1 );

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

        $this->placeholders['{sender_name}']    = $gift_card->sender_name;
        $this->placeholders['{gift_card_amount}'] = wc_price( $gift_card->balance );

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    public function get_content_html() {
        return wc_get_template_html( $this->template_html, array(
            'gift_card'     => $this->gift_card,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $this,
        ), '', $this->template_base );
    }

    public function get_content_plain() {
        return wc_get_template_html( $this->template_plain, array(
            'gift_card'     => $this->gift_card,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => true,
            'email'         => $this,
        ), '', $this->template_base );
    }
}
