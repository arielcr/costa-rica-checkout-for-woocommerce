<?php
/**
 * Global functions: the plugin's public procedural API.
 *
 * @package CRKit
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use CRKit\Plugin;

/**
 * Returns the shared plugin instance.
 *
 * @return Plugin
 */
function crkit_plugin(): Plugin {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = Plugin::create();
	}

	return $plugin;
}

/**
 * Boots the plugin. Hooked to `woocommerce_loaded`, so it only runs when WooCommerce is active.
 *
 * @return void
 */
function crkit_boot(): void {
	crkit_plugin()->boot();
}
