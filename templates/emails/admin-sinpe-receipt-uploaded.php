<?php
/**
 * Email to the store: a customer uploaded a SINPE Móvil receipt (HTML).
 *
 * Override by copying it to yourtheme/costa-rica-kit-for-woocommerce/emails/admin-sinpe-receipt-uploaded.php.
 *
 * @package CRKit\Templates
 * @version 0.1.0
 *
 * @var WC_Order $order              Order.
 * @var string   $email_heading      Heading.
 * @var string   $additional_content Additional content.
 * @var bool     $sent_to_admin      Always true.
 * @var bool     $plain_text         Always false.
 * @var WC_Email $email              Email.
 * @var string   $amount_crc_html    Amount in colones (wc_price markup).
 * @var string   $reference          Payment reference.
 * @var string   $order_url          Order edit URL.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce email hook. ?>

<p>
	<?php
	printf(
		/* translators: 1: order number, 2: customer name. */
		esc_html__( 'The customer of order #%1$s (%2$s) uploaded the SINPE Móvil payment receipt.', 'costa-rica-kit-for-woocommerce' ),
		esc_html( $order->get_order_number() ),
		esc_html( $order->get_formatted_billing_full_name() )
	);
	?>
</p>
<p>
	<?php
	printf(
		/* translators: 1: amount in colones, 2: payment reference. */
		esc_html__( 'Check that your account received %1$s with the reference %2$s, then confirm or reject the payment from the order screen.', 'costa-rica-kit-for-woocommerce' ),
		'<strong>' . wp_kses_post( $amount_crc_html ) . '</strong>',
		'<strong>' . esc_html( $reference ) . '</strong>'
	);
	?>
</p>
<p><a href="<?php echo esc_url( $order_url ); ?>"><?php esc_html_e( 'Review the order and the receipt', 'costa-rica-kit-for-woocommerce' ); ?></a></p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce email hook.

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WooCommerce email hook.
