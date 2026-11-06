<?php
/**
 * Receipt upload and download endpoints.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles `admin-post.php` requests to upload a receipt (customers and guests) and to view or
 * download it (store managers and the logged-in order owner).
 */
final class ReceiptController {

	/**
	 * Upload action.
	 */
	public const UPLOAD_ACTION = 'crkit_sinpe_upload_receipt';

	/**
	 * View/download action.
	 */
	public const DOWNLOAD_ACTION = 'crkit_sinpe_receipt';

	/**
	 * File input name.
	 */
	public const FILE_FIELD = 'crkit_receipt';

	/**
	 * Nonce field name.
	 */
	public const NONCE_FIELD = 'crkit_receipt_nonce';

	/**
	 * Query argument with the upload result.
	 */
	public const RESULT_ARG = 'crkit_receipt';

	/**
	 * Storage.
	 *
	 * @var ReceiptStorage
	 */
	private ReceiptStorage $storage;

	/**
	 * Whether files must come from an HTTP upload (is_uploaded_file()). Only tests turn it off.
	 *
	 * @var bool
	 */
	private bool $require_http_upload;

	/**
	 * Constructor.
	 *
	 * @param ReceiptStorage $storage             Storage.
	 * @param bool           $require_http_upload Whether files must come from an HTTP upload.
	 */
	public function __construct( ReceiptStorage $storage, bool $require_http_upload = true ) {
		$this->storage             = $storage;
		$this->require_http_upload = $require_http_upload;
	}

	/**
	 * Registers the endpoints.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::UPLOAD_ACTION, array( $this, 'handle_upload' ) );
		add_action( 'admin_post_nopriv_' . self::UPLOAD_ACTION, array( $this, 'handle_upload' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_download' ) );
	}

	/**
	 * Nonce action for uploads to an order.
	 *
	 * @param int $order_id Order id.
	 * @return string
	 */
	public static function upload_nonce_action( int $order_id ): string {
		return self::UPLOAD_ACTION . '_' . $order_id;
	}

	/**
	 * Whether the current visitor may upload a receipt for an order: the logged-in owner,
	 * or anyone with the order key (guests receive it in the order received URL and emails).
	 *
	 * @param WC_Order $order     Order.
	 * @param string   $order_key Order key sent with the request.
	 * @return bool
	 */
	public static function can_upload( WC_Order $order, string $order_key ): bool {
		$customer_id = $order->get_customer_id();

		if ( $customer_id > 0 && get_current_user_id() === $customer_id ) {
			return true;
		}

		return '' !== $order_key && hash_equals( $order->get_order_key(), $order_key );
	}

	/**
	 * Whether the current user may view an order's receipt: store managers or the logged-in owner.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function can_view( WC_Order $order ): bool {
		if ( current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}

		$user_id = get_current_user_id();

		return $user_id > 0 && $order->get_customer_id() === $user_id;
	}

	/**
	 * Whether the order accepts a receipt now.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function accepts_receipt( WC_Order $order ): bool {
		return OrderData::is_sinpe_order( $order ) && $order->has_status( 'on-hold' );
	}

	/**
	 * URL to view (or download) an order's receipt.
	 *
	 * @param WC_Order $order    Order.
	 * @param bool     $download Force a download instead of showing it inline.
	 * @return string
	 */
	public static function get_view_url( WC_Order $order, bool $download = false ): string {
		$args = array(
			'action'   => self::DOWNLOAD_ACTION,
			'order_id' => $order->get_id(),
		);

		if ( $download ) {
			$args['download'] = '1';
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::DOWNLOAD_ACTION . '_' . $order->get_id() );
	}

