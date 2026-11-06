<?php
/**
 * Unit tests for the core module definitions.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use CRKit\Modules\ExchangeRate\ExchangeRateModule;
use CRKit\Modules\Identification\IdentificationModule;
use CRKit\Modules\Locations\LocationsModule;
use CRKit\Modules\ModuleInterface;
use CRKit\Modules\SinpeMovil\SinpeMovilModule;

/**
 * Tests the core module definitions.
 *
 * @covers \CRKit\Modules\ExchangeRate\ExchangeRateModule
 * @covers \CRKit\Modules\Identification\IdentificationModule
 * @covers \CRKit\Modules\Locations\LocationsModule
 * @covers \CRKit\Modules\SinpeMovil\SinpeMovilModule
 */
final class ModulesTest extends TestCase {

	/**
	 * Core modules with their expected id and default state.
	 *
	 * Modules that change the checkout form are opt-in; see docs/DECISIONS.md.
	 *
	 * @return array<string, array{ModuleInterface, string, bool}>
	 */
	public static function provide_modules(): array {
		return array(
			'exchange rate'  => array( new ExchangeRateModule(), 'exchange_rate', false ),
			'sinpe movil'    => array( new SinpeMovilModule(), 'sinpe_movil', true ),
			'locations'      => array( new LocationsModule(), 'locations', false ),
			'identification' => array( new IdentificationModule(), 'identification', false ),
		);
	}

	/**
	 * Each module exposes a stable id, its default state and translated texts.
	 *
	 * @dataProvider provide_modules
	 *
	 * @param ModuleInterface $module             Module under test.
	 * @param string          $id                 Expected id.
	 * @param bool            $enabled_by_default Expected default state.
	 */
	public function test_module_definition( ModuleInterface $module, string $id, bool $enabled_by_default ): void {
		$this->assertSame( $id, $module->get_id() );
		$this->assertSame( $enabled_by_default, $module->is_enabled_by_default() );
		$this->assertNotSame( '', $module->get_title() );
		$this->assertNotSame( '', $module->get_description() );
	}

	/**
	 * Module ids are used as array keys inside the `crkit_modules` option.
	 *
	 * @dataProvider provide_modules
	 *
	 * @param ModuleInterface $module Module under test.
	 */
	public function test_module_ids_are_safe_option_keys( ModuleInterface $module ): void {
		$this->assertMatchesRegularExpression( '/^[a-z][a-z0-9_]*$/', $module->get_id() );
	}
}
