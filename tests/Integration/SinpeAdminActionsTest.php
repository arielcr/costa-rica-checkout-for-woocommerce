<?php
/**
 * Integration tests: confirm and reject payments, metabox, privacy and uninstall.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Modules\SinpeMovil\AdminMetabox;
use CRKit\Modules\SinpeMovil\OrderData;
use CRKit\Modules\SinpeMovil\Privacy;
use CRKit\Modules\SinpeMovil\ReceiptController;
use CRKit\Modules\SinpeMovil\ReceiptStorage;
use WPDieException;

/**
 * Tests the store-side actions on SINPE Móvil orders.
 *
 * @coversNothing
 */
final class SinpeAdminActionsTest extends SinpeTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->set_rate();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function test_confirm_completes_the_payment(): void {
		$order = $this->place_order();
		$fired = did_action( 'crkit_sinpe_payment_confirmed' );

		$this->assertTrue( ( new AdminMetabox() )->confirm( $order ) );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->has_status( 'processing' ) );
		$this->assertNotNull( $order->get_date_paid() );
		$this->assertSame( $fired + 1, did_action( 'crkit_sinpe_payment_confirmed' ) );
	}

	public function test_reject_uses_the_configured_status(): void {
		$order = $this->place_order();
		$this->assertTrue( ( new AdminMetabox() )->reject( $order ) );
		$this->assertTrue( wc_get_order( $order->get_id() )->has_status( 'failed' ) );

		$this->configure_gateway( array( 'reject_status' => 'cancelled' ) );
		$order = $this->place_order();
		$this->assertTrue( ( new AdminMetabox() )->reject( $order ) );
		$this->assertTrue( wc_get_order( $order->get_id() )->has_status( 'cancelled' ) );
	}

	public function test_only_on_hold_orders_can_be_confirmed_or_rejected(): void {
		$order = $this->place_order();
		$order->update_status( 'processing' );

		$this->assertFalse( ( new AdminMetabox() )->confirm( $order ) );
		$this->assertFalse( ( new AdminMetabox() )->reject( $order ) );
		$this->assertTrue( wc_get_order( $order->get_id() )->has_status( 'processing' ) );
	}

	public function test_confirm_endpoint_requires_a_valid_nonce(): void {
		$order    = $this->place_order();
		$_GET     = array(
			'order_id' => (string) $order->get_id(),
			'_wpnonce' => 'invalid',
		);
		$_REQUEST = $_GET;

		$this->expectException( WPDieException::class );
		( new AdminMetabox() )->handle_confirm();
	}

	public function test_confirm_endpoint_requires_the_capability(): void {
		$order = $this->place_order();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$_GET     = array(
			'order_id' => (string) $order->get_id(),
			'_wpnonce' => wp_create_nonce( AdminMetabox::CONFIRM_ACTION . '_' . $order->get_id() ),
		);
		$_REQUEST = $_GET;

		try {
			( new AdminMetabox() )->handle_confirm();
			$this->fail( 'Expected wp_die().' );
		} catch ( WPDieException $exception ) {
			$this->assertTrue( wc_get_order( $order->get_id() )->has_status( 'on-hold' ) );
		}
	}

	public function test_meta_box_is_added_to_sinpe_orders_only(): void {
		global $wp_meta_boxes;
		$screen = wc_get_page_screen_id( 'shop-order' );

		$other = wc_create_order();
		$other->set_payment_method( 'bacs' );
		$other->save();
		( new AdminMetabox() )->add_meta_box( $screen, $other );
		$this->assertFalse( isset( $wp_meta_boxes[ $screen ]['side']['high']['crkit-sinpe-movil'] ) );

		( new AdminMetabox() )->add_meta_box( $screen, $this->place_order() );
		$this->assertTrue( isset( $wp_meta_boxes[ $screen ]['side']['high']['crkit-sinpe-movil'] ) );
	}

	public function test_meta_box_shows_the_frozen_data_and_actions(): void {
		$order = $this->place_order();

		ob_start();
		( new AdminMetabox() )->render( $order );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '4,565', $html );
		$this->assertStringContainsString( '456.04', $html );
		$this->assertStringContainsString( 'No receipt uploaded yet.', $html );
		$this->assertStringContainsString( 'action=' . AdminMetabox::CONFIRM_ACTION, $html );
		$this->assertStringContainsString( '_wpnonce=', $html );
	}

	public function test_privacy_exporter_and_eraser(): void {
		$order   = $this->place_order();
		$storage = new ReceiptStorage();
		( new ReceiptController( $storage, false ) )->attach( $order, $this->store_png( $storage ) );
		$privacy = new Privacy( $storage );
		$file    = OrderData::get_receipt( wc_get_order( $order->get_id() ) )['file'];

		$export = $privacy->export( 'cliente@example.com' );
		$this->assertCount( 1, $export['data'] );
		$this->assertContains( 'receipt.png', wp_list_pluck( $export['data'][0]['data'], 'value' ) );

		update_option( 'woocommerce_erasure_request_removes_order_data', 'no' );
		$this->assertTrue( $privacy->erase( 'cliente@example.com' )['items_retained'] );
		$this->assertNotNull( $storage->get_path( $file ) );

		update_option( 'woocommerce_erasure_request_removes_order_data', 'yes' );
		$this->assertTrue( $privacy->erase( 'cliente@example.com' )['items_removed'] );
		$this->assertNull( $storage->get_path( $file ) );
		$this->assertNull( OrderData::get_receipt( wc_get_order( $order->get_id() ) ) );
	}

	public function test_uninstall_deletes_data_only_when_asked(): void {
		$order   = $this->place_order();
		$storage = new ReceiptStorage();
		( new ReceiptController( $storage, false ) )->attach( $order, $this->store_png( $storage ) );

		update_option( 'crkit_delete_data_on_uninstall', 'yes' );
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'costa-rica-kit-for-woocommerce/costa-rica-kit-for-woocommerce.php' );
		}
		include CRKIT_PLUGIN_DIR . 'uninstall.php';
		wp_cache_flush();

		$order = wc_get_order( $order->get_id() );
		$this->assertDirectoryDoesNotExist( $storage->get_dir() );
		$this->assertNull( OrderData::get_payment( $order ) );
		$this->assertFalse( get_option( 'woocommerce_crkit_sinpe_movil_settings' ) );
	}

	/**
	 * Stores a PNG directly.
	 *
	 * @param ReceiptStorage $storage Storage.
	 * @return array{file: string, name: string, type: string, size: int, uploaded_at: int}
	 */
	private function store_png( ReceiptStorage $storage ): array {
		$stored = $storage->store( $this->file_entry( $this->png_file(), 'receipt.png' ), MB_IN_BYTES, false );
		$this->assertIsArray( $stored );

		return $stored;
	}
}
