<?php
/**
 * SINPE Móvil payment steps (plain text email).
 *
 * Override by copying it to yourtheme/costa-rica-checkout-for-woocommerce/sinpe-movil/instructions-plain.php.
 *
 * @package CRKit\Templates
 * @version 0.1.0
 *
 * @var WC_Order $order            Order.
 * @var string   $amount_crc_text  Amount in colones, plain text.
 * @var bool     $is_converted     Whether the amount was converted from another currency.
 * @var string   $rate_text        Exchange rate used.
 * @var string   $phone            SINPE Móvil number, formatted.
 * @var string   $holder           Account holder.
 * @var string   $reference        Reference for the payment description.
 * @var string   $instructions     Extra instructions from the settings.
 * @var bool     $requires_receipt Whether a receipt is requested.
 * @var string   $receipt_url      Page to upload the receipt.
 */

defined( 'ABSPATH' ) || exit;

echo esc_html( wc_strtoupper( __( 'How to pay with SINPE Móvil', 'costa-rica-checkout-for-woocommerce' ) ) ) . "\n\n";

echo '1. ' . esc_html__( 'Open your bank app and choose SINPE Móvil.', 'costa-rica-checkout-for-woocommerce' ) . "\n";

/* translators: 1: amount in colones, 2: phone number. */
echo '2. ' . esc_html( sprintf( __( 'Send exactly %1$s to the number %2$s.', 'costa-rica-checkout-for-woocommerce' ), $amount_crc_text, $phone ) );
if ( '' !== $holder ) {
	/* translators: %s: account holder name. */
	echo ' ' . esc_html( sprintf( __( 'Your bank app will show the name %s.', 'costa-rica-checkout-for-woocommerce' ), rtrim( $holder, '.' ) ) );
}
echo "\n";

/* translators: %s: payment reference (order number). */
echo '3. ' . esc_html( sprintf( __( 'Write %s in the payment description.', 'costa-rica-checkout-for-woocommerce' ), $reference ) ) . "\n";

$crkit_step = 4;
if ( $requires_receipt && '' !== $receipt_url ) {
	/* translators: %s: URL of the page where the receipt is uploaded. */
	echo esc_html( $crkit_step . '. ' . sprintf( __( 'Upload the payment receipt on your order page: %s', 'costa-rica-checkout-for-woocommerce' ), $receipt_url ) ) . "\n";
	$crkit_step = 5;
}
echo esc_html( $crkit_step . '. ' . __( 'We will process your order as soon as we confirm the payment.', 'costa-rica-checkout-for-woocommerce' ) ) . "\n";

if ( $is_converted ) {
	echo "\n" . esc_html(
		sprintf(
			/* translators: 1: order total in the store currency, 2: exchange rate. */
			__( 'Order total %1$s, converted with an exchange rate of %2$s.', 'costa-rica-checkout-for-woocommerce' ),
			html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
			$rate_text
		)
	) . "\n";
}

if ( '' !== $instructions ) {
	echo "\n" . esc_html( wp_strip_all_tags( wptexturize( $instructions ) ) ) . "\n";
}

echo "\n----------------------------------------\n\n";
