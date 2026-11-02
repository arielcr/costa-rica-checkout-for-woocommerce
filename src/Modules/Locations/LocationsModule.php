<?php
/**
 * Locations module: Costa Rican provinces, cantons and districts in addresses.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Locations;

use CRKit\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Locations module.
 */
final class LocationsModule implements ModuleInterface {

	/**
	 * Module id.
	 */
	public const ID = 'locations';

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
		return __( 'Provinces, cantons and districts', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Adds dependent canton and district fields to Costa Rican addresses at checkout.', 'costa-rica-kit-for-woocommerce' );
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
