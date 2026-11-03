<?php
/**
 * Banco Central de Costa Rica (BCCR) SDDE exchange rate source (requires the merchant's token).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate\Providers;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Reads table ("cuadro") 1 of the BCCR SDDE API, which returns the buy (317) and sell (318) series in one call.
 *
 * The BCCR publishes the next day's rate in the afternoon, so a range up to tomorrow is requested and
 * the most recent date that is not after today (America/Costa_Rica) is used.
 */
final class BccrProvider extends AbstractHttpProvider {

	/**
	 * Provider id.
	 */
	public const ID = 'bccr';

	/**
	 * Default API base URL. Filterable with `crkit_bccr_api_base_url`.
	 */
	public const API_BASE_URL = 'https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API';

	/**
	 * Buy rate indicator code.
	 */
	public const BUY_CODE = '317';

	/**
	 * Sell rate indicator code.
	 */
	public const SELL_CODE = '318';

	/**
	 * Time zone used to decide which date is "today".
	 */
	public const TIMEZONE = 'America/Costa_Rica';

	/**
	 * Days requested before today, to survive weekends and holidays without new data.
	 */
	public const LOOKBACK_DAYS = 7;

	/**
	 * Merchant's API token.
	 *
	 * @var string
	 */
	private string $token;

	/**
	 * Current time, injectable for tests.
	 *
	 * @var DateTimeImmutable|null
	 */
	private ?DateTimeImmutable $now;

	/**
	 * Constructor.
	 *
	 * @param string                 $token API token.
	 * @param DateTimeImmutable|null $now   Current time. Defaults to now.
	 */
	public function __construct( string $token, ?DateTimeImmutable $now = null ) {
		$this->token = trim( $token );
		$this->now   = $now;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return __( 'Banco Central de Costa Rica (requires your BCCR token)', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * Fetches the most recent buy and sell rates that are not after today.
	 *
	 * @return ExchangeRate
	 * @throws RateProviderException When the token is missing, the request fails or there is no usable day.
	 */
	public function fetch(): ExchangeRate {
		if ( '' === $this->token ) {
			throw RateProviderException::missing_token();
		}

		$today = $this->today();
		$data  = $this->get_json( $this->get_series_url( $today ), array( 'Authorization' => 'Bearer ' . $this->token ) );

		return self::parse( $data, $today->format( 'Y-m-d' ), time() );
	}

	/**
	 * URL of the table 1 series from LOOKBACK_DAYS ago until tomorrow. Dates go as `yyyy%2Fmm%2Fdd`.
	 *
	 * @param DateTimeImmutable $today Today in Costa Rica.
	 * @return string
	 */
	public function get_series_url( DateTimeImmutable $today ): string {
		/**
		 * Filters the BCCR SDDE API base URL.
		 *
		 * @since 1.0.0
		 *
		 * @param string $base_url Base URL, without trailing slash.
		 */
		$base_url = untrailingslashit( (string) apply_filters( 'crkit_bccr_api_base_url', self::API_BASE_URL ) );

		$start = $today->modify( '-' . self::LOOKBACK_DAYS . ' days' );
		$end   = $today->modify( '+1 day' );

		return $base_url . '/cuadro/1/series?fechaInicio=' . rawurlencode( $start->format( 'Y/m/d' ) )
			. '&fechaFin=' . rawurlencode( $end->format( 'Y/m/d' ) )
			. '&idioma=ES';
	}

	/**
	 * Builds a rate from a decoded table 1 response.
	 *
	 * Days with a `null` value are discarded, buy and sell are joined by date, and only days with both
	 * values that are not after `$today` are accepted. The most recent one wins.
	 *
	 * @param array<mixed> $data       Decoded response.
	 * @param string       $today      Today in Costa Rica, `Y-m-d`.
	 * @param int          $fetched_at Request timestamp.
	 * @return ExchangeRate
	 * @throws RateProviderException If the response is not successful or has no usable day.
	 */
	public static function parse( array $data, string $today, int $fetched_at ): ExchangeRate {
		if ( true !== ( $data['estado'] ?? null ) ) {
			$message = isset( $data['mensaje'] ) && is_string( $data['mensaje'] ) ? $data['mensaje'] : 'estado is not true';
			throw RateProviderException::invalid_response( esc_html( $message ) );
		}

		$series = array(
			self::BUY_CODE  => array(),
			self::SELL_CODE => array(),
		);

		foreach ( self::get_indicators( $data ) as $indicator ) {
			$code = isset( $indicator['codigoIndicador'] ) ? (string) $indicator['codigoIndicador'] : '';

			if ( ! isset( $series[ $code ] ) || ! isset( $indicator['series'] ) || ! is_array( $indicator['series'] ) ) {
				continue;
			}

			foreach ( $indicator['series'] as $point ) {
				$date  = is_array( $point ) && isset( $point['fecha'] ) && is_string( $point['fecha'] ) ? substr( $point['fecha'], 0, 10 ) : '';
				$value = is_array( $point ) ? self::positive_float( $point['valorDatoPorPeriodo'] ?? null ) : null;

				if ( null !== $value && ExchangeRate::is_valid_date( $date ) && $date <= $today ) {
					$series[ $code ][ $date ] = $value;
				}
			}
		}

		$dates = array_keys( array_intersect_key( $series[ self::BUY_CODE ], $series[ self::SELL_CODE ] ) );

		if ( array() === $dates ) {
			throw RateProviderException::no_data();
		}

		rsort( $dates, SORT_STRING );
		$date = (string) $dates[0];

		return new ExchangeRate( $series[ self::BUY_CODE ][ $date ], $series[ self::SELL_CODE ][ $date ], $date, self::ID, $fetched_at );
	}

	/**
	 * Indicators of every table in the response.
	 *
	 * @param array<mixed> $data Decoded response.
	 * @return array<int, array<mixed>>
	 */
	private static function get_indicators( array $data ): array {
		$indicators = array();
		$tables     = isset( $data['datos'] ) && is_array( $data['datos'] ) ? $data['datos'] : array();

		foreach ( $tables as $table ) {
			if ( ! is_array( $table ) || ! isset( $table['indicadores'] ) || ! is_array( $table['indicadores'] ) ) {
				continue;
			}

			foreach ( $table['indicadores'] as $indicator ) {
				if ( is_array( $indicator ) ) {
					$indicators[] = $indicator;
				}
			}
		}

		return $indicators;
	}

	/**
	 * Today in Costa Rica.
	 *
	 * @return DateTimeImmutable
	 */
	private function today(): DateTimeImmutable {
		$now = $this->now ?? new DateTimeImmutable( 'now' );

		return $now->setTimezone( new DateTimeZone( self::TIMEZONE ) )->setTime( 0, 0 );
	}
}
