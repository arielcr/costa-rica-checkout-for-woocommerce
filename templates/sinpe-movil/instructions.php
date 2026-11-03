<?php
/**
 * SINPE Móvil payment steps (order received page, My Account and HTML email).
 *
 * Override by copying it to yourtheme/costa-rica-checkout-for-woocommerce/sinpe-movil/instructions.php.
 *
 * @package CRKit\Templates
 * @version 0.1.0
 *
 * @var WC_Order $order            Order.
 * @var string   $amount_crc_html  Amount in colones (wc_price markup).
 * @var bool     $is_converted     Whether the amount was converted from another currency.
 * @var string   $rate_text        Exchange rate used, e.g. "₡456.04".
 * @var string   $phone            SINPE Móvil number, formatted.
 * @var string   $holder           Account holder.
 * @var string   $reference        Reference for the payment description.
 * @var string   $instructions     Extra instructions from the settings.
 * @var bool     $requires_receipt Whether a receipt is requested.
 * @var string   $receipt_url      Page to upload the receipt (emails only).
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="crkit-sinpe-instructions">
	<h2 class="crkit-sinpe-instructions__title"><?php esc_html_e( 'How to pay with SINPE Móvil', 'costa-rica-checkout-for-woocommerce' ); ?></h2>
	<ol class="crkit-sinpe-instructions__steps">
		<li><?php esc_html_e( 'Open your bank app and choose SINPE Móvil.', 'costa-rica-checkout-for-woocommerce' ); ?></li>
		<li>
			<?php
			printf(
				/* translators: 1: amount in colones, 2: phone number. */
				esc_html__( 'Send exactly %1$s to the number %2$s.', 'costa-rica-checkout-for-woocommerce' ),
				'<strong>' . wp_kses_post( $amount_crc_html ) . '</strong>',
				'<strong>' . esc_html( $phone ) . '</strong>'
			);
			if ( '' !== $holder ) {
				echo ' ';
				printf(
					/* translators: %s: account holder name. */
					esc_html__( 'Your bank app will show the name %s.', 'costa-rica-checkout-for-woocommerce' ),
					'<strong>' . esc_html( rtrim( $holder, '.' ) ) . '</strong>'
				);
			}
			?>
		</li>
		<li>
			<?php
			printf(
				/* translators: %s: payment reference (order number). */
				esc_html__( 'Write %s in the payment description.', 'costa-rica-checkout-for-woocommerce' ),
				'<strong>' . esc_html( $reference ) . '</strong>'
			);
			?>
		</li>
		<?php if ( $requires_receipt ) : ?>
			<li>
				<?php if ( '' !== $receipt_url ) : ?>
					<?php
					printf(
						/* translators: %s: link to the page where the receipt is uploaded. */
						esc_html__( 'Upload the payment receipt on %s.', 'costa-rica-checkout-for-woocommerce' ),
						'<a href="' . esc_url( $receipt_url ) . '">' . esc_html__( 'your order page', 'costa-rica-checkout-for-woocommerce' ) . '</a>'
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Upload the payment receipt below.', 'costa-rica-checkout-for-woocommerce' ); ?>
				<?php endif; ?>
			</li>
		<?php endif; ?>
		<li><?php esc_html_e( 'We will process your order as soon as we confirm the payment.', 'costa-rica-checkout-for-woocommerce' ); ?></li>
	</ol>

	<?php if ( $is_converted ) : ?>
		<p class="crkit-sinpe-instructions__rate">
			<?php
			printf(
				/* translators: 1: order total in the store currency, 2: exchange rate. */
				esc_html__( 'Order total %1$s, converted with an exchange rate of %2$s.', 'costa-rica-checkout-for-woocommerce' ),
				wp_kses_post( $order->get_formatted_order_total() ),
				esc_html( $rate_text )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $instructions ) : ?>
		<div class="crkit-sinpe-instructions__extra"><?php echo wp_kses_post( wpautop( wptexturize( $instructions ) ) ); ?></div>
	<?php endif; ?>
</section>
