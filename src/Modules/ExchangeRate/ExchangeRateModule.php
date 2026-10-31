<?php
/**
 * Exchange rate module: CRC/USD rate providers, cache and scheduled refresh.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

use CRKit\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Exchange rate module.
 */
final class ExchangeRateModule implements ModuleInterface {

	/**
	 * Module id.
	 */
	public const ID = 'exchange_rate';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'Exchange rate', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Keeps the US dollar exchange rate (Ministerio de Hacienda, BCCR or manual) up to date for colón conversions.', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Module features are registered here as they are implemented.
	}
}
