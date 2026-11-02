<?php
/**
 * ID fields in the classic (shortcode) checkout.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Customer;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the same fields as the block checkout to the billing section and stores them under the same
 * meta keys (IdentificationStore). See docs/DECISIONS.md, ADR-0047.
 */
final class ClassicCheckout {

	/**
	 * Posted field name of the ID type.
	 */
	public const TYPE_KEY = 'crkit_id_type';

	/**
	 * Posted field name of the ID number.
	 */
	public const NUMBER_KEY = 'crkit_id_number';

	/**
	 * Posted field name of the "I need an electronic invoice" checkbox.
	 */
	public const INVOICE_KEY = 'crkit_electronic_invoice';

	/**
	 * Script handle.
	 */
	public const SCRIPT_HANDLE = 'crkit-identification-classic';

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
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_fields' ) );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'get_saved_value' ), 10, 2 );
		add_filter( 'woocommerce_form_field', array( $this, 'remove_optional_marker' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_customer', array( $this, 'save_to_customer' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'order_processed' ), 10, 3 );
		add_action( 'woocommerce_save_account_details', array( $this, 'account_details_saved' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Adds the fields after the billing email.
	 *
	 * @param mixed $fields Checkout fields per group.
	 * @return array<string, mixed>
	 */
	public function add_fields( mixed $fields ): array {
		$fields      = is_array( $fields ) ? $fields : array();
		$requirement = $this->settings->get_requirement();
		$billing     = isset( $fields['billing'] ) && is_array( $fields['billing'] ) ? $fields['billing'] : array();

		if ( IdentificationSettings::INVOICE === $requirement ) {
			$billing[ self::INVOICE_KEY ] = array(
				'type'     => 'checkbox',
				'label'    => __( 'I need an electronic invoice', 'costa-rica-kit-for-woocommerce' ),
				'required' => false,
				'class'    => array( 'form-row-wide' ),
				'priority' => 120,
			);
		}

		$required = IdentificationSettings::REQUIRED === $requirement;

		$billing[ self::TYPE_KEY ] = array(
			'type'     => 'select',
			'label'    => __( 'ID type', 'costa-rica-kit-for-woocommerce' ),
			'options'  => array( '' => __( 'Select an ID type', 'costa-rica-kit-for-woocommerce' ) ) + IdentificationType::get_labels(),
			'required' => $required,
			'class'    => array( 'form-row-first' ),
			'priority' => 121,
		);

		$billing[ self::NUMBER_KEY ] = array(
			'type'              => 'text',
			'label'             => __( 'ID number', 'costa-rica-kit-for-woocommerce' ),
			'required'          => $required,
			'class'             => array( 'form-row-last' ),
			'autocomplete'      => 'off',
			'custom_attributes' => array(
				'autocapitalize' => 'characters',
				'maxlength'      => '20',
			),
			'priority'          => 122,
		);

		$fields['billing'] = $billing;

		return $fields;
	}

	/**
	 * A checkbox is never "required": drop the "(optional)" marker WooCommerce adds to its label,
	 * in the classic checkout and in My Account → Account details (the block checkout field).
	 *
	 * @param mixed $field Field HTML.
	 * @param mixed $key   Field name.
	 * @return mixed
	 */
	public function remove_optional_marker( mixed $field, mixed $key ): mixed {
		$keys = array( self::INVOICE_KEY, IdentificationStore::get_meta_key( IdentificationStore::INVOICE_FIELD ) );

		if ( ! in_array( $key, $keys, true ) || ! is_string( $field ) ) {
			return $field;
		}

		return (string) preg_replace( '#(&nbsp;)?<span class="optional">[^<]*</span>#', '', $field, 1 );
	}

	/**
	 * Prefills the fields with the identification saved on the customer.
	 *
	 * @param mixed $value Value (null unless another filter set it).
	 * @param mixed $input Field name.
	 * @return mixed
	 */
	public function get_saved_value( mixed $value, mixed $input ): mixed {
		$fields = array(
			self::TYPE_KEY    => IdentificationStore::TYPE_FIELD,
			self::NUMBER_KEY  => IdentificationStore::NUMBER_FIELD,
			self::INVOICE_KEY => IdentificationStore::INVOICE_FIELD,
		);

		if ( null !== $value || ! is_string( $input ) || ! isset( $fields[ $input ] ) || ! is_user_logged_in() ) {
			return $value;
		}

		// The saved customer, not the session copy, which can hold an older ID (IdentificationStore::sync_session_customer()).
		$customer = new WC_Customer( get_current_user_id() );
		$saved    = (string) $customer->get_meta( IdentificationStore::get_meta_key( $fields[ $input ] ) );

		return '' === $saved ? $value : $saved;
	}

	/**
	 * Whether the posted data needs an ID.
	 *
	 * @param array<string, mixed> $data Posted data.
	 * @return bool
	 */
	private function is_required( array $data ): bool {
		$requirement = $this->settings->get_requirement();

		return IdentificationSettings::REQUIRED === $requirement
			|| ( IdentificationSettings::INVOICE === $requirement && ! empty( $data[ self::INVOICE_KEY ] ) );
	}

	/**
	 * Requires the fields when the customer asks for an electronic invoice and checks the number format.
	 * With the REQUIRED setting WooCommerce reports empty fields itself.
	 *
	 * @param mixed $data   Posted data.
	 * @param mixed $errors Errors.
	 * @return void
	 */
	public function validate_checkout( mixed $data, mixed $errors ): void {
		if ( ! is_array( $data ) || ! $errors instanceof WP_Error ) {
			return;
		}

		$type     = $this->get_posted( $data, self::TYPE_KEY );
		$number   = $this->get_posted( $data, self::NUMBER_KEY );
		$required = $this->is_required( $data );
		$messages = IdentificationType::get_errors( $type, $number );

		if ( $required && IdentificationSettings::INVOICE === $this->settings->get_requirement() ) {
			if ( '' === $type ) {
				/* translators: %s: field label. */
				$errors->add( 'crkit_id_type_required', sprintf( __( '%s is required for an electronic invoice.', 'costa-rica-kit-for-woocommerce' ), '<strong>' . esc_html__( 'ID type', 'costa-rica-kit-for-woocommerce' ) . '</strong>' ), array( 'id' => self::TYPE_KEY ) );
			}
			if ( '' === IdentificationType::normalize( $number ) ) {
				/* translators: %s: field label. */
				$errors->add( 'crkit_id_number_required', sprintf( __( '%s is required for an electronic invoice.', 'costa-rica-kit-for-woocommerce' ), '<strong>' . esc_html__( 'ID number', 'costa-rica-kit-for-woocommerce' ) . '</strong>' ), array( 'id' => self::NUMBER_KEY ) );
			}
		}

		// An empty type is already reported as required.
		if ( $required && '' === $type ) {
			unset( $messages['crkit_id_type_missing'] );
		}

		if ( '' !== $type && ! IdentificationType::exists( $type ) ) {
			$messages['crkit_id_type_missing'] = __( 'Select the ID type.', 'costa-rica-kit-for-woocommerce' );
		}

		foreach ( $messages as $code => $message ) {
			$errors->add( $code, esc_html( $message ), array( 'id' => 'crkit_id_type_missing' === $code ? self::TYPE_KEY : self::NUMBER_KEY ) );
		}
	}

	/**
	 * Saves the identification on the order.
	 *
	 * @param mixed $order Order.
	 * @param mixed $data  Posted data.
	 * @return void
	 */
	public function save_to_order( mixed $order, mixed $data ): void {
		if ( $order instanceof WC_Order && is_array( $data ) ) {
			$this->save( $order, $data );
		}
	}

	/**
	 * Saves the identification on the customer, so both checkouts and My Account prefill it.
	 * WooCommerce saves the customer after this action.
	 *
	 * @param mixed $customer Customer.
	 * @param mixed $data     Posted data.
	 * @return void
	 */
	public function save_to_customer( mixed $customer, mixed $data ): void {
		if ( $customer instanceof WC_Customer && is_array( $data ) ) {
			$this->save( $customer, $data );
		}
	}

	/**
	 * Saves the posted identification on an order or customer.
	 *
	 * @param WC_Order|WC_Customer $target Order or customer.
	 * @param array<string, mixed> $data   Posted data.
	 * @return void
	 */
	private function save( WC_Order|WC_Customer $target, array $data ): void {
		IdentificationStore::save(
			$target,
			$this->get_posted( $data, self::TYPE_KEY ),
			$this->get_posted( $data, self::NUMBER_KEY ),
			IdentificationSettings::INVOICE === $this->settings->get_requirement() && ! empty( $data[ self::INVOICE_KEY ] )
		);
	}

	/**
	 * Lets other plugins know the identification of a classic checkout order.
	 *
	 * @param mixed $order_id    Order id.
	 * @param mixed $posted_data Posted data.
	 * @param mixed $order       Order.
	 * @return void
	 */
	public function order_processed( mixed $order_id, mixed $posted_data = array(), mixed $order = null ): void {
		unset( $posted_data );
		$order = $order instanceof WC_Order ? $order : wc_get_order( absint( $order_id ) );

		if ( $order instanceof WC_Order ) {
			IdentificationStore::sync_session_customer( $order->get_customer_id() );
			OrderIdentification::fire_saved( $order );
		}
	}

	/**
	 * After WooCommerce saves My Account → Account details (block contact fields), refresh the session copy.
	 *
	 * @param mixed $user_id User id.
	 * @return void
	 */
	public function account_details_saved( mixed $user_id ): void {
		IdentificationStore::sync_session_customer( absint( $user_id ) );
	}

	/**
	 * A posted value as a string.
	 *
	 * @param array<string, mixed> $data Posted data.
	 * @param string               $key  Field name.
	 * @return string
	 */
	private function get_posted( array $data, string $key ): string {
		return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? trim( (string) $data[ $key ] ) : '';
	}

	/**
	 * With the INVOICE setting, marks the ID fields as required while the checkbox is checked.
	 * Classic checkout only.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( IdentificationSettings::INVOICE !== $this->settings->get_requirement() ) {
			return;
		}

		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}

		if ( function_exists( 'has_block' ) && has_block( 'woocommerce/checkout' ) ) {
			return;
		}

		$asset_file = CRKIT_PLUGIN_DIR . 'assets/build/identification-classic.asset.json';
		$asset      = is_readable( $asset_file ) ? wp_json_file_decode( $asset_file, array( 'associative' => true ) ) : array();
		$asset      = is_array( $asset ) ? $asset : array();
		$deps       = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array();

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CRKIT_PLUGIN_URL . 'assets/build/identification-classic.js',
			$deps,
			isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : CRKIT_VERSION,
			true
		);
	}
}
