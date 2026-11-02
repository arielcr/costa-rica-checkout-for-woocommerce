<?php
/**
 * Integration tests: the suite runs against its own database.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use WP_UnitTestCase;

/**
 * Guards against the test suite ever running on the development site database.
 *
 * @coversNothing
 */
final class TestEnvironmentTest extends WP_UnitTestCase {

	public function test_suite_uses_the_dedicated_test_database(): void {
		global $wpdb;

		$this->assertSame( 'crkit_tests', DB_NAME );
		$this->assertSame( 'crkit_tests', $wpdb->dbname );
		$this->assertNotSame( getenv( 'WORDPRESS_DB_NAME' ), $wpdb->dbname );
		$this->assertSame( 'wptests_', $wpdb->prefix );
	}
}
