<?php
/**
 * District field in the order screen.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Locations;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Lets store managers set the district of an order address (HPOS and legacy storage).
 * The address itself shows the district through the address format.
 */
final class AdminFields {

	/**
	 * Locations.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $locations;

	/**
	 * Shared address fields.
	 *
	 * @var AddressFields
	 */
	private AddressFields $fields;

	/**
	 * Constructor.
	 *
	 * @param LocationRepository $locations Locations.
	 * @param AddressFields      $fields    Shared address fields.
	 */
	public function __construct( LocationRepository $locations, AddressFields $fields ) {
		$this->locations = $locations;
		$this->fields    = $fields;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'add_field' ) );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'add_field' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'apply_after_save' ), 60 );
	}

	/**
	 * Adds a searchable district select to the address edit form.
	 *
	 * @param mixed $fields Admin address fields.
	 * @return array<string, mixed>
	 */
	public function add_field( mixed $fields ): array {
		$fields  = is_array( $fields ) ? $fields : array();
		$options = array( '' => __( '— Not in Costa Rica —', 'costa-rica-checkout-for-woocommerce' ) );

		foreach ( $this->locations->all() as $postcode => $location ) {
			/* translators: 1: district, 2: canton, 3: province. */
			$options[ $postcode ] = sprintf( __( '%1$s, %2$s, %3$s', 'costa-rica-checkout-for-woocommerce' ), $location->district, $location->canton, $location->province );
		}

		$fields[ AddressFields::DISTRICT ] = array(
			'label'         => __( 'District (Costa Rica)', 'costa-rica-checkout-for-woocommerce' ),
			'show'          => false,
			'type'          => 'select',
			'class'         => 'wc-enhanced-select',
			'wrapper_class' => 'form-field-wide',
			'options'       => $options,
		);

		return $fields;
	}

	/**
	 * After WooCommerce saves the order addresses: city and postcode from the district.
	 *
	 * @param mixed $order_id Order id.
	 * @return void
	 */
	public function apply_after_save( mixed $order_id ): void {
		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$changed = false;
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$location = $this->fields->get_district( $order, $group );

			if ( null !== $location && 'CR' === $order->{'get_' . $group . '_country'}() && $location->state === $order->{'get_' . $group . '_state'}() ) {
				$this->fields->set_district( $order, $group, $location );
				$changed = true;
			}
		}

		if ( $changed ) {
			$order->save();
		}
	}
}
