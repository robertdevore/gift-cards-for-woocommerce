<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo $email_heading . "\n\n";

printf( __( 'Hello! Your gift card with code %s is about to expire on %s.', 'gift-cards-for-woocommerce' ), $gift_card->code, date_i18n( wc_date_format(), strtotime( $gift_card->expiration_date ) ) );
echo "\n\n";

printf( __( 'Current Balance: %s', 'gift-cards-for-woocommerce' ), wc_price( $gift_card->balance ) );
echo "\n\n";

_e( 'Please use your gift card before it expires.', 'gift-cards-for-woocommerce' );
echo "\n\n";

echo __( 'Thank you!', 'gift-cards-for-woocommerce' );
echo "\n";
