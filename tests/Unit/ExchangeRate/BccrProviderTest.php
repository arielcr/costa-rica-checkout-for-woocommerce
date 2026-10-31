<?php
/**
 * Unit tests for the BCCR SDDE provider.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\ExchangeRate;

use CRKit\Modules\ExchangeRate\Providers\BccrProvider;
use CRKit\Modules\ExchangeRate\Providers\RateProviderException;
use CRKit\Tests\Unit\TestCase;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Tests the table 1 parser, the date filtering and the request.
 *
 * @covers \CRKit\Modules\ExchangeRate\Providers\BccrProvider
 * @covers \CRKit\Modules\ExchangeRate\Providers\AbstractHttpProvider
 */
final class BccrProviderTest extends TestCase {

	use HttpTestTrait;

	public function test_parses_the_real_table_1_response_and_keeps_the_latest_day(): void {
		$rate = BccrProvider::parse( self::fixture( 'bccr/cuadro-1-2026-10-07.json' ), '2026-10-07', 1700000000 );

		$this->assertSame( 453.46, $rate->buy );
		$this->assertSame( 457.8, $rate->sell );
		$this->assertSame( '2026-10-07', $rate->date );
		$this->assertSame( 'bccr', $rate->source );
	}

	public function test_ignores_tomorrows_rate(): void {
		// In the afternoon of 2026-10-06 the series already includes 2026-10-07.
		$rate = BccrProvider::parse( self::fixture( 'bccr/cuadro-1-2026-10-07.json' ), '2026-10-06', time() );

		$this->assertSame( '2026-10-06', $rate->date );
		$this->assertSame( 454.5, $rate->buy );
		$this->assertSame( 460.35, $rate->sell );
	}

	public function test_weekend_days_use_the_published_rate(): void {
		$rate = BccrProvider::parse( self::fixture( 'bccr/cuadro-1-2026-10-07.json' ), '2026-10-04', time() );

		$this->assertSame( '2026-10-04', $rate->date );
		$this->assertSame( 455.71, $rate->buy );
		$this->assertSame( 462.08, $rate->sell );
	}

	public function test_matches_the_hacienda_rate_of_the_same_day(): void {
		// SPEC §3B: on 2026-10-01 Hacienda reported 454.78 / 460.73, the same as the BCCR.
		$rate = BccrProvider::parse( self::fixture( 'bccr/cuadro-1-2026-10-07.json' ), '2026-10-01', time() );

		$this->assertSame( 454.78, $rate->buy );
		$this->assertSame( 460.73, $rate->sell );
	}

	public function test_discards_null_days_and_only_accepts_days_with_both_values(): void {
		// 2026-10-31 has a sell rate but a null buy rate; 2026-11-01 is tomorrow.
		$rate = BccrProvider::parse( self::fixture( 'bccr/cuadro-1-with-tomorrow-and-nulls.json' ), '2026-10-31', time() );

		$this->assertSame( '2026-10-30', $rate->date );
		$this->assertSame( 449.7, $rate->buy );
		$this->assertSame( 456.04, $rate->sell );
	}

	public function test_fails_when_no_day_is_usable(): void {
		$this->assert_parse_fails_with( self::fixture( 'bccr/cuadro-1-2026-10-07.json' ), '2026-09-29', 'no_data' );
	}

	public function test_fails_when_estado_is_false(): void {
		$this->assert_parse_fails_with( self::fixture( 'bccr/error-estado-false.json' ), '2026-10-31', 'invalid_response' );
	}

	public function test_fails_on_the_real_invalid_token_body(): void {
		$this->assert_parse_fails_with( self::fixture( 'bccr/error-500-invalid-token.json' ), '2026-10-31', 'invalid_response' );
	}

	public function test_series_url_requests_a_week_until_tomorrow_with_encoded_slashes(): void {
		$provider = new BccrProvider( 'token' );
		$url      = $provider->get_series_url( new DateTimeImmutable( '2026-10-31', new DateTimeZone( 'America/Costa_Rica' ) ) );

		$this->assertSame(
			'https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API/cuadro/1/series?fechaInicio=2026%2F10%2F24&fechaFin=2026%2F11%2F01&idioma=ES',
			$url
		);
	}

	public function test_fetch_uses_costa_rica_time_to_decide_today(): void {
		// 2026-11-01 03:00 UTC is still 2026-10-31 21:00 in Costa Rica (UTC-6).
		$now = new DateTimeImmutable( '2026-11-01 03:00:00', new DateTimeZone( 'UTC' ) );
		$this->fake_response( 200, self::raw_fixture( 'bccr/cuadro-1-with-tomorrow-and-nulls.json' ) );

		$rate = ( new BccrProvider( 'secret-token', $now ) )->fetch();

		$this->assertSame( '2026-10-30', $rate->date );
		$this->assertStringContainsString( 'fechaFin=2026%2F11%2F01', $this->last_request['url'] );
		$this->assertSame( 'Bearer secret-token', $this->last_request['args']['headers']['Authorization'] );
		$this->assertSame( 'application/json', $this->last_request['args']['headers']['Accept'] );
	}

	public function test_fetch_without_token_fails_without_a_request(): void {
		\Brain\Monkey\Functions\expect( 'wp_remote_get' )->never();

		try {
			( new BccrProvider( '  ' ) )->fetch();
			$this->fail( 'Expected a RateProviderException.' );
		} catch ( RateProviderException $exception ) {
			$this->assertSame( 'missing_token', $exception->get_error_code() );
		}
	}

	/**
	 * HTTP statuses documented by the BCCR and their error codes.
	 *
	 * @return array<string, array{int, string}>
	 */
	public static function provide_http_errors(): array {
		return array(
			'400' => array( 400, 'http_400' ),
			'401' => array( 401, 'http_401' ),
			'403' => array( 403, 'http_403' ),
			'404' => array( 404, 'http_404' ),
			'429' => array( 429, 'http_429' ),
			'500' => array( 500, 'http_500' ),
		);
	}

	/**
	 * Each documented status is mapped to its own error code.
	 *
	 * @dataProvider provide_http_errors
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Expected error code.
	 */
	public function test_fetch_maps_http_errors( int $status, string $code ): void {
		$this->fake_response( $status, 401 === $status ? '' : self::raw_fixture( 'bccr/error-500-invalid-token.json' ) );

		try {
			( new BccrProvider( 'token' ) )->fetch();
			$this->fail( 'Expected a RateProviderException.' );
		} catch ( RateProviderException $exception ) {
			$this->assertSame( $code, $exception->get_error_code() );
		}
	}

	/**
	 * Asserts that parsing fails with the given code.
	 *
	 * @param array<mixed> $data  Decoded response.
	 * @param string       $today Today.
	 * @param string       $code  Expected error code.
	 */
	private function assert_parse_fails_with( array $data, string $today, string $code ): void {
		try {
			BccrProvider::parse( $data, $today, time() );
			$this->fail( 'Expected a RateProviderException.' );
		} catch ( RateProviderException $exception ) {
			$this->assertSame( $code, $exception->get_error_code() );
		}
	}
}
