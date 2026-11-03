<?php
/**
 * WooCommerce → Settings → Costa Rica tab.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Admin;

use CRKit\Plugin;
use WC_Settings_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Settings tab built on the WooCommerce Settings API. The default section holds the module toggles;
 * each module adds its own section when it has settings.
 */
final class SettingsPage extends WC_Settings_Page {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		$this->id     = Plugin::SETTINGS_TAB;
		$this->label  = __( 'Costa Rica', 'costa-rica-kit-for-woocommerce' );

		parent::__construct();
	}

	/**
	 * Own sections of the tab.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections(): array {
		$sections = array(
			'' => __( 'Modules', 'costa-rica-kit-for-woocommerce' ),
		);

		foreach ( $this->plugin->get_settings_sections() as $id => $section ) {
			$sections[ $id ] = $section->get_settings_section_label();
		}

		return $sections;
	}

	/**
	 * Settings of a module section.
	 *
	 * @param string $section_id Section id.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_section_core( $section_id ): array {
		$sections = $this->plugin->get_settings_sections();

		return isset( $sections[ $section_id ] ) ? $sections[ $section_id ]->get_settings_section_fields() : array();
	}

	/**
	 * Settings of the default section: one toggle per module.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_default_section(): array {
		$settings = array(
			array(
				'id'    => 'crkit_modules_section',
				'type'  => 'title',
				'title' => __( 'Modules', 'costa-rica-kit-for-woocommerce' ),
				'desc'  => __( 'Enable only the features your store needs. Disabled modules add nothing to your store.', 'costa-rica-kit-for-woocommerce' ),
			),
		);

		foreach ( $this->plugin->get_modules() as $id => $module ) {
			$settings[] = array(
				'id'      => Plugin::MODULES_OPTION . '[' . $id . ']',
				'type'    => 'checkbox',
				'title'   => $module->get_title(),
				'desc'    => $module->get_description(),
				'default' => $module->is_enabled_by_default() ? 'yes' : 'no',
			);
		}

		$settings[] = array(
			'id'   => 'crkit_modules_section',
			'type' => 'sectionend',
		);

		$settings[] = array(
			'id'    => 'crkit_data_section',
			'type'  => 'title',
			'title' => __( 'Data', 'costa-rica-kit-for-woocommerce' ),
		);
		$settings[] = array(
			'id'      => Plugin::DELETE_DATA_OPTION,
			'type'    => 'checkbox',
			'title'   => __( 'Delete data on uninstall', 'costa-rica-kit-for-woocommerce' ),
			'desc'    => __( 'When the plugin is deleted, also delete SINPE Móvil payment receipts and the data this plugin added to orders and customers. Settings are always deleted.', 'costa-rica-kit-for-woocommerce' ),
			'default' => 'no',
		);
		$settings[] = array(
			'id'   => 'crkit_data_section',
			'type' => 'sectionend',
		);

		return $settings;
	}
}
