<?php
/**
 * ID fields on the order screen.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\Identification;

use WC_Admin_Meta_Boxes;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Edits the identification of any order in the billing column. WooCommerce only adds the block
 * checkout fields to orders placed with the block checkout, so they are replaced by these.
 */
final class AdminFields {

	/**
	 * Field ids (posted names) on the order screen.
	 */
	public const TYPE_ID    = 'crkit_admin_id_type';
	public const NUMBER_ID  = 'crkit_admin_id_number';
	public const INVOICE_ID = 'crkit_admin_electronic_invoice';

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
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'filter_billing_fields' ), 99, 3 );
	}

	/**
	 * Replaces the block checkout ID fields with fields for every order.
	 *
	 * The ID is shown with the billing address (OrderDisplay), so the type and number only appear while editing.
	 *
	 * @param mixed $fields  Billing fields.
	 * @param mixed $order   Order.
	 * @param mixed $context `edit` or `view`.
	 * @return array<string, mixed>
	 */
	public function filter_billing_fields( mixed $fields, mixed $order = null, mixed $context = 'edit' ): array {
		$fields = is_array( $fields ) ? $fields : array();

		// WooCommerce inserts the block fields keyed by field id, with their meta key as `id`.
		$fields = array_filter(
			$fields,
			static fn( $field, $key ): bool => ! str_starts_with( (string) $key, 'crkit/' )
				&& ! ( is_array( $field ) && isset( $field['id'] ) && str_starts_with( (string) $field['id'], IdentificationStore::META_PREFIX . 'crkit/' ) ),
			ARRAY_FILTER_USE_BOTH
		);

		if ( ! $order instanceof WC_Order ) {
			return $fields;
		}

		$identification = IdentificationStore::get( $order );
		$wants_invoice  = IdentificationStore::wants_electronic_invoice( $order );

		$fields['crkit_id_type'] = array(
			'id'              => self::TYPE_ID,
			'label'           => __( 'ID type', 'costa-rica-kit-for-woocommerce' ),
			'type'            => 'select',
			'options'         => array( '' => __( 'None', 'costa-rica-kit-for-woocommerce' ) ) + IdentificationType::get_labels(),
			'value'           => (string) $order->get_meta( IdentificationStore::get_meta_key( IdentificationStore::TYPE_FIELD ) ),
			'show'            => false,
			'update_callback' => array( $this, 'update_type' ),
		);

		$fields['crkit_id_number'] = array(
			'id'              => self::NUMBER_ID,
			'label'           => __( 'ID number', 'costa-rica-kit-for-woocommerce' ),
			'value'           => null === $identification ? '' : $identification->number,
			'show'            => false,
			'wrapper_class'   => 'last',
			'update_callback' => array( $this, 'update_number' ),
		);

		if ( $wants_invoice || IdentificationSettings::INVOICE === $this->settings->get_requirement() ) {
			$fields['crkit_electronic_invoice'] = array(
				'id'              => self::INVOICE_ID,
				'label'           => __( 'Electronic invoice', 'costa-rica-kit-for-woocommerce' ),
				'type'            => 'select',
				'options'         => array(
					''  => __( 'Not requested', 'costa-rica-kit-for-woocommerce' ),
					'1' => __( 'Requested', 'costa-rica-kit-for-woocommerce' ),
				),
				'value'           => 'view' === $context
					? ( $wants_invoice ? __( 'Requested', 'costa-rica-kit-for-woocommerce' ) : '' )
					: ( $wants_invoice ? '1' : '' ),
				'show'            => $wants_invoice,
				'wrapper_class'   => 'form-field-wide',
				'update_callback' => array( $this, 'update_invoice' ),
			);
		}

		return $fields;
	}

	/**
	 * Saves the ID type.
	 *
	 * @param mixed $id    Field id.
	 * @param mixed $value Posted value (cleaned by WooCommerce).
	 * @param mixed $order Order.
	 * @return void
	 */
	public function update_type( mixed $id, mixed $value, mixed $order ): void {
		unset( $id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$type = is_string( $value ) && IdentificationType::exists( $value ) ? $value : '';
		$this->update_meta( $order, IdentificationStore::TYPE_FIELD, $type );
	}

	/**
	 * Saves the ID number if it matches the type (saved just before). Otherwise keeps the previous one.
	 *
	 * @param mixed $id    Field id.
	 * @param mixed $value Posted value (cleaned by WooCommerce).
	 * @param mixed $order Order.
	 * @return void
	 */
	public function update_number( mixed $id, mixed $value, mixed $order ): void {
		unset( $id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$number = IdentificationType::normalize( is_string( $value ) ? $value : '' );
		$type   = (string) $order->get_meta( IdentificationStore::get_meta_key( IdentificationStore::TYPE_FIELD ) );
		$errors = IdentificationType::get_errors( $type, $number );

		if ( array() !== $errors ) {
			if ( class_exists( WC_Admin_Meta_Boxes::class ) ) {
				foreach ( $errors as $message ) {
					/* translators: %s: validation error. */
					WC_Admin_Meta_Boxes::add_error( sprintf( __( 'The ID number was not saved: %s', 'costa-rica-kit-for-woocommerce' ), $message ) );
				}
			}
			return;
		}

		$this->update_meta( $order, IdentificationStore::NUMBER_FIELD, $number );
	}

	/**
	 * Saves whether the customer asked for an electronic invoice.
	 *
	 * @param mixed $id    Field id.
	 * @param mixed $value Posted value.
	 * @param mixed $order Order.
	 * @return void
	 */
	public function update_invoice( mixed $id, mixed $value, mixed $order ): void {
		unset( $id );

		if ( $order instanceof WC_Order ) {
			$this->update_meta( $order, IdentificationStore::INVOICE_FIELD, '1' === $value ? '1' : '' );
		}
	}

	/**
	 * Updates or deletes one meta value. WooCommerce saves the order afterwards.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $field Field id.
	 * @param string   $value Value; empty deletes it.
	 * @return void
	 */
	private function update_meta( WC_Order $order, string $field, string $value ): void {
		if ( '' === $value ) {
			$order->delete_meta_data( IdentificationStore::get_meta_key( $field ) );
		} else {
			$order->update_meta_data( IdentificationStore::get_meta_key( $field ), $value );
		}
	}
}
