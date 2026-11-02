<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin options and its scheduled actions. Order data and payment
 * receipts are kept unless the merchant opts in to deleting all data.
 *
 * @package CRKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'crkit_modules' );

// Action Scheduler ships with WooCommerce; it is unavailable if WooCommerce is inactive.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'crkit' );
}
