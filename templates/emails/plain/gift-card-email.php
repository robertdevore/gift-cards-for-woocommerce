<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo $email_heading . "\n\n";

// Indicate that a custom image is available (since images can't be displayed in plain text)
if ( ! empty( $custom_email_image ) ) {
    echo '[' . __( 'Image: ', 'gift-cards-for-woocommerce' ) . esc_url( $custom_email_image ) . ']' . "\n\n";
}

printf( __( 'Hello! You\'ve received a gift card worth %s from %s.', 'gift-cards-for-woocommerce' ), wc_price( $gift_card->balance ), esc_html( $gift_card->sender_name ) );
echo "\n\n";

if ( ! empty( $gift_card->message ) ) {
    printf( __( 'Message: %s', 'gift-cards-for-woocommerce' ), $gift_card->message );
    echo "\n\n";
}

// Display custom text if set
if ( ! empty( $custom_email_text ) ) {
    echo wp_strip_all_tags( $custom_email_text ) . "\n\n";
}

printf( __( 'Redeem your gift card with code: %s', 'gift-cards-for-woocommerce' ), $gift_card->code );
echo "\n\n";

echo __( 'Thank you!', 'gift-cards-for-woocommerce' );
echo "\n";
