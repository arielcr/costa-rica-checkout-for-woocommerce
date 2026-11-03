<?php
/**
 * SINPE Móvil payment gateway.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Support\Phone;
use WC_Order;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

/**
 * Manual payment method (like bank transfer): the customer pays from their bank app,
 * uploads the receipt, and the store confirms the payment by hand.
 */
class Gateway extends WC_Payment_Gateway {

	/**
	 * Gateway id.
	 */
	public const ID = 'crkit_sinpe_movil';

	/**
	 * Default maximum receipt size, in MB.
	 */
	public const DEFAULT_MAX_UPLOAD_MB = 5;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->has_fields         = false;
		$this->method_title       = __( 'SINPE Móvil', 'costa-rica-checkout-for-woocommerce' );
		$this->method_description = __( 'Manual payments with SINPE Móvil. Customers pay from their bank app, see the exact amount in colones, and upload the receipt; you confirm the payment from the order screen.', 'costa-rica-checkout-for-woocommerce' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_settings' ) );
	}

	/**
	 * Settings screen, with a warning when a dollar store has no exchange rate module.
	 *
	 * @return void
	 */
	public function admin_options() {
		if ( 'CRC' !== get_woocommerce_currency() && null === crkit_exchange_rate_service() ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s</p></div>',
				esc_html__( 'Your store does not sell in colones and the Exchange rate module is disabled, so SINPE Móvil is not offered at checkout. Enable it in WooCommerce → Settings → Costa Rica.', 'costa-rica-checkout-for-woocommerce' )
			);
		}

