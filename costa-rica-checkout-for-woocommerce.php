<?php
/**
 * Plugin Name:          Costa Rica Checkout for WooCommerce
 * Description:          SINPE Móvil payments, exchange rates, provinces/cantons/districts and ID numbers for WooCommerce stores in Costa Rica.
 * Version:              1.0.0
 * Requires at least:    6.7
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               Ariel Orozco
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          costa-rica-checkout-for-woocommerce
 * Domain Path:          /languages
 * WC requires at least: 10.0
 * WC tested up to:      11.2
 *
 * @package CRKit
 */

defined( 'ABSPATH' ) || exit;

define( 'CRKIT_VERSION', '1.0.0' );
define( 'CRKIT_PLUGIN_FILE', __FILE__ );
define( 'CRKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! is_readable( CRKIT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Costa Rica Checkout for WooCommerce is missing its autoloader. Run "composer install" or install the plugin from its release package.', 'costa-rica-checkout-for-woocommerce' )
			);
		}
	);
	return;
}

require_once CRKIT_PLUGIN_DIR . 'vendor/autoload.php';
require_once CRKIT_PLUGIN_DIR . 'src/functions.php';

register_activation_hook( __FILE__, 'crkit_activate' );
register_deactivation_hook( __FILE__, 'crkit_deactivate' );

add_action( 'before_woocommerce_init', array( CRKit\Plugin::class, 'declare_wc_compatibility' ) );
add_action( 'woocommerce_loaded', 'crkit_boot' );
