<?php
/**
 * Contract for modules that add a section to the Costa Rica settings tab.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * A section of WooCommerce → Settings → Costa Rica.
 */
interface SettingsSectionInterface {

	/**
	 * Section id, used in the `section` query argument.
	 *
	 * @return string
	 */
	public function get_settings_section_id(): string;

	/**
	 * Translated section label.
	 *
	 * @return string
	 */
	public function get_settings_section_label(): string;

	/**
	 * WooCommerce Settings API fields of the section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings_section_fields(): array;
}
