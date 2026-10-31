<?php
/**
 * Integration test bootstrap: loads the WordPress test suite with WooCommerce and this plugin.
 *
 * Run inside wp-env: `npm run test:php:integration`.
 *
 * @package CRKit\Tests
 */

$crkit_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( false === $crkit_tests_dir || ! is_readable( $crkit_tests_dir . '/includes/functions.php' ) ) {
	echo 'WordPress test suite not found. Run the integration tests inside wp-env: npm run test:php:integration' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}

/*
 * wp-env runs the site and the test suite against the same database, and the test installer
 * drops every table that uses its prefix. A dedicated prefix keeps the development site intact.
 */
putenv( 'WORDPRESS_TABLE_PREFIX=wptests_' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Read by wp-env's wp-tests-config.php; test-only.

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
require_once $crkit_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		require_once dirname( __DIR__, 2 ) . '/costa-rica-kit-for-woocommerce.php';
	}
);

tests_add_filter(
	'setup_theme',
	static function (): void {
		WC_Install::install();

		// Reload capabilities after the install. See https://core.trac.wordpress.org/ticket/28374.
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to refresh roles in tests.
		wp_roles();
	}
);

require_once $crkit_tests_dir . '/includes/bootstrap.php';
