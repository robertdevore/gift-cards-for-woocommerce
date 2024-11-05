<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

?>

<p><?php printf( __( 'Hello! You\'ve received a gift card worth %s from %s.', 'gift-cards-for-woocommerce' ), wc_price( $gift_card->balance ), esc_html( $gift_card->sender_name ) ); ?></p>

<?php if ( ! empty( $gift_card->message ) ) : ?>
    <p><?php printf( __( 'Message: %s', 'gift-cards-for-woocommerce' ), nl2br( esc_html( $gift_card->message ) ) ); ?></p>
<?php endif; ?>

<p><?php printf( __( 'Redeem your gift card with code: <strong>%s</strong>', 'gift-cards-for-woocommerce' ), esc_html( $gift_card->code ) ); ?></p>

<?php

do_action( 'woocommerce_email_footer', $email );
