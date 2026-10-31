<?php
/**
 * Exchange rate cache.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the last good rate and the result of the last update attempt (both not autoloaded).
 */
final class RateRepository {

	/**
	 * Option with the last good rate.
	 */
	public const RATE_OPTION = 'crkit_exchange_rate';

	/**
	 * Option with the result of the last update attempt.
	 */
	public const STATUS_OPTION = 'crkit_exchange_rate_status';

	/**
	 * Last good rate, or null if there is none.
	 *
	 * @return ExchangeRate|null
	 */
	public function get(): ?ExchangeRate {
		return ExchangeRate::from_array( get_option( self::RATE_OPTION, null ) );
	}

	/**
	 * Saves a good rate.
	 *
	 * @param ExchangeRate $rate Rate.
	 * @return void
	 */
	public function save( ExchangeRate $rate ): void {
		update_option( self::RATE_OPTION, $rate->to_array(), false );
	}

	/**
	 * Result of the last update attempt.
	 *
	 * @return array{attempted_at: int, source: string, error_code: string, error_message: string}
	 */
	public function get_status(): array {
		$status = get_option( self::STATUS_OPTION, array() );
		$status = is_array( $status ) ? $status : array();

		return array(
			'attempted_at'  => isset( $status['attempted_at'] ) ? (int) $status['attempted_at'] : 0,
			'source'        => isset( $status['source'] ) ? (string) $status['source'] : '',
			'error_code'    => isset( $status['error_code'] ) ? (string) $status['error_code'] : '',
			'error_message' => isset( $status['error_message'] ) ? (string) $status['error_message'] : '',
		);
	}

	/**
	 * Records the result of an update attempt. An empty error code means success.
	 *
	 * @param string $source        Provider id.
	 * @param string $error_code    Error code, empty on success.
	 * @param string $error_message Technical error message, empty on success.
	 * @return void
	 */
	public function save_status( string $source, string $error_code = '', string $error_message = '' ): void {
		update_option(
			self::STATUS_OPTION,
			array(
				'attempted_at'  => time(),
				'source'        => $source,
				'error_code'    => $error_code,
				'error_message' => $error_message,
			),
			false
		);
	}
}
