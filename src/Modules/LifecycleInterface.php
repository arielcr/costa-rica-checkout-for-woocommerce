<?php
/**
 * Optional contract for modules that need setup or cleanup.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Called when the plugin is activated or deactivated, and when the merchant toggles the module.
 */
interface LifecycleInterface {

	/**
	 * Sets the module up (for example, schedules background actions).
	 *
	 * @return void
	 */
	public function activate(): void;

	/**
	 * Cleans the module up (for example, unschedules background actions). Must be safe to call twice.
	 *
	 * @return void
	 */
	public function deactivate(): void;
}
