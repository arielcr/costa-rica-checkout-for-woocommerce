<?php
/**
 * Costa Rican identification types and their number formats.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

defined( 'ABSPATH' ) || exit;

/**
 * Types use the codes of Hacienda's electronic receipts (note 4 of "Anexos y Estructuras" v4.4),
 * so e-invoicing plugins can use them as they are. Validation checks the format only: there is no
 * public check digit. See docs/DECISIONS.md, ADR-0046.
 */
final class IdentificationType {

	/**
	 * Cédula física (individuals): 9 digits, not starting with 0.
	 */
	public const PHYSICAL = '01';

	/**
	 * Cédula jurídica (companies): 10 characters. Letters are allowed since Hacienda's April 2026 update.
	 */
	public const LEGAL = '02';

	/**
	 * DIMEX (foreign residents): 11 or 12 digits, not starting with 0.
	 */
	public const DIMEX = '03';

	/**
	 * NITE (special tax ID issued by Hacienda): 10 digits.
	 */
	public const NITE = '04';

	/**
	 * Format of each type, after normalization.
	 */
	private const PATTERNS = array(
		self::PHYSICAL => '/^[1-9][0-9]{8}$/',
		self::LEGAL    => '/^[0-9A-Z]{10}$/',
		self::DIMEX    => '/^[1-9][0-9]{10,11}$/',
		self::NITE     => '/^[0-9]{10}$/',
	);

	/**
	 * Translated type names, keyed by code.
	 *
	 * @return array<string, string>
	 */
	public static function get_labels(): array {
		return array(
			self::PHYSICAL => __( 'Cédula física', 'costa-rica-checkout-for-woocommerce' ),
			self::LEGAL    => __( 'Cédula jurídica', 'costa-rica-checkout-for-woocommerce' ),
			self::DIMEX    => __( 'DIMEX', 'costa-rica-checkout-for-woocommerce' ),
			self::NITE     => __( 'NITE', 'costa-rica-checkout-for-woocommerce' ),
		);
	}

	/**
	 * Whether a code is a known type.
	 *
	 * @param string $type Type code.
	 * @return bool
	 */
	public static function exists( string $type ): bool {
		return isset( self::PATTERNS[ $type ] );
	}

	/**
	 * Translated name of a type, or an empty string for unknown codes.
	 *
	 * @param string $type Type code.
	 * @return string
	 */
	public static function get_label( string $type ): string {
		return self::get_labels()[ $type ] ?? '';
	}

	/**
	 * Removes spaces and hyphens (any dash) and upper-cases letters.
	 *
	 * @param string $number Raw number.
	 * @return string
	 */
	public static function normalize( string $number ): string {
		return strtoupper( (string) preg_replace( '/[\s\-‐‑‒–—―]+/u', '', $number ) );
	}

	/**
	 * Whether a number has the format of its type, after normalization.
	 *
	 * @param string $type   Type code.
	 * @param string $number Raw number.
	 * @return bool
	 */
	public static function is_valid( string $type, string $number ): bool {
		return self::exists( $type ) && 1 === preg_match( self::PATTERNS[ $type ], self::normalize( $number ) );
	}

	/**
	 * Error shown when a number does not have the format of its type.
	 *
	 * @param string $type Type code.
	 * @return string
	 */
	public static function get_format_error( string $type ): string {
		switch ( $type ) {
			case self::PHYSICAL:
				return __( 'A cédula física has 9 digits and does not start with 0.', 'costa-rica-checkout-for-woocommerce' );
			case self::LEGAL:
				return __( 'A cédula jurídica has 10 characters (digits, or digits and letters).', 'costa-rica-checkout-for-woocommerce' );
			case self::DIMEX:
				return __( 'A DIMEX has 11 or 12 digits and does not start with 0.', 'costa-rica-checkout-for-woocommerce' );
			case self::NITE:
				return __( 'A NITE has 10 digits.', 'costa-rica-checkout-for-woocommerce' );
			default:
				return __( 'Select the ID type.', 'costa-rica-checkout-for-woocommerce' );
		}
	}

	/**
	 * Format errors of a type and number pair. An empty number has no errors: whether the fields
	 * are required is checked separately.
	 *
	 * @param string $type   Type code (may be empty).
	 * @param string $number Raw number (may be empty).
	 * @return array<string, string> Error messages keyed by error code.
	 */
	public static function get_errors( string $type, string $number ): array {
		if ( '' === self::normalize( $number ) ) {
			return array();
		}

		if ( ! self::exists( $type ) ) {
			return array( 'crkit_id_type_missing' => __( 'Select the ID type.', 'costa-rica-checkout-for-woocommerce' ) );
		}

		if ( ! self::is_valid( $type, $number ) ) {
			return array( 'crkit_id_number_invalid' => self::get_format_error( $type ) );
		}

		return array();
	}
}
