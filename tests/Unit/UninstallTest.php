<?php
/**
 * Unit tests for uninstall.php.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Tests the uninstall routine.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

	public function test_uninstall_removes_options_and_scheduled_actions(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'costa-rica-kit-for-woocommerce/costa-rica-kit-for-woocommerce.php' );
		}

		Functions\expect( 'delete_option' )->once()->with( 'crkit_modules' );
		Functions\expect( 'as_unschedule_all_actions' )->once()->with( '', array(), 'crkit' );

		require CRKIT_PLUGIN_DIR . 'uninstall.php';
	}
}
