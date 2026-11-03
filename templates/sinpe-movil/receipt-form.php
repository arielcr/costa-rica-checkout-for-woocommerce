<?php
/**
 * SINPE Móvil receipt upload form (order received page and My Account).
 *
 * Override by copying it to yourtheme/costa-rica-checkout-for-woocommerce/sinpe-movil/receipt-form.php.
 *
 * @package CRKit\Templates
 * @version 0.1.0
 *
 * @var WC_Order                                $order        Order.
 * @var string                                  $context      `thankyou` or `account`.
 * @var array<string, mixed>|null               $receipt      Current receipt, if any.
 * @var array{type: string, text: string}|null  $result       Result of the last upload.
 * @var string                                  $max_size     Maximum file size, formatted.
 * @var string                                  $action_url   Form action.
 * @var string                                  $action       admin-post action.
 * @var string                                  $file_field   File input name.
 * @var string                                  $nonce_field  Nonce field name.
 * @var string                                  $nonce_action Nonce action.
 * @var string                                  $view_url     URL to view the receipt, if allowed.
 * @var string                                  $accept       Value of the accept attribute.
 * @var bool                                    $can_upload   Whether the order accepts a receipt.
 */

defined( 'ABSPATH' ) || exit;

$crkit_has_error = null !== $result && 'success' !== $result['type'];
?>
<section id="crkit-sinpe-receipt" class="crkit-sinpe-receipt">
	<h3 class="crkit-sinpe-receipt__title"><?php esc_html_e( 'Payment receipt', 'costa-rica-checkout-for-woocommerce' ); ?></h3>

	<?php if ( null !== $result ) : ?>
		<?php if ( 'success' === $result['type'] ) : ?>
			<div class="woocommerce-message" role="status"><?php echo esc_html( $result['text'] ); ?></div>
		<?php else : ?>
			<div id="crkit-receipt-error" role="alert"><ul class="woocommerce-error"><li><?php echo esc_html( $result['text'] ); ?></li></ul></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( null !== $receipt ) : ?>
		<p class="crkit-sinpe-receipt__current">
			<?php
			printf(
				/* translators: %s: date and time of the upload. */
				esc_html__( 'Receipt received on %s.', 'costa-rica-checkout-for-woocommerce' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $receipt['uploaded_at'] ) )
			);
			?>
			<?php if ( '' !== $view_url ) : ?>
				<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View receipt', 'costa-rica-checkout-for-woocommerce' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'costa-rica-checkout-for-woocommerce' ); ?></span></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( $can_upload ) : ?>
		<form class="crkit-sinpe-receipt__form" method="post" action="<?php echo esc_url( $action_url ); ?>" enctype="multipart/form-data">
			<p class="form-row">
				<label for="crkit-receipt-file">
					<?php
					echo esc_html( null === $receipt ? __( 'Upload your receipt', 'costa-rica-checkout-for-woocommerce' ) : __( 'Replace your receipt', 'costa-rica-checkout-for-woocommerce' ) );
					?>
				</label>
				<input type="file" id="crkit-receipt-file" name="<?php echo esc_attr( $file_field ); ?>" accept="<?php echo esc_attr( $accept ); ?>" required aria-invalid="<?php echo esc_attr( $crkit_has_error ? 'true' : 'false' ); ?>" aria-describedby="<?php echo esc_attr( $crkit_has_error ? 'crkit-receipt-error crkit-receipt-help' : 'crkit-receipt-help' ); ?>" />
				<span id="crkit-receipt-help" class="description">
					<?php
					printf(
						/* translators: %s: maximum file size, e.g. "5 MB". */
						esc_html__( 'JPG, PNG, WEBP or PDF, up to %s.', 'costa-rica-checkout-for-woocommerce' ),
						esc_html( $max_size )
					);
					?>
				</span>
			</p>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>" />
			<input type="hidden" name="order_key" value="<?php echo esc_attr( $order->get_order_key() ); ?>" />
			<input type="hidden" name="context" value="<?php echo esc_attr( $context ); ?>" />
			<?php wp_nonce_field( $nonce_action, $nonce_field, false ); ?>
			<p>
				<button type="submit" class="button wp-element-button"><?php esc_html_e( 'Send receipt', 'costa-rica-checkout-for-woocommerce' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</section>
