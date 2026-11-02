<?php
/**
 * Public access to the identification of an order, for e-invoicing plugins.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Backs `crkit_get_order_identification()`, the `crkit_order_identification` filter and the
 * `crkit_order_identification_saved` action.
 */
final class OrderIdentification {

	/**
	 * Identification of an order as an array, or null if the order has none.
	 *
	 * @param WC_Order $order Order.
	 * @return array{type: string, type_label: string, number: string, electronic_invoice: bool}|null
	 */
	public static function get( WC_Order $order ): ?array {
		$identification = IdentificationStore::get( $order );
		$data           = null === $identification ? null : $identification->to_array();

		/**
		 * Filters the identification of an order before other plugins read it.
		 *
		 * @since 0.2.0
		 *
		 * @param array|null $data  `type` (Hacienda code: 01 física, 02 jurídica, 03 DIMEX, 04 NITE),
		 *                          `type_label`, `number` (no spaces or hyphens) and
		 *                          `electronic_invoice` (bool), or null.
		 * @param WC_Order   $order Order.
		 */
		$data = apply_filters( 'crkit_order_identification', $data, $order );

		return is_array( $data ) && isset( $data['type'], $data['type_label'], $data['number'], $data['electronic_invoice'] ) ? $data : null;
	}

	/**
	 * Fires `crkit_order_identification_saved` after checkout if the order has an identification.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function fire_saved( WC_Order $order ): void {
		$data = self::get( $order );

		if ( null === $data ) {
			return;
		}

		/**
		 * Fires when a customer places an order with an ID number, in the classic or the block checkout.
		 *
		 * @since 0.2.0
		 *
		 * @param array    $data  Identification (see `crkit_order_identification`).
		 * @param WC_Order $order Order.
		 */
		do_action( 'crkit_order_identification_saved', $data, $order );
	}
}
