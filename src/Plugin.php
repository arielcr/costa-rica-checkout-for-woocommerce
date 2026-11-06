<?php
/**
 * Plugin container: holds the modules and wires the shared integrations.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use CRKit\Admin\SettingsPage;
use CRKit\Admin\SettingsSectionInterface;
use CRKit\Modules\ExchangeRate\ExchangeRateModule;
use CRKit\Modules\Identification\IdentificationModule;
use CRKit\Modules\LifecycleInterface;
use CRKit\Modules\Locations\LocationsModule;
use CRKit\Modules\ModuleInterface;
use CRKit\Modules\PersonalDataInterface;
use CRKit\Modules\SinpeMovil\SinpeMovilModule;

defined( 'ABSPATH' ) || exit;

/**
 * Simple container that registers the enabled modules.
 */
final class Plugin {

	/**
	 * Option that stores the module toggles as `[ module_id => 'yes'|'no' ]`.
	 */
	public const MODULES_OPTION = 'crkit_modules';

	/**
	 * Id of the WooCommerce settings tab.
	 */
	public const SETTINGS_TAB = 'crkit';

	/**
	 * Option (`yes`/`no`) to delete receipts and order data on uninstall.
	 */
	public const DELETE_DATA_OPTION = 'crkit_delete_data_on_uninstall';

	/**
	 * Registered modules, keyed by module id.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private array $modules = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 *
	 * @param ModuleInterface[] $modules Modules to register.
	 */
	public function __construct( array $modules ) {
		foreach ( $modules as $module ) {
			$this->modules[ $module->get_id() ] = $module;
		}
	}

	/**
	 * Builds the plugin with the core modules plus any added through the `crkit_registered_modules` filter.
	 *
	 * @return self
	 */
	public static function create(): self {
		$modules = array(
			new ExchangeRateModule(),
			new SinpeMovilModule(),
			new LocationsModule(),
			new IdentificationModule(),
		);

		/**
		 * Filters the modules registered by the plugin.
		 *
		 * @since 1.0.0
		 *
		 * @param ModuleInterface[] $modules Module instances.
		 */
		$modules = apply_filters( 'crkit_registered_modules', $modules );

		return new self(
			array_filter(
				is_array( $modules ) ? $modules : array(),
				static fn( $module ): bool => $module instanceof ModuleInterface
			)
		);
	}

