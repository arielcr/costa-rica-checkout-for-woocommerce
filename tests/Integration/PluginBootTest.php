<?php
/**
 * Integration tests: plugin boot with WordPress and WooCommerce loaded.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use CRKit\Plugin;
use WP_UnitTestCase;

/**
 * Tests that the plugin boots and declares its WooCommerce compatibility.
 *
 * @coversNothing
 */
final class PluginBootTest extends WP_UnitTestCase {

	private const BASENAME = 'costa-rica-checkout-for-woocommerce/costa-rica-checkout-for-woocommerce.php';

	public function test_plugin_boots_after_woocommerce(): void {
		$this->assertSame( 1, did_action( 'crkit_loaded' ) );
		$this->assertTrue( defined( 'CRKIT_VERSION' ) );
	}

	public function test_version_constant_matches_plugin_header(): void {
		$header = get_file_data( CRKIT_PLUGIN_FILE, array( 'Version' => 'Version' ) );

		$this->assertSame( CRKIT_VERSION, $header['Version'] );
	}

	/**
	 * Feature ids declared by the plugin.
	 *
	 * @return array<string, array{string}>
	 */
	public static function provide_features(): array {
		return array(
			'HPOS'           => array( 'custom_order_tables' ),
			'block checkout' => array( 'cart_checkout_blocks' ),
		);
	}

	/**
	 * The plugin is listed as compatible with each WooCommerce feature.
	 *
	 * @dataProvider provide_features
	 *
	 * @param string $feature Feature id.
	 */
	public function test_declares_compatibility_with_feature( string $feature ): void {
		$plugins = FeaturesUtil::get_compatible_plugins_for_feature( $feature );

		$this->assertContains( self::BASENAME, $plugins['compatible'] );
		$this->assertNotContains( self::BASENAME, $plugins['incompatible'] );
	}

	public function test_plugin_row_has_settings_link(): void {
		$links = apply_filters( 'plugin_action_links_' . self::BASENAME, array() );

		$this->assertArrayHasKey( 'settings', $links );
		$this->assertStringContainsString( esc_url( Plugin::get_settings_url() ), $links['settings'] );
	}
}
