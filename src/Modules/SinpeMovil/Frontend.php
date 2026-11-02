<?php
/**
 * Payment instructions and receipt form for customers.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use CRKit\Support\Phone;
use WC_Email;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the payment steps and the receipt form on the order received page (classic and block
 * themes) and in My Account → View order, and adds the steps to the on-hold email.
 */
final class Frontend {

	/**
	 * Template folder inside a theme: `yourtheme/costa-rica-kit-for-woocommerce/`.
	 */
	public const TEMPLATE_PATH = 'costa-rica-kit-for-woocommerce/';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_thankyou_' . Gateway::ID, array( $this, 'render_thankyou' ) );
		add_action( 'woocommerce_view_order', array( $this, 'render_view_order' ), 5 );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'render_email_instructions' ), 10, 4 );
	}

	/**
	 * Order received page.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function render_thankyou( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {
			$this->render_payment_box( $order, 'thankyou' );
		}
	}

	/**
	 * My Account → View order.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function render_view_order( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order && OrderData::is_sinpe_order( $order ) ) {
			$this->render_payment_box( $order, 'account' );
		}
	}

	/**
	 * Instructions while the order is on hold, then the receipt form.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $context `thankyou` or `account`.
	 * @return void
	 */
	private function render_payment_box( WC_Order $order, string $context ): void {
		$payment = OrderData::get_payment( $order );
		$gateway = ReceiptController::get_gateway();

		if ( null === $payment || ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		$template_args = $this->get_template_args( $order, $payment, $gateway );

		wc_get_template( 'sinpe-movil/instructions.php', $template_args, self::TEMPLATE_PATH, CRKIT_PLUGIN_DIR . 'templates/' );

		if ( null !== $gateway && $gateway->requires_receipt() ) {
			wc_get_template(
				'sinpe-movil/receipt-form.php',
				array(
					'order'        => $order,
					'context'      => $context,
					'receipt'      => OrderData::get_receipt( $order ),
					'result'       => $this->get_result_message(),
					'max_size'     => size_format( $gateway->get_max_upload_bytes() ),
					'action_url'   => admin_url( 'admin-post.php' ),
					'action'       => ReceiptController::UPLOAD_ACTION,
					'file_field'   => ReceiptController::FILE_FIELD,
					'nonce_field'  => ReceiptController::NONCE_FIELD,
					'nonce_action' => ReceiptController::upload_nonce_action( $order->get_id() ),
					'view_url'     => ReceiptController::can_view( $order ) ? ReceiptController::get_view_url( $order ) : '',
					'accept'       => '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf',
					'can_upload'   => ReceiptController::accepts_receipt( $order ),
				),
				self::TEMPLATE_PATH,
				CRKIT_PLUGIN_DIR . 'templates/'
			);
		}
	}

	/**
	 * Adds the payment steps to the "Order on-hold" customer email.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether the email goes to the store.
	 * @param bool     $plain_text    Plain text email.
	 * @param WC_Email $email         Email.
	 * @return void
	 */
	public function render_email_instructions( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		if ( ! $order instanceof WC_Order || $sent_to_admin || ! OrderData::is_sinpe_order( $order ) || ! $order->has_status( 'on-hold' ) ) {
			return;
		}

		if ( $email instanceof WC_Email && 'customer_on_hold_order' !== $email->id ) {
			return;
		}

		$payment = OrderData::get_payment( $order );

		if ( null === $payment ) {
			return;
		}

		$gateway       = ReceiptController::get_gateway();
		$template_args = $this->get_template_args( $order, $payment, $gateway );

		$template_args['receipt_url'] = null !== $gateway && $gateway->requires_receipt() ? $order->get_checkout_order_received_url() : '';

		wc_get_template(
			$plain_text ? 'sinpe-movil/instructions-plain.php' : 'sinpe-movil/instructions.php',
			$template_args,
			self::TEMPLATE_PATH,
			CRKIT_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Variables for the instructions templates.
	 *
	 * @param WC_Order                                                                                                                                                                                  $order   Order.
	 * @param array{amount_crc: int, currency: string, rate: float|null, surcharge: float, rate_type: string, rate_source: string, rate_date: string, reference: string, phone: string, holder: string} $payment Frozen payment data.
	 * @param Gateway|null                                                                                                                                                                              $gateway Gateway.
	 * @return array<string, mixed>
	 */
	private function get_template_args( WC_Order $order, array $payment, ?Gateway $gateway ): array {
		return array(
			'order'            => $order,
			'amount_crc'       => $payment['amount_crc'],
			'amount_crc_html'  => Formatter::crc_html( $payment['amount_crc'] ),
			'amount_crc_text'  => Formatter::crc( $payment['amount_crc'] ),
			'is_converted'     => null !== $payment['rate'],
			'rate_text'        => null === $payment['rate'] ? '' : Formatter::rate( $payment['rate'], $payment['surcharge'] ),
			'phone'            => Phone::format( $payment['phone'] ),
			'holder'           => $payment['holder'],
			'reference'        => $payment['reference'],
			'instructions'     => null === $gateway ? '' : $gateway->get_instructions(),
			'requires_receipt' => null !== $gateway && $gateway->requires_receipt(),
			'receipt_url'      => '',
		);
	}

	/**
	 * Message for the result of the last upload, from the redirect query argument.
	 *
	 * @return array{type: string, text: string}|null
	 */
	private function get_result_message(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$code = isset( $_GET[ ReceiptController::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ ReceiptController::RESULT_ARG ] ) ) : '';

		if ( '' === $code ) {
			return null;
		}

		$messages = array(
			'uploaded'     => __( 'Thank you! We received your receipt and will confirm your payment soon.', 'costa-rica-kit-for-woocommerce' ),
			'no_file'      => __( 'Choose a file to upload.', 'costa-rica-kit-for-woocommerce' ),
			'too_large'    => __( 'The file is too large.', 'costa-rica-kit-for-woocommerce' ),
			'invalid_type' => __( 'Upload a JPG, PNG, WEBP or PDF file.', 'costa-rica-kit-for-woocommerce' ),
			'expired'      => __( 'The page expired. Reload it and try again.', 'costa-rica-kit-for-woocommerce' ),
			'not_allowed'  => __( 'You cannot upload a receipt for this order.', 'costa-rica-kit-for-woocommerce' ),
			'closed'       => __( 'This order no longer accepts receipts.', 'costa-rica-kit-for-woocommerce' ),
			'storage'      => __( 'The receipt could not be saved. Please try again or contact the store.', 'costa-rica-kit-for-woocommerce' ),
		);

		return array(
			'type' => 'uploaded' === $code ? 'success' : 'error',
			'text' => $messages[ $code ] ?? $messages['storage'],
		);
	}
}
