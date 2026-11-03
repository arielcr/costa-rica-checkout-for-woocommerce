<?php
/**
 * Unit tests for the locations dataset and repository.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\Locations;

use Brain\Monkey\Functions;
use CRKit\Modules\Locations\LocationRepository;
use CRKit\Tests\Unit\TestCase;

/**
 * Checks the dataset against the official 2025 administrative division (Decreto 44882-MGP).
 *
 * @covers \CRKit\Modules\Locations\LocationRepository
 * @covers \CRKit\Modules\Locations\Location
 */
final class LocationRepositoryTest extends TestCase {

	private LocationRepository $repository;

	protected function set_up(): void {
		parent::set_up();
		Functions\when( 'wp_json_file_decode' )->alias(
			static fn( string $file, array $options = array() ) => json_decode( (string) file_get_contents( $file ), ! empty( $options['associative'] ) ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test stub.
		);
		$this->repository = new LocationRepository();
	}

	public function test_counts_match_the_official_division(): void {
		$all = $this->repository->all();

		$this->assertCount( 7, $this->repository->get_states() );
		$this->assertCount( 84, array_unique( array_map( static fn( $location ) => $location->get_canton_id(), $all ) ) );
		$this->assertCount( 492, $all );
	}

	/**
	 * Districts per province in the decree.
	 *
	 * @return array<string, array{string, int, int}>
	 */
	public static function provide_provinces(): array {
		return array(
			'San José'   => array( 'CR-SJ', 20, 123 ),
			'Alajuela'   => array( 'CR-A', 16, 116 ),
			'Cartago'    => array( 'CR-C', 8, 53 ),
			'Heredia'    => array( 'CR-H', 10, 48 ),
			'Guanacaste' => array( 'CR-G', 11, 61 ),
			'Puntarenas' => array( 'CR-P', 13, 61 ),
			'Limón'      => array( 'CR-L', 6, 30 ),
		);
	}

	/**
	 * Cantons and districts per province.
	 *
	 * @dataProvider provide_provinces
	 *
	 * @param string $state     WooCommerce state.
	 * @param int    $cantons   Expected cantons.
	 * @param int    $districts Expected districts.
	 */
	public function test_counts_per_province( string $state, int $cantons, int $districts ): void {
		$this->assertCount( $cantons, $this->repository->get_cantons( $state ) );
		$this->assertCount( $districts, $this->repository->get_districts_in_state( $state ) );
	}

	public function test_postcodes_are_unique_five_digit_codes_built_from_the_official_codes(): void {
		foreach ( $this->repository->all() as $postcode => $location ) {
			$this->assertMatchesRegularExpression( '/^[1-7]\d{4}$/', (string) $postcode );
			$this->assertSame( (string) $postcode, $location->get_postcode() );
		}
	}

	public function test_known_districts(): void {
		$carmen = $this->repository->find( '10101' );
		$this->assertSame( array( 'Carmen', 'San José', 'San José', 'CR-SJ' ), array( $carmen->district, $carmen->canton, $carmen->province, $carmen->state ) );

		$this->assertSame( 'San Isidro de El General', $this->repository->find( '11901' )->district );
		$this->assertSame( 'San José', $this->repository->find( '20506' )->district, 'Atenas 06, printed with a typo in the decree.' );
		$this->assertSame( 'Isla del Coco', $this->repository->find( '60110' )->district );
	}

	public function test_districts_that_became_cantons_are_vacant(): void {
		$this->assertNull( $this->repository->find( '20306' ), 'Río Cuarto, now canton 216.' );
		$this->assertNull( $this->repository->find( '60109' ), 'Monteverde, now canton 612.' );
		$this->assertNull( $this->repository->find( '60702' ), 'Puerto Jiménez, now canton 613.' );

		$this->assertSame( 'Río Cuarto', $this->repository->get_cantons( 'CR-A' )['216'] );
		$this->assertSame( 'Monteverde', $this->repository->get_cantons( 'CR-P' )['612'] );
		$this->assertSame( 'Puerto Jiménez', $this->repository->get_cantons( 'CR-P' )['613'] );
	}

	public function test_districts_of_a_canton(): void {
		$this->assertSame(
			array(
				'10201' => 'Escazú',
				'10202' => 'San Antonio',
				'10203' => 'San Rafael',
			),
			$this->repository->get_districts( '102' )
		);
	}

	public function test_missing_dataset_yields_no_locations(): void {
		$this->assertSame( array(), ( new LocationRepository( '/nonexistent.json' ) )->all() );
	}
}
