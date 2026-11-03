<?php
/**
 * Dependent province → canton → district selects in the classic checkout and My Account.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Locations;

use WC_Customer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds canton and district selects (selectWoo) to Costa Rican addresses in shortcode forms.
 * The canton is saved as the city and the district postcode as the postcode.
 */
final class ClassicCheckout {

	/**
	 * Script handle.
	 */
	public const SCRIPT_HANDLE = 'crkit-locations-classic';

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
		add_filter( 'woocommerce_default_address_fields', array( $this, 'add_address_fields' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'fill_checkout_options' ) );
		add_filter( 'woocommerce_address_to_edit', array( $this, 'fill_edit_address_options' ), 20, 2 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'apply_posted_district' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_after_save_address_validation', array( $this, 'validate_edit_address' ), 10, 2 );
		add_action( 'woocommerce_customer_save_address', array( $this, 'save_edit_address' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Adds the canton and district selects to every address form (hidden outside Costa Rica by the locale).
	 *
	 * @param mixed $fields Default address fields.
	 * @return array<string, mixed>
	 */
	public function add_address_fields( mixed $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();

		$fields[ AddressFields::CANTON ] = array(
			'label'    => __( 'Canton', 'costa-rica-checkout-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array( '' => __( 'Select a canton…', 'costa-rica-checkout-for-woocommerce' ) ),
			'required' => false,
			'class'    => array( 'form-row-wide', 'address-field', 'crkit-canton-field' ),
			'priority' => 81,
		);

		$fields[ AddressFields::DISTRICT ] = array(
			'label'    => __( 'District', 'costa-rica-checkout-for-woocommerce' ),
			'type'     => 'select',
			'options'  => array( '' => __( 'Select a district…', 'costa-rica-checkout-for-woocommerce' ) ),
			'required' => false,
			'class'    => array( 'form-row-wide', 'address-field', 'crkit-district-field' ),
			'priority' => 82,
		);

		return $fields;
	}

	/**
	 * Fills the options of the checkout selects for the saved address, so the form works before the script loads.
	 *
	 * @param mixed $fields Checkout fields.
	 * @return array<string, mixed>
	 */
	public function fill_checkout_options( mixed $fields ): array {
		$fields = is_array( $fields ) ? $fields : array();

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			if ( ! isset( $fields[ $group ] ) || ! is_array( $fields[ $group ] ) || ! function_exists( 'WC' ) || null === WC()->checkout() ) {
				continue;
			}

			$state    = (string) WC()->checkout()->get_value( $group . '_state' );
			$district = (string) WC()->checkout()->get_value( $group . '_' . AddressFields::DISTRICT );

			$fields[ $group ] = $this->fill_options( $fields[ $group ], $group . '_', $state, $district );
		}

		return $fields;
	}

	/**
	 * Fills the options of the My Account edit address selects.
	 *
	 * @param mixed $address   Address fields with values.
	 * @param mixed $load_address `billing` or `shipping`.
	 * @return array<string, mixed>
	 */
	public function fill_edit_address_options( mixed $address, mixed $load_address ): array {
		$address = is_array( $address ) ? $address : array();
		$prefix  = ( 'shipping' === $load_address ? 'shipping' : 'billing' ) . '_';

		$state    = isset( $address[ $prefix . 'state' ]['value'] ) ? (string) $address[ $prefix . 'state' ]['value'] : '';
		$district = isset( $address[ $prefix . AddressFields::DISTRICT ]['value'] ) ? (string) $address[ $prefix . AddressFields::DISTRICT ]['value'] : '';

		return $this->fill_options( $address, $prefix, $state, $district );
	}

	/**
	 * Canton and district options for a state and a saved district.
	 *
	 * @param array<string, mixed> $fields   Fields of one address.
	 * @param string               $prefix   `billing_` or `shipping_`.
	 * @param string               $state    Selected state.
	 * @param string               $district Saved district postcode.
	 * @return array<string, mixed>
	 */
	private function fill_options( array $fields, string $prefix, string $state, string $district ): array {
		$canton_key   = $prefix . AddressFields::CANTON;
		$district_key = $prefix . AddressFields::DISTRICT;

		if ( ! isset( $fields[ $canton_key ], $fields[ $district_key ] ) ) {
			return $fields;
		}

		$location = $this->locations->find( $district );
		$location = null !== $location && $location->state === $state ? $location : null;

		$fields[ $canton_key ]['options'] += $this->locations->get_cantons( $state );

		if ( null !== $location ) {
			$fields[ $canton_key ]['default']    = $location->get_canton_id();
			$fields[ $canton_key ]['value']      = $location->get_canton_id();
			$fields[ $district_key ]['options'] += $this->locations->get_districts( $location->get_canton_id() );
		}

		return $fields;
	}

	/**
	 * Fills city and postcode from the selected district and drops the canton, which is the city.
	 *
	 * @param mixed $data Posted checkout data.
	 * @return array<string, mixed>
	 */
	public function apply_posted_district( mixed $data ): array {
		$data = is_array( $data ) ? $data : array();

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			unset( $data[ $group . '_' . AddressFields::CANTON ] );

			$location = $this->get_posted_location( $data, $group );

			if ( null !== $location ) {
				$data[ $group . '_city' ]     = $location->canton;
				$data[ $group . '_postcode' ] = $location->get_postcode();
			}
		}

		return $data;
	}

	/**
	 * Requires a district that belongs to the selected province for Costa Rican addresses.
	 *
	 * @param mixed    $data   Posted checkout data.
	 * @param WP_Error $errors Validation errors.
	 * @return void
	 */
	public function validate_checkout( mixed $data, $errors ): void {
		if ( ! is_array( $data ) || ! $errors instanceof WP_Error ) {
			return;
		}

		$groups = array( 'billing' );
		if ( ! empty( $data['ship_to_different_address'] ) && WC()->cart && WC()->cart->needs_shipping_address() ) {
			$groups[] = 'shipping';
		}

		foreach ( $groups as $group ) {
			$message = $this->get_validation_error( $data, $group );

			if ( null !== $message ) {
				$errors->add( 'validation', $message, array( 'id' => $group . '_' . AddressFields::DISTRICT ) );
			}
		}
	}

	/**
	 * Same validation for My Account → Addresses.
	 *
	 * @param mixed $user_id      User id.
	 * @param mixed $load_address `billing` or `shipping`.
	 * @return void
	 */
	public function validate_edit_address( mixed $user_id, mixed $load_address ): void {
		$group = 'shipping' === $load_address ? 'shipping' : 'billing';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the nonce before this action.
		$data    = wp_unslash( $_POST );
		$message = $this->get_validation_error( is_array( $data ) ? $data : array(), $group );

		if ( null !== $message ) {
			wc_add_notice( $message, 'error', array( 'id' => $group . '_' . AddressFields::DISTRICT ) );
		}
	}

	/**
	 * After My Account saves an address: city and postcode from the district.
	 *
	 * @param mixed $user_id      User id.
	 * @param mixed $load_address `billing` or `shipping`.
	 * @return void
	 */
	public function save_edit_address( mixed $user_id, mixed $load_address ): void {
		$group    = 'shipping' === $load_address ? 'shipping' : 'billing';
		$customer = new WC_Customer( absint( $user_id ) );
		$location = $this->fields->get_district( $customer, $group );

		$customer->delete_meta_data( $group . '_' . AddressFields::CANTON );

		if ( null !== $location && 'CR' === $customer->{'get_' . $group . '_country'}() ) {
			$this->fields->set_district( $customer, $group, $location );
		}

		/**
		 * Fires after a Costa Rican district is saved from a classic address form.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Customer   $customer Customer.
		 * @param string        $group    `billing` or `shipping`.
		 * @param Location|null $location District.
		 */
		do_action( 'crkit_customer_district_saved', $customer, $group, $location );

		$customer->save();
	}

	/**
	 * Script for the dependent selects, on the checkout and the edit address page only.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) ) {
			return;
		}

		// The block checkout has its own fields; this script is for shortcode forms only.
		if ( is_checkout() && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		$asset_file = CRKIT_PLUGIN_DIR . 'assets/build/locations-classic.asset.json';
		$asset      = is_readable( $asset_file ) ? wp_json_file_decode( $asset_file, array( 'associative' => true ) ) : array();
		$asset      = is_array( $asset ) ? $asset : array();
		$deps       = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CRKIT_PLUGIN_URL . 'assets/build/locations-classic.js',
			array_unique( array_merge( $deps, array( 'jquery', 'selectWoo' ) ) ),
			isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : CRKIT_VERSION,
			true
		);
		wp_add_inline_script( self::SCRIPT_HANDLE, 'window.crkitLocations = ' . wp_json_encode( $this->get_script_data() ) . ';', 'before' );
		wp_set_script_translations( self::SCRIPT_HANDLE, 'costa-rica-checkout-for-woocommerce', CRKIT_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Cantons and districts per state for the script.
	 *
	 * @return array<string, array<string, array{name: string, districts: array<string, string>}>>
	 */
	private function get_script_data(): array {
		$data = array();

		foreach ( $this->locations->all() as $postcode => $location ) {
			$data[ $location->state ][ $location->get_canton_id() ]['name']                   = $location->canton;
			$data[ $location->state ][ $location->get_canton_id() ]['districts'][ $postcode ] = $location->district;
		}

		return $data;
	}

	/**
	 * District selected in posted data, if it is Costa Rican and belongs to the selected province.
	 *
	 * @param array<string, mixed> $data  Posted data.
	 * @param string               $group `billing` or `shipping`.
	 * @return Location|null
	 */
	private function get_posted_location( array $data, string $group ): ?Location {
		if ( 'CR' !== ( $data[ $group . '_country' ] ?? '' ) ) {
			return null;
		}

		$location = $this->locations->find( wc_clean( (string) ( $data[ $group . '_' . AddressFields::DISTRICT ] ?? '' ) ) );
		$state    = (string) ( $data[ $group . '_state' ] ?? '' );

		return null !== $location && $state === $location->state ? $location : null;
	}

	/**
	 * Validation message for a Costa Rican address without a valid district, or null.
	 *
	 * @param array<string, mixed> $data  Posted data.
	 * @param string               $group `billing` or `shipping`.
	 * @return string|null
	 */
	private function get_validation_error( array $data, string $group ): ?string {
		if ( 'CR' !== ( $data[ $group . '_country' ] ?? '' ) || null !== $this->get_posted_location( $data, $group ) ) {
			return null;
		}

		return 'shipping' === $group
			? __( 'Select the district of the shipping address.', 'costa-rica-checkout-for-woocommerce' )
			: __( 'Select the district of the billing address.', 'costa-rica-checkout-for-woocommerce' );
	}
}
