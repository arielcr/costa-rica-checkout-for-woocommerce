<?php
/**
 * Unit tests for module lifecycle (activation, deactivation, toggles).
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use Brain\Monkey\Functions;
use CRKit\Admin\SettingsSectionInterface;
use CRKit\Modules\LifecycleInterface;
use CRKit\Modules\ModuleInterface;
use CRKit\Plugin;
use Mockery;
use Mockery\MockInterface;

/**
 * Tests that modules are set up and cleaned up at the right moments.
 *
 * @covers \CRKit\Plugin
 */
final class PluginLifecycleTest extends TestCase {

	public function test_activation_sets_up_enabled_lifecycle_modules_only(): void {
		Functions\when( 'get_option' )->justReturn( array( 'off' => 'no' ) );

		$on = $this->lifecycle_module( 'on' );
		$on->expects( 'activate' )->once();
		$off = $this->lifecycle_module( 'off' );
		$off->shouldNotReceive( 'activate' );

		( new Plugin( array( $on, $off ) ) )->activate();
	}

	public function test_deactivation_cleans_up_every_lifecycle_module(): void {
		Functions\when( 'get_option' )->justReturn( array( 'off' => 'no' ) );

		$on = $this->lifecycle_module( 'on' );
		$on->expects( 'deactivate' )->once();
		$off = $this->lifecycle_module( 'off' );
		$off->expects( 'deactivate' )->once();

		( new Plugin( array( $on, $off ) ) )->deactivate();
	}

	public function test_toggling_activates_and_deactivates_only_what_changed(): void {
		$turned_on = $this->lifecycle_module( 'a' );
		$turned_on->expects( 'activate' )->once();
		$turned_on->shouldNotReceive( 'deactivate' );

		$turned_off = $this->lifecycle_module( 'b' );
		$turned_off->expects( 'deactivate' )->once();
		$turned_off->shouldNotReceive( 'activate' );

		$unchanged = $this->lifecycle_module( 'c' );
		$unchanged->shouldNotReceive( 'activate' );
		$unchanged->shouldNotReceive( 'deactivate' );

		( new Plugin( array( $turned_on, $turned_off, $unchanged ) ) )->handle_modules_updated(
			array(
				'a' => 'no',
				'b' => 'yes',
				'c' => 'yes',
			),
			array(
				'a' => 'yes',
				'b' => 'no',
				'c' => 'yes',
			)
		);
	}

	public function test_first_save_compares_against_module_defaults(): void {
		// Enabled by default and saved as enabled: no change, so no activation.
		$module = $this->lifecycle_module( 'a' );
		$module->shouldNotReceive( 'activate' );

		( new Plugin( array( $module ) ) )->handle_modules_added( Plugin::MODULES_OPTION, array( 'a' => 'yes' ) );
	}

	public function test_settings_sections_come_from_enabled_modules(): void {
		Functions\when( 'get_option' )->justReturn( array( 'hidden' => 'no' ) );

		$shown = Mockery::mock( ModuleInterface::class, SettingsSectionInterface::class );
		$shown->allows( 'get_id' )->andReturn( 'shown' );
		$shown->allows( 'is_enabled_by_default' )->andReturn( true );
		$shown->allows( 'get_settings_section_id' )->andReturn( 'shown_section' );

		$hidden = Mockery::mock( ModuleInterface::class, SettingsSectionInterface::class );
		$hidden->allows( 'get_id' )->andReturn( 'hidden' );
		$hidden->allows( 'is_enabled_by_default' )->andReturn( true );
		$hidden->allows( 'get_settings_section_id' )->andReturn( 'hidden_section' );

		$this->assertSame( array( 'shown_section' ), array_keys( ( new Plugin( array( $shown, $hidden ) ) )->get_settings_sections() ) );
	}

	/**
	 * Module with lifecycle, enabled by default.
	 *
	 * @param string $id Module id.
	 * @return ModuleInterface&LifecycleInterface&MockInterface
	 */
	private function lifecycle_module( string $id ): ModuleInterface {
		$module = Mockery::mock( ModuleInterface::class, LifecycleInterface::class );
		$module->allows( 'get_id' )->andReturn( $id );
		$module->allows( 'is_enabled_by_default' )->andReturn( true );

		return $module;
	}
}
