<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo $email_heading . "\n\n";

printf( __( 'Hello! You\'ve received a gift card worth %s from %s.', 'gift-cards-for-woocommerce' ), wc_price( $gift_card->balance ), esc_html( $gift_card->sender_name ) );
echo "\n\n";

if ( ! empty( $gift_card->message ) ) {
    printf( __( 'Message: %s', 'gift-cards-for-woocommerce' ), $gift_card->message );
    echo "\n\n";
}

printf( __( 'Redeem your gift card with code: %s', 'gift-cards-for-woocommerce' ), $gift_card->code );
echo "\n\n";

echo __( 'Thank you!', 'gift-cards-for-woocommerce' );
echo "\n";
