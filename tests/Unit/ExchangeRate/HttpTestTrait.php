<?php
/**
 * Helpers to fake wp_remote_get() responses.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\ExchangeRate;

use Brain\Monkey\Functions;

/**
 * Fakes the WordPress HTTP API.
 */
trait HttpTestTrait {

	/**
	 * Last request made through wp_remote_get().
	 *
	 * @var array{url: string, args: array<string, mixed>}|null
	 */
	protected ?array $last_request = null;

	/**
	 * Makes wp_remote_get() return the given status and body.
	 *
	 * @param int    $status HTTP status.
	 * @param string $body   Response body.
	 */
	protected function fake_response( int $status, string $body ): void {
		Functions\when( 'wp_remote_get' )->alias(
			function ( string $url, array $args ) use ( $status, $body ): array {
				$this->last_request = array(
					'url'  => $url,
					'args' => $args,
				);
				return array(
					'response' => array( 'code' => $status ),
					'body'     => $body,
				);
			}
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( array $response ) => $response['response']['code'] );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( array $response ) => $response['body'] );
	}

	/**
	 * Makes wp_remote_get() fail with a WP_Error.
	 *
	 * @param string $message Error message.
	 */
	protected function fake_network_error( string $message ): void {
		$error = \Mockery::mock( 'WP_Error' );
		$error->allows( 'get_error_message' )->andReturn( $message );

		Functions\when( 'wp_remote_get' )->justReturn( $error );
	}
}
