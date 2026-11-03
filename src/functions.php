<?php
/**
 * Global functions: the plugin's public procedural API.
 *
 * @package CRKit
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\ExchangeRateModule;
use CRKit\Modules\ExchangeRate\ExchangeRateService;
use CRKit\Modules\Identification\OrderIdentification;
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

/**
 * Plugin activation. Hooked with register_activation_hook().
 *
 * @return void
 */
function crkit_activate(): void {
	crkit_plugin()->activate();
}

/**
 * Plugin deactivation. Hooked with register_deactivation_hook().
 *
 * @return void
 */
function crkit_deactivate(): void {
	crkit_plugin()->deactivate();
}

/**
 * Exchange rate service, or null if the exchange rate module is disabled.
 *
 * @return ExchangeRateService|null
 */
function crkit_exchange_rate_service(): ?ExchangeRateService {
	$module = crkit_plugin()->get_enabled_module( ExchangeRateModule::ID );

	return $module instanceof ExchangeRateModule ? $module->get_service() : null;
}

/**
 * Usable US dollar exchange rate in colones, from the cache (never calls external services).
 *
 * Returns null if the module is disabled, there is no rate yet, or the automatic rate is outdated.
 *
 * @param string $type `sell` (default) or `buy`.
 * @return float|null
 */
function crkit_get_exchange_rate( string $type = ExchangeRate::SELL ): ?float {
	$service = crkit_exchange_rate_service();

	return null === $service ? null : $service->get_rate( $type );
}

/**
 * Converts an amount to colones with the usable sell rate.
 *
 * CRC amounts are returned as is. Returns null for unsupported currencies or when there is no usable rate.
 *
 * @param float  $amount   Amount.
 * @param string $currency ISO 4217 currency code, e.g. `USD`.
 * @return float|null
 */
function crkit_convert_to_crc( float $amount, string $currency ): ?float {
	$service = crkit_exchange_rate_service();

	if ( null === $service ) {
		return 'CRC' === strtoupper( $currency ) ? $amount : null;
	}

	return $service->convert_to_crc( $amount, $currency );
}

/**
 * Customer identification of an order, for e-invoicing plugins. Available even if the ID module is
 * disabled later, as long as the order has an ID.
 *
 * @param WC_Order|int $order Order or order id.
 * @return array{type: string, type_label: string, number: string, electronic_invoice: bool}|null
 *         `type` is Hacienda's code (01 física, 02 jurídica, 03 DIMEX, 04 NITE), `number` has no
 *         spaces or hyphens. Null if the order has no ID.
 */
function crkit_get_order_identification( $order ): ?array {
	$order = $order instanceof WC_Order ? $order : wc_get_order( absint( $order ) );

	return $order instanceof WC_Order ? OrderIdentification::get( $order ) : null;
}
