<?php
/**
 * Unit tests for uninstall.php.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Tests the uninstall routine when data must be kept. Data deletion is covered by the integration suite.
 *
 * @coversNothing
 */
final class UninstallTest extends TestCase {

	public function test_uninstall_removes_options_and_scheduled_actions_but_keeps_data(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'costa-rica-kit-for-woocommerce/costa-rica-kit-for-woocommerce.php' );
		}

		$this->use_options_store(
			array(
				'crkit_modules'                  => array( 'sinpe_movil' => 'yes' ),
				'crkit_exchange_rate'            => array( 'sell' => 456.04 ),
				'crkit_exchange_rate_settings'   => array( 'source' => 'bccr' ),
				'crkit_delete_data_on_uninstall' => 'no',
				'unrelated_option'               => 'kept',
			)
		);
		Functions\expect( 'as_unschedule_all_actions' )->once()->with( '', array(), 'crkit' );
		Functions\expect( 'wp_upload_dir' )->never();

		require CRKIT_PLUGIN_DIR . 'uninstall.php';

		$this->assertSame( array( 'unrelated_option' => 'kept' ), $this->options );
	}
}
