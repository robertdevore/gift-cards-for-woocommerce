<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

?>

<p><?php printf( __( 'Hello! Your gift card with code <strong>%s</strong> is about to expire on %s.', 'gift-cards-for-woocommerce' ), esc_html( $gift_card->code ), date_i18n( wc_date_format(), strtotime( $gift_card->expiration_date ) ) ); ?></p>

<p><?php printf( __( 'Current Balance: %s', 'gift-cards-for-woocommerce' ), wc_price( $gift_card->balance ) ); ?></p>

<p><?php _e( 'Please use your gift card before it expires.', 'gift-cards-for-woocommerce' ); ?></p>

<?php

do_action( 'woocommerce_email_footer', $email );
