<?php
/**
 * Display formatting for exchange rates.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Formats rates with the store's price separators and rate dates without time zone shifts.
 */
final class RateFormatter {

	/**
	 * Rate as plain text, e.g. "₡456.04".
	 *
	 * @param float $rate Rate in colones.
	 * @return string
	 */
	public static function rate( float $rate ): string {
		return '₡' . number_format( $rate, 2, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
	}

	/**
	 * A `Y-m-d` rate date in the site's date format, without shifting the day by time zone.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	public static function date( string $date ): string {
		$timestamp = strtotime( $date . ' 00:00:00 UTC' );

		return false === $timestamp ? $date : (string) wp_date( (string) get_option( 'date_format' ), $timestamp, new \DateTimeZone( 'UTC' ) );
	}
}
