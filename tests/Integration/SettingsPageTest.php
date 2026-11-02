<?php
/**
 * Integration tests: WooCommerce → Settings → Costa Rica tab.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Admin\SettingsPage;
use CRKit\Plugin;
use WC_Admin_Settings;
use WC_Settings_Page;
use WP_UnitTestCase;

/**
 * Tests the settings tab and how module toggles are saved.
 *
 * @coversNothing
 */
final class SettingsPageTest extends WP_UnitTestCase {

	/**
	 * Loads the WooCommerce admin settings classes, which are only included in wp-admin.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
	}

	public function test_costa_rica_tab_is_registered(): void {
		$page = $this->get_page();

		$this->assertSame( 'crkit', $page->get_id() );
		$this->assertSame( 'Costa Rica', $page->get_label() );
	}

	public function test_default_section_has_one_toggle_per_module(): void {
		$toggles = array_values(
			array_filter(
				$this->get_page()->get_settings_for_section( '' ),
				static fn( array $setting ): bool => 'checkbox' === $setting['type']
			)
		);

		$this->assertSame(
			array(
				'crkit_modules[exchange_rate]',
				'crkit_modules[sinpe_movil]',
				'crkit_modules[locations]',
				'crkit_modules[identification]',
			),
			array_column( $toggles, 'id' )
		);
		$this->assertSame( array( 'yes', 'yes', 'no', 'no' ), array_column( $toggles, 'default' ) );
	}

	public function test_saving_the_tab_stores_toggles_in_a_single_option(): void {
		$settings = $this->get_page()->get_settings_for_section( '' );

		// Only "locations" is checked: unchecked boxes are not posted by the browser.
		WC_Admin_Settings::save_fields( $settings, array( Plugin::MODULES_OPTION => array( 'locations' => '1' ) ) );

		$this->assertSame(
			array(
				'exchange_rate'  => 'no',
				'sinpe_movil'    => 'no',
				'locations'      => 'yes',
				'identification' => 'no',
			),
			get_option( Plugin::MODULES_OPTION )
		);
		$this->assertFalse( crkit_plugin()->is_module_enabled( 'sinpe_movil' ) );
		$this->assertTrue( crkit_plugin()->is_module_enabled( 'locations' ) );
	}

	/**
	 * Returns the plugin settings page as registered in WooCommerce.
	 *
	 * @return WC_Settings_Page
	 */
	private function get_page(): WC_Settings_Page {
		$pages = array_filter(
			apply_filters( 'woocommerce_get_settings_pages', array() ),
			static fn( $page ): bool => $page instanceof SettingsPage
		);

		$this->assertCount( 1, $pages );

		return reset( $pages );
	}
}
