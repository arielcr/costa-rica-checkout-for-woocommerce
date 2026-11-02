<?php
/**
 * Unit tests for the exchange rate error messages.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\ExchangeRate;

use CRKit\Modules\ExchangeRate\ExchangeRateAdmin;
use CRKit\Tests\Unit\TestCase;

/**
 * Tests that each documented error has a clear message.
 *
 * @covers \CRKit\Modules\ExchangeRate\ExchangeRateAdmin::get_error_message
 */
final class ErrorMessageTest extends TestCase {

	public function test_bccr_500_mentions_the_token_and_the_service(): void {
		$message = ExchangeRateAdmin::get_error_message( 'http_500', 'bccr' );

		$this->assertStringContainsString( 'token is invalid', $message );
		$this->assertStringContainsString( 'BCCR service is failing', $message );
	}

	public function test_hacienda_500_does_not_mention_a_token(): void {
		$this->assertStringNotContainsString( 'token', ExchangeRateAdmin::get_error_message( 'http_503', 'hacienda' ) );
	}

	/**
	 * Every documented code has its own message.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_codes(): array {
		return array(
			'400'           => array( 'http_400', '(400)' ),
			'401'           => array( 'http_401', '(401)' ),
			'403'           => array( 'http_403', '(403)' ),
			'404'           => array( 'http_404', '(404)' ),
			'429'           => array( 'http_429', '(429)' ),
			'missing token' => array( 'missing_token', 'needs a token' ),
			'network'       => array( 'network', 'could not be reached' ),
			'other status'  => array( 'http_418', 'HTTP status 418' ),
		);
	}

	/**
	 * Each code maps to a specific message.
	 *
	 * @dataProvider provide_codes
	 *
	 * @param string $code     Error code.
	 * @param string $expected Expected fragment.
	 */
	public function test_each_code_has_a_specific_message( string $code, string $expected ): void {
		$this->assertStringContainsString( $expected, ExchangeRateAdmin::get_error_message( $code, 'bccr' ) );
	}
}
