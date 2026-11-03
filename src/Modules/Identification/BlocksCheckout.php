<?php
/**
 * ID fields in the block checkout (Additional Checkout Fields API).
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the ID type, the ID number and, when the ID is required only for an electronic invoice,
 * the "I need an electronic invoice" checkbox as contact fields. WooCommerce saves them on the order and
 * the customer and shows them in My Account → Account details. See docs/DECISIONS.md, ADR-0047.
 */
final class BlocksCheckout {

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
		add_action( 'woocommerce_init', array( $this, 'register_fields' ) );
		add_action( 'woocommerce_blocks_validate_location_contact_fields', array( $this, 'validate_contact_fields' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'order_processed' ) );
	}

	/**
	 * Registers the fields.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		foreach ( $this->get_field_definitions( $this->settings->get_requirement() ) as $field ) {
			woocommerce_register_additional_checkout_field( $field );
		}
	}

	/**
	 * Field definitions for a requirement setting.
	 *
	 * The fields are never hidden: My Account → Account details evaluates the rules without checkout
	 * data and would drop hidden fields. For the same reason the `required` rules only match at checkout,
	 * so the ID is optional in Account details with every setting (ADR-0048). With the INVOICE setting
	 * the fields are required only while the checkbox is checked.
	 *
	 * @param string $requirement One of the IdentificationSettings constants.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_field_definitions( string $requirement ): array {
		$required = IdentificationSettings::REQUIRED === $requirement ? $this->get_required_rule( false ) : false;
		$fields   = array();

		if ( IdentificationSettings::INVOICE === $requirement ) {
			$required = $this->get_required_rule( true );
			$fields[] = array(
				'id'                         => IdentificationStore::INVOICE_FIELD,
				'label'                      => __( 'I need an electronic invoice', 'costa-rica-kit-for-woocommerce' ),
				// A checkbox is never "required": without this WooCommerce adds "(optional)" to the label.
				'optionalLabel'              => __( 'I need an electronic invoice', 'costa-rica-kit-for-woocommerce' ),
				'location'                   => 'contact',
				'type'                       => 'checkbox',
				'show_in_order_confirmation' => false,
			);
		}

		$options = array();
		foreach ( IdentificationType::get_labels() as $value => $label ) {
			$options[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		$fields[] = array(
			'id'                         => IdentificationStore::TYPE_FIELD,
			'label'                      => __( 'ID type', 'costa-rica-kit-for-woocommerce' ),
			'placeholder'                => __( 'Select an ID type', 'costa-rica-kit-for-woocommerce' ),
			'location'                   => 'contact',
			'type'                       => 'select',
			'options'                    => $options,
			'required'                   => $required,
			'show_in_order_confirmation' => false,
		);

		$fields[] = array(
			'id'                         => IdentificationStore::NUMBER_FIELD,
			'label'                      => __( 'ID number', 'costa-rica-kit-for-woocommerce' ),
			'location'                   => 'contact',
			'type'                       => 'text',
			'required'                   => $required,
			'sanitize_callback'          => static fn( $value ): string => IdentificationType::normalize( is_scalar( $value ) ? (string) $value : '' ),
			'attributes'                 => array(
				'autocomplete'   => 'off',
				'autocapitalize' => 'characters',
				'maxLength'      => 20,
			),
			'show_in_order_confirmation' => false,
		);

		return $fields;
	}

	/**
	 * JSON Schema for the `required` option of the ID fields.
	 *
	 * Matches at checkout only: the block checkout (browser and Store API) always sends checkout data
	 * (payment method, order note…), while My Account → Account details sends none. Optionally, the
	 * "I need an electronic invoice" checkbox must also be checked.
	 *
	 * @param bool $only_with_invoice Whether the checkbox must be checked.
	 * @return array<string, mixed>
	 */
	private function get_required_rule( bool $only_with_invoice ): array {
		$rule = array(
			'type'       => 'object',
			'required'   => array( 'checkout' ),
			'properties' => array(
				'checkout' => array(
					'type'          => 'object',
					'minProperties' => 1,
				),
			),
		);

		if ( $only_with_invoice ) {
			$rule['required'][]             = 'customer';
			$rule['properties']['customer'] = array(
				'type'       => 'object',
				'required'   => array( 'additional_fields' ),
				'properties' => array(
					'additional_fields' => array(
						'type'       => 'object',
						'required'   => array( IdentificationStore::INVOICE_FIELD ),
						'properties' => array(
							IdentificationStore::INVOICE_FIELD => array( 'const' => true ),
						),
					),
				),
			);
		}

		return $rule;
	}

	/**
	 * Checks the number against the selected type, at checkout and in My Account → Account details.
	 * Whether the fields are required is checked by WooCommerce from the field rules.
	 *
	 * @param mixed $errors Errors.
	 * @param mixed $fields Contact field values.
	 * @param mixed $group  Group (`other`).
	 * @return void
	 */
	public function validate_contact_fields( mixed $errors, mixed $fields, mixed $group = 'other' ): void {
		unset( $group );

		if ( ! $errors instanceof WP_Error || ! is_array( $fields ) ) {
			return;
		}

		$type   = isset( $fields[ IdentificationStore::TYPE_FIELD ] ) && is_scalar( $fields[ IdentificationStore::TYPE_FIELD ] ) ? (string) $fields[ IdentificationStore::TYPE_FIELD ] : '';
		$number = isset( $fields[ IdentificationStore::NUMBER_FIELD ] ) && is_scalar( $fields[ IdentificationStore::NUMBER_FIELD ] ) ? (string) $fields[ IdentificationStore::NUMBER_FIELD ] : '';

		foreach ( IdentificationType::get_errors( $type, $number ) as $code => $message ) {
			$errors->add( $code, $message );
		}
	}

	/**
	 * Lets other plugins know the identification of a block checkout order.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public function order_processed( mixed $order ): void {
		if ( $order instanceof WC_Order ) {
			OrderIdentification::fire_saved( $order );
		}
	}
}
