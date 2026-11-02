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
	 * Lazily built repository.
	 *
	 * @var LocationRepository|null
	 */
	private ?LocationRepository $repository = null;

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
	 * Locations (official 2025 administrative division).
	 *
	 * @return LocationRepository
	 */
	public function get_repository(): LocationRepository {
		if ( null === $this->repository ) {
			$this->repository = new LocationRepository();
		}

		return $this->repository;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$fields = new AddressFields( $this->get_repository() );
		$fields->register();

		( new ClassicCheckout( $this->get_repository(), $fields ) )->register();
		( new BlocksCheckout( $this->get_repository(), $fields ) )->register();
		( new AdminFields( $this->get_repository(), $fields ) )->register();
	}
}
