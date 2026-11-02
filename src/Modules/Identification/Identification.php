<?php
/**
 * Identification of a customer or an order.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

defined( 'ABSPATH' ) || exit;

/**
 * ID type, normalized number and whether the customer asked for an electronic invoice.
 */
final class Identification {

	/**
	 * Constructor.
	 *
	 * @param string $type               Type code (IdentificationType constants).
	 * @param string $number             Normalized number.
	 * @param bool   $electronic_invoice Whether the customer asked for an electronic invoice.
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $number,
		public readonly bool $electronic_invoice = false
	) {}

	/**
	 * Translated type name.
	 *
	 * @return string
	 */
	public function get_type_label(): string {
		return IdentificationType::get_label( $this->type );
	}

	/**
	 * Array shape shared with other plugins (`crkit_get_order_identification()`).
	 *
	 * @return array{type: string, type_label: string, number: string, electronic_invoice: bool}
	 */
	public function to_array(): array {
		return array(
			'type'               => $this->type,
			'type_label'         => $this->get_type_label(),
			'number'             => $this->number,
			'electronic_invoice' => $this->electronic_invoice,
		);
	}
}
