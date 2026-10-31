<?php
/**
 * Unit tests for the exchange rate service.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\ExchangeRate;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\ExchangeRateService;
use CRKit\Modules\ExchangeRate\ExchangeRateSettings;
use CRKit\Modules\ExchangeRate\Providers\RateProviderException;
use CRKit\Modules\ExchangeRate\Providers\RateProviderInterface;
use CRKit\Modules\ExchangeRate\RateRepository;
use CRKit\Tests\Unit\TestCase;
use Mockery;

/**
 * Tests cache reads, manual override, staleness, conversion and refresh.
 *
 * @covers \CRKit\Modules\ExchangeRate\ExchangeRateService
 * @covers \CRKit\Modules\ExchangeRate\ExchangeRateSettings
 * @covers \CRKit\Modules\ExchangeRate\RateRepository
 * @covers \CRKit\Modules\ExchangeRate\ExchangeRate
 */
final class ExchangeRateServiceTest extends TestCase {

	protected function set_up(): void {
		parent::set_up();
		$this->use_options_store();
		Functions\when( 'wc_get_logger' )->justReturn( Mockery::spy( 'WC_Logger' ) );
	}

	public function test_no_rate_means_stale_and_no_conversion(): void {
		$service = $this->service();

		$this->assertNull( $service->get_current() );
		$this->assertTrue( $service->is_stale() );
		$this->assertNull( $service->get_rate() );
		$this->assertNull( $service->convert_to_crc( 10.0, 'USD' ) );
	}

	public function test_reads_the_cached_rate(): void {
		$this->cache_rate( 449.7, 456.04, time() - 3600 );
		$service = $this->service();

		$this->assertFalse( $service->is_stale() );
		$this->assertSame( 456.04, $service->get_rate( 'sell' ) );
		$this->assertSame( 449.7, $service->get_rate( 'buy' ) );
		$this->assertNull( $service->get_rate( 'average' ) );
	}

	public function test_rate_older_than_the_maximum_age_is_stale(): void {
		$this->cache_rate( 449.7, 456.04, time() - 49 * 3600 );
		$service = $this->service();

		$this->assertTrue( $service->is_stale() );
		$this->assertNull( $service->get_rate() );
		$this->assertNotNull( $service->get_current(), 'The outdated rate is kept for display.' );
	}

	public function test_maximum_age_is_configurable(): void {
		$this->cache_rate( 449.7, 456.04, time() - 5 * 3600 );
		$this->options[ ExchangeRateSettings::OPTION ] = array( 'max_age_hours' => '4' );

		$this->assertTrue( $this->service()->is_stale() );
	}

	public function test_manual_rate_wins_and_never_goes_stale(): void {
		$this->cache_rate( 449.7, 456.04, time() - 100 * 3600 );
		$this->options[ ExchangeRateSettings::OPTION ] = array(
			'manual_enabled' => 'yes',
			'manual_rate'    => '500.5',
		);
		$service                                       = $this->service();

		$this->assertSame( 'manual', $service->get_current()->source );
		$this->assertFalse( $service->is_stale() );
		$this->assertSame( 500.5, $service->get_rate( 'sell' ) );
		$this->assertSame( 500.5, $service->get_rate( 'buy' ) );
	}

	public function test_manual_rate_is_ignored_when_off_or_invalid(): void {
		$this->cache_rate( 449.7, 456.04, time() );

		$this->options[ ExchangeRateSettings::OPTION ] = array(
			'manual_enabled' => 'no',
			'manual_rate'    => '500',
		);
		$this->assertSame( 456.04, $this->service()->get_rate() );

		$this->options[ ExchangeRateSettings::OPTION ] = array(
			'manual_enabled' => 'yes',
			'manual_rate'    => '-1',
		);
		$this->assertSame( 456.04, $this->service()->get_rate() );
	}

	public function test_converts_usd_with_the_sell_rate_and_keeps_crc(): void {
		$this->cache_rate( 449.7, 456.04, time() );
		$service = $this->service();

		$this->assertEqualsWithDelta( 4560.4, $service->convert_to_crc( 10.0, 'usd' ), 0.0001 );
		$this->assertEqualsWithDelta( 4497.0, $service->convert_to_crc( 10.0, 'USD', 'buy' ), 0.0001 );
		$this->assertSame( 1234.5, $service->convert_to_crc( 1234.5, 'CRC' ) );
		$this->assertNull( $service->convert_to_crc( 10.0, 'EUR' ) );
	}

