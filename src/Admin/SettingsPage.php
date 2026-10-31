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
		return array(
			'' => __( 'Modules', 'costa-rica-kit-for-woocommerce' ),
		);
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

		return $settings;
	}
}
