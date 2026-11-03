<?php
/**
 * Demo products, shipping zone, checkout pages and customer. Run by bin/seed.sh:
 * `wp eval-file bin/seed-data.php <crc|usd>`. Idempotent.
 *
 * @package CRKit
 */

defined( 'ABSPATH' ) || exit;

$crkit_scenario = isset( $args[0] ) && 'crc' === $args[0] ? 'crc' : 'usd'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP-CLI eval-file arguments.
$crkit_prices   = array(
	'usd' => array(
		'coffee'   => '12.50',
		'shirt-s'  => '20.00',
		'shirt-m'  => '20.00',
		'shirt-l'  => '22.00',
		'hammock'  => '300.00',
		'surf'     => '1200.00',
		'shipping' => '5',
	),
	'crc' => array(
		'coffee'   => '6500',
		'shirt-s'  => '10000',
		'shirt-m'  => '10000',
		'shirt-l'  => '11000',
		'hammock'  => '150000',
		'surf'     => '600000',
		'shipping' => '2500',
	),
);
$crkit_price = $crkit_prices[ $crkit_scenario ];

/**
 * Simple product by SKU, created or updated.
 *
 * @param string $sku   SKU.
 * @param string $name  Name.
 * @param string $price Price.
 * @param string $desc  Short description.
 * @return int Product id.
 */
function crkit_seed_simple( string $sku, string $name, string $price, string $desc ): int {
	$id      = wc_get_product_id_by_sku( $sku );
	$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_sku( $sku );
	$product->set_regular_price( $price );
	$product->set_short_description( $desc );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 50 );
	$product->set_status( 'publish' );

	return $product->save();
}

// WooCommerce's placeholder image (product thumbnails) can point to a missing file in a wp-env site;
// without the option WooCommerce uses the placeholder bundled with the plugin.
$crkit_placeholder = (int) get_option( 'woocommerce_placeholder_image', 0 );
if ( $crkit_placeholder && ! file_exists( (string) get_attached_file( $crkit_placeholder ) ) ) {
	delete_option( 'woocommerce_placeholder_image' );
}

crkit_seed_simple( 'CRK-COFFEE', 'Café de Tarrazú 340 g', $crkit_price['coffee'], 'Café de altura, tueste medio.' );
crkit_seed_simple( 'CRK-HAMMOCK', 'Hamaca de algodón', $crkit_price['hammock'], 'Producto de valor alto: SINPE Móvil sigue disponible (tope ₡500.000).' );
crkit_seed_simple( 'CRK-SURF', 'Tabla de surf', $crkit_price['surf'], 'Supera el tope de ₡500.000: SINPE Móvil no se ofrece.' );

// Variable product.
$crkit_shirt_id = wc_get_product_id_by_sku( 'CRK-SHIRT' );
$crkit_shirt    = $crkit_shirt_id ? new WC_Product_Variable( $crkit_shirt_id ) : new WC_Product_Variable();
$crkit_shirt->set_name( 'Camiseta Pura Vida' );
$crkit_shirt->set_sku( 'CRK-SHIRT' );
$crkit_shirt->set_status( 'publish' );
$crkit_attribute = new WC_Product_Attribute();
$crkit_attribute->set_name( 'Talla' );
$crkit_attribute->set_options( array( 'S', 'M', 'L' ) );
$crkit_attribute->set_visible( true );
$crkit_attribute->set_variation( true );
$crkit_shirt->set_attributes( array( $crkit_attribute ) );
$crkit_shirt_id = $crkit_shirt->save();

foreach ( array( 'S', 'M', 'L' ) as $crkit_size ) {
	$crkit_sku          = 'CRK-SHIRT-' . $crkit_size;
	$crkit_variation_id = wc_get_product_id_by_sku( $crkit_sku );
	$crkit_variation    = $crkit_variation_id ? new WC_Product_Variation( $crkit_variation_id ) : new WC_Product_Variation();
	$crkit_variation->set_parent_id( $crkit_shirt_id );
	$crkit_variation->set_sku( $crkit_sku );
	$crkit_variation->set_attributes( array( 'talla' => $crkit_size ) );
	$crkit_variation->set_regular_price( $crkit_price[ 'shirt-' . strtolower( $crkit_size ) ] );
	$crkit_variation->set_manage_stock( true );
	$crkit_variation->set_stock_quantity( 20 );
	$crkit_variation->set_status( 'publish' );
	$crkit_variation->save();
}
WC_Product_Variable::sync( $crkit_shirt_id );

