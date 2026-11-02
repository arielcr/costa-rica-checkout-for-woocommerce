<?php
/**
 * Identification settings.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the ID fields are optional, required, or required only for an electronic invoice.
 */
final class IdentificationSettings {

	/**
	 * Option name. Fields are saved as `crkit_identification_settings[key]`.
	 */
	public const OPTION = 'crkit_identification_settings';

	/**
	 * The customer may leave the ID empty.
	 */
	public const OPTIONAL = 'optional';

	/**
	 * Every order needs an ID.
	 */
	public const REQUIRED = 'required';

	/**
	 * The ID is required only if the customer checks "I need an electronic invoice". Default.
	 */
	public const INVOICE = 'invoice';

	/**
	 * Translated requirement options, keyed by value.
	 *
	 * @return array<string, string>
	 */
	public static function get_requirement_options(): array {
		return array(
			self::INVOICE  => __( 'Required only if the customer asks for an electronic invoice', 'costa-rica-kit-for-woocommerce' ),
			self::OPTIONAL => __( 'Optional', 'costa-rica-kit-for-woocommerce' ),
			self::REQUIRED => __( 'Required', 'costa-rica-kit-for-woocommerce' ),
		);
	}

	/**
	 * Selected requirement.
	 *
	 * @return string One of the OPTIONAL, REQUIRED and INVOICE constants.
	 */
	public function get_requirement(): string {
		$settings    = get_option( self::OPTION, array() );
		$requirement = is_array( $settings ) && isset( $settings['requirement'] ) && is_string( $settings['requirement'] ) ? $settings['requirement'] : '';

		return array_key_exists( $requirement, self::get_requirement_options() ) ? $requirement : self::INVOICE;
	}
}
