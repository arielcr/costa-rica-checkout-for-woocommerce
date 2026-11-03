<?php
/**
 * ID in My Account → Account details.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Customer;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce renders and saves the block contact fields in Account details. There the ID is always
 * optional (checked if typed); with the REQUIRED setting, customers without one see a reminder.
 * See docs/DECISIONS.md, ADR-0048.
 */
final class AccountDetails {

	/**
	 * Settings.
	 *
	 * @var IdentificationSettings
	 */
	private IdentificationSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param IdentificationSettings $settings Settings.
	 */
	public function __construct( IdentificationSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_edit_account_form_start', array( $this, 'print_reminder' ) );
		add_action( 'woocommerce_save_account_details', array( $this, 'account_details_saved' ), 20 );
	}

	/**
	 * Whether the current customer should be reminded to add an ID.
	 *
	 * @return bool
	 */
	public function needs_reminder(): bool {
		if ( IdentificationSettings::REQUIRED !== $this->settings->get_requirement() || ! is_user_logged_in() ) {
			return false;
		}

		return null === IdentificationStore::get( new WC_Customer( get_current_user_id() ) );
	}

	/**
	 * Informational notice at the top of the Account details form.
	 *
	 * @return void
	 */
	public function print_reminder(): void {
		if ( $this->needs_reminder() && function_exists( 'wc_print_notice' ) ) {
			wc_print_notice( esc_html__( 'Add your ID number to speed up your next purchases.', 'costa-rica-checkout-for-woocommerce' ), 'notice' );
		}
	}

	/**
	 * After WooCommerce saves Account details, refresh the session copy of the ID.
	 *
	 * @param mixed $user_id User id.
	 * @return void
	 */
	public function account_details_saved( mixed $user_id ): void {
		IdentificationStore::sync_session_customer( absint( $user_id ) );
	}
}