	public function test_other_currencies_can_be_added_with_a_filter(): void {
		Filters\expectApplied( 'crkit_convert_to_crc' )->andReturnUsing(
			static fn( $converted, float $amount, string $currency ) => 'EUR' === $currency ? $amount * 507.82 : $converted
		);

		$this->assertEqualsWithDelta( 5078.2, $this->service()->convert_to_crc( 10.0, 'EUR' ), 0.0001 );
	}

	public function test_refresh_saves_the_rate_and_fires_the_updated_action(): void {
		$rate = new ExchangeRate( 449.7, 456.04, '2026-10-31', 'fake', time() );
		$this->use_provider( $this->provider_returning( $rate ) );
		Actions\expectDone( 'crkit_exchange_rate_updated' )->once()->with( $rate, null );

		$this->assertTrue( $this->service()->refresh() );
		$this->assertSame( $rate->to_array(), $this->options[ RateRepository::RATE_OPTION ] );
		$this->assertSame( '', $this->options[ RateRepository::STATUS_OPTION ]['error_code'] );
	}

	public function test_failed_refresh_keeps_the_last_good_rate(): void {
		$this->cache_rate( 449.7, 456.04, time() - 3600 );
		$before = $this->options[ RateRepository::RATE_OPTION ];

		$provider = Mockery::mock( RateProviderInterface::class );
		$provider->allows( 'get_id' )->andReturn( 'fake' );
		$provider->allows( 'fetch' )->andThrow( RateProviderException::http( 429 ) );
		$this->use_provider( $provider );
		Actions\expectDone( 'crkit_exchange_rate_update_failed' )->once();

		$this->assertFalse( $this->service()->refresh() );
		$this->assertSame( $before, $this->options[ RateRepository::RATE_OPTION ] );
		$this->assertSame( 'http_429', $this->options[ RateRepository::STATUS_OPTION ]['error_code'] );
		$this->assertSame( 'fake', $this->options[ RateRepository::STATUS_OPTION ]['source'] );
	}

	public function test_core_providers_are_registered_and_filterable(): void {
		$this->assertSame( array( 'hacienda', 'bccr' ), array_keys( $this->service()->get_providers() ) );

		$extra = Mockery::mock( RateProviderInterface::class );
		$extra->allows( 'get_id' )->andReturn( 'bank' );
		Filters\expectApplied( 'crkit_rate_providers' )->andReturnUsing(
			static fn( array $providers ): array => array_merge( $providers, array( $extra, 'not-a-provider' ) )
		);

		$this->assertSame( array( 'hacienda', 'bccr', 'bank' ), array_keys( $this->service()->get_providers() ) );
	}

	public function test_unknown_source_falls_back_to_hacienda(): void {
		$this->options[ ExchangeRateSettings::OPTION ] = array( 'source' => 'gone' );

		$this->assertSame( 'hacienda', $this->service()->get_selected_provider()->get_id() );
	}

	public function test_value_object_round_trip_and_validation(): void {
		$rate = new ExchangeRate( 449.7, 456.04, '2026-10-31', 'hacienda', 1700000000 );

		$this->assertEquals( $rate, ExchangeRate::from_array( $rate->to_array() ) );
		$this->assertNull( ExchangeRate::from_array( 'garbage' ) );
		$this->assertNull( ExchangeRate::from_array( array_merge( $rate->to_array(), array( 'sell' => 0 ) ) ) );
		$this->assertNull( ExchangeRate::from_array( array_merge( $rate->to_array(), array( 'date' => '2026-02-30' ) ) ) );
		$this->assertNull( ExchangeRate::from_array( array( 'buy' => 1 ) ) );
	}

	private function service(): ExchangeRateService {
		return new ExchangeRateService( new RateRepository(), new ExchangeRateSettings() );
	}

	private function cache_rate( float $buy, float $sell, int $fetched_at ): void {
		$this->options[ RateRepository::RATE_OPTION ] = ( new ExchangeRate( $buy, $sell, '2026-10-31', 'hacienda', $fetched_at ) )->to_array();
	}

	private function provider_returning( ExchangeRate $rate ): RateProviderInterface {
		$provider = Mockery::mock( RateProviderInterface::class );
		$provider->allows( 'get_id' )->andReturn( 'fake' );
		$provider->allows( 'fetch' )->andReturn( $rate );

		return $provider;
	}

	private function use_provider( RateProviderInterface $provider ): void {
		$this->options[ ExchangeRateSettings::OPTION ] = array( 'source' => 'fake' );
		Filters\expectApplied( 'crkit_rate_providers' )->andReturn( array( $provider ) );
	}
}
