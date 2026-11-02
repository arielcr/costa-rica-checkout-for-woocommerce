<?php
/**
 * Exchange rate value object.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Colones per US dollar, as published by a source on a given date.
 */
final class ExchangeRate {

	/**
	 * Buy rate type.
	 */
	public const BUY = 'buy';

	/**
	 * Sell rate type.
	 */
	public const SELL = 'sell';

	/**
	 * Constructor.
	 *
	 * @param float  $buy        Buy rate (CRC per USD).
	 * @param float  $sell       Sell rate (CRC per USD).
	 * @param string $date       Date of the published rate, `Y-m-d`.
	 * @param string $source     Provider id (`hacienda`, `bccr`, `manual`, …).
	 * @param int    $fetched_at Unix timestamp of the successful request.
	 */
	public function __construct(
		public readonly float $buy,
		public readonly float $sell,
		public readonly string $date,
		public readonly string $source,
		public readonly int $fetched_at
	) {
	}

	/**
	 * Whether a rate type is valid.
	 *
	 * @param string $type Rate type.
	 * @return bool
	 */
	public static function is_valid_type( string $type ): bool {
		return self::BUY === $type || self::SELL === $type;
	}

	/**
	 * Rate of the given type. Anything other than `buy` returns the sell rate.
	 *
	 * @param string $type Rate type.
	 * @return float
	 */
	public function get( string $type ): float {
		return self::BUY === $type ? $this->buy : $this->sell;
	}

	/**
	 * Array representation for storage.
	 *
	 * @return array{buy: float, sell: float, date: string, source: string, fetched_at: int}
	 */
	public function to_array(): array {
		return array(
			'buy'        => $this->buy,
			'sell'       => $this->sell,
			'date'       => $this->date,
			'source'     => $this->source,
			'fetched_at' => $this->fetched_at,
		);
	}

	/**
	 * Rebuilds a rate from stored data. Returns null if the data is incomplete or invalid.
	 *
	 * @param mixed $data Stored data.
	 * @return self|null
	 */
	public static function from_array( mixed $data ): ?self {
		if ( ! is_array( $data ) ) {
			return null;
		}

		foreach ( array( 'buy', 'sell', 'date', 'source', 'fetched_at' ) as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				return null;
			}
		}

		if ( ! is_numeric( $data['buy'] ) || ! is_numeric( $data['sell'] ) || ! is_numeric( $data['fetched_at'] ) ) {
			return null;
		}

		$buy  = (float) $data['buy'];
		$sell = (float) $data['sell'];

		if ( $buy <= 0 || $sell <= 0 || ! self::is_valid_date( (string) $data['date'] ) ) {
			return null;
		}

		return new self( $buy, $sell, (string) $data['date'], (string) $data['source'], (int) $data['fetched_at'] );
	}

	/**
	 * Whether a string is a valid `Y-m-d` date.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	public static function is_valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
