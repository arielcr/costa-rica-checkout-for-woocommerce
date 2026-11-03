<?php
/**
 * Email to the store: a customer uploaded a SINPE Móvil receipt (plain text).
 *
 * Override by copying it to yourtheme/costa-rica-checkout-for-woocommerce/emails/plain/admin-sinpe-receipt-uploaded.php.
 *
 * @package CRKit\Templates
 * @version 0.1.0
 *
 * @var WC_Order $order              Order.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Always true.
 * @var bool     $plain_text         Always true.
 * @var WC_Email $email              Email.
 * @var string   $amount_crc_text    Amount in colones, plain text.
 * @var string   $reference          Payment reference.
 * @var string   $order_url          Order edit URL.
 */

defined( 'ABSPATH' ) || exit;

echo "=================\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=================\n\n";

/* translators: 1: order number, 2: customer name. */
echo esc_html( sprintf( __( 'The customer of order #%1$s (%2$s) uploaded the SINPE Móvil payment receipt.', 'costa-rica-checkout-for-woocommerce' ), $order->get_order_number(), $order->get_formatted_billing_full_name() ) ) . "\n\n";

/* translators: 1: amount in colones, 2: payment reference. */
echo esc_html( sprintf( __( 'Check that your account received %1$s with the reference %2$s, then confirm or reject the payment from the order screen.', 'costa-rica-checkout-for-woocommerce' ), $amount_crc_text, $reference ) ) . "\n\n";

echo esc_html__( 'Review the order and the receipt', 'costa-rica-checkout-for-woocommerce' ) . ': ' . esc_url_raw( $order_url ) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce email hook.

echo "\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce filter.
