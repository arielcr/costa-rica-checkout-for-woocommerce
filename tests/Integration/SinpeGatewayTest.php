<?php
/**
 * Integration tests: SINPE Móvil gateway settings, availability and process_payment().
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Modules\SinpeMovil\Gateway;
use CRKit\Modules\SinpeMovil\OrderData;
use CRKit\Modules\SinpeMovil\ReceiptController;

/**
 * Tests the gateway in CRC and USD stores.
 *
 * @coversNothing
 */
final class SinpeGatewayTest extends SinpeTestCase {

	public function test_gateway_is_registered_without_refund_support(): void {
		$gateways = WC()->payment_gateways()->payment_gateways();

		$this->assertInstanceOf( Gateway::class, $gateways[ Gateway::ID ] );
		$this->assertFalse( $gateways[ Gateway::ID ]->supports( 'refunds' ) );
	}

	public function test_phone_setting_is_normalized_and_invalid_numbers_are_rejected(): void {
		$gateway = $this->configure_gateway( array( 'phone' => '88887777' ) );
		$field   = $gateway->get_field_key( 'phone' );

		$_POST[ $field ] = '+506 6000-1234';
		$gateway->process_admin_options();
		$this->assertSame( '60001234', $gateway->get_option( 'phone' ) );

		$_POST[ $field ] = '1234';
		$gateway->process_admin_options();
		$this->assertSame( '60001234', $gateway->get_option( 'phone' ), 'Invalid numbers keep the previous value.' );
		$this->assertNotEmpty( $gateway->get_errors() );
	}

	public function test_not_available_without_a_phone_number(): void {
		update_option( 'woocommerce_currency', 'CRC' );

		$this->assertFalse( $this->configure_gateway( array( 'phone' => '' ) )->is_available() );
	}

	public function test_colon_store_is_available_without_an_exchange_rate(): void {
		update_option( 'woocommerce_currency', 'CRC' );
		$this->fill_cart( 25000.0 );

		$this->assertTrue( $this->configure_gateway( array() )->is_available() );
	}

	public function test_dollar_store_needs_a_current_rate(): void {
		$this->fill_cart( 10.0 );
		$gateway = $this->configure_gateway( array() );

		$this->assertFalse( $gateway->is_available(), 'No rate.' );

		$this->set_rate( 456.04, 449.7, 72 );
		$this->assertFalse( $gateway->is_available(), 'Outdated rate.' );

		$this->set_rate( 456.04, 449.7, 1 );
		$this->assertTrue( $gateway->is_available(), 'Current rate.' );
	}

	public function test_outdated_rate_does_not_affect_colon_stores(): void {
		update_option( 'woocommerce_currency', 'CRC' );
		$this->set_rate( 456.04, 449.7, 500 );
		$this->fill_cart( 25000.0 );

		$this->assertTrue( $this->configure_gateway( array() )->is_available() );
	}

	public function test_maximum_amount_in_colones_hides_the_method(): void {
		$this->set_rate();
		$this->fill_cart( 250.0 ); // 250 × 456.04 = ₡114,010.
		$gateway = $this->configure_gateway( array( 'max_amount_crc' => '100000' ) );

		$this->assertFalse( $gateway->is_available() );

		$gateway = $this->configure_gateway( array( 'max_amount_crc' => '120000' ) );
		$this->assertTrue( $gateway->is_available() );
	}

	public function test_process_payment_freezes_the_amount_and_puts_the_order_on_hold(): void {
		$this->set_rate();
		$product = $this->create_product( 10.01, 5 );
		$order   = $this->create_order( $product );
		$this->fill_cart( 10.0 );

		$result = ReceiptController::get_gateway()->process_payment( $order->get_id() );
		$order  = wc_get_order( $order->get_id() );

		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( $order->get_checkout_order_received_url(), $result['redirect'] );
		$this->assertTrue( $order->has_status( 'on-hold' ) );

		$payment = OrderData::get_payment( $order );
		$this->assertSame( 4565, $payment['amount_crc'] ); // 10.01 × 456.04 = 4564.96 → 4565.
		$this->assertSame( 456.04, $payment['rate'] );
		$this->assertSame( 'sell', $payment['rate_type'] );
		$this->assertSame( 'hacienda', $payment['rate_source'] );
		$this->assertSame( '2026-10-31', $payment['rate_date'] );
		$this->assertSame( (string) $order->get_order_number(), $payment['reference'] );
		$this->assertSame( '88887777', $payment['phone'] );
		$this->assertSame( 'Tienda Demo S.A.', $payment['holder'] );

		$this->assertSame( 4, wc_get_product( $product->get_id() )->get_stock_quantity(), 'Stock reduced.' );
		$this->assertTrue( WC()->cart->is_empty(), 'Cart emptied.' );
		$this->assertStringContainsString( 'Awaiting SINPE Móvil payment of', $this->all_notes( $order ) );
	}