	/**
	 * Declares compatibility with WooCommerce features. Hooked to `before_woocommerce_init`.
	 *
	 * @return void
	 */
	public static function declare_wc_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', CRKIT_PLUGIN_FILE, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CRKIT_PLUGIN_FILE, true );
	}

	/**
	 * Registers the shared hooks and the enabled modules. Runs once.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CRKIT_PLUGIN_FILE ), array( $this, 'add_plugin_action_links' ) );
		add_action( 'add_option_' . self::MODULES_OPTION, array( $this, 'handle_modules_added' ), 10, 2 );
		add_action( 'update_option_' . self::MODULES_OPTION, array( $this, 'handle_modules_updated' ), 10, 2 );

		foreach ( $this->get_enabled_modules() as $module ) {
			$module->register();
		}

		// Data stored by a module stays after it is disabled, so its privacy tools always load.
		foreach ( $this->modules as $module ) {
			if ( $module instanceof PersonalDataInterface ) {
				$module->register_privacy_tools();
			}
		}

		/**
		 * Fires after the plugin and its enabled modules are registered.
		 *
		 * @since 1.0.0
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'crkit_loaded', $this );
	}

	/**
	 * All registered modules, keyed by id.
	 *
	 * @return array<string, ModuleInterface>
	 */
	public function get_modules(): array {
		return $this->modules;
	}

	/**
	 * Module by id, whether it is enabled or not.
	 *
	 * @param string $id Module id.
	 * @return ModuleInterface|null
	 */
	public function get_module( string $id ): ?ModuleInterface {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Module by id, only if it is enabled.
	 *
	 * @param string $id Module id.
	 * @return ModuleInterface|null
	 */
	public function get_enabled_module( string $id ): ?ModuleInterface {
		return $this->is_module_enabled( $id ) ? $this->modules[ $id ] : null;
	}

	/**
	 * Modules the merchant has enabled (or that are enabled by default), keyed by id.
	 *
	 * @return array<string, ModuleInterface>
	 */
	public function get_enabled_modules(): array {
		return array_filter(
			$this->modules,
			fn( ModuleInterface $module ): bool => $this->is_module_enabled( $module->get_id() )
		);
	}

	/**
	 * Whether a module is enabled. Unknown modules are never enabled.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public function is_module_enabled( string $id ): bool {
		return $this->is_enabled_in( $id, get_option( self::MODULES_OPTION, array() ) );
	}

	/**
	 * Whether a module is enabled according to a given value of the toggles option.
	 *
	 * @param string $id      Module id.
	 * @param mixed  $toggles Value of the `crkit_modules` option.
	 * @return bool
	 */
	private function is_enabled_in( string $id, mixed $toggles ): bool {
		if ( ! isset( $this->modules[ $id ] ) ) {
			return false;
		}

		if ( is_array( $toggles ) && isset( $toggles[ $id ] ) ) {
			return 'yes' === $toggles[ $id ];
		}

		return $this->modules[ $id ]->is_enabled_by_default();
	}

	/**
	 * Enabled modules that add a section to the settings tab, keyed by section id.
	 *
	 * @return array<string, SettingsSectionInterface>
	 */
	public function get_settings_sections(): array {
		$sections = array();

		foreach ( $this->get_enabled_modules() as $module ) {
			if ( $module instanceof SettingsSectionInterface ) {
				$sections[ $module->get_settings_section_id() ] = $module;
			}
		}

		return $sections;
	}

	/**
	 * Plugin activation: sets up the enabled modules.
	 *
	 * @return void
	 */
	public function activate(): void {
		foreach ( $this->get_enabled_modules() as $module ) {
			if ( $module instanceof LifecycleInterface ) {
				$module->activate();
			}
		}
	}

	/**
	 * Plugin deactivation: cleans up every module, enabled or not.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		foreach ( $this->modules as $module ) {
			if ( $module instanceof LifecycleInterface ) {
				$module->deactivate();
			}
		}
	}

	/**
	 * First save of the module toggles.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function handle_modules_added( string $option, mixed $value ): void {
		$this->handle_modules_updated( array(), $value );
	}

	/**
	 * Sets up modules that were just enabled and cleans up those that were just disabled.
	 *
	 * @param mixed $old_value Previous toggles.
	 * @param mixed $new_value New toggles.
	 * @return void
	 */
	public function handle_modules_updated( mixed $old_value, mixed $new_value ): void {
		foreach ( $this->modules as $id => $module ) {
			if ( ! $module instanceof LifecycleInterface ) {
				continue;
			}

			$was_enabled = $this->is_enabled_in( $id, $old_value );
			$is_enabled  = $this->is_enabled_in( $id, $new_value );

			if ( $is_enabled && ! $was_enabled ) {
				$module->activate();
			} elseif ( $was_enabled && ! $is_enabled ) {
				$module->deactivate();
			}
		}
	}

	/**
	 * Adds the "Costa Rica" tab to WooCommerce → Settings.
	 *
	 * @param mixed $pages Settings pages.
	 * @return array<int|string, mixed>
	 */
	public function add_settings_page( mixed $pages ): array {
		$pages   = is_array( $pages ) ? $pages : array();
		$pages[] = new SettingsPage( $this );

		return $pages;
	}

	/**
	 * Adds a "Settings" link to the plugin row in the Plugins screen.
	 *
	 * @param mixed $links Action links.
	 * @return array<int|string, mixed>
	 */
	public function add_plugin_action_links( mixed $links ): array {
		$links = is_array( $links ) ? $links : array();

		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::get_settings_url() ),
			esc_html__( 'Settings', 'costa-rica-checkout-for-woocommerce' )
		);

		return array_merge( array( 'settings' => $settings_link ), $links );
	}

	/**
	 * URL of the plugin settings tab, optionally pointing to a section.
	 *
	 * @param string $section Section id. Empty for the default section.
	 * @return string
	 */
	public static function get_settings_url( string $section = '' ): string {
		$args = array(
			'page' => 'wc-settings',
			'tab'  => self::SETTINGS_TAB,
		);

		if ( '' !== $section ) {
			$args['section'] = $section;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
