<?php
/**
 * Unit tests for the Ministerio de Hacienda provider.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\ExchangeRate;

use CRKit\Modules\ExchangeRate\Providers\HaciendaProvider;
use CRKit\Modules\ExchangeRate\Providers\RateProviderException;
use CRKit\Tests\Unit\TestCase;

/**
 * Tests the Hacienda parser against the real response and HTTP failures.
 *
 * @covers \CRKit\Modules\ExchangeRate\Providers\HaciendaProvider
 * @covers \CRKit\Modules\ExchangeRate\Providers\AbstractHttpProvider
 * @covers \CRKit\Modules\ExchangeRate\Providers\RateProviderException
 */
final class HaciendaProviderTest extends TestCase {

	use HttpTestTrait;

	public function test_parses_the_real_response(): void {
		$rate = HaciendaProvider::parse( self::fixture( 'hacienda/tc-2026-10-31.json' ), 1700000000 );

		$this->assertSame( 449.7, $rate->buy );
		$this->assertSame( 456.04, $rate->sell );
		$this->assertSame( '2026-10-31', $rate->date );
		$this->assertSame( 'hacienda', $rate->source );
		$this->assertSame( 1700000000, $rate->fetched_at );
	}

	/**
	 * Responses without a usable value or date are rejected.
	 *
	 * @return array<string, array{array<mixed>}>
	 */
	public static function provide_invalid_responses(): array {
		$valid = self::fixture( 'hacienda/tc-2026-10-31.json' );

		$null_sell                            = $valid;
		$null_sell['dolar']['venta']['valor'] = null;

		$zero_buy                             = $valid;
		$zero_buy['dolar']['compra']['valor'] = 0;

		$bad_date                            = $valid;
		$bad_date['dolar']['venta']['fecha'] = '31/10/2026';

		return array(
			'empty object' => array( array() ),
			'euro only'    => array( array( 'euro' => $valid['euro'] ) ),
			'null sell'    => array( $null_sell ),
			'zero buy'     => array( $zero_buy ),
			'bad date'     => array( $bad_date ),
		);
	}

	/**
	 * Invalid responses raise an invalid_response error.
	 *
	 * @dataProvider provide_invalid_responses
	 *
	 * @param array<mixed> $data Decoded response.
	 * @throws RateProviderException Expected.
	 */
	public function test_rejects_invalid_responses( array $data ): void {
		$this->expectException( RateProviderException::class );

		try {
			HaciendaProvider::parse( $data, time() );
		} catch ( RateProviderException $exception ) {
			$this->assertSame( 'invalid_response', $exception->get_error_code() );
			throw $exception;
		}
	}

	public function test_fetch_sends_a_short_timeout_and_a_plugin_user_agent(): void {
		$this->fake_response( 200, self::raw_fixture( 'hacienda/tc-2026-10-31.json' ) );

		$rate = ( new HaciendaProvider() )->fetch();

		$this->assertSame( 456.04, $rate->sell );
		$this->assertSame( HaciendaProvider::API_URL, $this->last_request['url'] );
		$this->assertSame( 10, $this->last_request['args']['timeout'] );
		$this->assertStringStartsWith( 'CostaRicaCheckoutForWooCommerce/0.1.0', $this->last_request['args']['user-agent'] );
		$this->assertArrayNotHasKey( 'sslverify', $this->last_request['args'] );
	}

	/**
	 * HTTP statuses and their error codes.
	 *
	 * @return array<string, array{int, string}>
	 */
	public static function provide_http_errors(): array {
		return array(
			'401' => array( 401, 'http_401' ),
			'429' => array( 429, 'http_429' ),
			'500' => array( 500, 'http_500' ),
			'503' => array( 503, 'http_503' ),
		);
	}

	/**
	 * Non-2xx statuses raise an error with the status in the code.
	 *
	 * @dataProvider provide_http_errors
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Expected error code.
	 */
	public function test_fetch_maps_http_errors( int $status, string $code ): void {
		$this->fake_response( $status, '' );

		$this->assert_fetch_fails_with( $code );
	}

	public function test_fetch_maps_network_errors(): void {
		$this->fake_network_error( 'cURL error 28: Operation timed out' );

		$this->assert_fetch_fails_with( 'network' );
	}

	public function test_fetch_rejects_a_non_json_body(): void {
		$this->fake_response( 200, '<html>Maintenance</html>' );

		$this->assert_fetch_fails_with( 'invalid_response' );
	}

	private function assert_fetch_fails_with( string $code ): void {
		try {
			( new HaciendaProvider() )->fetch();
			$this->fail( 'Expected a RateProviderException.' );
		} catch ( RateProviderException $exception ) {
			$this->assertSame( $code, $exception->get_error_code() );
		}
	}
}
