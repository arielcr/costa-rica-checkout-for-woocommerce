<?php
/**
 * Contract for a feature module that the merchant can enable or disable.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * A self-contained feature (payment gateway, exchange rate, locations, ID number).
 */
interface ModuleInterface {

	/**
	 * Unique, stable identifier. Used as the key in the `crkit_modules` option.
	 *
	 * @return string Lowercase snake_case id, e.g. `sinpe_movil`.
	 */
	public function get_id(): string;

	/**
	 * Translated module name shown in the settings.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Translated one-line description shown next to the module toggle.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Whether the module is enabled before the merchant saves the settings for the first time.
	 *
	 * @return bool
	 */
	public function is_enabled_by_default(): bool;

	/**
	 * Registers the module hooks. Only called when the module is enabled.
	 *
	 * @return void
	 */
	public function register(): void;
}
