<?php
/**
 * Personal data: privacy policy text, export and erasure of ID numbers.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Customer;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the ID to WooCommerce's own personal data exporters and erasers (customer and orders).
 * Orders follow the WooCommerce setting "Remove personal data from orders on request".
 */
final class Privacy {

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'woocommerce_privacy_export_customer_personal_data', array( $this, 'export_customer' ), 10, 2 );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( $this, 'export_order' ), 10, 2 );
		add_filter( 'woocommerce_privacy_erase_personal_data_customer', array( $this, 'erase_customer' ), 10, 2 );
		add_filter( 'woocommerce_privacy_remove_order_personal_data_meta', array( $this, 'order_meta_to_remove' ) );
		add_filter( 'woocommerce_privacy_remove_order_personal_data_meta_value', array( $this, 'delete_order_meta_value' ), 10, 2 );
	}

	/**
	 * Suggested text for the privacy policy.
	 *
	 * @return void
	 */
	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">' . esc_html__( 'Suggested text for stores that ask for an ID number at checkout:', 'costa-rica-checkout-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'At checkout we ask for your ID type and number (cédula física, cédula jurídica, DIMEX or NITE) to issue the electronic invoice that Costa Rican tax law requires. We store it with your order and, if you have an account, in your account so you do not have to type it again. We keep it as long as we keep the order records, and you can change it in your account.', 'costa-rica-checkout-for-woocommerce' ) . '</p>';

		wp_add_privacy_policy_content( __( 'Costa Rica Checkout for WooCommerce: ID number', 'costa-rica-checkout-for-woocommerce' ), wp_kses_post( $content ) );
	}

	/**
	 * Export rows of an identification.
	 *
	 * @param Identification $identification Identification.
	 * @return array<int, array{name: string, value: string}>
	 */
	private function get_rows( Identification $identification ): array {
		$rows = array(
			array(
				'name'  => __( 'ID type', 'costa-rica-checkout-for-woocommerce' ),
				'value' => '' === $identification->get_type_label() ? $identification->type : $identification->get_type_label(),
			),
			array(
				'name'  => __( 'ID number', 'costa-rica-checkout-for-woocommerce' ),
				'value' => $identification->number,
			),
		);

		if ( $identification->electronic_invoice ) {
			$rows[] = array(
				'name'  => __( 'Electronic invoice', 'costa-rica-checkout-for-woocommerce' ),
				'value' => __( 'Requested', 'costa-rica-checkout-for-woocommerce' ),
			);
		}

		return $rows;
	}

	/**
	 * Adds the ID to the customer data export.
	 *
	 * @param mixed $personal_data Name and value pairs.
	 * @param mixed $customer      Customer.
	 * @return array<int, mixed>
	 */
	public function export_customer( mixed $personal_data, mixed $customer ): array {
		$personal_data  = is_array( $personal_data ) ? $personal_data : array();
		$identification = $customer instanceof WC_Customer ? IdentificationStore::get( $customer ) : null;

		return null === $identification ? $personal_data : array_merge( $personal_data, $this->get_rows( $identification ) );
	}

	/**
	 * Adds the ID to the export of each order.
	 *
	 * @param mixed $personal_data Name and value pairs.
	 * @param mixed $order         Order.
	 * @return array<int, mixed>
	 */
	public function export_order( mixed $personal_data, mixed $order ): array {
		$personal_data  = is_array( $personal_data ) ? $personal_data : array();
		$identification = $order instanceof WC_Order ? IdentificationStore::get( $order ) : null;

		return null === $identification ? $personal_data : array_merge( $personal_data, $this->get_rows( $identification ) );
	}

	/**
	 * Removes the ID from the customer. WooCommerce has already saved the customer.
	 *
	 * @param mixed $response Eraser response.
	 * @param mixed $customer Customer.
	 * @return array<string, mixed>
	 */
	public function erase_customer( mixed $response, mixed $customer ): array {
		$response = is_array( $response ) ? $response : array();

		if ( $customer instanceof WC_Customer && IdentificationStore::delete( $customer ) ) {
			$customer->save();

			$response['messages']      = isset( $response['messages'] ) && is_array( $response['messages'] ) ? $response['messages'] : array();
			$response['messages'][]    = __( 'Removed the customer ID number.', 'costa-rica-checkout-for-woocommerce' );
			$response['items_removed'] = true;
		}

		return $response;
	}

	/**
	 * Order meta that WooCommerce removes when it erases the personal data of an order.
	 *
	 * @param mixed $meta Meta keys and data types.
	 * @return array<string, string>
	 */
	public function order_meta_to_remove( mixed $meta ): array {
		$meta = is_array( $meta ) ? $meta : array();

		$meta[ IdentificationStore::get_meta_key( IdentificationStore::TYPE_FIELD ) ]    = 'text';
		$meta[ IdentificationStore::get_meta_key( IdentificationStore::NUMBER_FIELD ) ]  = 'text';
		$meta[ IdentificationStore::get_meta_key( IdentificationStore::INVOICE_FIELD ) ] = 'text';

		return $meta;
	}

	/**
	 * Deletes the ID meta instead of storing an anonymized "[deleted]" text.
	 *
	 * @param mixed $anon_value Anonymized value.
	 * @param mixed $meta_key   Meta key.
	 * @return mixed
	 */
	public function delete_order_meta_value( mixed $anon_value, mixed $meta_key ): mixed {
		return is_string( $meta_key ) && str_starts_with( $meta_key, IdentificationStore::META_PREFIX . 'crkit/' ) ? '' : $anon_value;
	}
}
