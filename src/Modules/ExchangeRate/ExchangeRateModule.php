<?php
/**
 * Exchange rate module: CRC/USD rate providers, cache and scheduled refresh.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

use CRKit\Admin\SettingsSectionInterface;
use CRKit\Modules\LifecycleInterface;
use CRKit\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Exchange rate module.
 */
final class ExchangeRateModule implements ModuleInterface, LifecycleInterface, SettingsSectionInterface {

	/**
	 * Module id.
	 */
	public const ID = 'exchange_rate';

	/**
	 * Lazily built service.
	 *
	 * @var ExchangeRateService|null
	 */
	private ?ExchangeRateService $service = null;

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
		return __( 'Exchange rate', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Keeps the US dollar exchange rate up to date for colón conversions. SINPE Móvil needs it in stores that do not sell in colones. When enabled, this site connects to the selected source (Ministerio de Hacienda by default, or BCCR) right away and then every 12 hours. No customer data is sent.', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		// Opt-in: no external request until the merchant enables the module (ADR-0053).
		return false;
	}

	/**
	 * Exchange rate service.
	 *
	 * @return ExchangeRateService
	 */
	public function get_service(): ExchangeRateService {
		if ( null === $this->service ) {
			$this->service = new ExchangeRateService( new RateRepository(), new ExchangeRateSettings() );
		}

		return $this->service;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		( new Scheduler( $this->get_service() ) )->register();

		// Not limited to is_admin(): settings can also be saved through the REST API.
		( new ExchangeRateAdmin( $this->get_service() ) )->register();
		( new Shortcode( $this->get_service() ) )->register();
	}

	/**
	 * {@inheritDoc}
	 */
	public function activate(): void {
		Scheduler::schedule();
	}

	/**
	 * {@inheritDoc}
	 */
	public function deactivate(): void {
		Scheduler::unschedule();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_section_id(): string {
		return ExchangeRateAdmin::SECTION;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_section_label(): string {
		return __( 'Exchange rate', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_section_fields(): array {
		return ( new ExchangeRateAdmin( $this->get_service() ) )->get_settings_fields();
	}
}
