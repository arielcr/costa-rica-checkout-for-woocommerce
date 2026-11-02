<?php
/**
 * Integration tests: provinces, cantons and districts in addresses.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use CRKit\Modules\Locations\AddressFields;
use CRKit\Modules\Locations\AdminFields;
use CRKit\Modules\Locations\BlocksCheckout;
use CRKit\Modules\Locations\ClassicCheckout;
use CRKit\Modules\Locations\LocationRepository;
use WC_Customer;
use WP_Error;
use WP_UnitTestCase;

/**
 * Tests the locale, both checkouts, the stored district and the address format.
 *
 * @coversNothing
 */
final class LocationsTest extends WP_UnitTestCase {

	private LocationRepository $locations;
	private AddressFields $fields;
	private ?string $request_uri;

	public function set_up(): void {
		parent::set_up();
		$this->request_uri      = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Test state.
		$this->locations        = new LocationRepository();
		$this->fields           = new AddressFields( $this->locations );
		WC()->countries->locale = null;
	}

	public function tear_down(): void {
		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		$_POST                  = array();
		WC()->countries->locale = null;
		parent::tear_down();
	}

	public function test_costa_rica_locale_replaces_city_and_postcode_with_canton_and_district(): void {
		$cr = WC()->countries->get_country_locale()['CR'];

		$this->assertTrue( $cr['city']['hidden'] );
		$this->assertFalse( $cr['city']['required'] );
		$this->assertTrue( $cr['postcode']['hidden'] );
		$this->assertTrue( $cr[ AddressFields::CANTON ]['required'] );
		$this->assertTrue( $cr[ AddressFields::DISTRICT ]['required'] );
		$this->assertSame( 'Province', $cr['state']['label'], 'WooCommerce provinces are reused.' );
	}

	public function test_other_countries_keep_city_and_postcode(): void {
		$locale = WC()->countries->get_country_locale();

		$this->assertTrue( $locale['default'][ AddressFields::DISTRICT ]['hidden'], 'Hidden unless a country shows them.' );
		$this->assertTrue( $locale['default'][ AddressFields::CANTON ]['hidden'] );
		$this->assertArrayNotHasKey( 'hidden', $locale['US']['city'] ?? array() );
	}

	public function test_store_api_requests_do_not_require_the_classic_fields(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';

		$cr = WC()->countries->get_country_locale()['CR'];

		$this->assertFalse( $cr[ AddressFields::CANTON ]['required'] );
		$this->assertFalse( $cr[ AddressFields::DISTRICT ]['required'] );
	}

	public function test_classic_checkout_fills_city_and_postcode_from_the_district(): void {
		$data = ( new ClassicCheckout( $this->locations, $this->fields ) )->apply_posted_district(
			array(
				'billing_country'        => 'CR',
				'billing_state'          => 'CR-H',
				'billing_crkit_canton'   => '402',
				'billing_crkit_district' => '40206',
				'billing_city'           => 'typed by the customer',
				'billing_postcode'       => '',
			)
		);

		$this->assertSame( 'Barva', $data['billing_city'] );
		$this->assertSame( '40206', $data['billing_postcode'] );
		$this->assertArrayNotHasKey( 'billing_crkit_canton', $data, 'The canton is the city; it is not stored twice.' );
	}

	public function test_classic_checkout_rejects_a_district_from_another_province(): void {
		$classic = new ClassicCheckout( $this->locations, $this->fields );
		$errors  = new WP_Error();
		$data    = array(
			'billing_country'        => 'CR',
			'billing_state'          => 'CR-SJ',
			'billing_crkit_district' => '40206',
		);

		$classic->validate_checkout( $classic->apply_posted_district( $data ), $errors );

		$this->assertSame( array( 'billing_crkit_district' ), array( $errors->get_error_data( 'validation' )['id'] ) );
	}

	public function test_classic_checkout_ignores_other_countries(): void {
		$classic = new ClassicCheckout( $this->locations, $this->fields );
		$errors  = new WP_Error();
		$data    = $classic->apply_posted_district(
			array(
				'billing_country'  => 'US',
				'billing_city'     => 'Miami',
				'billing_postcode' => '33101',
			)
		);
		$classic->validate_checkout( $data, $errors );

		$this->assertSame( 'Miami', $data['billing_city'] );
		$this->assertFalse( $errors->has_errors() );
	}

