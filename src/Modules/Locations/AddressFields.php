<?php
/**
 * Costa Rican address fields shared by both checkouts, My Account and the admin.
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
 * Country locale (canton and district instead of a free city and postcode), the stored district,
 * and the address format "District, Canton, Province" used everywhere WooCommerce prints addresses.
 */
final class AddressFields {

	/**
	 * Address field key of the district (stores the five-digit postcode of the district).
	 */
	public const DISTRICT = 'crkit_district';

	/**
	 * Address field key of the canton (classic forms only; the canton is saved in `city`).
	 */
	public const CANTON = 'crkit_canton';

	/**
	 * Replacement token of the district in the address format.
	 */
	public const FORMAT_TOKEN = '{crkit_district}';

	/**
	 * Locations.
	 *
	 * @var LocationRepository
	 */
	private LocationRepository $locations;

	/**
	 * Constructor.
	 *
	 * @param LocationRepository $locations Locations.
	 */
	public function __construct( LocationRepository $locations ) {
		$this->locations = $locations;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_country_locale_default', array( $this, 'filter_default_locale' ) );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'filter_country_locale' ) );
		add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'filter_field_selectors' ) );
		add_filter( 'woocommerce_localisation_address_formats', array( $this, 'filter_address_formats' ) );
		add_filter( 'woocommerce_formatted_address_replacements', array( $this, 'filter_address_replacements' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'add_order_district' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'add_order_district' ), 10, 2 );
		add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'add_customer_district' ), 10, 3 );
	}

	/**
	 * District of an address of an order or a customer.
	 *
	 * @param WC_Data $data  Order or customer.
	 * @param string  $group `billing` or `shipping`.
	 * @return Location|null
	 */
	public function get_district( WC_Data $data, string $group ): ?Location {
		$key = $data instanceof WC_Order ? '_' . $group . '_' . self::DISTRICT : $group . '_' . self::DISTRICT;

		return $this->locations->find( (string) $data->get_meta( $key ) );
	}

	/**
	 * Saves the district of an address, with the canton as city and the district postcode.
	 * Call save() on the object afterwards.
	 *
	 * @param WC_Order|WC_Customer $data     Order or customer.
	 * @param string               $group    `billing` or `shipping`.
	 * @param Location             $location District.
	 * @return void
	 */
	public function set_district( WC_Data $data, string $group, Location $location ): void {
		$key = $data instanceof WC_Order ? '_' . $group . '_' . self::DISTRICT : $group . '_' . self::DISTRICT;

		$data->update_meta_data( $key, $location->get_postcode() );

		$city_setter     = 'set_' . $group . '_city';
		$postcode_setter = 'set_' . $group . '_postcode';
		if ( is_callable( array( $data, $city_setter ) ) && is_callable( array( $data, $postcode_setter ) ) ) {
			$data->{$city_setter}( $location->canton );
			$data->{$postcode_setter}( $location->get_postcode() );
		}
	}

	/**
	 * Our fields are hidden and optional for every country by default.
	 *
	 * @param mixed $locale Default locale.
	 * @return array<string, mixed>
	 */
	public function filter_default_locale( mixed $locale ): array {
		$locale = is_array( $locale ) ? $locale : array();

		foreach ( array( self::CANTON, self::DISTRICT ) as $field ) {
			$locale[ $field ] = array(
				'hidden'   => true,
				'required' => false,
			);
		}

		return $locale;
	}

	/**
	 * Costa Rica: canton and district are required; city and postcode are filled from them.
	 *
	 * The canton and district fields only exist in shortcode forms. The Store API (block checkout)
	 * validates addresses against the locale too, so there they are never required: the block
	 * checkout requires its own district field (BlocksCheckout).
	 *
	 * @param mixed $locale Locale per country.
	 * @return array<string, mixed>
	 */
	public function filter_country_locale( mixed $locale ): array {
		$locale   = is_array( $locale ) ? $locale : array();
		$required = ! ( function_exists( 'WC' ) && WC()->is_store_api_request() );

		$locale['CR'] = array_merge(
			isset( $locale['CR'] ) && is_array( $locale['CR'] ) ? $locale['CR'] : array(),
			array(
				'city'         => array(
					'hidden'   => true,
					'required' => false,
				),
				'postcode'     => array(
					'hidden'   => true,
					'required' => false,
				),
				self::CANTON   => array(
					'label'    => __( 'Canton', 'costa-rica-kit-for-woocommerce' ),
					'hidden'   => false,
					'required' => $required,
				),
				self::DISTRICT => array(
					'label'    => __( 'District', 'costa-rica-kit-for-woocommerce' ),
					'hidden'   => false,
					'required' => $required,
				),
			)
		);

		return $locale;
	}

	/**
	 * Lets WooCommerce's address script show, hide and require our fields per country.
	 *
	 * @param mixed $selectors Field selectors.
	 * @return array<string, mixed>
	 */
	public function filter_field_selectors( mixed $selectors ): array {
		$selectors = is_array( $selectors ) ? $selectors : array();

		foreach ( array( self::CANTON, self::DISTRICT ) as $field ) {
			$selectors[ $field ] = '#billing_' . $field . '_field, #shipping_' . $field . '_field';
		}

		return $selectors;
	}

	/**
	 * Costa Rican address format: "District, Canton, Province" on one line, then the postcode.
	 *
	 * @param mixed $formats Address formats per country.
	 * @return array<string, mixed>
	 */
	public function filter_address_formats( mixed $formats ): array {
		$formats       = is_array( $formats ) ? $formats : array();
		$formats['CR'] = "{name}\n{company}\n{address_1}\n{address_2}\n" . self::FORMAT_TOKEN . "{city}, {state}\n{postcode}\n{country}";

		return $formats;
	}

	/**
	 * Replaces the district token. Empty when the address has no district.
	 *
	 * @param mixed $replacements Replacements.
	 * @param mixed $args         Address arguments.
	 * @return array<string, mixed>
	 */
	public function filter_address_replacements( mixed $replacements, mixed $args ): array {
		$replacements = is_array( $replacements ) ? $replacements : array();
		$district     = is_array( $args ) && ! empty( $args[ self::DISTRICT ] ) ? (string) $args[ self::DISTRICT ] : '';

		$replacements[ self::FORMAT_TOKEN ] = '' === $district ? '' : $district . ', ';

		return $replacements;
	}

	/**
	 * Adds the district name to an order address before it is formatted.
	 *
	 * @param mixed $address Address arguments.
	 * @param mixed $order   Order.
	 * @return array<string, mixed>
	 */
	public function add_order_district( mixed $address, mixed $order ): array {
		$address = is_array( $address ) ? $address : array();

		if ( $order instanceof WC_Order ) {
			$group    = 'woocommerce_order_formatted_shipping_address' === current_filter() ? 'shipping' : 'billing';
			$location = $this->get_district( $order, $group );

			$address[ self::DISTRICT ] = null === $location ? '' : $location->district;
		}

		return $address;
	}

	/**
	 * Adds the district name to a customer address in My Account.
	 *
	 * @param mixed $address     Address arguments.
	 * @param mixed $customer_id Customer id.
	 * @param mixed $group       `billing` or `shipping`.
	 * @return array<string, mixed>
	 */
	public function add_customer_district( mixed $address, mixed $customer_id, mixed $group ): array {
		$address = is_array( $address ) ? $address : array();
		$group   = 'shipping' === $group ? 'shipping' : 'billing';

		$location                  = $this->locations->find( (string) get_user_meta( absint( $customer_id ), $group . '_' . self::DISTRICT, true ) );
		$address[ self::DISTRICT ] = null === $location ? '' : $location->district;

		return $address;
	}
}
