<?php
/**
 * SINPE Móvil box in the order edit screen (HPOS and legacy storage).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use Automattic\WooCommerce\Utilities\OrderUtil;
use CRKit\Support\Phone;
use WC_Order;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the frozen amount, rate, reference and receipt, with "Confirm payment" and "Reject payment".
 */
final class AdminMetabox {

	/**
	 * Confirm action.
	 */
	public const CONFIRM_ACTION = 'crkit_sinpe_confirm';

	/**
	 * Reject action.
	 */
	public const REJECT_ACTION = 'crkit_sinpe_reject';

	/**
	 * Query argument with the result of an action.
	 */
	public const RESULT_ARG = 'crkit_sinpe';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_action( 'admin_post_' . self::CONFIRM_ACTION, array( $this, 'handle_confirm' ) );
		add_action( 'admin_post_' . self::REJECT_ACTION, array( $this, 'handle_reject' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Adds the box to SINPE Móvil orders.
	 *
	 * @param string           $screen_id     Screen or post type.
	 * @param WP_Post|WC_Order $post_or_order Post (legacy storage) or order (HPOS).
	 * @return void
	 */
	public function add_meta_box( $screen_id, $post_or_order = null ): void {
		$screen = wc_get_page_screen_id( 'shop-order' );
		$order  = self::resolve_order( $post_or_order );

		if ( null === $order || ! OrderData::is_sinpe_order( $order ) ) {
			return;
		}

		add_meta_box( 'crkit-sinpe-movil', __( 'SINPE Móvil payment', 'costa-rica-kit-for-woocommerce' ), array( $this, 'render' ), $screen, 'side', 'high' );
	}

	/**
	 * Renders the box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Post or order.
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = self::resolve_order( $post_or_order );

		if ( null === $order ) {
			return;
		}

		$payment = OrderData::get_payment( $order );
		$receipt = OrderData::get_receipt( $order );

		if ( null === $payment ) {
			echo '<p>' . esc_html__( 'This order has no SINPE Móvil payment data.', 'costa-rica-kit-for-woocommerce' ) . '</p>';
			return;
		}

		$rates = crkit_exchange_rate_service();
		?>
		<p class="crkit-sinpe-amount">
			<?php esc_html_e( 'Amount to receive:', 'costa-rica-kit-for-woocommerce' ); ?>
			<strong><?php echo wp_kses_post( Formatter::crc_html( $payment['amount_crc'] ) ); ?></strong>
		</p>
		<ul class="crkit-sinpe-details">
			<?php if ( null !== $payment['rate'] ) : ?>
				<li>
					<?php
					printf(
						/* translators: 1: exchange rate, 2: source, 3: date of the rate. */
						esc_html__( 'Exchange rate: %1$s (%2$s, %3$s)', 'costa-rica-kit-for-woocommerce' ),
						esc_html( Formatter::rate( $payment['rate'], $payment['surcharge'] ) ),
						esc_html( null === $rates ? $payment['rate_source'] : $rates->get_source_label( $payment['rate_source'] ) ),
						esc_html( $payment['rate_date'] )
					);
					?>
				</li>
			<?php endif; ?>
			<li>
				<?php
				/* translators: %s: payment reference. */
				printf( esc_html__( 'Reference: %s', 'costa-rica-kit-for-woocommerce' ), '<strong>' . esc_html( $payment['reference'] ) . '</strong>' );
				?>
			</li>
			<li>
				<?php
				/* translators: %s: SINPE Móvil number. */
				printf( esc_html__( 'Paid to: %s', 'costa-rica-kit-for-woocommerce' ), esc_html( trim( Phone::format( $payment['phone'] ) . ' ' . $payment['holder'] ) ) );
				?>
			</li>
		</ul>

		<p class="crkit-sinpe-receipt">
			<?php if ( null === $receipt ) : ?>
				<?php esc_html_e( 'No receipt uploaded yet.', 'costa-rica-kit-for-woocommerce' ); ?>
			<?php else : ?>
				<?php
				printf(
					/* translators: %s: date and time of the upload. */
					esc_html__( 'Receipt uploaded on %s.', 'costa-rica-kit-for-woocommerce' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $receipt['uploaded_at'] ) )
				);
				?>
				<br />
				<a href="<?php echo esc_url( ReceiptController::get_view_url( $order ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'costa-rica-kit-for-woocommerce' ); ?></a>
				|
				<a href="<?php echo esc_url( ReceiptController::get_view_url( $order, true ) ); ?>"><?php esc_html_e( 'Download', 'costa-rica-kit-for-woocommerce' ); ?></a>
			<?php endif; ?>
		</p>