	public function test_saved_district_is_shown_in_the_formatted_address(): void {
		$order = wc_create_order();
		$order->set_billing_first_name( 'Ana' );
		$order->set_billing_last_name( 'Mora' );
		$order->set_billing_address_1( 'De la iglesia 100 m norte' );
		$order->set_billing_country( 'CR' );
		$order->set_billing_state( 'CR-SJ' );
		$this->fields->set_district( $order, 'billing', $this->locations->find( '10101' ) );
		$order->save();

		$order = wc_get_order( $order->get_id() );

		$this->assertSame( 'San José', $order->get_billing_city() );
		$this->assertSame( '10101', $order->get_billing_postcode() );
		$this->assertSame( '10101', $order->get_meta( '_billing_crkit_district' ) );
		$this->assertStringContainsString( 'Carmen, San José, San José', $order->get_formatted_billing_address() );
	}

	public function test_seven_block_fields_with_the_districts_of_each_province(): void {
		$controller = Package::container()->get( CheckoutFields::class );
		$fields     = $controller->get_fields_for_location( 'address' );

		foreach ( array(
			'CR-SJ' => 123,
			'CR-A'  => 116,
			'CR-C'  => 53,
			'CR-H'  => 48,
			'CR-G'  => 61,
			'CR-P'  => 61,
			'CR-L'  => 30,
		) as $state => $count ) {
			$id = BlocksCheckout::get_field_id( $state );
			$this->assertArrayHasKey( $id, $fields );
			$this->assertCount( $count, $fields[ $id ]['options'] );
			$this->assertFalse( $fields[ $id ]['show_in_order_confirmation'] );
		}

		$this->assertSame( 'Carmen (San José)', $fields['crkit/district-sj']['options'][0]['label'] );
	}

	public function test_block_district_fills_the_order_address(): void {
		$order = wc_create_order();
		$order->set_shipping_country( 'CR' );
		$order->set_shipping_state( 'CR-SJ' );
		$order->set_billing_country( 'CR' );
		$order->set_billing_state( 'CR-H' );
		$order->update_meta_data( '_wc_shipping/crkit/district-sj', '10203' );
		$order->update_meta_data( '_wc_billing/crkit/district-h', '40206' );
		$order->update_meta_data( '_wc_billing/crkit/district-sj', '10101' ); // Stale value from another province.

		( new BlocksCheckout( $this->locations, $this->fields ) )->apply_to_order( $order );

		$this->assertSame( array( 'Escazú', '10203', '10203' ), array( $order->get_shipping_city(), $order->get_shipping_postcode(), $order->get_meta( '_shipping_crkit_district' ) ) );
		$this->assertSame( array( 'Barva', '40206', '40206' ), array( $order->get_billing_city(), $order->get_billing_postcode(), $order->get_meta( '_billing_crkit_district' ) ) );
	}

	public function test_block_district_fills_the_customer_address_for_shipping_rates(): void {
		$customer = new WC_Customer();
		$customer->set_shipping_country( 'CR' );
		$customer->set_shipping_state( 'CR-G' );
		$customer->update_meta_data( '_wc_shipping/crkit/district-g', '50101' );

		( new BlocksCheckout( $this->locations, $this->fields ) )->apply_to_customer( $customer );

		$this->assertSame( 'Liberia', $customer->get_shipping_city() );
		$this->assertSame( '50101', $customer->get_shipping_postcode() );
	}

	public function test_my_account_copies_the_district_into_the_block_field_of_its_province(): void {
		$_POST = array(
			'billing_state'          => 'CR-H',
			'billing_crkit_district' => '40206',
		);

		( new BlocksCheckout( $this->locations, $this->fields ) )->prepare_edit_address_values( 1, 'billing' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Test assertions.
		$this->assertSame( '40206', $_POST['_wc_billing/crkit/district-h'] );
		$this->assertSame( '', $_POST['_wc_billing/crkit/district-sj'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function test_admin_uses_one_searchable_district_field_instead_of_the_block_fields(): void {
		$fields = ( new AdminFields( $this->locations, $this->fields ) )->add_field(
			array(
				'crkit/district-sj' => array(),
				'city'              => array(),
			)
		);
		$fields = ( new BlocksCheckout( $this->locations, $this->fields ) )->remove_from_admin_fields( $fields );

		$this->assertArrayNotHasKey( 'crkit/district-sj', $fields );
		$this->assertCount( 493, $fields[ AddressFields::DISTRICT ]['options'] );
		$this->assertSame( 'Carmen, San José, San José', $fields[ AddressFields::DISTRICT ]['options']['10101'] );
	}
}
