<?php
/**
 * Contract for exchange rate sources.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate\Providers;

use CRKit\Modules\ExchangeRate\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * An exchange rate source. Add your own through the `crkit_rate_providers` filter.
 */
interface RateProviderInterface {

	/**
	 * Unique id, stored in the settings and in each rate.
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Translated name shown in the settings.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Fetches the current USD rate. Only called from background jobs or explicit admin actions.
	 *
	 * @return ExchangeRate
	 * @throws RateProviderException When the source fails or returns unusable data.
	 */
	public function fetch(): ExchangeRate;
}
