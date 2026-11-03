<?php
/**
 * Ministerio de Hacienda exchange rate source (default, no credentials).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate\Providers;

use CRKit\Modules\ExchangeRate\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Reads `GET https://api.hacienda.go.cr/indicadores/tc`.
 *
 * Verified response shape (tests/fixtures/hacienda):
 * `{ "dolar": { "venta": { "fecha": "Y-m-d", "valor": 456.04 }, "compra": { "fecha": "Y-m-d", "valor": 449.7 } }, "euro": {…} }`
 */
final class HaciendaProvider extends AbstractHttpProvider {

	/**
	 * Provider id.
	 */
	public const ID = 'hacienda';

	/**
	 * Default endpoint. Filterable with `crkit_hacienda_api_url`.
	 */
	public const API_URL = 'https://api.hacienda.go.cr/indicadores/tc';

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
		return __( 'Ministerio de Hacienda (no credentials needed)', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fetch(): ExchangeRate {
		/**
		 * Filters the Ministerio de Hacienda exchange rate endpoint.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Endpoint URL.
		 */
		$url = (string) apply_filters( 'crkit_hacienda_api_url', self::API_URL );

		return self::parse( $this->get_json( $url ), time() );
	}

	/**
	 * Builds a rate from a decoded response.
	 *
	 * @param array<mixed> $data       Decoded response.
	 * @param int          $fetched_at Request timestamp.
	 * @return ExchangeRate
	 * @throws RateProviderException If the response has an unexpected shape.
	 */
	public static function parse( array $data, int $fetched_at ): ExchangeRate {
		$dollar = isset( $data['dolar'] ) && is_array( $data['dolar'] ) ? $data['dolar'] : array();
		$buy    = isset( $dollar['compra'] ) && is_array( $dollar['compra'] ) ? $dollar['compra'] : array();
		$sell   = isset( $dollar['venta'] ) && is_array( $dollar['venta'] ) ? $dollar['venta'] : array();

		$buy_value  = self::positive_float( $buy['valor'] ?? null );
		$sell_value = self::positive_float( $sell['valor'] ?? null );
		$date       = isset( $sell['fecha'] ) && is_string( $sell['fecha'] ) ? $sell['fecha'] : '';

		if ( null === $buy_value || null === $sell_value ) {
			throw RateProviderException::invalid_response( 'missing dolar.compra.valor or dolar.venta.valor' );
		}

		if ( ! ExchangeRate::is_valid_date( $date ) ) {
			throw RateProviderException::invalid_response( 'missing or invalid dolar.venta.fecha' );
		}

		return new ExchangeRate( $buy_value, $sell_value, $date, self::ID, $fetched_at );
	}
}
