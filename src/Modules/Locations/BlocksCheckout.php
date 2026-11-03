<?php
/**
 * District selection in the block checkout (Additional Checkout Fields API).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Locations;

use WC_Customer;
use WC_Data;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Registers one district select per province. JSON Schema rules show (and require) only the one
 * that matches the selected province; city and postcode are filled from it on the server.
 * See docs/DECISIONS.md, ADR-0040.
 */
final class BlocksCheckout {

	/**
	 * Field id prefix; the suffix is the WooCommerce state code without "CR-", in lower case.
	 */
	public const FIELD_PREFIX = 'crkit/district-';

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
		add_action( 'woocommerce_init', array( $this, 'register_fields' ) );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'apply_to_customer' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_to_order' ) );
		add_action( 'woocommerce_checkout_update_customer', array( $this, 'sync_classic_checkout_customer' ) );
		add_filter( 'woocommerce_address_to_edit', array( $this, 'remove_from_edit_address' ), 30, 2 );
		add_action( 'woocommerce_customer_save_address', array( $this, 'prepare_edit_address_values' ), 5, 2 );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'remove_from_admin_fields' ), 99 );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'remove_from_admin_fields' ), 99 );
	}

	/**
	 * Field id for a WooCommerce state, e.g. `crkit/district-sj` for `CR-SJ`.
	 *
	 * @param string $state WooCommerce state code.
	 * @return string
	 */
	public static function get_field_id( string $state ): string {
		return self::FIELD_PREFIX . strtolower( (string) preg_replace( '/^CR-/', '', $state ) );
	}

	/**
	 * Registers the seven district fields.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		foreach ( $this->locations->get_states() as $state ) {
			$options = $this->get_district_options( $state );
			$visible = $this->get_visibility_rule( $state );

			woocommerce_register_additional_checkout_field(
				array(
					'id'                         => self::get_field_id( $state ),
					'label'                      => __( 'District', 'costa-rica-checkout-for-woocommerce' ),
					'placeholder'                => __( 'Select a district', 'costa-rica-checkout-for-woocommerce' ),
					'location'                   => 'address',
					'type'                       => 'select',
					'options'                    => $options,
					'required'                   => $visible,
					'hidden'                     => array( 'not' => $visible ),
					'show_in_order_confirmation' => false,
				)
			);
		}
	}

	/**
	 * District options of a province, labelled "Canton — District" and sorted by canton, then district,
	 * so that a native select (no search, e.g. on mobile) groups each canton's districts together.
	 * See docs/DECISIONS.md, ADR-0045.
	 *
	 * @param string $state WooCommerce state code.
	 * @return array<int, array{value: string, label: string}>
	 */
	public function get_district_options( string $state ): array {
		$locations = $this->locations->get_districts_in_state( $state );

		uasort(
			$locations,
			static function ( Location $a, Location $b ): int {
				$by_canton = strcasecmp( remove_accents( $a->canton ), remove_accents( $b->canton ) );

				return 0 !== $by_canton ? $by_canton : strcasecmp( remove_accents( $a->district ), remove_accents( $b->district ) );
			}
		);

		$options = array();
		foreach ( $locations as $postcode => $location ) {
			$options[] = array(
				'value' => (string) $postcode,
				/* translators: 1: canton name, 2: district name. */
				'label' => sprintf( _x( '%1$s — %2$s', 'canton and district', 'costa-rica-checkout-for-woocommerce' ), $location->canton, $location->district ),
			);
		}

		return $options;
	}

	/**
	 * JSON Schema: the address is Costa Rican and in the given province.
	 *
	 * @param string $state WooCommerce state code.
	 * @return array<string, mixed>
	 */
	private function get_visibility_rule( string $state ): array {
		return array(
			'type'       => 'object',
			'required'   => array( 'customer' ),
			'properties' => array(
				'customer' => array(
					'type'       => 'object',
					'required'   => array( 'address' ),
					'properties' => array(
						'address' => array(
							'type'       => 'object',
							'required'   => array( 'country', 'state' ),
							'properties' => array(
								'country' => array( 'const' => 'CR' ),
								'state'   => array( 'const' => $state ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Cart address updates: city and postcode from the district, so shipping rates use them.
	 *
	 * @param mixed $customer Customer.
	 * @return void
	 */
	public function apply_to_customer( mixed $customer ): void {
		if ( $customer instanceof WC_Customer ) {
			$this->apply( $customer );
		}
	}

	/**
	 * Placing an order: city, postcode and the stored district from the selected field.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public function apply_to_order( mixed $order ): void {
		if ( $order instanceof WC_Order ) {
			$this->apply( $order );
		}
	}

	/**
	 * Applies the district field of the selected province to both addresses of an object.
	 *
	 * @param WC_Order|WC_Customer $data Order or customer.
	 * @return void
	 */
	private function apply( WC_Data $data ): void {
		foreach ( array( 'billing', 'shipping' ) as $group ) {
			if ( 'CR' !== $data->{'get_' . $group . '_country'}() ) {
				continue;
			}

			$state    = (string) $data->{'get_' . $group . '_state'}();
			$location = $this->locations->find( (string) $data->get_meta( '_wc_' . $group . '/' . self::get_field_id( $state ) ) );

			if ( null !== $location && $location->state === $state ) {
				$this->fields->set_district( $data, $group, $location );
			}
		}
	}

	/**
	 * Classic checkout orders: copy the district to the block field so the block checkout prefills it.
	 *
	 * @param mixed $customer Customer.
	 * @return void
	 */
	public function sync_classic_checkout_customer( mixed $customer ): void {
		if ( ! $customer instanceof WC_Customer ) {
			return;
		}

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$location = $this->fields->get_district( $customer, $group );

			if ( null !== $location ) {
				$customer->update_meta_data( '_wc_' . $group . '/' . self::get_field_id( $location->state ), $location->get_postcode() );
			}
		}
	}

	/**
	 * My Account uses the classic dependent selects; hide WooCommerce's copy of the block fields.
	 *
	 * @param mixed $address      Address fields.
	 * @param mixed $load_address `billing` or `shipping`.
	 * @return array<string, mixed>
	 */
	public function remove_from_edit_address( mixed $address, mixed $load_address ): array {
		$address = is_array( $address ) ? $address : array();
		$prefix  = '_wc_' . ( 'shipping' === $load_address ? 'shipping' : 'billing' ) . '/' . self::FIELD_PREFIX;

		return array_filter( $address, static fn( $key ): bool => ! str_starts_with( (string) $key, $prefix ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Before WooCommerce saves the block fields from My Account, fill them from the classic district select,
	 * so the required rule passes and the block checkout gets the same district.
	 *
	 * @param mixed $user_id      User id.
	 * @param mixed $load_address `billing` or `shipping`.
	 * @return void
	 */
	public function prepare_edit_address_values( mixed $user_id, mixed $load_address ): void {
		$group = 'shipping' === $load_address ? 'shipping' : 'billing';

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified the nonce before this action.
		$state    = isset( $_POST[ $group . '_state' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $group . '_state' ] ) ) : '';
		$district = isset( $_POST[ $group . '_' . AddressFields::DISTRICT ] ) ? sanitize_text_field( wp_unslash( $_POST[ $group . '_' . AddressFields::DISTRICT ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// WooCommerce reads, validates and saves the block address fields from $_POST right after this
		// action (CheckoutFieldsFrontend::save_address_fields()); it offers no filter for their values.
		// Only the plugin's own keys are written, with sanitized values.
		foreach ( $this->locations->get_states() as $province_state ) {
			$_POST[ '_wc_' . $group . '/' . self::get_field_id( $province_state ) ] = $province_state === $state ? $district : '';
		}
	}

	/**
	 * The order screen edits the district with its own select (AdminFields); drop the seven block fields there.
	 *
	 * @param mixed $fields Admin address fields.
	 * @return array<string, mixed>
	 */
	public function remove_from_admin_fields( mixed $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();

		// WooCommerce inserts the block fields with array_splice(), which drops their keys: match the field id too.
		return array_filter(
			$fields,
			static fn( $field, $key ): bool => ! str_contains( (string) $key, self::FIELD_PREFIX )
				&& ! ( is_array( $field ) && isset( $field['id'] ) && str_contains( (string) $field['id'], self::FIELD_PREFIX ) ),
			ARRAY_FILTER_USE_BOTH
		);
	}
}
