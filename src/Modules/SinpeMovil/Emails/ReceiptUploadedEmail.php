<?php
/**
 * Email to the store when a customer uploads a SINPE Móvil receipt.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil\Emails;

use CRKit\Modules\SinpeMovil\Formatter;
use CRKit\Modules\SinpeMovil\Frontend;
use CRKit\Modules\SinpeMovil\OrderData;
use WC_Email;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Configurable in WooCommerce → Settings → Emails.
 */
class ReceiptUploadedEmail extends WC_Email {

	/**
	 * Email id.
	 */
	public const ID = 'crkit_sinpe_receipt_uploaded';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = self::ID;
		$this->title          = __( 'SINPE Móvil receipt uploaded', 'costa-rica-kit-for-woocommerce' );
		$this->description    = __( 'Sent to the store when a customer uploads a SINPE Móvil payment receipt.', 'costa-rica-kit-for-woocommerce' );
		$this->template_html  = 'emails/admin-sinpe-receipt-uploaded.php';
		$this->template_plain = 'emails/plain/admin-sinpe-receipt-uploaded.php';
		$this->template_base  = CRKIT_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		add_action( 'crkit_sinpe_receipt_uploaded_notification', array( $this, 'trigger' ), 10, 1 );

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}]: SINPE Móvil receipt for order #{order_number}', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Payment receipt received', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * Sends the email.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public function trigger( $order_id ): void {
		$this->setup_locale();

		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {
			$this->object                         = $order;
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}'] = $order->get_order_number();

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}

		$this->restore_locale();
	}

	/**
	 * HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->get_template_args( false ), Frontend::TEMPLATE_PATH, $this->template_base );
	}

	/**
	 * Plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->get_template_args( true ), Frontend::TEMPLATE_PATH, $this->template_base );
	}

	/**
	 * Default additional content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return '';
	}

	/**
	 * Settings fields: same as the core "New order" email.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		/* translators: %s: list of available placeholders. */
		$placeholder_text  = sprintf( __( 'Available placeholders: %s', 'costa-rica-kit-for-woocommerce' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'costa-rica-kit-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'costa-rica-kit-for-woocommerce' ),
				'default' => 'yes',
			),
			'recipient'          => array(
				'title'       => __( 'Recipient(s)', 'costa-rica-kit-for-woocommerce' ),
				'type'        => 'text',
				/* translators: %s: admin email address. */
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'costa-rica-kit-for-woocommerce' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
				'placeholder' => '',
				'default'     => '',
				'desc_tip'    => true,
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'costa-rica-kit-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'costa-rica-kit-for-woocommerce' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'costa-rica-kit-for-woocommerce' ),
				'description' => __( 'Text to appear below the main email content.', 'costa-rica-kit-for-woocommerce' ) . ' ' . $placeholder_text,
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'costa-rica-kit-for-woocommerce' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'costa-rica-kit-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'costa-rica-kit-for-woocommerce' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Template variables.
	 *
	 * @param bool $plain_text Plain text email.
	 * @return array<string, mixed>
	 */
	private function get_template_args( bool $plain_text ): array {
		$order   = $this->object instanceof WC_Order ? $this->object : null;
		$payment = null === $order ? null : OrderData::get_payment( $order );
		$amount  = null === $payment ? 0 : $payment['amount_crc'];

		return array(
			'order'              => $order,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => true,
			'plain_text'         => $plain_text,
			'email'              => $this,
			'amount_crc_html'    => Formatter::crc_html( $amount ),
			'amount_crc_text'    => Formatter::crc( $amount ),
			'reference'          => null === $payment ? '' : $payment['reference'],
			'order_url'          => null === $order ? '' : $order->get_edit_order_url(),
		);
	}
}
