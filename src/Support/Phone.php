<?php
/**
 * Costa Rican phone numbers.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes, validates and formats 8-digit Costa Rican phone numbers.
 */
final class Phone {

	/**
	 * Digits only, without the +506 country code. Accepts spaces, hyphens, dots and parentheses.
	 *
	 * @param string $phone Raw phone number.
	 * @return string
	 */
	public static function normalize( string $phone ): string {
		$digits = (string) preg_replace( '/\D+/', '', $phone );

		if ( 11 === strlen( $digits ) && str_starts_with( $digits, '506' ) ) {
			$digits = substr( $digits, 3 );
		}

		return $digits;
	}

	/**
	 * Whether a number normalizes to 8 digits.
	 *
	 * @param string $phone Raw phone number.
	 * @return bool
	 */
	public static function is_valid( string $phone ): bool {
		return 1 === preg_match( '/^\d{8}$/', self::normalize( $phone ) );
	}

	/**
	 * Display format, e.g. `8888-8888`. Invalid numbers are returned normalized.
	 *
	 * @param string $phone Raw phone number.
	 * @return string
	 */
	public static function format( string $phone ): string {
		$digits = self::normalize( $phone );

		return 8 === strlen( $digits ) ? substr( $digits, 0, 4 ) . '-' . substr( $digits, 4 ) : $digits;
	}
}
