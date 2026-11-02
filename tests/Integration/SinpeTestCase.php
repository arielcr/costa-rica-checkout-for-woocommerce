<?php
/**
 * Base class for SINPE Móvil integration tests.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\RateRepository;
use CRKit\Modules\SinpeMovil\Gateway;
use CRKit\Modules\SinpeMovil\ReceiptController;
use WC_Order;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Helpers: gateway settings, exchange rate, products, orders and test files.
 */
abstract class SinpeTestCase extends WP_UnitTestCase {

	/**
	 * Temporary files created by the test.
	 *
	 * @var array<int, string>
	 */
	private array $temp_files = array();

	public function set_up(): void {
		parent::set_up();
		update_option( 'woocommerce_currency', 'USD' );
		delete_option( RateRepository::RATE_OPTION );
		delete_option( 'crkit_exchange_rate_settings' );
		$this->configure_gateway( array() );
	}

	public function tear_down(): void {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
			}
		}
		$_POST  = array();
		$_FILES = array();
		$_GET   = array();
		parent::tear_down();
	}

	/**
	 * The registered gateway, with fresh settings.
	 *
	 * @param array<string, string> $settings Settings to override.
	 * @return Gateway
	 */
	protected function configure_gateway( array $settings ): Gateway {
		$defaults = array(
			'enabled'         => 'yes',
			'title'           => 'SINPE Móvil',
			'description'     => 'Pay with SINPE Móvil.',
			'instructions'    => '',
			'phone'           => '88887777',
			'holder'          => 'Tienda Demo S.A.',
			'require_receipt' => 'yes',
			'max_upload_mb'   => '5',
			'max_amount_crc'  => '',
			'rate_type'       => 'sell',
			'surcharge'       => '',
			'reject_status'   => 'failed',
		);

		update_option( 'woocommerce_' . Gateway::ID . '_settings', array_merge( $defaults, $settings ) );

		$gateway = ReceiptController::get_gateway();
		$this->assertInstanceOf( Gateway::class, $gateway );
		$gateway->init_settings();
		$gateway->enabled = $gateway->get_option( 'enabled' );

		return $gateway;
	}

	/**
	 * Caches a rate fetched `$age_hours` ago.
	 *
	 * @param float $sell      Sell rate.
	 * @param float $buy       Buy rate.
	 * @param int   $age_hours Age in hours.
	 */
	protected function set_rate( float $sell = 456.04, float $buy = 449.7, int $age_hours = 1 ): void {
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( $buy, $sell, '2026-10-31', 'hacienda', time() - $age_hours * HOUR_IN_SECONDS ) )->to_array(), false );
	}

	/**
	 * Simple product.
	 *
	 * @param float $price Price.
	 * @param int   $stock Stock quantity.
	 * @return WC_Product_Simple
	 */
	protected function create_product( float $price = 10.01, int $stock = 5 ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_name( 'Café de Tarrazú' );
		$product->set_regular_price( (string) $price );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $stock );
		$product->save();

		return $product;
	}

	/**
	 * Pending order paid with SINPE Móvil.
	 *
	 * @param WC_Product_Simple|null $product     Product (one unit).
	 * @param int                    $customer_id Customer id, 0 for a guest.
	 * @return WC_Order
	 */
	protected function create_order( ?WC_Product_Simple $product = null, int $customer_id = 0 ): WC_Order {
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$order->add_product( $product ?? $this->create_product(), 1 );
		$order->set_billing_email( 'cliente@example.com' );
		$order->set_billing_first_name( 'Ana' );
		$order->set_billing_last_name( 'Mora' );
		$order->set_payment_method( Gateway::ID );
		$order->set_currency( get_woocommerce_currency() );
		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * Order placed with SINPE Móvil (on hold, amount frozen).
	 *
	 * @param int $customer_id Customer id, 0 for a guest.
	 * @return WC_Order
	 */
	protected function place_order( int $customer_id = 0 ): WC_Order {
		$order  = $this->create_order( null, $customer_id );
		$result = ReceiptController::get_gateway()->process_payment( $order->get_id() );
		$this->assertSame( 'success', $result['result'] );

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Temporary file with the given content.
	 *
	 * @param string $content Content.
	 * @return string Path.
	 */
	protected function temp_file( string $content ): string {
		$path = (string) wp_tempnam( 'crkit-test' );
		file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->temp_files[] = $path;

		return $path;
	}

	/**
	 * Real PNG image.
	 *
	 * @return string Path.
	 */
	protected function png_file(): string {
		$image = imagecreatetruecolor( 4, 4 );
		ob_start();
		imagepng( $image );
		$png = (string) ob_get_clean();

		return $this->temp_file( $png );
	}

	/**
	 * Minimal PDF document.
	 *
	 * @return string Path.
	 */
	protected function pdf_file(): string {
		return $this->temp_file( "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n" );
	}

	/**
	 * $_FILES entry for a path.
	 *
	 * @param string $path Path.
	 * @param string $name Original file name.
	 * @return array<string, mixed>
	 */
	protected function file_entry( string $path, string $name ): array {
		return array(
			'name'     => $name,
			'type'     => 'application/octet-stream',
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $path ),
		);
	}
}