		<?php if ( $order->has_status( 'on-hold' ) && current_user_can( 'edit_shop_orders' ) ) : ?>
			<p class="crkit-sinpe-actions">
				<a class="button button-primary" href="<?php echo esc_url( self::get_action_url( self::CONFIRM_ACTION, $order ) ); ?>"><?php esc_html_e( 'Confirm payment', 'costa-rica-kit-for-woocommerce' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::get_action_url( self::REJECT_ACTION, $order ) ); ?>"><?php esc_html_e( 'Reject payment', 'costa-rica-kit-for-woocommerce' ); ?></a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Nonce-protected URL of an action.
	 *
	 * @param string   $action Action.
	 * @param WC_Order $order  Order.
	 * @return string
	 */
	public static function get_action_url( string $action, WC_Order $order ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => $action,
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			$action . '_' . $order->get_id()
		);
	}

	/**
	 * Handles "Confirm payment".
	 *
	 * @return void
	 */
	public function handle_confirm(): void {
		$order  = $this->verify_request( self::CONFIRM_ACTION );
		$result = $this->confirm( $order ) ? 'confirmed' : 'error';

		$this->redirect( $order, $result );
	}

	/**
	 * Handles "Reject payment".
	 *
	 * @return void
	 */
	public function handle_reject(): void {
		$order  = $this->verify_request( self::REJECT_ACTION );
		$result = $this->reject( $order ) ? 'rejected' : 'error';

		$this->redirect( $order, $result );
	}

	/**
	 * Marks the payment as received: payment_complete() moves the order to processing (or completed).
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public function confirm( WC_Order $order ): bool {
		if ( ! OrderData::is_sinpe_order( $order ) || ! $order->has_status( 'on-hold' ) ) {
			return false;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: user display name. */
				__( 'SINPE Móvil payment confirmed by %s.', 'costa-rica-kit-for-woocommerce' ),
				wp_get_current_user()->display_name
			)
		);
		$order->payment_complete();

		/**
		 * Fires after a store manager confirms a SINPE Móvil payment.
		 *
		 * @since 0.1.0
		 *
		 * @param int      $order_id Order id.
		 * @param WC_Order $order    Order.
		 */
		do_action( 'crkit_sinpe_payment_confirmed', $order->get_id(), $order );

		return true;
	}

	/**
	 * Rejects the payment: moves the order to the status chosen in the gateway settings.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public function reject( WC_Order $order ): bool {
		if ( ! OrderData::is_sinpe_order( $order ) || ! $order->has_status( 'on-hold' ) ) {
			return false;
		}

		$gateway = ReceiptController::get_gateway();
		$status  = null === $gateway ? 'failed' : $gateway->get_reject_status();

		$order->update_status(
			$status,
			sprintf(
				/* translators: %s: user display name. */
				__( 'SINPE Móvil payment rejected by %s.', 'costa-rica-kit-for-woocommerce' ),
				wp_get_current_user()->display_name
			)
		);

		/**
		 * Fires after a store manager rejects a SINPE Móvil payment.
		 *
		 * @since 0.1.0
		 *
		 * @param int      $order_id Order id.
		 * @param WC_Order $order    Order.
		 * @param string   $status   New order status.
		 */
		do_action( 'crkit_sinpe_payment_rejected', $order->get_id(), $order, $status );

		return true;
	}

	/**
	 * Shows the result of a confirm or reject action on the order screen.
	 *
	 * @return void
	 */
	public function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$result = isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : '';

		$messages = array(
			'confirmed' => array( 'success', __( 'SINPE Móvil payment confirmed.', 'costa-rica-kit-for-woocommerce' ) ),
			'rejected'  => array( 'warning', __( 'SINPE Móvil payment rejected.', 'costa-rica-kit-for-woocommerce' ) ),
			'error'     => array( 'error', __( 'The order is no longer waiting for a SINPE Móvil payment.', 'costa-rica-kit-for-woocommerce' ) ),
		);

		if ( isset( $messages[ $result ] ) && current_user_can( 'edit_shop_orders' ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $result ][0] ), esc_html( $messages[ $result ][1] ) );
		}
	}

	/**
	 * Checks the nonce and capability and returns the order, or stops.
	 *
	 * @param string $action Action.
	 * @return WC_Order
	 */
	private function verify_request( string $action ): WC_Order {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified next.

		check_admin_referer( $action . '_' . $order_id );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this order.', 'costa-rica-kit-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'costa-rica-kit-for-woocommerce' ), '', array( 'response' => 404 ) );
		}

		return $order;
	}

	/**
	 * Redirects back to the order with the result.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $result Result code.
	 * @return void
	 */
	private function redirect( WC_Order $order, string $result ): void {
		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result, $order->get_edit_order_url() ) );
		exit;
	}

	/**
	 * Order from the meta box callback argument.
	 *
	 * @param mixed $post_or_order Post or order.
	 * @return WC_Order|null
	 */
	private static function resolve_order( mixed $post_or_order ): ?WC_Order {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		if ( $post_or_order instanceof WP_Post && OrderUtil::is_order( $post_or_order->ID, wc_get_order_types() ) ) {
			$order = wc_get_order( $post_or_order->ID );

			return $order instanceof WC_Order ? $order : null;
		}

		return null;
	}
}
