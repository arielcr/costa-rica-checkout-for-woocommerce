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

	public function test_order_storage_matches_the_requested_mode(): void {
		$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$sync = 'yes' === get_option( 'woocommerce_custom_orders_table_data_sync_enabled' );

		$this->assertSame( 'posts' !== CRKIT_TEST_ORDER_STORAGE, $hpos, 'HPOS usage' );
		$this->assertSame( 'sync' === CRKIT_TEST_ORDER_STORAGE, $sync, 'Compatibility mode' );
	}
}
