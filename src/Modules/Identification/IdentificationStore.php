<?php
/**
 * Storage of the identification on orders and customers.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Data;

defined( 'ABSPATH' ) || exit;

/**
 * Both checkouts store the identification under the meta keys of the block checkout contact fields
 * (`_wc_other/crkit/…`), on the order and on the customer. The block checkout and My Account → Account
 * details read and write them natively. See docs/DECISIONS.md, ADR-0047.
 */
final class IdentificationStore {

	/**
	 * Block checkout field id of the ID type.
	 */
	public const TYPE_FIELD = 'crkit/id-type';

	/**
	 * Block checkout field id of the ID number.
	 */
	public const NUMBER_FIELD = 'crkit/id-number';

	/**
	 * Block checkout field id of the "I need an electronic invoice" checkbox.
	 */
	public const INVOICE_FIELD = 'crkit/electronic-invoice';

	/**
	 * Meta key prefix WooCommerce uses for contact and order fields.
	 */
	public const META_PREFIX = '_wc_other/';

	/**
	 * Meta key of a field.
	 *
	 * @param string $field Field id (one of the *_FIELD constants).
	 * @return string
	 */
	public static function get_meta_key( string $field ): string {
		return self::META_PREFIX . $field;
	}

	/**
	 * Identification of an order or customer, or null if it has no ID number.
	 *
	 * @param WC_Data $data Order or customer.
	 * @return Identification|null
	 */
	public static function get( WC_Data $data ): ?Identification {
		$number = IdentificationType::normalize( (string) $data->get_meta( self::get_meta_key( self::NUMBER_FIELD ) ) );

		if ( '' === $number ) {
			return null;
		}

		return new Identification(
			(string) $data->get_meta( self::get_meta_key( self::TYPE_FIELD ) ),
			$number,
			self::wants_electronic_invoice( $data )
		);
	}

	/**
	 * Whether the "I need an electronic invoice" checkbox is checked on an order or customer.
	 *
	 * @param WC_Data $data Order or customer.
	 * @return bool
	 */
	public static function wants_electronic_invoice( WC_Data $data ): bool {
		return '1' === (string) $data->get_meta( self::get_meta_key( self::INVOICE_FIELD ) );
	}

	/**
	 * Saves the identification. Empty values delete the meta. Call save() on the object afterwards.
	 *
	 * @param WC_Data $data               Order or customer.
	 * @param string  $type               Type code.
	 * @param string  $number             Raw number; it is normalized.
	 * @param bool    $electronic_invoice Whether the customer asked for an electronic invoice.
	 * @return void
	 */
	public static function save( WC_Data $data, string $type, string $number, bool $electronic_invoice ): void {
		$values = array(
			self::TYPE_FIELD    => IdentificationType::exists( $type ) ? $type : '',
			self::NUMBER_FIELD  => IdentificationType::normalize( $number ),
			self::INVOICE_FIELD => $electronic_invoice ? '1' : '',
		);

		foreach ( $values as $field => $value ) {
			if ( '' === $value ) {
				$data->delete_meta_data( self::get_meta_key( $field ) );
			} else {
				$data->update_meta_data( self::get_meta_key( $field ), $value );
			}
		}
	}

	/**
	 * Deletes the identification. Call save() on the object afterwards.
	 *
	 * @param WC_Data $data Order or customer.
	 * @return bool Whether there was anything to delete.
	 */
	public static function delete( WC_Data $data ): bool {
		$deleted = false;

		foreach ( array( self::TYPE_FIELD, self::NUMBER_FIELD, self::INVOICE_FIELD ) as $field ) {
			if ( $data->meta_exists( self::get_meta_key( $field ) ) ) {
				$data->delete_meta_data( self::get_meta_key( $field ) );
				$deleted = true;
			}
		}

		return $deleted;
	}
}
