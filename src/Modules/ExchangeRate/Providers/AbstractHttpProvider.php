<?php
/**
 * Shared HTTP handling for exchange rate sources.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Performs JSON GET requests with a short timeout, TLS verification and a plugin user agent.
 */
abstract class AbstractHttpProvider implements RateProviderInterface {

	/**
	 * Request timeout in seconds.
	 */
	public const TIMEOUT = 10;

	/**
	 * User agent that identifies the plugin and its version.
	 *
	 * @return string
	 */
	public static function get_user_agent(): string {
		return 'CostaRicaKitForWooCommerce/' . CRKIT_VERSION . ' (+https://wordpress.org/plugins/costa-rica-checkout-for-woocommerce/)';
	}

	/**
	 * GETs a URL and decodes its JSON body.
	 *
	 * @param string                $url     URL.
	 * @param array<string, string> $headers Extra request headers.
	 * @return array<mixed>
	 * @throws RateProviderException On network errors, non-2xx responses or invalid JSON.
	 */
	protected function get_json( string $url, array $headers = array() ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => self::TIMEOUT,
				'redirection' => 2,
				'user-agent'  => self::get_user_agent(),
				'headers'     => array_merge( array( 'Accept' => 'application/json' ), $headers ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw RateProviderException::network( esc_html( $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status >= 300 ) {
			throw RateProviderException::http( (int) $status );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			throw RateProviderException::invalid_response( 'body is not a JSON object' );
		}

		return $data;
	}

	/**
	 * Positive float from a raw value, or null.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null
	 */
	protected static function positive_float( mixed $value ): ?float {
		if ( ! is_int( $value ) && ! is_float( $value ) && ! ( is_string( $value ) && is_numeric( $value ) ) ) {
			return null;
		}

		$value = (float) $value;

		return $value > 0 ? $value : null;
	}
}
