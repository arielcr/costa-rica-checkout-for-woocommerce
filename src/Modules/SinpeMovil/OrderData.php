<?php
/**
 * SINPE Móvil data stored in the order (WooCommerce CRUD only, HPOS compatible).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Order meta keys and accessors. The amount, rate and payee are frozen when the order is placed.
 */
final class OrderData {

	public const AMOUNT_CRC  = '_crkit_sinpe_amount_crc';
	public const CURRENCY    = '_crkit_sinpe_currency';
	public const RATE        = '_crkit_sinpe_exchange_rate';
	public const SURCHARGE   = '_crkit_sinpe_surcharge';
	public const RATE_TYPE   = '_crkit_sinpe_rate_type';
	public const RATE_SOURCE = '_crkit_sinpe_rate_source';
	public const RATE_DATE   = '_crkit_sinpe_rate_date';
	public const REFERENCE   = '_crkit_sinpe_reference';
	public const PHONE       = '_crkit_sinpe_phone';
	public const HOLDER      = '_crkit_sinpe_holder';
	public const RECEIPT     = '_crkit_sinpe_receipt';

	/**
	 * Whether the order was paid (or is being paid) with SINPE Móvil.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_sinpe_order( WC_Order $order ): bool {
		return Gateway::ID === $order->get_payment_method();
	}

	/**
	 * Freezes the quote, reference and payee in the order. Call $order->save() afterwards.
	 *
	 * @param WC_Order     $order     Order.
	 * @param PaymentQuote $quote     Quote.
	 * @param string       $reference Reference the customer writes in the SINPE description.
	 * @param string       $phone     Merchant's SINPE Móvil number (normalized).
	 * @param string       $holder    Account holder name.
	 * @return void
	 */
	public static function save_quote( WC_Order $order, PaymentQuote $quote, string $reference, string $phone, string $holder ): void {
		$order->update_meta_data( self::AMOUNT_CRC, (string) $quote->amount_crc );
		$order->update_meta_data( self::CURRENCY, $quote->currency );
		$order->update_meta_data( self::REFERENCE, $reference );
		$order->update_meta_data( self::PHONE, $phone );
		$order->update_meta_data( self::HOLDER, $holder );

		if ( $quote->is_converted() ) {
			$order->update_meta_data( self::RATE, (string) $quote->rate );
			$order->update_meta_data( self::SURCHARGE, (string) $quote->surcharge );
			$order->update_meta_data( self::RATE_TYPE, $quote->rate_type );
			$order->update_meta_data( self::RATE_SOURCE, $quote->rate_source );
			$order->update_meta_data( self::RATE_DATE, $quote->rate_date );
		} else {
			foreach ( array( self::RATE, self::SURCHARGE, self::RATE_TYPE, self::RATE_SOURCE, self::RATE_DATE ) as $key ) {
				$order->delete_meta_data( $key );
			}
		}
	}

	/**
	 * Frozen payment details, or null if the order has none.
	 *
	 * @param WC_Order $order Order.
	 * @return array{amount_crc: int, currency: string, rate: float|null, surcharge: float, rate_type: string, rate_source: string, rate_date: string, reference: string, phone: string, holder: string}|null
	 */
	public static function get_payment( WC_Order $order ): ?array {
		$amount = $order->get_meta( self::AMOUNT_CRC );

		if ( '' === $amount || ! is_numeric( $amount ) ) {
			return null;
		}

		$rate = $order->get_meta( self::RATE );

		return array(
			'amount_crc'  => (int) $amount,
			'currency'    => (string) $order->get_meta( self::CURRENCY ),
			'rate'        => is_numeric( $rate ) ? (float) $rate : null,
			'surcharge'   => (float) $order->get_meta( self::SURCHARGE ),
			'rate_type'   => (string) $order->get_meta( self::RATE_TYPE ),
			'rate_source' => (string) $order->get_meta( self::RATE_SOURCE ),
			'rate_date'   => (string) $order->get_meta( self::RATE_DATE ),
			'reference'   => (string) $order->get_meta( self::REFERENCE ),
			'phone'       => (string) $order->get_meta( self::PHONE ),
			'holder'      => (string) $order->get_meta( self::HOLDER ),
		);
	}

	/**
	 * Uploaded receipt, or null.
	 *
	 * @param WC_Order $order Order.
	 * @return array{file: string, name: string, type: string, size: int, uploaded_at: int}|null
	 */
	public static function get_receipt( WC_Order $order ): ?array {
		$receipt = $order->get_meta( self::RECEIPT );

		if ( ! is_array( $receipt ) || empty( $receipt['file'] ) ) {
			return null;
		}

		return array(
			'file'        => (string) $receipt['file'],
			'name'        => isset( $receipt['name'] ) ? (string) $receipt['name'] : '',
			'type'        => isset( $receipt['type'] ) ? (string) $receipt['type'] : '',
			'size'        => isset( $receipt['size'] ) ? (int) $receipt['size'] : 0,
			'uploaded_at' => isset( $receipt['uploaded_at'] ) ? (int) $receipt['uploaded_at'] : 0,
		);
	}

	/**
	 * Stores the receipt reference. Call $order->save() afterwards.
	 *
	 * @param WC_Order                                                                     $order   Order.
	 * @param array{file: string, name: string, type: string, size: int, uploaded_at: int} $receipt Receipt data.
	 * @return void
	 */
	public static function set_receipt( WC_Order $order, array $receipt ): void {
		$order->update_meta_data( self::RECEIPT, $receipt );
	}

	/**
	 * Removes the receipt reference. Call $order->save() afterwards.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function delete_receipt( WC_Order $order ): void {
		$order->delete_meta_data( self::RECEIPT );
	}
}
