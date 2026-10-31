<?php
/**
 * Integration tests: receipt upload, storage and download permissions.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Modules\SinpeMovil\OrderData;
use CRKit\Modules\SinpeMovil\ReceiptController;
use CRKit\Modules\SinpeMovil\ReceiptStorage;
use WC_Order;
use WPDieException;

/**
 * Tests who can upload, which files are accepted and who can view them.
 *
 * @coversNothing
 */
final class SinpeReceiptTest extends SinpeTestCase {

	/**
	 * Controller without the is_uploaded_file() check (test files are not HTTP uploads).
	 *
	 * @var ReceiptController
	 */
	private ReceiptController $controller;

	/**
	 * Storage.
	 *
	 * @var ReceiptStorage
	 */
	private ReceiptStorage $storage;

	public function set_up(): void {
		parent::set_up();
		$this->set_rate();
		$this->storage    = new ReceiptStorage();
		$this->controller = new ReceiptController( $this->storage, false );
	}

	public function test_owner_uploads_a_png_receipt(): void {
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order    = $this->place_order( $customer );
		wp_set_current_user( $customer );
		reset_phpmailer_instance();

		$result = $this->upload( $order, $this->png_file(), 'comprobante.png' );
		$order  = wc_get_order( $order->get_id() );
		$stored = OrderData::get_receipt( $order );

		$this->assertSame( 'uploaded', $result['code'] );
		$this->assertSame( 'image/png', $stored['type'] );
		$this->assertSame( 'comprobante.png', $stored['name'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}\.png$/', $stored['file'], 'Random, unguessable name.' );
		$this->assertFileExists( $this->storage->get_dir() . '/' . $stored['file'] );
		$this->assertStringNotContainsString( '/' . gmdate( 'Y' ) . '/', $this->storage->get_dir(), 'Outside the dated Media Library folders.' );
		$this->assertSame( array(), get_posts( array( 'post_type' => 'attachment' ) ), 'Not in the Media Library.' );

		$subjects = wp_list_pluck( tests_retrieve_phpmailer_instance()->mock_sent, 'subject' );
		$this->assertContains( '[Test Blog]: SINPE Móvil receipt for order #' . $order->get_order_number(), $subjects );
	}

	public function test_receipt_folder_is_protected(): void {
		$this->assertTrue( $this->storage->ensure_dir() );

		$this->assertFileExists( $this->storage->get_dir() . '/index.php' );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $this->storage->get_dir() . '/.htaccess' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test assertion.
		$this->assertNull( $this->storage->get_path( '.htaccess' ) );
		$this->assertNull( $this->storage->get_path( '../../wp-config.php' ) );
	}

	public function test_guest_with_the_order_key_uploads_a_pdf(): void {
		$order = $this->place_order();
		wp_set_current_user( 0 );

		$this->assertSame( 'uploaded', $this->upload( $order, $this->pdf_file(), 'comprobante.pdf' )['code'] );
		$this->assertSame( 'application/pdf', OrderData::get_receipt( wc_get_order( $order->get_id() ) )['type'] );
	}

	public function test_guest_without_the_order_key_is_rejected(): void {
		$order = $this->place_order();
		wp_set_current_user( 0 );

		$this->assertSame( 'not_allowed', $this->upload( $order, $this->png_file(), 'a.png', '' )['code'] );
		$this->assertSame( 'not_allowed', $this->upload( $order, $this->png_file(), 'a.png', 'wc_order_wrongkey' )['code'] );
		$this->assertNull( OrderData::get_receipt( wc_get_order( $order->get_id() ) ) );
	}

	public function test_another_customer_cannot_upload_without_the_key(): void {
		$order = $this->place_order( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );

		$this->assertSame( 'not_allowed', $this->upload( $order, $this->png_file(), 'a.png', '' )['code'] );
	}

	public function test_invalid_nonce_is_rejected(): void {
		$order = $this->place_order();

		$this->assertSame( 'expired', $this->upload( $order, $this->png_file(), 'a.png', null, 'bad-nonce' )['code'] );
	}

	/**
	 * Files that must be rejected.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function provide_invalid_files(): array {
		return array(
			'text renamed to jpg' => array( 'This is not an image.', 'receipt.jpg', 'invalid_type' ),
			'php renamed to png'  => array( '<?php echo "pwned";', 'receipt.png', 'invalid_type' ),
			'php file'            => array( '<?php echo "pwned";', 'receipt.php', 'invalid_type' ),
			'svg'                 => array( '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'receipt.svg', 'invalid_type' ),
			'pdf renamed to png'  => array( "%PDF-1.4\n%%EOF\n", 'receipt.png', 'invalid_type' ),
			'text renamed to pdf' => array( 'Just some text, not a PDF.', 'receipt.pdf', 'invalid_type' ),
			'html renamed to pdf' => array( '<html><script>alert(1)</script></html>', 'receipt.pdf', 'invalid_type' ),
			'empty file'          => array( '', 'receipt.png', 'no_file' ),
		);
	}

	/**
	 * Wrong types are rejected by their real content, not by their name.
	 *
	 * @dataProvider provide_invalid_files
	 *
	 * @param string $content Content.
	 * @param string $name    File name.
	 * @param string $code    Expected result.
	 */
	public function test_invalid_files_are_rejected( string $content, string $name, string $code ): void {
		$order = $this->place_order();

		$this->assertSame( $code, $this->upload( $order, $this->temp_file( $content ), $name )['code'] );
		$this->assertNull( OrderData::get_receipt( wc_get_order( $order->get_id() ) ) );
	}

	public function test_files_over_the_limit_are_rejected(): void {
		$this->configure_gateway( array( 'max_upload_mb' => '1' ) );
		$order = $this->place_order();
		$big   = $this->temp_file( (string) file_get_contents( $this->png_file() ) . str_repeat( "\0", MB_IN_BYTES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.

		$this->assertSame( 'too_large', $this->upload( $order, $big, 'big.png' )['code'] );
	}

	public function test_missing_file_is_reported(): void {
		$order = $this->place_order();

		$this->assertSame( 'no_file', $this->upload( $order, null, '' )['code'] );
	}

	public function test_orders_that_are_not_on_hold_do_not_accept_receipts(): void {
		$order = $this->place_order();
		$order->update_status( 'processing' );

		$this->assertSame( 'closed', $this->upload( $order, $this->png_file(), 'a.png' )['code'] );
	}

	public function test_a_new_upload_replaces_and_deletes_the_previous_file(): void {
		$order = $this->place_order();
		$this->upload( $order, $this->png_file(), 'first.png' );
		$first = OrderData::get_receipt( wc_get_order( $order->get_id() ) )['file'];

		$this->upload( $order, $this->pdf_file(), 'second.pdf' );
		$second = OrderData::get_receipt( wc_get_order( $order->get_id() ) )['file'];

		$this->assertNotSame( $first, $second );
		$this->assertNull( $this->storage->get_path( $first ) );
		$this->assertNotNull( $this->storage->get_path( $second ) );
	}

	public function test_view_permissions(): void {
		$owner = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order = $this->place_order( $owner );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertTrue( ReceiptController::can_view( $order ), 'Shop manager.' );

		wp_set_current_user( $owner );
		$this->assertTrue( ReceiptController::can_view( $order ), 'Owner.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertFalse( ReceiptController::can_view( $order ), 'Another customer.' );

		wp_set_current_user( 0 );
		$this->assertFalse( ReceiptController::can_view( $order ), 'Guest.' );
	}

	public function test_download_requires_a_valid_nonce(): void {
		$order = $this->place_order();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_GET = array(
			'order_id' => (string) $order->get_id(),
			'_wpnonce' => 'invalid',
		);

		$this->expectException( WPDieException::class );
		$this->controller->handle_download();
	}

	public function test_download_is_denied_to_other_customers(): void {
		$order = $this->place_order( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->upload( $order, $this->png_file(), 'a.png' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$_GET = array(
			'order_id' => (string) $order->get_id(),
			'_wpnonce' => wp_create_nonce( ReceiptController::DOWNLOAD_ACTION . '_' . $order->get_id() ),
		);

		try {
			$this->controller->handle_download();
			$this->fail( 'Expected wp_die().' );
		} catch ( WPDieException $exception ) {
			$this->assertStringContainsString( 'not allowed', $exception->getMessage() );
		}
	}

	/**
	 * Simulates the upload form submission.
	 *
	 * @param WC_Order    $order     Order.
	 * @param string|null $path      File path, or null for no file.
	 * @param string      $name      Original name.
	 * @param string|null $order_key Order key; null for the right one.
	 * @param string|null $nonce     Nonce; null for a valid one.
	 * @return array{code: string, order: WC_Order|null, context: string}
	 */
	private function upload( WC_Order $order, ?string $path, string $name, ?string $order_key = null, ?string $nonce = null ): array {
		$_POST  = array(
			'action'                       => ReceiptController::UPLOAD_ACTION,
			'order_id'                     => (string) $order->get_id(),
			'order_key'                    => $order_key ?? $order->get_order_key(),
			'context'                      => 'thankyou',
			ReceiptController::NONCE_FIELD => $nonce ?? wp_create_nonce( ReceiptController::upload_nonce_action( $order->get_id() ) ),
		);
		$_FILES = null === $path ? array() : array( ReceiptController::FILE_FIELD => $this->file_entry( $path, $name ) );

		// wp_handle_sideload() moves the file; keep a copy so the test can reuse paths.
		if ( null !== $path ) {
			$copy = $this->temp_file( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
			$_FILES[ ReceiptController::FILE_FIELD ]['tmp_name'] = $copy;
		}

		return $this->controller->process_upload();
	}
}
