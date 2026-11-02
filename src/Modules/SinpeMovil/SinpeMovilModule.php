<?php
/**
 * SINPE Móvil module: manual payment gateway with receipt uploads.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use CRKit\Modules\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * SINPE Móvil module.
 */
final class SinpeMovilModule implements ModuleInterface {

	/**
	 * Module id.
	 */
	public const ID = 'sinpe_movil';

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
		return __( 'SINPE Móvil', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Adds SINPE Móvil as a manual payment method with payment receipt uploads.', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Module features are registered here as they are implemented.
	}
}
