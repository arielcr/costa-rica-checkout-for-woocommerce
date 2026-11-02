<?php
/**
 * Costa Rican provinces, cantons and districts (official 2025 administrative division).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to data/cr-locations.json. Postcodes identify districts.
 */
final class LocationRepository {

	/**
	 * Path of the dataset.
	 *
	 * @var string
	 */
	private string $file;

	/**
	 * Districts keyed by postcode, loaded on first use.
	 *
	 * @var array<string, Location>|null
	 */
	private ?array $districts = null;

	/**
	 * Constructor.
	 *
	 * @param string|null $file Dataset path. Defaults to the bundled data/cr-locations.json.
	 */
	public function __construct( ?string $file = null ) {
		$this->file = $file ?? CRKIT_PLUGIN_DIR . 'data/cr-locations.json';
	}

	/**
	 * WooCommerce state codes of the 7 provinces, keyed by province code.
	 *
	 * @return array<string, string>
	 */
	public function get_states(): array {
		$states = array();

		foreach ( $this->all() as $location ) {
			$states[ $location->province_code ] = $location->state;
		}

		return $states;
	}

	/**
	 * Cantons of a province, as canton id (3 digits) => name.
	 *
	 * @param string $state WooCommerce state code, e.g. `CR-SJ`.
	 * @return array<string, string>
	 */
	public function get_cantons( string $state ): array {
		$cantons = array();

		foreach ( $this->all() as $location ) {
			if ( $location->state === $state ) {
				$cantons[ $location->get_canton_id() ] = $location->canton;
			}
		}

		return $cantons;
	}

	/**
	 * Districts of a canton, as postcode => name.
	 *
	 * @param string $canton_id Canton id, e.g. `101`.
	 * @return array<string, string>
	 */
	public function get_districts( string $canton_id ): array {
		$districts = array();

		foreach ( $this->all() as $postcode => $location ) {
			if ( $location->get_canton_id() === $canton_id ) {
				$districts[ $postcode ] = $location->district;
			}
		}

		return $districts;
	}

	/**
	 * Districts of a province, keyed by postcode.
	 *
	 * @param string $state WooCommerce state code.
	 * @return array<string, Location>
	 */
	public function get_districts_in_state( string $state ): array {
		return array_filter( $this->all(), static fn( Location $location ): bool => $location->state === $state );
	}

	/**
	 * District by postcode.
	 *
	 * @param string $postcode Five-digit postcode.
	 * @return Location|null
	 */
	public function find( string $postcode ): ?Location {
		return $this->all()[ $postcode ] ?? null;
	}

	/**
	 * Every district, keyed by postcode, in official order.
	 *
	 * @return array<string, Location>
	 */
	public function all(): array {
		if ( null === $this->districts ) {
			$this->districts = $this->load();
		}

		return $this->districts;
	}

	/**
	 * Reads the dataset.
	 *
	 * @return array<string, Location>
	 */
	private function load(): array {
		$data      = is_readable( $this->file ) ? wp_json_file_decode( $this->file, array( 'associative' => true ) ) : null;
		$provinces = is_array( $data ) && isset( $data['provinces'] ) && is_array( $data['provinces'] ) ? $data['provinces'] : array();
		$districts = array();

		foreach ( $provinces as $province ) {
			foreach ( $province['cantons'] ?? array() as $canton ) {
				foreach ( $canton['districts'] ?? array() as $district ) {
					$location = new Location(
						(string) $province['code'],
						(string) $province['wc_state'],
						(string) $province['name'],
						(string) $canton['code'],
						(string) $canton['name'],
						(string) $district['code'],
						(string) $district['name']
					);

					$districts[ $location->get_postcode() ] = $location;
				}
			}
		}

		return $districts;
	}
}
