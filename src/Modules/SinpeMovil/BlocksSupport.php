<?php
/**
 * SINPE Móvil in the block checkout.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the client-side payment method and passes it the data it needs to estimate
 * the amount in colones and to decide whether it can be used (maximum amount, rate available).
 */
final class BlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Script handle.
	 */
	public const SCRIPT_HANDLE = 'crkit-sinpe-movil-blocks';

	/**
	 * Payment method name.
	 *
	 * @var string
	 */
	protected $name = Gateway::ID;

	/**
	 * Loads the gateway settings.
	 *
	 * @return void
	 */
	public function initialize() {
		$settings       = get_option( 'woocommerce_' . Gateway::ID . '_settings', array() );
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Whether the method is enabled.
	 *
	 * @return bool
	 */
	public function is_active() {
		$gateway = ReceiptController::get_gateway();

		return null !== $gateway && 'yes' === $gateway->enabled;
	}

	/**
	 * Script that registers the method in the block checkout.
	 *
	 * @return array<int, string>
	 */
	public function get_payment_method_script_handles() {
		$asset_file = CRKIT_PLUGIN_DIR . 'assets/build/sinpe-movil-blocks.asset.json';
		$asset      = is_readable( $asset_file ) ? wp_json_file_decode( $asset_file, array( 'associative' => true ) ) : array();
		$asset      = is_array( $asset ) ? $asset : array();

		wp_register_script(
			self::SCRIPT_HANDLE,
			CRKIT_PLUGIN_URL . 'assets/build/sinpe-movil-blocks.js',
			isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : CRKIT_VERSION,
			true
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'costa-rica-kit-for-woocommerce', CRKIT_PLUGIN_DIR . 'languages' );

		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * Data for the client: texts, effective rate and limits. The rate comes from the cache.
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data() {
		$gateway  = ReceiptController::get_gateway();
		$currency = get_woocommerce_currency();
		$rate     = null;

		if ( null !== $gateway && 'CRC' !== $currency ) {
			$quote = $gateway->get_quote( 1.0, $currency );
			$rate  = null === $quote || null === $quote->rate ? null : $quote->rate * ( 1 + $quote->surcharge / 100 );
		}

		return array(
			'title'         => null === $gateway ? '' : $gateway->get_title(),
			'description'   => null === $gateway ? '' : $gateway->get_description(),
			'supports'      => null === $gateway ? array( 'products' ) : array_values( array_filter( $gateway->supports, array( $gateway, 'supports' ) ) ),
			'storeCurrency' => $currency,
			'rate'          => $rate,
			'rateAvailable' => 'CRC' === $currency || null !== $rate,
			'maxAmountCrc'  => null === $gateway ? 0 : $gateway->get_max_amount_crc(),
		);
	}
}
