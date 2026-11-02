<?php
/**
 * [crkit_exchange_rate] shortcode and "Exchange rate (Costa Rica)" block.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the stored exchange rate. Never calls external services.
 *
 * Shortcode attributes: `type` (both|sell|buy, default both), `show_date` and `show_source` (yes|no, default yes).
 */
final class Shortcode {

	/**
	 * Shortcode tag.
	 */
	public const TAG = 'crkit_exchange_rate';

	/**
	 * Editor script handle of the block.
	 */
	public const BLOCK_SCRIPT_HANDLE = 'crkit-exchange-rate-block-editor';

	/**
	 * Service.
	 *
	 * @var ExchangeRateService
	 */
	private ExchangeRateService $service;

	/**
	 * Constructor.
	 *
	 * @param ExchangeRateService $service Service.
	 */
	public function __construct( ExchangeRateService $service ) {
		$this->service = $service;
	}

	/**
	 * Registers the shortcode and the block.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param mixed $atts Attributes.
	 * @return string
	 */
	public function render_shortcode( mixed $atts ): string {
		$atts = shortcode_atts(
			array(
				'type'        => 'both',
				'show_date'   => 'yes',
				'show_source' => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		return $this->render(
			sanitize_key( (string) $atts['type'] ),
			wc_string_to_bool( (string) $atts['show_date'] ),
			wc_string_to_bool( (string) $atts['show_source'] )
		);
	}

	/**
	 * Registers the dynamic block (metadata in blocks/exchange-rate/block.json).
	 *
	 * @return void
	 */
	public function register_block(): void {
		$asset_file = CRKIT_PLUGIN_DIR . 'assets/build/exchange-rate-block.asset.json';
		$asset      = is_readable( $asset_file ) ? wp_json_file_decode( $asset_file, array( 'associative' => true ) ) : array();
		$asset      = is_array( $asset ) ? $asset : array();

		wp_register_script(
			self::BLOCK_SCRIPT_HANDLE,
			CRKIT_PLUGIN_URL . 'assets/build/exchange-rate-block.js',
			isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) && is_string( $asset['version'] ) ? $asset['version'] : CRKIT_VERSION,
			true
		);
		wp_set_script_translations( self::BLOCK_SCRIPT_HANDLE, 'costa-rica-kit-for-woocommerce', CRKIT_PLUGIN_DIR . 'languages' );

		register_block_type(
			CRKIT_PLUGIN_DIR . 'blocks/exchange-rate',
			array(
				'editor_script_handles' => array( self::BLOCK_SCRIPT_HANDLE ),
				'render_callback'       => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param mixed $attributes Block attributes.
	 * @return string
	 */
	public function render_block( mixed $attributes ): string {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$html       = $this->render(
			isset( $attributes['type'] ) ? sanitize_key( (string) $attributes['type'] ) : 'both',
			! isset( $attributes['showDate'] ) || (bool) $attributes['showDate'],
			! isset( $attributes['showSource'] ) || (bool) $attributes['showSource']
		);

		return '' === $html ? '' : sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $html );
	}

	/**
	 * Markup of the current rate. Empty when there is no rate yet.
	 *
	 * @param string $type        `both`, `sell` or `buy`.
	 * @param bool   $show_date   Show the date of the rate.
	 * @param bool   $show_source Show the source.
	 * @return string
	 */
	public function render( string $type, bool $show_date, bool $show_source ): string {
		$rate = $this->service->get_current();

		if ( null === $rate ) {
			return '';
		}

		$type   = in_array( $type, array( 'both', 'sell', 'buy' ), true ) ? $type : 'both';
		$values = array();

		if ( 'buy' !== $type ) {
			/* translators: %s: sell exchange rate in colones. */
			$values[] = sprintf( __( 'Sell: %s', 'costa-rica-kit-for-woocommerce' ), RateFormatter::rate( $rate->sell ) );
		}
		if ( 'sell' !== $type ) {
			/* translators: %s: buy exchange rate in colones. */
			$values[] = sprintf( __( 'Buy: %s', 'costa-rica-kit-for-woocommerce' ), RateFormatter::rate( $rate->buy ) );
		}

		$details = '';
		if ( $show_date && $show_source ) {
			/* translators: 1: date of the rate, 2: source name. */
			$details = sprintf( __( 'Rate of %1$s, source: %2$s', 'costa-rica-kit-for-woocommerce' ), RateFormatter::date( $rate->date ), $this->service->get_source_label( $rate->source ) );
		} elseif ( $show_date ) {
			/* translators: %s: date of the rate. */
			$details = sprintf( __( 'Rate of %s', 'costa-rica-kit-for-woocommerce' ), RateFormatter::date( $rate->date ) );
		} elseif ( $show_source ) {
			/* translators: %s: source name. */
			$details = sprintf( __( 'Source: %s', 'costa-rica-kit-for-woocommerce' ), $this->service->get_source_label( $rate->source ) );
		}

		return sprintf(
			'<span class="crkit-exchange-rate%1$s"><span class="crkit-exchange-rate__values">%2$s</span>%3$s</span>',
			$this->service->is_stale() ? ' is-outdated' : '',
			esc_html( implode( ' · ', $values ) ),
			'' === $details ? '' : ' <small class="crkit-exchange-rate__details">' . esc_html( $details ) . '</small>'
		);
	}
}
