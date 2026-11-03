<?php
/**
 * Identification module: customer ID type and number (cédula) at checkout.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use CRKit\Admin\SettingsSectionInterface;
use CRKit\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Identification module.
 */
final class IdentificationModule implements ModuleInterface, SettingsSectionInterface {

	/**
	 * Module id.
	 */
	public const ID = 'identification';

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
		return __( 'ID number (cédula)', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Collects the customer ID type and number at checkout for e-invoicing plugins.', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$settings = new IdentificationSettings();

		( new BlocksCheckout( $settings ) )->register();
		( new ClassicCheckout( $settings ) )->register();
		( new AccountDetails( $settings ) )->register();
		( new AdminFields( $settings ) )->register();
		( new OrderDisplay() )->register();
		( new Privacy() )->register();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_section_id(): string {
		return 'identification';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_section_label(): string {
		return __( 'ID number', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_settings_section_fields(): array {
		return array(
			array(
				'id'    => 'crkit_identification_section',
				'type'  => 'title',
				'title' => __( 'ID number (cédula)', 'costa-rica-kit-for-woocommerce' ),
				'desc'  => __( 'Customers choose the ID type (cédula física, cédula jurídica, DIMEX or NITE) and type the number. The number is checked against the format Hacienda sets for its type; there is no public check digit, so it is not looked up in any registry. E-invoicing plugins can read it with crkit_get_order_identification().', 'costa-rica-kit-for-woocommerce' ),
			),
			array(
				'id'      => IdentificationSettings::OPTION . '[requirement]',
				'type'    => 'select',
				'title'   => __( 'ID number at checkout', 'costa-rica-kit-for-woocommerce' ),
				'options' => IdentificationSettings::get_requirement_options(),
				'default' => IdentificationSettings::INVOICE,
				'desc'    => __( 'With "Required only if the customer asks for an electronic invoice", checkout shows an "I need an electronic invoice" checkbox.', 'costa-rica-kit-for-woocommerce' ),
			),
			array(
				'id'   => 'crkit_identification_section',
				'type' => 'sectionend',
			),
		);
	}
}
