<?php
/**
 * Privacy: policy text, personal data exporter and eraser for payment receipts.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Integrates SINPE Móvil receipts with the WordPress privacy tools.
 */
final class Privacy {

	/**
	 * Orders processed per page by the exporter and eraser.
	 */
	public const PER_PAGE = 10;

	/**
	 * Storage.
	 *
	 * @var ReceiptStorage
	 */
	private ReceiptStorage $storage;

	/**
	 * Constructor.
	 *
	 * @param ReceiptStorage $storage Storage.
	 */
	public function __construct( ReceiptStorage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggested text for the privacy policy.
	 *
	 * @return void
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for stores that accept SINPE Móvil:', 'costa-rica-kit-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'When you pay with SINPE Móvil, we ask you to upload the payment receipt issued by your bank. The receipt can include your name, phone number, bank and the transaction number. We use it only to confirm your payment, we store it privately with your order, and only store staff can see it. We keep it as long as we keep the order records.', 'costa-rica-kit-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'To show prices in colones, the store gets the exchange rate from the Ministerio de Hacienda or the Banco Central de Costa Rica. Those requests do not include any of your data.', 'costa-rica-kit-for-woocommerce' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Costa Rica Kit for WooCommerce', 'costa-rica-kit-for-woocommerce' ), wp_kses_post( $content ) );
	}

	/**
	 * Adds the exporter.
	 *
	 * @param array<string, mixed> $exporters Exporters.
	 * @return array<string, mixed>
	 */
	public function register_exporter( $exporters ): array {
		$exporters                         = is_array( $exporters ) ? $exporters : array();
		$exporters['crkit-sinpe-receipts'] = array(
			'exporter_friendly_name' => __( 'SINPE Móvil payments', 'costa-rica-kit-for-woocommerce' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Adds the eraser.
	 *
	 * @param array<string, mixed> $erasers Erasers.
	 * @return array<string, mixed>
	 */
	public function register_eraser( $erasers ): array {
		$erasers                         = is_array( $erasers ) ? $erasers : array();
		$erasers['crkit-sinpe-receipts'] = array(
			'eraser_friendly_name' => __( 'SINPE Móvil payment receipts', 'costa-rica-kit-for-woocommerce' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Exports SINPE Móvil payment data of the orders placed with an email address.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( $email_address, $page = 1 ): array {
		$orders = $this->get_orders( (string) $email_address, (int) $page );
		$data   = array();

		foreach ( $orders as $order ) {
			$payment = OrderData::get_payment( $order );
			$receipt = OrderData::get_receipt( $order );

			if ( null === $payment ) {
				continue;
			}

			$items = array(
				array(
					'name'  => __( 'Order number', 'costa-rica-kit-for-woocommerce' ),
					'value' => $order->get_order_number(),
				),
				array(
					'name'  => __( 'Amount in colones', 'costa-rica-kit-for-woocommerce' ),
					'value' => Formatter::crc( $payment['amount_crc'] ),
				),
			);

			if ( null !== $receipt ) {
				$items[] = array(
					'name'  => __( 'Payment receipt', 'costa-rica-kit-for-woocommerce' ),
					'value' => $receipt['name'],
				);
				$items[] = array(
					'name'  => __( 'Receipt uploaded on', 'costa-rica-kit-for-woocommerce' ),
					'value' => wp_date( 'Y-m-d H:i', $receipt['uploaded_at'] ),
				);
			}

			$data[] = array(
				'group_id'    => 'crkit-sinpe-movil',
				'group_label' => __( 'SINPE Móvil payments', 'costa-rica-kit-for-woocommerce' ),
				'item_id'     => 'crkit-sinpe-' . $order->get_id(),
				'data'        => $items,
			);
		}

		return array(
			'data' => $data,
			'done' => count( $orders ) < self::PER_PAGE,
		);
	}

	/**
	 * Deletes the receipts of the orders placed with an email address, following the WooCommerce
	 * setting "Remove personal data from orders on request". Otherwise they are retained.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( $email_address, $page = 1 ): array {
		$orders   = $this->get_orders( (string) $email_address, (int) $page );
		$erase    = 'yes' === get_option( 'woocommerce_erasure_request_removes_order_data', 'no' );
		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $orders ) < self::PER_PAGE,
		);

		foreach ( $orders as $order ) {
			$receipt = OrderData::get_receipt( $order );

			if ( null === $receipt ) {
				continue;
			}

			if ( ! $erase ) {
				$response['items_retained'] = true;
				/* translators: %s: order number. */
				$response['messages'][] = sprintf( __( 'The SINPE Móvil receipt of order %s was retained, following the WooCommerce privacy settings.', 'costa-rica-kit-for-woocommerce' ), $order->get_order_number() );
				continue;
			}

			$this->storage->delete( $receipt['file'] );
			OrderData::delete_receipt( $order );
			$order->save();

			$response['items_removed'] = true;
			/* translators: %s: order number. */
			$response['messages'][] = sprintf( __( 'Removed the SINPE Móvil receipt of order %s.', 'costa-rica-kit-for-woocommerce' ), $order->get_order_number() );
		}

		return $response;
	}

	/**
	 * SINPE Móvil orders placed with an email address.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page          Page.
	 * @return array<int, WC_Order>
	 */
	private function get_orders( string $email_address, int $page ): array {
		$orders = wc_get_orders(
			array(
				'billing_email'  => $email_address,
				'payment_method' => Gateway::ID,
				'limit'          => self::PER_PAGE,
				'page'           => max( 1, $page ),
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'type'           => 'shop_order',
			)
		);

		return array_values( array_filter( is_array( $orders ) ? $orders : array(), static fn( $order ): bool => $order instanceof WC_Order ) );
	}
}
