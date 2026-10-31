<?php
/**
 * Base class for unit tests.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Sets up and tears down Brain Monkey around each test.
 */
abstract class TestCase extends PolyfillTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * In-memory options used by use_options_store().
	 *
	 * @var array<string, mixed>
	 */
	protected array $options = array();

	/**
	 * Sets up Brain Monkey and the most common WordPress function stubs.
	 */
	protected function set_up(): void {
		parent::set_up();
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
		Monkey\Functions\stubEscapeFunctions();
		Monkey\Functions\stubs(
			array(
				'sanitize_key'      => static fn( $key ): string => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ),
				'absint'            => static fn( $value ): int => abs( (int) $value ),
				'untrailingslashit' => static fn( string $value ): string => rtrim( $value, '/\\' ),
				'wp_date'           => static fn( string $format, ?int $timestamp = null ): string => gmdate( $format, $timestamp ?? time() ),
				'is_wp_error'       => static fn( $thing ): bool => $thing instanceof \WP_Error,
			)
		);
		$this->options = array();
	}

	/**
	 * Tears down Brain Monkey.
	 */
	protected function tear_down(): void {
		Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Backs get_option()/update_option()/delete_option() with $this->options.
	 *
	 * @param array<string, mixed> $options Initial options.
	 */
	protected function use_options_store( array $options = array() ): void {
		$this->options = $options;

		Monkey\Functions\when( 'get_option' )->alias(
			fn( string $name, $default_value = false ) => array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default_value
		);
		Monkey\Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Monkey\Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );
				return true;
			}
		);
	}

	/**
	 * Decoded JSON fixture from tests/fixtures.
	 *
	 * @param string $path Path relative to tests/fixtures.
	 * @return array<mixed>
	 */
	protected static function fixture( string $path ): array {
		return json_decode( self::raw_fixture( $path ), true );
	}

	/**
	 * Raw content of a fixture from tests/fixtures.
	 *
	 * @param string $path Path relative to tests/fixtures.
	 * @return string
	 */
	protected static function raw_fixture( string $path ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.
	}
}
