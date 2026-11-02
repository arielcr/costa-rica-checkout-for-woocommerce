<?php
/**
 * Display formatting for SINPE Móvil amounts and rates.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

defined( 'ABSPATH' ) || exit;

/**
 * Formats colón amounts with the store's separators.
 */
final class Formatter {

	/**
	 * Amount in colones as HTML (wc_price markup), without decimals.
	 *
	 * @param int $amount Amount in colones.
	 * @return string
	 */
	public static function crc_html( int $amount ): string {
		return wc_price(
			$amount,
			array(
				'currency' => 'CRC',
				'decimals' => 0,
			)
		);
	}

	/**
	 * Amount in colones as plain text, e.g. "₡45,604".
	 *
	 * @param int $amount Amount in colones.
	 * @return string
	 */
	public static function crc( int $amount ): string {
		return html_entity_decode( wp_strip_all_tags( self::crc_html( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Exchange rate as plain text, e.g. "₡456.04" or "₡456.04 + 1.5%".
	 *
	 * @param float $rate      Rate in colones.
	 * @param float $surcharge Surcharge percentage.
	 * @return string
	 */
	public static function rate( float $rate, float $surcharge = 0.0 ): string {
		$text = '₡' . number_format( $rate, 2, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );

		if ( $surcharge > 0 ) {
			$text .= ' + ' . wc_format_localized_decimal( (string) $surcharge ) . '%';
		}

		return $text;
	}
}