		parent::admin_options();
	}

	/**
	 * Saves the settings from the gateway settings screen.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		$this->process_admin_options();
	}

	/**
	 * Settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'costa-rica-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable SINPE Móvil', 'costa-rica-checkout-for-woocommerce' ),
				'default' => 'no',
			),
			'title'           => array(
				'title'       => __( 'Title', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'safe_text',
				'description' => __( 'Name of the payment method at checkout.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => __( 'SINPE Móvil', 'costa-rica-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Shown at checkout when the customer selects SINPE Móvil.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => __( 'Pay from your bank app with SINPE Móvil. After placing the order you will see the number and the exact amount in colones.', 'costa-rica-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions'    => array(
				'title'       => __( 'Extra instructions', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Optional text added to the payment steps on the order received page and in the on-hold email.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'phone'           => array(
				'title'       => __( 'SINPE Móvil number', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( '8-digit number that receives the payments, e.g. 8888-8888. Required to offer the method.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => '',
				'placeholder' => '8888-8888',
			),
			'holder'          => array(
				'title'       => __( 'Account holder', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Name the customer sees in their bank app before confirming, so they know they are paying the right person.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => '',
			),
			'require_receipt' => array(
				'title'   => __( 'Payment receipt', 'costa-rica-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ask the customer to upload the payment receipt', 'costa-rica-checkout-for-woocommerce' ),
				'default' => 'yes',
			),
			'max_upload_mb'   => array(
				'title'             => __( 'Maximum receipt size (MB)', 'costa-rica-checkout-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Allowed formats: JPG, PNG, WEBP and PDF.', 'costa-rica-checkout-for-woocommerce' ),
				'default'           => (string) self::DEFAULT_MAX_UPLOAD_MB,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			'max_amount_crc'  => array(
				'title'             => __( 'Maximum amount per order (₡)', 'costa-rica-checkout-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'The method is not offered when the order total in colones is higher. Leave empty for no limit. The BCCR limits SINPE Móvil payments by SMS to ₡100,000 per day; the limit in bank apps depends on each bank (usually up to ₡500,000).', 'costa-rica-checkout-for-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			'rate_type'       => array(
				'title'       => __( 'Exchange rate', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Only for stores that do not sell in colones. Uses the rate from the Exchange rate module.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => ExchangeRate::SELL,
				'options'     => array(
					ExchangeRate::SELL => __( 'Sell rate', 'costa-rica-checkout-for-woocommerce' ),
					ExchangeRate::BUY  => __( 'Buy rate', 'costa-rica-checkout-for-woocommerce' ),
				),
			),
			'surcharge'       => array(
				'title'             => __( 'Exchange rate surcharge (%)', 'costa-rica-checkout-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Optional percentage added to the exchange rate, for stores that do not sell in colones.', 'costa-rica-checkout-for-woocommerce' ),
				'default'           => '',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			'reject_status'   => array(
				'title'       => __( 'Rejected payments', 'costa-rica-checkout-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Order status when you reject a payment from the order screen.', 'costa-rica-checkout-for-woocommerce' ),
				'default'     => 'failed',
				'options'     => array(
					'failed'    => __( 'Failed (the customer can pay again)', 'costa-rica-checkout-for-woocommerce' ),
					'cancelled' => __( 'Cancelled', 'costa-rica-checkout-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * Validates and normalizes the SINPE Móvil number.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 * @return string
	 * @throws \Exception When the number is not valid; WooCommerce shows the message and keeps the old value.
	 */
	public function validate_phone_field( $key, $value ): string {
		$value = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		if ( ! Phone::is_valid( $value ) ) {
			throw new \Exception( esc_html__( 'The SINPE Móvil number must have 8 digits, for example 8888-8888.', 'costa-rica-checkout-for-woocommerce' ) );
		}

		return Phone::normalize( $value );
	}

	/**
	 * Validates the maximum amount: a whole positive number of colones, or empty.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 * @return string
	 */
	public function validate_max_amount_crc_field( $key, $value ): string {
		$value = absint( $value );

		return $value > 0 ? (string) $value : '';
	}

	/**
	 * Validates the surcharge: a non-negative decimal, or empty.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 * @return string
	 */
	public function validate_surcharge_field( $key, $value ): string {
		$value = wc_format_decimal( (string) $value );

		return is_numeric( $value ) && (float) $value > 0 ? (string) $value : '';
	}

	/**
	 * Validates the maximum receipt size.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Posted value.
	 * @return string
	 */
	public function validate_max_upload_mb_field( $key, $value ): string {
		$value = absint( $value );

		return (string) ( $value > 0 ? $value : self::DEFAULT_MAX_UPLOAD_MB );
	}

	/**
	 * Merchant's SINPE Móvil number, normalized.
	 *
	 * @return string
	 */
	public function get_phone(): string {
		return Phone::normalize( (string) $this->get_option( 'phone' ) );
	}

	/**
	 * Account holder name.
	 *
	 * @return string
	 */
	public function get_holder(): string {
		return (string) $this->get_option( 'holder' );
	}

	/**
	 * Extra instructions.
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return (string) $this->get_option( 'instructions' );
	}

	/**
	 * Whether the customer is asked to upload a receipt.
	 *
	 * @return bool
	 */
	public function requires_receipt(): bool {
		return 'yes' === $this->get_option( 'require_receipt', 'yes' );
	}

	/**
	 * Maximum receipt size in bytes, capped by the server upload limit.
	 *
	 * @return int
	 */
	public function get_max_upload_bytes(): int {
		$mb    = absint( $this->get_option( 'max_upload_mb', (string) self::DEFAULT_MAX_UPLOAD_MB ) );
		$bytes = ( $mb > 0 ? $mb : self::DEFAULT_MAX_UPLOAD_MB ) * MB_IN_BYTES;

		return (int) min( $bytes, wp_max_upload_size() );
	}

	/**
	 * Maximum amount per order in colones, 0 for no limit.
	 *
	 * @return int
	 */
	public function get_max_amount_crc(): int {
		return absint( $this->get_option( 'max_amount_crc' ) );
	}

	/**
	 * Rate type used for conversions.
	 *
	 * @return string
	 */
	public function get_rate_type(): string {
		$type = (string) $this->get_option( 'rate_type', ExchangeRate::SELL );

		return ExchangeRate::is_valid_type( $type ) ? $type : ExchangeRate::SELL;
	}

	/**
	 * Surcharge percentage.
	 *
	 * @return float
	 */
	public function get_surcharge(): float {
		$surcharge = (string) $this->get_option( 'surcharge' );

		return is_numeric( $surcharge ) ? max( 0.0, (float) $surcharge ) : 0.0;
	}

	/**
	 * Order status for rejected payments.
	 *
	 * @return string
	 */
	public function get_reject_status(): string {
		return 'cancelled' === $this->get_option( 'reject_status' ) ? 'cancelled' : 'failed';
	}

	/**
	 * Quote in colones for a total, using the cached exchange rate.
	 *
	 * @param float  $total    Total.
	 * @param string $currency Currency of the total.
	 * @return PaymentQuote|null Null when there is no usable rate for a non-CRC total.
	 */
	public function get_quote( float $total, string $currency ): ?PaymentQuote {
		$calculator = new QuoteCalculator( crkit_exchange_rate_service() );

		return $calculator->quote( $total, $currency, $this->get_rate_type(), $this->get_surcharge() );
	}

	/**
	 * Whether a quote is within the maximum amount per order.
	 *
	 * @param PaymentQuote $quote Quote.
	 * @return bool
	 */
	public function is_within_limit( PaymentQuote $quote ): bool {
		$max = $this->get_max_amount_crc();

		return 0 === $max || $quote->amount_crc <= $max;
	}

	/**
	 * Available when enabled, configured, and the checkout total can be converted to colones within the limit.
	 * Reads the cached rate only.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! parent::is_available() || '' === $this->get_phone() ) {
			return false;
		}

		$total = $this->get_checkout_total();

		if ( null === $total ) {
			return true;
		}

		$quote = $this->get_quote( $total['amount'], $total['currency'] );

		return null !== $quote && $this->is_within_limit( $quote );
	}

	/**
	 * Total being paid: the order on the order-pay page, otherwise the cart. Null outside checkout.
	 *
	 * @return array{amount: float, currency: string}|null
	 */
	private function get_checkout_total(): ?array {
		$order_id = absint( get_query_var( 'order-pay' ) );

		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );

			if ( $order instanceof WC_Order ) {
				return array(
					'amount'   => (float) $order->get_total( 'edit' ),
					'currency' => $order->get_currency(),
				);
			}
		}

		if ( function_exists( 'WC' ) && null !== WC()->cart ) {
			return array(
				'amount'   => (float) WC()->cart->get_total( 'edit' ),
				'currency' => get_woocommerce_currency(),
			);
		}

		return null;
	}

	/**
	 * Places the order on hold with the amount in colones frozen, reduces stock and empties the cart.
	 *
	 * @param int $order_id Order id.
	 * @return array{result: string, redirect?: string}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return array( 'result' => 'failure' );
		}

		$quote = $this->get_quote( (float) $order->get_total( 'edit' ), $order->get_currency() );

		if ( null === $quote || ! $this->is_within_limit( $quote ) ) {
			wc_add_notice( __( 'SINPE Móvil is not available for this order right now. Please choose another payment method.', 'costa-rica-checkout-for-woocommerce' ), 'error' );

			return array( 'result' => 'failure' );
		}

		OrderData::save_quote( $order, $quote, (string) $order->get_order_number(), $this->get_phone(), $this->get_holder() );

		$order->update_status( 'on-hold', $this->get_order_note( $quote ) );

		wc_reduce_stock_levels( $order->get_id() );

		if ( null !== WC()->cart ) {
			WC()->cart->empty_cart();
		}

		/**
		 * Fires after a SINPE Móvil order is placed on hold, waiting for the payment.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Order     $order Order.
		 * @param PaymentQuote $quote Amount in colones and the rate used.
		 */
		do_action( 'crkit_sinpe_payment_pending', $order, $quote );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Display name of a rate source.
	 *
	 * @param string $source Source id.
	 * @return string
	 */
	private function get_source_label( string $source ): string {
		$rates = crkit_exchange_rate_service();

		return null === $rates ? $source : $rates->get_source_label( $source );
	}

	/**
	 * Order note with the frozen amount and rate.
	 *
	 * @param PaymentQuote $quote Quote.
	 * @return string
	 */
	private function get_order_note( PaymentQuote $quote ): string {
		$amount = Formatter::crc( $quote->amount_crc );

		if ( ! $quote->is_converted() ) {
			/* translators: %s: amount in colones. */
			return sprintf( __( 'Awaiting SINPE Móvil payment of %s.', 'costa-rica-checkout-for-woocommerce' ), $amount );
		}

		return sprintf(
			/* translators: 1: amount in colones, 2: exchange rate, 3: rate source, 4: date of the rate. */
			__( 'Awaiting SINPE Móvil payment of %1$s (exchange rate %2$s from %3$s, %4$s).', 'costa-rica-checkout-for-woocommerce' ),
			$amount,
			Formatter::rate( (float) $quote->rate, $quote->surcharge ),
			$this->get_source_label( $quote->rate_source ),
			$quote->rate_date
		);
	}
}