	/**
	 * Handles a receipt upload and redirects back with the result.
	 *
	 * @return void
	 */
	public function handle_upload(): void {
		$result   = $this->process_upload();
		$order    = $result['order'];
		$redirect = null === $order ? home_url( '/' ) : $this->get_return_url( $order, $result['context'] );

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result['code'], $redirect ) . '#crkit-sinpe-receipt' );
		exit;
	}

	/**
	 * Validates and stores an upload.
	 *
	 * @return array{code: string, order: WC_Order|null, context: string}
	 */
	public function process_upload(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- The nonce is verified below, after reading the order id it is bound to.
		$order_id  = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
		$context   = isset( $_POST['context'] ) && 'account' === sanitize_key( wp_unslash( $_POST['context'] ) ) ? 'account' : 'thankyou';
		$nonce     = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$order = $order_id > 0 ? wc_get_order( $order_id ) : false;
		$order = $order instanceof WC_Order ? $order : null;

		$fail = static fn( string $code ): array => array(
			'code'    => $code,
			'order'   => $order,
			'context' => $context,
		);

		// An upload bigger than post_max_size arrives with an empty $_POST.
		if ( 0 === $order_id && $this->exceeds_post_max_size() ) {
			return $fail( 'too_large' );
		}

		if ( null === $order || ! wp_verify_nonce( $nonce, self::upload_nonce_action( $order_id ) ) ) {
			return $fail( 'expired' );
		}

		if ( ! self::can_upload( $order, $order_key ) ) {
			return $fail( 'not_allowed' );
		}

		if ( ! self::accepts_receipt( $order ) ) {
			return $fail( 'closed' );
		}

		$gateway = self::get_gateway();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array; validated by ReceiptStorage (size, extension and real MIME type).
		$file   = isset( $_FILES[ self::FILE_FIELD ] ) && is_array( $_FILES[ self::FILE_FIELD ] ) ? $_FILES[ self::FILE_FIELD ] : array();
		$stored = $this->storage->store( $file, null === $gateway ? Gateway::DEFAULT_MAX_UPLOAD_MB * MB_IN_BYTES : $gateway->get_max_upload_bytes(), $this->require_http_upload );

		if ( $stored instanceof WP_Error ) {
			return $fail( (string) $stored->get_error_code() );
		}

		$this->attach( $order, $stored );

		return array(
			'code'    => 'uploaded',
			'order'   => $order,
			'context' => $context,
		);
	}

	/**
	 * Links a stored receipt to the order, replacing the previous one, and notifies the store.
	 *
	 * @param WC_Order                                                                     $order   Order.
	 * @param array{file: string, name: string, type: string, size: int, uploaded_at: int} $receipt Stored receipt.
	 * @return void
	 */
	public function attach( WC_Order $order, array $receipt ): void {
		$previous = OrderData::get_receipt( $order );

		OrderData::set_receipt( $order, $receipt );
		$order->add_order_note(
			null === $previous
				? __( 'The customer uploaded the SINPE Móvil payment receipt.', 'costa-rica-checkout-for-woocommerce' )
				: __( 'The customer replaced the SINPE Móvil payment receipt.', 'costa-rica-checkout-for-woocommerce' )
		);
		$order->save();

		if ( null !== $previous && $previous['file'] !== $receipt['file'] ) {
			$this->storage->delete( $previous['file'] );
		}

		/**
		 * Fires after a customer uploads a SINPE Móvil payment receipt.
		 * WooCommerce sends the "SINPE Móvil receipt uploaded" email on this action.
		 *
		 * @since 1.0.0
		 *
		 * @param int                  $order_id Order id.
		 * @param array<string, mixed> $receipt  Receipt data (file, name, type, size, uploaded_at).
		 */
		do_action( 'crkit_sinpe_receipt_uploaded', $order->get_id(), $receipt );
	}

	/**
	 * Serves a receipt to authorized users.
	 *
	 * @return void
	 */
	public function handle_download(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified next.
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::DOWNLOAD_ACTION . '_' . $order_id ) ) {
			wp_die( esc_html__( 'This link has expired. Go back and try again.', 'costa-rica-checkout-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || ! self::can_view( $order ) ) {
			wp_die( esc_html__( 'You are not allowed to view this receipt.', 'costa-rica-checkout-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		$receipt = OrderData::get_receipt( $order );
		$path    = null === $receipt ? null : $this->storage->get_path( $receipt['file'] );

		if ( null === $receipt || null === $path ) {
			wp_die( esc_html__( 'Receipt not found.', 'costa-rica-checkout-for-woocommerce' ), '', array( 'response' => 404 ) );
		}

		$download  = isset( $_GET['download'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
		$extension = strtolower( pathinfo( $receipt['file'], PATHINFO_EXTENSION ) );
		$filename  = sanitize_file_name( 'receipt-' . $order->get_order_number() . '.' . $extension );

		nocache_headers();
		header( 'Content-Type: ' . $receipt['type'] );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streams a private file after the permission check.
		exit;
	}

	/**
	 * Where to send the customer back after an upload.
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $context `account` or `thankyou`.
	 * @return string
	 */
	private function get_return_url( WC_Order $order, string $context ): string {
		if ( 'account' === $context && is_user_logged_in() ) {
			return $order->get_view_order_url();
		}

		return $order->get_checkout_order_received_url();
	}

	/**
	 * Whether the request body was dropped for exceeding post_max_size.
	 *
	 * @return bool
	 */
	private function exceeds_post_max_size(): bool {
		$length = isset( $_SERVER['CONTENT_LENGTH'] ) ? absint( $_SERVER['CONTENT_LENGTH'] ) : 0;

		return empty( $_POST ) && $length > 0 && $length > wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only checks emptiness.
	}

	/**
	 * The SINPE Móvil gateway instance.
	 *
	 * @return Gateway|null
	 */
	public static function get_gateway(): ?Gateway {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ Gateway::ID ] ?? null;

		return $gateway instanceof Gateway ? $gateway : null;
	}
}