// Shipping zone for Costa Rica with a flat rate in the store currency.
$crkit_zone = null;
foreach ( WC_Shipping_Zones::get_zones() as $crkit_zone_data ) {
	if ( 'Costa Rica' === $crkit_zone_data['zone_name'] ) {
		$crkit_zone = new WC_Shipping_Zone( $crkit_zone_data['id'] );
	}
}
if ( null === $crkit_zone ) {
	$crkit_zone = new WC_Shipping_Zone();
	$crkit_zone->set_zone_name( 'Costa Rica' );
	$crkit_zone->add_location( 'CR', 'country' );
	$crkit_zone->save();
	$crkit_zone->add_shipping_method( 'flat_rate' );
}
foreach ( $crkit_zone->get_shipping_methods() as $crkit_method ) {
	if ( 'flat_rate' === $crkit_method->id ) {
		update_option(
			$crkit_method->get_instance_option_key(),
			array(
				'title'      => 'Envío por Correos',
				'tax_status' => 'none',
				'cost'       => $crkit_price['shipping'],
			)
		);
	}
}
WC_Cache_Helper::get_transient_version( 'shipping', true );

// Checkout pages: WooCommerce's checkout page uses the block; add one with the classic shortcode.
$crkit_checkout_id = (int) get_option( 'woocommerce_checkout_page_id' );
wp_update_post(
	array(
		'ID'         => $crkit_checkout_id,
		'post_title' => 'Checkout (bloques)',
	)
);
$crkit_classic = get_page_by_path( 'checkout-clasico' );
if ( ! $crkit_classic ) {
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Checkout (clásico)',
			'post_name'    => 'checkout-clasico',
			'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
		)
	);
}

// Customer with a Costa Rican address.
$crkit_customer = get_user_by( 'login', 'cliente' );
$crkit_user_id  = $crkit_customer ? $crkit_customer->ID : wp_insert_user(
	array(
		'user_login' => 'cliente',
		'user_pass'  => 'cliente',
		'user_email' => 'cliente@example.com',
		'first_name' => 'Ana',
		'last_name'  => 'Mora',
		'role'       => 'customer',
	)
);
$crkit_wc_customer = new WC_Customer( $crkit_user_id );
$crkit_wc_customer->set_billing_first_name( 'Ana' );
$crkit_wc_customer->set_billing_last_name( 'Mora' );
$crkit_wc_customer->set_billing_address_1( 'De la iglesia, 100 m norte' );
$crkit_wc_customer->set_billing_city( 'Escazú' );
$crkit_wc_customer->set_billing_state( 'CR-SJ' );
$crkit_wc_customer->set_billing_postcode( '10201' );
$crkit_wc_customer->set_billing_country( 'CR' );
$crkit_wc_customer->set_billing_phone( '88880000' );
$crkit_wc_customer->set_billing_email( 'cliente@example.com' );
$crkit_wc_customer->set_shipping_first_name( 'Ana' );
$crkit_wc_customer->set_shipping_last_name( 'Mora' );
$crkit_wc_customer->set_shipping_address_1( 'De la iglesia, 100 m norte' );
$crkit_wc_customer->set_shipping_city( 'Escazú' );
$crkit_wc_customer->set_shipping_state( 'CR-SJ' );
$crkit_wc_customer->set_shipping_postcode( '10201' );
$crkit_wc_customer->set_shipping_country( 'CR' );
// District (Escazú, Escazú, San José): classic forms and the block checkout field of San José.
foreach ( array( 'billing', 'shipping' ) as $crkit_group ) {
	$crkit_wc_customer->update_meta_data( $crkit_group . '_crkit_district', '10201' );
	$crkit_wc_customer->update_meta_data( '_wc_' . $crkit_group . '/crkit/district-sj', '10201' );
}
// Saved ID (cédula física, sample number): both checkouts and My Account → Account details prefill it.
$crkit_wc_customer->update_meta_data( '_wc_other/crkit/id-type', '01' );
$crkit_wc_customer->update_meta_data( '_wc_other/crkit/id-number', '100000001' );
$crkit_wc_customer->delete_meta_data( '_wc_other/crkit/electronic-invoice' );
$crkit_wc_customer->save();

// Page with the exchange rate shortcode and block ([[…]] prints a shortcode literally).
$crkit_rate_page = get_page_by_path( 'tipo-de-cambio' );
wp_insert_post(
	array(
		'ID'           => $crkit_rate_page ? $crkit_rate_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Tipo de cambio',
		'post_name'    => 'tipo-de-cambio',
		'post_content' => "<!-- wp:paragraph -->\n<p>Shortcode <code>[[crkit_exchange_rate]]</code>:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[crkit_exchange_rate]\n<!-- /wp:shortcode -->\n\n<!-- wp:paragraph -->\n<p>Bloque «Tipo de cambio (Costa Rica)», solo venta:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:crkit/exchange-rate {\"type\":\"sell\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"1rem\",\"bottom\":\"1rem\",\"left\":\"1rem\",\"right\":\"1rem\"}}},\"backgroundColor\":\"accent-5\"} /-->",
	)
);

WP_CLI::success( sprintf( 'Seeded the %s scenario.', strtoupper( $crkit_scenario ) ) );
