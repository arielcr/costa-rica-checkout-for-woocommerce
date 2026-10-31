<?php
/**
 * Exchange rate service used by the other modules.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

use CRKit\Modules\ExchangeRate\Providers\BccrProvider;
use CRKit\Modules\ExchangeRate\Providers\HaciendaProvider;
use CRKit\Modules\ExchangeRate\Providers\RateProviderException;
use CRKit\Modules\ExchangeRate\Providers\RateProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the cached rate (never calls external services) and refreshes it from background jobs.
 */
final class ExchangeRateService {

	/**
	 * Source id of a manual rate.
	 */
	public const MANUAL_SOURCE = 'manual';

	/**
	 * Cache.
	 *
	 * @var RateRepository
	 */
	private RateRepository $repository;

	/**
	 * Settings.
	 *
	 * @var ExchangeRateSettings
	 */
	private ExchangeRateSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param RateRepository       $repository Cache.
	 * @param ExchangeRateSettings $settings   Settings.
	 */
	public function __construct( RateRepository $repository, ExchangeRateSettings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	/**
	 * Settings.
	 *
	 * @return ExchangeRateSettings
	 */
	public function get_settings(): ExchangeRateSettings {
		return $this->settings;
	}

	/**
	 * Cache.
	 *
	 * @return RateRepository
	 */
	public function get_repository(): RateRepository {
		return $this->repository;
	}

	/**
	 * Available rate sources, keyed by id.
	 *
	 * @return array<string, RateProviderInterface>
	 */
	public function get_providers(): array {
		$providers = array(
			HaciendaProvider::ID => new HaciendaProvider(),
			BccrProvider::ID     => new BccrProvider( $this->settings->get_bccr_token() ),
		);

		/**
		 * Filters the exchange rate sources.
		 *
		 * @since 0.1.0
		 *
		 * @param RateProviderInterface[] $providers Providers keyed by id.
		 * @param ExchangeRateSettings    $settings  Exchange rate settings.
		 */
		$providers = apply_filters( 'crkit_rate_providers', $providers, $this->settings );

		$valid = array();
		foreach ( is_array( $providers ) ? $providers : array() as $provider ) {
			if ( $provider instanceof RateProviderInterface ) {
				$valid[ $provider->get_id() ] = $provider;
			}
		}

		return $valid;
	}

	/**
	 * Provider selected in the settings. Falls back to Hacienda if the selected one is gone.
	 *
	 * @return RateProviderInterface|null
	 */
	public function get_selected_provider(): ?RateProviderInterface {
		$providers = $this->get_providers();

		return $providers[ $this->settings->get_source() ] ?? $providers[ HaciendaProvider::ID ] ?? null;
	}

	/**
	 * Current rate: the manual rate if it is on, otherwise the last good automatic rate (even if outdated).
	 *
	 * @return ExchangeRate|null
	 */
	public function get_current(): ?ExchangeRate {
		$manual = $this->settings->get_manual_rate();

		if ( null !== $manual ) {
			return new ExchangeRate( $manual, $manual, wp_date( 'Y-m-d' ), self::MANUAL_SOURCE, time() );
		}

		return $this->repository->get();
	}

	/**
	 * Whether there is no usable rate: none at all, or an automatic one older than the maximum age.
	 * A manual rate never goes stale.
	 *
	 * @param int|null $now Current timestamp, for tests.
	 * @return bool
	 */
	public function is_stale( ?int $now = null ): bool {
		$rate = $this->get_current();

		if ( null === $rate ) {
			return true;
		}

		if ( self::MANUAL_SOURCE === $rate->source ) {
			return false;
		}

		$age = ( $now ?? time() ) - $rate->fetched_at;

		return $age > $this->settings->get_max_age_hours() * HOUR_IN_SECONDS;
	}

	/**
	 * Usable rate of the given type, or null if there is none or it is outdated.
	 *
	 * @param string $type `sell` or `buy`.
	 * @return float|null
	 */
	public function get_rate( string $type = ExchangeRate::SELL ): ?float {
		$rate  = $this->get_current();
		$value = null;

		if ( null !== $rate && ExchangeRate::is_valid_type( $type ) && ! $this->is_stale() ) {
			$value = $rate->get( $type );
		}

		/**
		 * Filters the usable exchange rate (CRC per USD).
		 *
		 * @since 0.1.0
		 *
		 * @param float|null        $value Rate, or null if unavailable or outdated.
		 * @param string            $type  `sell` or `buy`.
		 * @param ExchangeRate|null $rate  Current rate data.
		 */
		$value = apply_filters( 'crkit_exchange_rate', $value, $type, $rate );

		return is_numeric( $value ) && (float) $value > 0 ? (float) $value : null;
	}

	/**
	 * Converts an amount to colones. CRC amounts are returned as is; USD uses the usable rate.
	 * Returns null for other currencies or when there is no usable rate.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency ISO 4217 currency code.
	 * @param string $type     Rate type, `sell` or `buy`.
	 * @return float|null
	 */
	public function convert_to_crc( float $amount, string $currency, string $type = ExchangeRate::SELL ): ?float {
		$currency  = strtoupper( $currency );
		$converted = null;

		if ( 'CRC' === $currency ) {
			$converted = $amount;
		} elseif ( 'USD' === $currency ) {
			$rate      = $this->get_rate( $type );
			$converted = null === $rate ? null : $amount * $rate;
		}

		/**
		 * Filters an amount converted to colones. Use it to support other currencies.
		 *
		 * @since 0.1.0
		 *
		 * @param float|null $converted Amount in CRC, or null if it cannot be converted.
		 * @param float      $amount    Original amount.
		 * @param string     $currency  Original currency (upper case).
		 * @param string     $type      Rate type, `sell` or `buy`.
		 */
		$converted = apply_filters( 'crkit_convert_to_crc', $converted, $amount, $currency, $type );

		return is_numeric( $converted ) ? (float) $converted : null;
	}

	/**
	 * Fetches a new rate from the selected source. On failure the last good rate is kept.
	 * Runs from the scheduled action or an explicit admin action, never during a storefront request.
	 *
	 * @return bool Whether a new rate was saved.
	 */
	public function refresh(): bool {
		$provider = $this->get_selected_provider();

		if ( null === $provider ) {
			return false;
		}

		$previous = $this->repository->get();

		try {
			$rate = $provider->fetch();
		} catch ( RateProviderException $exception ) {
			$this->repository->save_status( $provider->get_id(), $exception->get_error_code(), $exception->getMessage() );
			$this->log( 'warning', sprintf( 'Exchange rate update from "%s" failed: %s', $provider->get_id(), $exception->getMessage() ) );

			/**
			 * Fires when an exchange rate update fails. The last good rate is kept.
			 *
			 * @since 0.1.0
			 *
			 * @param RateProviderException $exception Error.
			 * @param string                $source    Provider id.
			 */
			do_action( 'crkit_exchange_rate_update_failed', $exception, $provider->get_id() );

			return false;
		}

		$this->repository->save( $rate );
		$this->repository->save_status( $provider->get_id() );

		/**
		 * Fires after a new exchange rate is saved.
		 *
		 * @since 0.1.0
		 *
		 * @param ExchangeRate      $rate     New rate.
		 * @param ExchangeRate|null $previous Previous rate, if any.
		 */
		do_action( 'crkit_exchange_rate_updated', $rate, $previous );

		return true;
	}

	/**
	 * Writes to the WooCommerce log (WooCommerce → Status → Logs, source `crkit-exchange-rate`).
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @return void
	 */
	private function log( string $level, string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'crkit-exchange-rate' ) );
		}
	}
}
