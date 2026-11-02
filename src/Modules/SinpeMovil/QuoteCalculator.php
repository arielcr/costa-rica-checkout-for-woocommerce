<?php
/**
 * Converts totals to the amount to pay in colones.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\ExchangeRateService;

defined( 'ABSPATH' ) || exit;

/**
 * Builds payment quotes from the cached exchange rate. Never calls external services.
 */
final class QuoteCalculator {

	/**
	 * Exchange rate service, or null if the exchange rate module is disabled.
	 *
	 * @var ExchangeRateService|null
	 */
	private ?ExchangeRateService $rates;

	/**
	 * Constructor.
	 *
	 * @param ExchangeRateService|null $rates Exchange rate service.
	 */
	public function __construct( ?ExchangeRateService $rates ) {
		$this->rates = $rates;
	}

	/**
	 * Quote for a total. CRC totals need no rate. Other currencies need a usable rate, otherwise null.
	 *
	 * @param float  $total     Total in `$currency`.
	 * @param string $currency  ISO 4217 code.
	 * @param string $rate_type `sell` or `buy`.
	 * @param float  $surcharge Surcharge percentage on top of the rate (non-CRC totals only).
	 * @return PaymentQuote|null
	 */
	public function quote( float $total, string $currency, string $rate_type = ExchangeRate::SELL, float $surcharge = 0.0 ): ?PaymentQuote {
		$currency  = strtoupper( $currency );
		$rate_type = ExchangeRate::is_valid_type( $rate_type ) ? $rate_type : ExchangeRate::SELL;
		$surcharge = max( 0.0, $surcharge );

		if ( 'CRC' === $currency ) {
			return new PaymentQuote( self::round_up( $total ), $currency );
		}

		if ( null === $this->rates ) {
			return null;
		}

		$converted = $this->rates->convert_to_crc( $total, $currency, $rate_type );

		if ( null === $converted ) {
			return null;
		}

		$rate    = $total > 0 ? $converted / $total : (float) $this->rates->get_rate( $rate_type );
		$current = $this->rates->get_current();

		return new PaymentQuote(
			self::round_up( $converted * ( 1 + $surcharge / 100 ) ),
			$currency,
			round( $rate, 4 ),
			$surcharge,
			$rate_type,
			null === $current ? '' : $current->source,
			null === $current ? '' : $current->date
		);
	}

	/**
	 * Rounds up to whole colones. Rounds to cents first so floating point noise
	 * (e.g. 4607.000000001) does not add a colón.
	 *
	 * @param float $amount Amount in colones.
	 * @return int
	 */
	public static function round_up( float $amount ): int {
		return (int) ceil( round( $amount, 2 ) );
	}
}
