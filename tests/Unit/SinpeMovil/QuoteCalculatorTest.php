<?php
/**
 * Unit tests for CRC quotes and rounding.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\SinpeMovil;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\ExchangeRateService;
use CRKit\Modules\ExchangeRate\ExchangeRateSettings;
use CRKit\Modules\ExchangeRate\RateRepository;
use CRKit\Modules\SinpeMovil\QuoteCalculator;
use CRKit\Tests\Unit\TestCase;

/**
 * Tests conversion, surcharge and rounding up to whole colones.
 *
 * @covers \CRKit\Modules\SinpeMovil\QuoteCalculator
 * @covers \CRKit\Modules\SinpeMovil\PaymentQuote
 */
final class QuoteCalculatorTest extends TestCase {

	/**
	 * Amounts and their rounded value.
	 *
	 * @return array<string, array{float, int}>
	 */
	public static function provide_rounding(): array {
		return array(
			'exact'               => array( 4607.0, 4607 ),
			'cents round up'      => array( 4607.01, 4608 ),
			'half'                => array( 4607.5, 4608 ),
			'float noise above'   => array( 4607.000000001, 4607 ),
			'float noise below'   => array( 4606.999999999, 4607 ),
			'sub-cent is ignored' => array( 4607.004, 4607 ),
			'sub-cent rounds up'  => array( 4607.005, 4608 ),
			'zero'                => array( 0.0, 0 ),
		);
	}

	/**
	 * Amounts are rounded to cents first, then up to the next colón.
	 *
	 * @dataProvider provide_rounding
	 *
	 * @param float $amount   Amount.
	 * @param int   $expected Rounded amount.
	 */
	public function test_round_up( float $amount, int $expected ): void {
		$this->assertSame( $expected, QuoteCalculator::round_up( $amount ) );
	}

	public function test_crc_totals_need_no_rate(): void {
		$quote = ( new QuoteCalculator( null ) )->quote( 25999.5, 'crc', 'sell', 5.0 );

		$this->assertSame( 26000, $quote->amount_crc );
		$this->assertSame( 'CRC', $quote->currency );
		$this->assertFalse( $quote->is_converted() );
		$this->assertSame( 0.0, $quote->surcharge, 'No surcharge on colón totals.' );
	}

	public function test_usd_total_uses_the_rate_and_rounds_up(): void {
		$this->cache_rate( 449.7, 456.04 );
		$quote = ( new QuoteCalculator( $this->service() ) )->quote( 10.01, 'USD' );

		// 10.01 × 456.04 = 4564.9604 → 4565.
		$this->assertSame( 4565, $quote->amount_crc );
		$this->assertSame( 456.04, $quote->rate );
		$this->assertSame( 'sell', $quote->rate_type );
		$this->assertSame( 'hacienda', $quote->rate_source );
		$this->assertSame( '2026-10-31', $quote->rate_date );
	}

	public function test_buy_rate_and_surcharge(): void {
		$this->cache_rate( 449.7, 456.04 );
		$quote = ( new QuoteCalculator( $this->service() ) )->quote( 100.0, 'USD', 'buy', 2.5 );

		// 100 × 449.7 = 44970; + 2.5% = 46094.25 → 46095.
		$this->assertSame( 46095, $quote->amount_crc );
		$this->assertSame( 449.7, $quote->rate );
		$this->assertSame( 2.5, $quote->surcharge );
		$this->assertSame( 'buy', $quote->rate_type );
	}

	public function test_no_quote_without_a_usable_rate(): void {
		$this->cache_rate( 449.7, 456.04, time() - 100 * 3600 );

		$this->assertNull( ( new QuoteCalculator( $this->service() ) )->quote( 10.0, 'USD' ) );
		$this->assertNull( ( new QuoteCalculator( null ) )->quote( 10.0, 'USD' ), 'Exchange rate module disabled.' );
	}

	public function test_no_quote_for_unsupported_currencies(): void {
		$this->cache_rate( 449.7, 456.04 );

		$this->assertNull( ( new QuoteCalculator( $this->service() ) )->quote( 10.0, 'EUR' ) );
	}

	private function service(): ExchangeRateService {
		return new ExchangeRateService( new RateRepository(), new ExchangeRateSettings() );
	}

	private function cache_rate( float $buy, float $sell, ?int $fetched_at = null ): void {
		$this->use_options_store(
			array(
				RateRepository::RATE_OPTION => ( new ExchangeRate( $buy, $sell, '2026-10-31', 'hacienda', $fetched_at ?? time() ) )->to_array(),
			)
		);
	}
}
