<?php
/**
 * Uninstall routine.
 *
 * Always removes the plugin options and its scheduled actions. Payment receipts and the
 * data added to orders are only deleted if the merchant enabled "Delete data on uninstall".
 *
 * The plugin is not loaded here, so this file only uses WordPress core functions and,
 * when WooCommerce is active, Action Scheduler.
 *
 * @package CRKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$crkit_delete_data = 'yes' === get_option( 'crkit_delete_data_on_uninstall', 'no' );

$crkit_options = array(
	'crkit_modules',
	'crkit_delete_data_on_uninstall',
	'crkit_exchange_rate',
	'crkit_exchange_rate_status',
	'crkit_exchange_rate_settings',
	'woocommerce_crkit_sinpe_movil_settings',
	'woocommerce_crkit_sinpe_receipt_uploaded_settings',
);

foreach ( $crkit_options as $crkit_option ) {
	delete_option( $crkit_option );
}

// Action Scheduler ships with WooCommerce; it is unavailable if WooCommerce is inactive.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'crkit' );
}

if ( $crkit_delete_data ) {
	// Payment receipts.
	$crkit_uploads = wp_upload_dir( null, false );
	$crkit_dir     = trailingslashit( $crkit_uploads['basedir'] ) . 'crkit-receipts';

	if ( is_dir( $crkit_dir ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( WP_Filesystem() ) {
			global $wp_filesystem;
			$wp_filesystem->delete( $crkit_dir, true );
		}
	}

	/*
	 * Order meta added by the plugin, in both order storages: the WooCommerce order tables (HPOS)
	 * and the posts meta table (legacy storage and sync mode). The CRUD cannot be used for a bulk
	 * delete without loading every order. Customer meta lives in the users meta table.
	 *
	 * - `_crkit_*`: SINPE Móvil payment data and receipts.
	 * - `_billing_crkit_*`, `_shipping_crkit_*`: Costa Rican district of order addresses.
	 * - `_wc_billing/crkit/*`, `_wc_shipping/crkit/*`: block checkout district fields.
	 */
	global $wpdb;
	$crkit_order_meta = array( '_crkit_', '_billing_crkit_', '_shipping_crkit_', '_wc_billing/crkit/', '_wc_shipping/crkit/' );
	$crkit_user_meta  = array( 'billing_crkit_', 'shipping_crkit_', '_wc_billing/crkit/', '_wc_shipping/crkit/' );

	$crkit_orders_meta = $wpdb->prefix . 'wc_orders_meta';
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off cleanup on uninstall; nothing to cache.
	$crkit_has_hpos = $crkit_orders_meta === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $crkit_orders_meta ) ) );

	foreach ( $crkit_order_meta as $crkit_prefix ) {
		$crkit_like = $wpdb->esc_like( $crkit_prefix ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $crkit_like ) );

		if ( $crkit_has_hpos ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $crkit_orders_meta, $crkit_like ) );
		}
	}

	foreach ( $crkit_user_meta as $crkit_prefix ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( $crkit_prefix ) . '%' ) );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery
}
