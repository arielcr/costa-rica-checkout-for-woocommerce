<?php
/**
 * Unit test bootstrap. WordPress is not loaded: its functions are mocked with Brain Monkey.
 *
 * @package CRKit\Tests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'CRKIT_VERSION', '0.1.0' );
define( 'CRKIT_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/costa-rica-kit-for-woocommerce.php' );
define( 'CRKIT_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
define( 'CRKIT_PLUGIN_URL', 'https://example.org/wp-content/plugins/costa-rica-kit-for-woocommerce/' );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__, 2 ) . '/src/functions.php';
