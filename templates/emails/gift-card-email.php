<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

// Display custom image if set
if ( ! empty( $custom_email_image ) ) {
    echo '<img src="' . esc_url( $custom_email_image ) . '" alt="' . esc_attr__( 'Gift Card', 'gift-cards-for-woocommerce' ) . '" style="width:100%; height:auto;" />';
}
?>

<p><?php printf( __( 'Hello! You\'ve received a gift card worth %s from %s.', 'gift-cards-for-woocommerce' ), wc_price( $gift_card->balance ), esc_html( $gift_card->sender_name ) ); ?></p>

<?php if ( ! empty( $gift_card->message ) ) : ?>
    <p><?php printf( __( 'Message: %s', 'gift-cards-for-woocommerce' ), nl2br( esc_html( $gift_card->message ) ) ); ?></p>
<?php endif; ?>

<!-- Display custom text if set -->
<?php if ( ! empty( $custom_email_text ) ) : ?>
    <div><?php echo wpautop( wp_kses_post( $custom_email_text ) ); ?></div>
<?php endif; ?>

<p><?php printf( __( 'Redeem your gift card with code: <strong>%s</strong>', 'gift-cards-for-woocommerce' ), esc_html( $gift_card->code ) ); ?></p>

<?php
do_action( 'woocommerce_email_footer', $email );
