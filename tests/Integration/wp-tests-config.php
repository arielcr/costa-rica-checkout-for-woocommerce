<?php
/**
 * WordPress test suite configuration for the integration tests.
 *
 * The test installer drops and recreates every table of its database on each run.
 * To make sure it can never reach the development site, the suite uses its own
 * database (`crkit_tests`) on the wp-env MySQL server, never `wordpress`.
 *
 * Loaded through WP_TESTS_CONFIG_FILE_PATH (see bootstrap.php), also by the
 * installer subprocess.
 *
 * @package CRKit\Tests
 */

define( 'CRKIT_TESTS_DB_NAME', 'crkit_tests' );

// Hard stop if the environment ever points the test suite to the site database.
if ( CRKIT_TESTS_DB_NAME === getenv( 'WORDPRESS_DB_NAME' ) ) {
	echo 'Refusing to run: the test database must not be the site database.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
	exit( 1 );
}

define( 'DB_NAME', CRKIT_TESTS_DB_NAME );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ? getenv( 'WORDPRESS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ? getenv( 'WORDPRESS_DB_PASSWORD' ) : 'password' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ? getenv( 'WORDPRESS_DB_HOST' ) : 'mysql' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the WordPress test suite.

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'FS_METHOD', 'direct' );
