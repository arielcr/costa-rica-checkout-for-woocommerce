<?php
/**
 * Unit tests for the plugin container.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CRKit\Modules\ModuleInterface;
use CRKit\Plugin;
use Mockery;
use Mockery\MockInterface;

/**
 * Tests the plugin container.
 *
 * @covers \CRKit\Plugin
 */
final class PluginTest extends TestCase {

	private const BASENAME = 'costa-rica-checkout-for-woocommerce/costa-rica-checkout-for-woocommerce.php';

	public function test_module_uses_its_default_when_no_toggle_is_saved(): void {
		Functions\when( 'get_option' )->justReturn( array() );

		$plugin = new Plugin( array( $this->module( 'on', true ), $this->module( 'off', false ) ) );

		$this->assertTrue( $plugin->is_module_enabled( 'on' ) );
		$this->assertFalse( $plugin->is_module_enabled( 'off' ) );
	}

	public function test_saved_toggle_overrides_module_default(): void {
		Functions\expect( 'get_option' )
			->with( Plugin::MODULES_OPTION, array() )
			->andReturn(
				array(
					'on'  => 'no',
					'off' => 'yes',
				)
			);

		$plugin = new Plugin( array( $this->module( 'on', true ), $this->module( 'off', false ) ) );

		$this->assertFalse( $plugin->is_module_enabled( 'on' ) );
		$this->assertTrue( $plugin->is_module_enabled( 'off' ) );
	}

	public function test_corrupt_option_falls_back_to_module_defaults(): void {
		Functions\when( 'get_option' )->justReturn( 'not-an-array' );

		$plugin = new Plugin( array( $this->module( 'on', true ) ) );

		$this->assertTrue( $plugin->is_module_enabled( 'on' ) );
	}

	public function test_unknown_module_is_never_enabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'ghost' => 'yes' ) );

		$this->assertFalse( ( new Plugin( array() ) )->is_module_enabled( 'ghost' ) );
	}

	public function test_get_enabled_modules_filters_by_toggle(): void {
		Functions\when( 'get_option' )->justReturn( array( 'b' => 'no' ) );

		$plugin = new Plugin( array( $this->module( 'a', true ), $this->module( 'b', true ), $this->module( 'c', false ) ) );

		$this->assertSame( array( 'a' ), array_keys( $plugin->get_enabled_modules() ) );
	}

	public function test_boot_registers_hooks_and_only_enabled_modules(): void {
		Functions\when( 'get_option' )->justReturn( array( 'disabled' => 'no' ) );
		Functions\when( 'plugin_basename' )->justReturn( self::BASENAME );

		$enabled = $this->module( 'enabled', true );
		$enabled->expects( 'register' )->once();
		$disabled = $this->module( 'disabled', true );
		$disabled->shouldNotReceive( 'register' );

		$plugin = new Plugin( array( $enabled, $disabled ) );
		Actions\expectDone( 'crkit_loaded' )->once()->with( $plugin );

		$plugin->boot();

		$this->assertNotFalse( has_filter( 'woocommerce_get_settings_pages', array( $plugin, 'add_settings_page' ) ) );
		$this->assertNotFalse( has_filter( 'plugin_action_links_' . self::BASENAME, array( $plugin, 'add_plugin_action_links' ) ) );
	}

	public function test_boot_runs_only_once(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'plugin_basename' )->justReturn( self::BASENAME );

		$module = $this->module( 'module', true );
		$module->expects( 'register' )->once();

		$plugin = new Plugin( array( $module ) );
		$plugin->boot();
		$plugin->boot();
	}

	public function test_create_registers_the_core_modules_in_order(): void {
		$this->assertSame(
			array( 'exchange_rate', 'sinpe_movil', 'locations', 'identification' ),
			array_keys( Plugin::create()->get_modules() )
		);
	}

	public function test_create_accepts_filtered_modules_and_discards_invalid_entries(): void {
		$extra = $this->module( 'extra', false );

		Filters\expectApplied( 'crkit_registered_modules' )
			->once()
			->andReturnUsing(
				static fn( array $modules ): array => array_merge( $modules, array( $extra, 'not-a-module', null ) )
			);

		$modules = Plugin::create()->get_modules();

		$this->assertCount( 5, $modules );
		$this->assertSame( $extra, $modules['extra'] );
	}

	public function test_create_survives_a_filter_returning_a_non_array(): void {
		Filters\expectApplied( 'crkit_registered_modules' )->once()->andReturn( null );

		$this->assertSame( array(), Plugin::create()->get_modules() );
	}

	public function test_plugin_action_links_start_with_the_settings_link(): void {
		$this->stub_admin_url_functions();

		$links = ( new Plugin( array() ) )->add_plugin_action_links( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );

		$this->assertSame( array( 'settings', 'deactivate' ), array_keys( $links ) );
		$this->assertStringContainsString( 'page=wc-settings', $links['settings'] );
		$this->assertStringContainsString( 'tab=crkit', $links['settings'] );
		$this->assertStringContainsString( '>Settings</a>', $links['settings'] );
	}

	public function test_plugin_action_links_tolerate_a_non_array_value(): void {
		$this->stub_admin_url_functions();

		$this->assertSame( array( 'settings' ), array_keys( ( new Plugin( array() ) )->add_plugin_action_links( null ) ) );
	}

	public function test_settings_url_can_point_to_a_section(): void {
		$this->stub_admin_url_functions();

		$this->assertSame(
			'https://example.org/wp-admin/admin.php?page=wc-settings&tab=crkit&section=sinpe_movil',
			Plugin::get_settings_url( 'sinpe_movil' )
		);
		$this->assertSame(
			'https://example.org/wp-admin/admin.php?page=wc-settings&tab=crkit',
			Plugin::get_settings_url()
		);
	}

	public function test_crkit_plugin_returns_a_shared_instance(): void {
		$this->assertSame( crkit_plugin(), crkit_plugin() );
	}

	/**
	 * Mock module with a fixed id and default state.
	 *
	 * @param string $id                 Module id.
	 * @param bool   $enabled_by_default Default state.
	 * @return ModuleInterface&MockInterface
	 */
	private function module( string $id, bool $enabled_by_default ): ModuleInterface {
		$module = Mockery::mock( ModuleInterface::class );
		$module->allows( 'get_id' )->andReturn( $id );
		$module->allows( 'is_enabled_by_default' )->andReturn( $enabled_by_default );

		return $module;
	}

	private function stub_admin_url_functions(): void {
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://example.org/wp-admin/' . $path );
		Functions\when( 'add_query_arg' )->alias( static fn( array $args, string $url ): string => $url . '?' . http_build_query( $args ) );
	}
}
