<?php
/**
 * Shows the identification of an order.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the ID to the billing address wherever WooCommerce prints it (order screen, emails, order
 * received page in classic and block themes, My Account orders), and repeats it in the order emails
 * when the customer asked for an electronic invoice. See docs/DECISIONS.md, ADR-0047.
 */
final class OrderDisplay {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_order_get_formatted_billing_address', array( $this, 'add_to_billing_address' ), 10, 3 );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'add_email_fields' ), 10, 3 );
	}

	/**
	 * Appends "ID type: number" to the formatted billing address.
	 *
	 * @param mixed $address     Formatted address (HTML).
	 * @param mixed $raw_address Address fields.
	 * @param mixed $order       Order.
	 * @return string
	 */
	public function add_to_billing_address( mixed $address, mixed $raw_address = array(), mixed $order = null ): string {
		unset( $raw_address );
		$address = is_string( $address ) ? $address : '';

		if ( ! $order instanceof WC_Order ) {
			return $address;
		}

		$identification = IdentificationStore::get( $order );
		if ( null === $identification ) {
			return $address;
		}

		$line = self::format( $identification );

		return '' === $address ? $line : $address . '<br/>' . $line;
	}

	/**
	 * "Cédula física: 100000001", escaped for HTML.
	 *
	 * @param Identification $identification Identification.
	 * @return string
	 */
	public static function format( Identification $identification ): string {
		$label = $identification->get_type_label();

		return esc_html(
			'' === $label
				? $identification->number
				/* translators: 1: ID type, e.g. "Cédula física", 2: ID number. */
				: sprintf( _x( '%1$s: %2$s', 'ID type and number', 'costa-rica-kit-for-woocommerce' ), $label, $identification->number )
		);
	}

	/**
	 * "Cédula física 112340567", as plain text (WooCommerce escapes email meta fields).
	 *
	 * @param Identification $identification Identification.
	 * @return string
	 */
	public static function format_inline( Identification $identification ): string {
		$label = $identification->get_type_label();

		return '' === $label
			? $identification->number
			/* translators: 1: ID type, e.g. "Cédula física", 2: ID number. */
			: sprintf( _x( '%1$s %2$s', 'ID type and number in a sentence', 'costa-rica-kit-for-woocommerce' ), $label, $identification->number );
	}

	/**
	 * Order emails, when the customer asked for an electronic invoice: the store sees the request with
	 * the ID, and the customer sees the ID the invoice will be issued to, to catch typing mistakes early.
	 *
	 * @param mixed $fields        Fields.
	 * @param mixed $sent_to_admin Whether the email goes to the store.
	 * @param mixed $order         Order.
	 * @return array<string, mixed>
	 */
	public function add_email_fields( mixed $fields, mixed $sent_to_admin = false, mixed $order = null ): array {
		$fields = is_array( $fields ) ? $fields : array();

		if ( ! $order instanceof WC_Order || ! IdentificationStore::wants_electronic_invoice( $order ) ) {
			return $fields;
		}

		$identification = IdentificationStore::get( $order );
		if ( null === $identification ) {
			return $fields;
		}

		$fields['crkit_electronic_invoice'] = array(
			'label' => $sent_to_admin
				? __( 'The customer asked for an electronic invoice', 'costa-rica-kit-for-woocommerce' )
				: __( 'Electronic invoice requested in the name of', 'costa-rica-kit-for-woocommerce' ),
			'value' => self::format_inline( $identification ),
		);

		return $fields;
	}
}
