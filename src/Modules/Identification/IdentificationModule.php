<?php
/**
 * Identification module: customer ID type and number (cédula) at checkout.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use CRKit\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Identification module.
 */
final class IdentificationModule implements ModuleInterface {

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
		// Module features are registered here as they are implemented.
	}
}
