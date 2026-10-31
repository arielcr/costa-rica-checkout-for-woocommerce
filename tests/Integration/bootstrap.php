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
 * Use our own configuration: a dedicated database, never the development site's one.
 * wp-env's default wp-tests-config.php points to the site database, and the test
 * installer drops every table it finds there.
 */
define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
require_once WP_TESTS_CONFIG_FILE_PATH;

// The installer expects the database to exist. Creating it is the only statement run outside it.
// phpcs:disable WordPress.DB.RestrictedFunctions -- Test bootstrap: WordPress is not loaded yet.
$crkit_mysqli = mysqli_connect( DB_HOST, DB_USER, DB_PASSWORD );
if ( false === $crkit_mysqli ) {
	echo 'Could not connect to the test database server.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}
mysqli_query( $crkit_mysqli, 'CREATE DATABASE IF NOT EXISTS `' . CRKIT_TESTS_DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
mysqli_close( $crkit_mysqli );
// phpcs:enable WordPress.DB.RestrictedFunctions

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
