<?php
/**
 * Integration tests: customer ID type and number.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use CRKit\Modules\Identification\AdminFields;
use CRKit\Modules\Identification\BlocksCheckout;
use CRKit\Modules\Identification\ClassicCheckout;
use CRKit\Modules\Identification\IdentificationSettings;
use CRKit\Modules\Identification\IdentificationStore;
use CRKit\Modules\Identification\OrderDisplay;
use CRKit\Modules\Identification\Privacy;
use WC_Customer;
use WC_Order;
use WP_Error;
use WP_UnitTestCase;

/**
 * Tests both checkouts, the order screen, emails, the public function and personal data.
 *
 * @coversNothing
 */
final class IdentificationTest extends WP_UnitTestCase {

	private IdentificationSettings $settings;

	public function set_up(): void {
		parent::set_up();
		$this->settings = new IdentificationSettings();
	}

	private function set_requirement( string $requirement ): void {
		update_option( IdentificationSettings::OPTION, array( 'requirement' => $requirement ) );
	}

	private function order_with_id( string $type = '01', string $number = '112340567', bool $invoice = false ): WC_Order {
		$order = wc_create_order();
		$order->set_billing_first_name( 'Ana' );
		$order->set_billing_last_name( 'Mora' );
		$order->set_billing_country( 'CR' );
		IdentificationStore::save( $order, $type, $number, $invoice );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	public function test_requirement_defaults_to_electronic_invoice_only(): void {
		delete_option( IdentificationSettings::OPTION );
		$this->assertSame( IdentificationSettings::INVOICE, $this->settings->get_requirement() );

		$this->set_requirement( 'nonsense' );
		$this->assertSame( IdentificationSettings::INVOICE, $this->settings->get_requirement() );
	}

	public function test_block_fields_follow_the_requirement(): void {
		$blocks = new BlocksCheckout( $this->settings );

		$optional = $blocks->get_field_definitions( IdentificationSettings::OPTIONAL );
		$this->assertSame( array( 'crkit/id-type', 'crkit/id-number' ), array_column( $optional, 'id' ) );
		$this->assertSame( array( false, false ), array_column( $optional, 'required' ) );

		$required = $blocks->get_field_definitions( IdentificationSettings::REQUIRED );
		$this->assertSame( array( true, true ), array_column( $required, 'required' ) );

		$invoice = $blocks->get_field_definitions( IdentificationSettings::INVOICE );
		$this->assertSame( array( 'crkit/electronic-invoice', 'crkit/id-type', 'crkit/id-number' ), array_column( $invoice, 'id' ) );
		$this->assertIsArray( $invoice[1]['required'] );
		$this->assertSame( array( 'const' => true ), $invoice[2]['required']['properties']['customer']['properties']['additional_fields']['properties']['crkit/electronic-invoice'] );
		$this->assertSame( array( '01', '02', '03', '04' ), array_column( $invoice[1]['options'], 'value' ) );
		$this->assertSame( array( false, false, false ), array_column( $invoice, 'show_in_order_confirmation' ) );
	}

	public function test_block_fields_are_registered_as_contact_fields(): void {
		$controller = Package::container()->get( CheckoutFields::class );

		$this->assertSame(
			array( 'crkit/electronic-invoice', 'crkit/id-type', 'crkit/id-number' ),
			array_values( array_intersect( array_keys( $controller->get_fields_for_location( 'contact' ) ), array( 'crkit/electronic-invoice', 'crkit/id-type', 'crkit/id-number' ) ) )
		);
		$this->assertSame( '3101A00001', $controller->sanitize_field( 'crkit/id-number', ' 3-101-a00001 ' ) );
	}

	public function test_block_checkout_rejects_a_number_that_does_not_match_its_type(): void {
		$controller = Package::container()->get( CheckoutFields::class );

		$errors = $controller->validate_fields_for_location(
			array(
				'crkit/id-type'   => '03',
				'crkit/id-number' => '1234567890',
			),
			'contact'
		);
		$this->assertSame( array( 'crkit_id_number_invalid' ), $errors->get_error_codes() );

		$errors = $controller->validate_fields_for_location(
			array(
				'crkit/id-type'   => '',
				'crkit/id-number' => '112340567',
			),
			'contact'
		);
		$this->assertSame( array( 'crkit_id_type_missing' ), $errors->get_error_codes() );

		$errors = $controller->validate_fields_for_location(
			array(
				'crkit/id-type'   => '03',
				'crkit/id-number' => '155812345678',
			),
			'contact'
		);
		$this->assertFalse( $errors->has_errors() );
	}

	public function test_classic_fields_follow_the_requirement(): void {
		$this->set_requirement( IdentificationSettings::INVOICE );
		$fields = ( new ClassicCheckout( $this->settings ) )->add_fields( array( 'billing' => array() ) );
		$this->assertSame( array( ClassicCheckout::INVOICE_KEY, ClassicCheckout::TYPE_KEY, ClassicCheckout::NUMBER_KEY ), array_keys( $fields['billing'] ) );
		$this->assertFalse( $fields['billing'][ ClassicCheckout::NUMBER_KEY ]['required'] );

		$this->set_requirement( IdentificationSettings::REQUIRED );
		$fields = ( new ClassicCheckout( $this->settings ) )->add_fields( array( 'billing' => array() ) );
		$this->assertSame( array( ClassicCheckout::TYPE_KEY, ClassicCheckout::NUMBER_KEY ), array_keys( $fields['billing'] ) );
		$this->assertTrue( $fields['billing'][ ClassicCheckout::TYPE_KEY ]['required'] );
	}

	public function test_classic_checkout_requires_the_id_only_for_an_electronic_invoice(): void {
		$this->set_requirement( IdentificationSettings::INVOICE );
		$classic = new ClassicCheckout( $this->settings );

		$errors = new WP_Error();
		$classic->validate_checkout( array( ClassicCheckout::INVOICE_KEY => 0 ), $errors );
		$this->assertFalse( $errors->has_errors() );

		$errors = new WP_Error();
		$classic->validate_checkout( array( ClassicCheckout::INVOICE_KEY => 1 ), $errors );
		$this->assertSame( array( 'crkit_id_type_required', 'crkit_id_number_required' ), $errors->get_error_codes() );
		$this->assertSame( array( 'id' => ClassicCheckout::NUMBER_KEY ), $errors->get_error_data( 'crkit_id_number_required' ) );

		$errors = new WP_Error();
		$classic->validate_checkout(
			array(
				ClassicCheckout::INVOICE_KEY => 1,
				ClassicCheckout::TYPE_KEY    => '01',
				ClassicCheckout::NUMBER_KEY  => '0-1234-0567',
			),
			$errors
		);
		$this->assertSame( array( 'crkit_id_number_invalid' ), $errors->get_error_codes() );
	}

	public function test_classic_checkout_with_required_ids_leaves_empty_fields_to_woocommerce(): void {
		$this->set_requirement( IdentificationSettings::REQUIRED );
		$errors = new WP_Error();

		( new ClassicCheckout( $this->settings ) )->validate_checkout( array( ClassicCheckout::NUMBER_KEY => '112340567' ), $errors );

		$this->assertFalse( $errors->has_errors(), 'WooCommerce reports the empty required type.' );
	}

	public function test_classic_checkout_saves_the_id_on_the_order_and_the_customer(): void {
		$this->set_requirement( IdentificationSettings::INVOICE );
		$classic = new ClassicCheckout( $this->settings );
		$data    = array(
			ClassicCheckout::INVOICE_KEY => 1,
			ClassicCheckout::TYPE_KEY    => '02',
			ClassicCheckout::NUMBER_KEY  => '3-101-123456',
		);

		$order = wc_create_order();
		$classic->save_to_order( $order, $data );
		$order->save();
		$customer = new WC_Customer( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$classic->save_to_customer( $customer, $data );
		$customer->save();

		foreach ( array( wc_get_order( $order->get_id() ), new WC_Customer( $customer->get_id() ) ) as $saved ) {
			$this->assertSame( '02', $saved->get_meta( '_wc_other/crkit/id-type' ) );
			$this->assertSame( '3101123456', $saved->get_meta( '_wc_other/crkit/id-number' ) );
			$this->assertSame( '1', $saved->get_meta( '_wc_other/crkit/electronic-invoice' ) );
		}
	}

	public function test_classic_checkout_prefills_the_saved_id(): void {
		$user = self::factory()->user->create( array( 'role' => 'customer' ) );
		update_user_meta( $user, '_wc_other/crkit/id-type', '03' );
		update_user_meta( $user, '_wc_other/crkit/id-number', '155812345678' );
		wp_set_current_user( $user );
		WC()->customer = new WC_Customer( $user );

		$classic = new ClassicCheckout( $this->settings );

		$this->assertSame( '03', $classic->get_saved_value( null, ClassicCheckout::TYPE_KEY ) );
		$this->assertSame( '155812345678', $classic->get_saved_value( null, ClassicCheckout::NUMBER_KEY ) );
		$this->assertNull( $classic->get_saved_value( null, ClassicCheckout::INVOICE_KEY ) );
		$this->assertSame( 'posted', $classic->get_saved_value( 'posted', ClassicCheckout::NUMBER_KEY ) );
		$this->assertNull( $classic->get_saved_value( null, 'billing_first_name' ) );
	}

	public function test_classic_invoice_checkbox_has_no_optional_marker(): void {
		$classic = new ClassicCheckout( $this->settings );
		$html    = '<label for="crkit_electronic_invoice">I need an electronic invoice&nbsp;<span class="optional">(optional)</span></label>';

		$this->assertSame( '<label for="crkit_electronic_invoice">I need an electronic invoice</label>', $classic->remove_optional_marker( $html, ClassicCheckout::INVOICE_KEY ) );
		$this->assertSame( '<label for="crkit_electronic_invoice">I need an electronic invoice</label>', $classic->remove_optional_marker( $html, '_wc_other/crkit/electronic-invoice' ) );
		$this->assertSame( $html, $classic->remove_optional_marker( $html, ClassicCheckout::NUMBER_KEY ) );
	}

	public function test_the_id_is_part_of_the_formatted_billing_address(): void {
		$order = $this->order_with_id( '01', '112340567' );

		$this->assertStringEndsWith( '<br/>Cédula física: 112340567', $order->get_formatted_billing_address() );
		$this->assertStringNotContainsString( 'Cédula', wc_create_order()->get_formatted_billing_address() );
	}

	public function test_store_emails_say_when_an_electronic_invoice_was_requested(): void {
		$display = new OrderDisplay();
		$order   = $this->order_with_id( '01', '112340567', true );

		$this->assertArrayHasKey( 'crkit_electronic_invoice', $display->add_email_fields( array(), true, $order ) );
		$this->assertSame( array(), $display->add_email_fields( array(), false, $order ) );
		$this->assertSame( array(), $display->add_email_fields( array(), true, $this->order_with_id() ) );
	}

	public function test_admin_replaces_the_block_fields_and_validates_edits(): void {
		$this->set_requirement( IdentificationSettings::INVOICE );
		$admin  = new AdminFields( $this->settings );
		$order  = $this->order_with_id( '01', '112340567', true );
		$fields = $admin->filter_billing_fields(
			array(
				'email'           => array(),
				'crkit/id-number' => array( 'id' => '_wc_other/crkit/id-number' ),
				0                 => array( 'id' => '_wc_other/crkit/id-type' ),
			),
			$order,
			'edit'
		);

		$this->assertSame( array( 'email', 'crkit_id_type', 'crkit_id_number', 'crkit_electronic_invoice' ), array_keys( $fields ) );
		$this->assertSame( '112340567', $fields['crkit_id_number']['value'] );
		$this->assertSame( '1', $fields['crkit_electronic_invoice']['value'] );

		$admin->update_type( AdminFields::TYPE_ID, '03', $order );
		$admin->update_number( AdminFields::NUMBER_ID, '1234', $order );
		$this->assertSame( '112340567', $order->get_meta( '_wc_other/crkit/id-number' ), 'An invalid number is not saved.' );

		$admin->update_number( AdminFields::NUMBER_ID, '1558-1234-5678', $order );
		$admin->update_invoice( AdminFields::INVOICE_ID, '', $order );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		$this->assertSame( '03', $order->get_meta( '_wc_other/crkit/id-type' ) );
		$this->assertSame( '155812345678', $order->get_meta( '_wc_other/crkit/id-number' ) );
		$this->assertSame( '', $order->get_meta( '_wc_other/crkit/electronic-invoice' ) );
	}

	public function test_public_function_filter_and_checkout_action(): void {
		$order = $this->order_with_id( '02', '3101A00001', true );

		$this->assertSame(
			array(
				'type'               => '02',
				'type_label'         => 'Cédula jurídica',
				'number'             => '3101A00001',
				'electronic_invoice' => true,
			),
			crkit_get_order_identification( $order->get_id() )
		);
		$this->assertNull( crkit_get_order_identification( wc_create_order() ) );
		$this->assertNull( crkit_get_order_identification( 0 ) );

		$filter = static fn( $data ) => array_merge( $data, array( 'number' => 'X' ) );
		add_filter( 'crkit_order_identification', $filter );
		$this->assertSame( 'X', crkit_get_order_identification( $order )['number'] );
		remove_filter( 'crkit_order_identification', $filter );

		$received = array();
		add_action(
			'crkit_order_identification_saved',
			static function ( $data, $saved_order ) use ( &$received ): void {
				$received[] = array( $data['number'], $saved_order->get_id() );
			},
			10,
			2
		);
		( new ClassicCheckout( $this->settings ) )->order_processed( $order->get_id(), array(), $order );
		( new BlocksCheckout( $this->settings ) )->order_processed( $order );
		( new BlocksCheckout( $this->settings ) )->order_processed( wc_create_order() );

		$this->assertSame( array( array( '3101A00001', $order->get_id() ), array( '3101A00001', $order->get_id() ) ), $received );
	}

	public function test_personal_data_export_and_erasure(): void {
		$privacy  = new Privacy();
		$order    = $this->order_with_id( '01', '112340567', true );
		$customer = new WC_Customer( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		IdentificationStore::save( $customer, '03', '155812345678', false );
		$customer->save();

		$this->assertSame( array( 'Cédula física', '112340567', 'Requested' ), array_column( $privacy->export_order( array(), $order ), 'value' ) );
		$this->assertSame( array( 'DIMEX', '155812345678' ), array_column( $privacy->export_customer( array(), $customer ), 'value' ) );

		$response = $privacy->erase_customer( array( 'messages' => array() ), $customer );
		$this->assertTrue( $response['items_removed'] );
		$this->assertNull( IdentificationStore::get( new WC_Customer( $customer->get_id() ) ) );

		$meta = $privacy->order_meta_to_remove( array() );
		$this->assertArrayHasKey( '_wc_other/crkit/id-number', $meta );
		$this->assertSame( '', $privacy->delete_order_meta_value( '[deleted]', '_wc_other/crkit/id-number' ) );
		$this->assertSame( '[deleted]', $privacy->delete_order_meta_value( '[deleted]', 'Transaction ID' ) );
	}
}
