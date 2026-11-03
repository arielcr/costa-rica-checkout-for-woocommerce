<?php
/**
 * SINPE Móvil module: manual payment gateway with receipt uploads.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use CRKit\Modules\ModuleInterface;
use CRKit\Modules\PersonalDataInterface;
use CRKit\Modules\SinpeMovil\Emails\ReceiptUploadedEmail;

defined( 'ABSPATH' ) || exit;

/**
 * SINPE Móvil module.
 */
final class SinpeMovilModule implements ModuleInterface, PersonalDataInterface {

	/**
	 * Module id.
	 */
	public const ID = 'sinpe_movil';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_title(): string {
		return __( 'SINPE Móvil', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Adds SINPE Móvil as a manual payment method with payment receipt uploads. Configure it in WooCommerce → Settings → Payments.', 'costa-rica-checkout-for-woocommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_enabled_by_default(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$storage = new ReceiptStorage();

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_blocks_support' ) );
		add_filter( 'woocommerce_email_classes', array( $this, 'add_email' ) );
		add_filter( 'woocommerce_email_actions', array( $this, 'add_email_action' ) );

		( new Frontend() )->register();
		( new ReceiptController( $storage ) )->register();

		if ( is_admin() ) {
			( new AdminMetabox() )->register();
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_privacy_tools(): void {
		( new Privacy( new ReceiptStorage() ) )->register();
	}

	/**
	 * Adds the gateway.
	 *
	 * @param mixed $gateways Gateway class names or instances.
	 * @return array<int|string, mixed>
	 */
	public function add_gateway( mixed $gateways ): array {
		$gateways   = is_array( $gateways ) ? $gateways : array();
		$gateways[] = Gateway::class;

		return $gateways;
	}

	/**
	 * Registers the block checkout integration.
	 *
	 * @param PaymentMethodRegistry $registry Registry.
	 * @return void
	 */
	public function register_blocks_support( $registry ): void {
		if ( $registry instanceof PaymentMethodRegistry ) {
			$registry->register( new BlocksSupport() );
		}
	}

	/**
	 * Adds the "SINPE Móvil receipt uploaded" email.
	 *
	 * @param mixed $emails Email instances keyed by class name.
	 * @return array<string, mixed>
	 */
	public function add_email( mixed $emails ): array {
		$emails = is_array( $emails ) ? $emails : array();

		$emails['CRKit_Sinpe_Receipt_Uploaded_Email'] = new ReceiptUploadedEmail();

		return $emails;
	}

	/**
	 * Lets WooCommerce send the email when a receipt is uploaded.
	 *
	 * @param mixed $actions Email actions.
	 * @return array<int, string>
	 */
	public function add_email_action( mixed $actions ): array {
		$actions   = is_array( $actions ) ? $actions : array();
		$actions[] = 'crkit_sinpe_receipt_uploaded';

		return $actions;
	}
}
