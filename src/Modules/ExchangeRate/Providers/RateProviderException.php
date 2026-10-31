<?php
/**
 * Exchange rate source error.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Raised when a source fails. The error code is mapped to a translated message in the admin.
 */
final class RateProviderException extends \RuntimeException {

	/**
	 * Machine-readable error code, e.g. `http_401`, `network`, `invalid_response`.
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * Constructor.
	 *
	 * @param string $error_code Error code.
	 * @param string $message    Technical message (English, for logs).
	 * @param int    $status     HTTP status, if any.
	 */
	public function __construct( string $error_code, string $message, int $status = 0 ) {
		parent::__construct( $message, $status );
		$this->error_code = $error_code;
	}

	/**
	 * Non-2xx HTTP response.
	 *
	 * @param int $status HTTP status.
	 * @return self
	 */
	public static function http( int $status ): self {
		return new self( 'http_' . $status, sprintf( 'Unexpected HTTP status %d.', $status ), $status );
	}

	/**
	 * Connection error (DNS, timeout, TLS…).
	 *
	 * @param string $detail Error detail.
	 * @return self
	 */
	public static function network( string $detail ): self {
		return new self( 'network', 'Request failed: ' . $detail );
	}

	/**
	 * The response could not be parsed or has an unexpected shape.
	 *
	 * @param string $detail Error detail.
	 * @return self
	 */
	public static function invalid_response( string $detail ): self {
		return new self( 'invalid_response', 'Invalid response: ' . $detail );
	}

	/**
	 * The response has no usable rate for the requested period.
	 *
	 * @return self
	 */
	public static function no_data(): self {
		return new self( 'no_data', 'The response has no usable rate for the requested period.' );
	}

	/**
	 * The source needs a token that is not configured.
	 *
	 * @return self
	 */
	public static function missing_token(): self {
		return new self( 'missing_token', 'The source requires a token and none is configured.' );
	}

	/**
	 * Machine-readable error code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}
}
