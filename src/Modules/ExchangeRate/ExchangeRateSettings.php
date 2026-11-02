<?php
/**
 * Exchange rate settings.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

use CRKit\Modules\ExchangeRate\Providers\HaciendaProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the `crkit_exchange_rate_settings` option, saved from the Costa Rica settings tab.
 */
final class ExchangeRateSettings {

	/**
	 * Option name. Fields are saved as `crkit_exchange_rate_settings[key]`.
	 */
	public const OPTION = 'crkit_exchange_rate_settings';

	/**
	 * Default maximum age of an automatic rate, in hours.
	 */
	public const DEFAULT_MAX_AGE_HOURS = 48;

	/**
	 * Raw value of a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private function get( string $key ): string {
		$settings = get_option( self::OPTION, array() );

		return is_array( $settings ) && isset( $settings[ $key ] ) && is_scalar( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
	}

	/**
	 * Selected provider id.
	 *
	 * @return string
	 */
	public function get_source(): string {
		$source = sanitize_key( $this->get( 'source' ) );

		return '' === $source ? HaciendaProvider::ID : $source;
	}

	/**
	 * BCCR API token.
	 *
	 * @return string
	 */
	public function get_bccr_token(): string {
		return $this->get( 'bccr_token' );
	}

	/**
	 * Manual rate (CRC per USD) if the manual override is on and valid, otherwise null.
	 *
	 * @return float|null
	 */
	public function get_manual_rate(): ?float {
		if ( 'yes' !== $this->get( 'manual_enabled' ) ) {
			return null;
		}

		$rate = $this->get( 'manual_rate' );

		return is_numeric( $rate ) && (float) $rate > 0 ? (float) $rate : null;
	}

	/**
	 * Maximum age of an automatic rate before it is considered outdated, in hours.
	 *
	 * @return int
	 */
	public function get_max_age_hours(): int {
		$hours = absint( $this->get( 'max_age_hours' ) );

		return $hours > 0 ? $hours : self::DEFAULT_MAX_AGE_HOURS;
	}
}