	public function test_frozen_amount_does_not_follow_later_rate_changes(): void {
		$this->set_rate( 456.04 );
		$order = $this->place_order();

		$this->set_rate( 500.0 );

		$this->assertSame( 4565, OrderData::get_payment( wc_get_order( $order->get_id() ) )['amount_crc'] );
	}

	public function test_process_payment_in_a_colon_store_has_no_rate(): void {
		update_option( 'woocommerce_currency', 'CRC' );
		$order = $this->create_order( $this->create_product( 25999.5 ) );

		ReceiptController::get_gateway()->process_payment( $order->get_id() );
		$payment = OrderData::get_payment( wc_get_order( $order->get_id() ) );

		$this->assertSame( 26000, $payment['amount_crc'] );
		$this->assertNull( $payment['rate'] );
	}

	public function test_surcharge_and_buy_rate_are_applied(): void {
		$this->set_rate( 456.04, 449.7 );
		$this->configure_gateway(
			array(
				'rate_type' => 'buy',
				'surcharge' => '2.5',
			)
		);
		$order = $this->create_order( $this->create_product( 100.0 ) );

		ReceiptController::get_gateway()->process_payment( $order->get_id() );
		$payment = OrderData::get_payment( wc_get_order( $order->get_id() ) );

		$this->assertSame( 46095, $payment['amount_crc'] ); // 100 × 449.7 × 1.025 = 46094.25.
		$this->assertSame( 449.7, $payment['rate'] );
		$this->assertSame( 2.5, $payment['surcharge'] );
	}

	public function test_process_payment_fails_without_a_rate_and_leaves_the_order_pending(): void {
		$order = $this->create_order();

		$result = ReceiptController::get_gateway()->process_payment( $order->get_id() );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertTrue( wc_get_order( $order->get_id() )->has_status( 'pending' ) );
		$this->assertNull( OrderData::get_payment( wc_get_order( $order->get_id() ) ) );
		wc_clear_notices();
	}

	public function test_on_hold_email_contains_the_payment_steps(): void {
		$this->set_rate();
		reset_phpmailer_instance();

		$order  = $this->place_order();
		$mailer = tests_retrieve_phpmailer_instance();
		$bodies = implode( "\n", array_map( static fn( $mail ) => $mail['body'], $mailer->mock_sent ) );

		$this->assertStringContainsString( 'How to pay with SINPE Móvil', $bodies );
		$this->assertStringContainsString( '8888-7777', $bodies );
		$this->assertStringContainsString( '4,565', $bodies );
		$this->assertStringContainsString( $order->get_order_key(), $bodies, 'Link to upload the receipt.' );
	}

	public function test_settings_warn_dollar_stores_without_the_exchange_rate_module(): void {
		$gateway = $this->configure_gateway( array() );

		update_option( 'crkit_modules', array( 'exchange_rate' => 'no' ) );
		ob_start();
		$gateway->admin_options();
		$this->assertStringContainsString( 'Exchange rate module is disabled', (string) ob_get_clean() );

		update_option( 'woocommerce_currency', 'CRC' );
		ob_start();
		$gateway->admin_options();
		$this->assertStringNotContainsString( 'Exchange rate module is disabled', (string) ob_get_clean() );
	}

	public function test_order_received_page_shows_steps_and_receipt_form(): void {
		$this->set_rate();
		$order = $this->place_order();

		ob_start();
		do_action( 'woocommerce_thankyou_' . Gateway::ID, $order->get_id() );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'How to pay with SINPE Móvil', $html );
		$this->assertStringContainsString( '8888-7777', $html );
		$this->assertStringContainsString( 'Tienda Demo S.A.', $html );
		$this->assertStringContainsString( 'enctype="multipart/form-data"', $html );
		$this->assertStringContainsString( 'name="order_key" value="' . $order->get_order_key() . '"', $html );
	}

	/**
	 * Fills the cart so its total is $total in the store currency.
	 *
	 * @param float $total Total.
	 */
	private function fill_cart( float $total ): void {
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->create_product( $total, 10 )->get_id(), 1 );
		WC()->cart->calculate_totals();
	}

	private function all_notes( \WC_Order $order ): string {
		return implode( "\n", wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' ) );
	}
}
