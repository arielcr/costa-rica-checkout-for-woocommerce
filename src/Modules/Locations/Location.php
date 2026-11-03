<?php
/**
 * A Costa Rican district with its canton and province.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Locations;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable district location. The postcode is province (1) + canton (2) + district (2) digits.
 */
final class Location {

	/**
	 * Constructor.
	 *
	 * @param string $province_code Province code, e.g. `1`.
	 * @param string $state         WooCommerce state code, e.g. `CR-SJ`.
	 * @param string $province      Province name.
	 * @param string $canton_code   Canton code within the province, e.g. `01`.
	 * @param string $canton        Canton name.
	 * @param string $district_code District code within the canton, e.g. `01`.
	 * @param string $district      District name.
	 */
	public function __construct(
		public readonly string $province_code,
		public readonly string $state,
		public readonly string $province,
		public readonly string $canton_code,
		public readonly string $canton,
		public readonly string $district_code,
		public readonly string $district
	) {
	}

	/**
	 * Five-digit postcode, e.g. `10101`.
	 *
	 * @return string
	 */
	public function get_postcode(): string {
		return $this->province_code . $this->canton_code . $this->district_code;
	}

	/**
	 * Three-digit canton code, e.g. `101`.
	 *
	 * @return string
	 */
	public function get_canton_id(): string {
		return $this->province_code . $this->canton_code;
	}
}
