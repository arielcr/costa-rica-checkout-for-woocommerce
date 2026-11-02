<?php
/**
 * Amount to pay in colones, with the exchange rate used.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result of converting an order or cart total to colones.
 */
final class PaymentQuote {

	/**
	 * Constructor.
	 *
	 * @param int        $amount_crc  Amount to pay, in whole colones (rounded up).
	 * @param string     $currency    Currency of the original total.
	 * @param float|null $rate        Exchange rate used (CRC per unit), before the surcharge. Null for CRC totals.
	 * @param float      $surcharge   Surcharge percentage applied on top of the rate.
	 * @param string     $rate_type   `sell` or `buy`; empty for CRC totals.
	 * @param string     $rate_source Rate source id; empty for CRC totals.
	 * @param string     $rate_date   Date of the rate, `Y-m-d`; empty for CRC totals.
	 */
	public function __construct(
		public readonly int $amount_crc,
		public readonly string $currency,
		public readonly ?float $rate = null,
		public readonly float $surcharge = 0.0,
		public readonly string $rate_type = '',
		public readonly string $rate_source = '',
		public readonly string $rate_date = ''
	) {
	}

	/**
	 * Whether a currency conversion was needed.
	 *
	 * @return bool
	 */
	public function is_converted(): bool {
		return null !== $this->rate;
	}
}
