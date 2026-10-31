<?php
/**
 * Exchange rate admin: settings section, status, "Update now" and notices.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

use CRKit\Modules\ExchangeRate\Providers\HaciendaProvider;
use CRKit\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin integration of the exchange rate module.
 */
final class ExchangeRateAdmin {

	/**
	 * Settings section id in the Costa Rica tab.
	 */
	public const SECTION = 'exchange_rate';

	/**
	 * Action name (admin-post.php) of the "Update now" button.
	 */
	public const REFRESH_ACTION = 'crkit_refresh_exchange_rate';

	/**
	 * Query argument with the result of a manual refresh.
	 */
	public const RESULT_ARG = 'crkit_rate_refreshed';

	/**
	 * Custom WooCommerce settings field type that renders the current rate.
	 */
	public const STATUS_FIELD_TYPE = 'crkit_exchange_rate_status';

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
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::REFRESH_ACTION, array( $this, 'handle_refresh' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
		add_action( 'woocommerce_admin_field_' . self::STATUS_FIELD_TYPE, array( $this, 'render_status_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'sanitize_option' ), 10, 2 );
		add_action( 'update_option_' . ExchangeRateSettings::OPTION, array( $this, 'maybe_refresh_after_save' ), 10, 2 );
	}

	/**
	 * Fields of the exchange rate settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings_fields(): array {
		$sources = array();
		foreach ( $this->service->get_providers() as $id => $provider ) {
			$sources[ $id ] = $provider->get_name();
		}

		$option = ExchangeRateSettings::OPTION;

		return array(
			array(
				'id'    => 'crkit_exchange_rate_section',
				'type'  => 'title',
				'title' => __( 'Exchange rate', 'costa-rica-kit-for-woocommerce' ),
				'desc'  => __( 'US dollar rate in colones, used to show the exact amount in colones when your store sells in dollars. It is updated in the background twice a day; customers never wait for it.', 'costa-rica-kit-for-woocommerce' ),
			),
			array(
				'id'    => 'crkit_exchange_rate_current',
				'type'  => self::STATUS_FIELD_TYPE,
				'title' => __( 'Current rate', 'costa-rica-kit-for-woocommerce' ),
			),
			array(
				'id'      => $option . '[source]',
				'type'    => 'select',
				'title'   => __( 'Source', 'costa-rica-kit-for-woocommerce' ),
				'options' => $sources,
				'default' => HaciendaProvider::ID,
			),
			array(
				'id'    => $option . '[bccr_token]',
				'type'  => 'password',
				'title' => __( 'BCCR token', 'costa-rica-kit-for-woocommerce' ),
				'desc'  => __( 'Only for the BCCR source. Generate it in the BCCR economic indicators site, under My profile → Generate token.', 'costa-rica-kit-for-woocommerce' ),
			),
			array(
				'id'      => $option . '[manual_enabled]',
				'type'    => 'checkbox',
				'title'   => __( 'Manual rate', 'costa-rica-kit-for-woocommerce' ),
				'desc'    => __( 'Use a fixed rate instead of the automatic source', 'costa-rica-kit-for-woocommerce' ),
				'default' => 'no',
			),
			array(
				'id'                => $option . '[manual_rate]',
				'type'              => 'number',
				'title'             => __( 'Manual rate (₡ per US$)', 'costa-rica-kit-for-woocommerce' ),
				'desc'              => __( 'Used for both buying and selling while the manual rate is on.', 'costa-rica-kit-for-woocommerce' ),
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			array(
				'id'                => $option . '[max_age_hours]',
				'type'              => 'number',
				'title'             => __( 'Maximum age (hours)', 'costa-rica-kit-for-woocommerce' ),
				'desc'              => __( 'If the automatic rate is older than this and there is no manual rate, a notice is shown and SINPE Móvil is hidden in stores that do not sell in colones.', 'costa-rica-kit-for-woocommerce' ),
				'default'           => (string) ExchangeRateSettings::DEFAULT_MAX_AGE_HOURS,
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'id'   => 'crkit_exchange_rate_section',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Sanitizes the exchange rate settings on save.
	 *
	 * @param mixed $value  Value to save.
	 * @param mixed $option Field definition.
	 * @return mixed
	 */
	public function sanitize_option( mixed $value, mixed $option ): mixed {
		$id = is_array( $option ) && isset( $option['id'] ) ? (string) $option['id'] : '';
		$id = str_starts_with( $id, ExchangeRateSettings::OPTION . '[' ) ? substr( $id, strlen( ExchangeRateSettings::OPTION ) + 1, -1 ) : '';

		switch ( $id ) {
			case 'source':
				$value = sanitize_key( (string) $value );
				return array_key_exists( $value, $this->service->get_providers() ) ? $value : HaciendaProvider::ID;
			case 'bccr_token':
				return trim( sanitize_text_field( (string) $value ) );
			case 'manual_rate':
				$value = wc_format_decimal( (string) $value );
				return is_numeric( $value ) && (float) $value > 0 ? $value : '';
			case 'max_age_hours':
				$hours = absint( $value );
				return (string) ( $hours > 0 ? $hours : ExchangeRateSettings::DEFAULT_MAX_AGE_HOURS );
			default:
				return $value;
		}
	}

	/**
	 * Fetches a new rate right after the merchant changes the source or the token.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value New settings.
	 * @return void
	 */
	public function maybe_refresh_after_save( mixed $old_value, mixed $new_value ): void {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();

		foreach ( array( 'source', 'bccr_token' ) as $key ) {
			if ( ( $old_value[ $key ] ?? '' ) !== ( $new_value[ $key ] ?? '' ) ) {
				$this->service->refresh();
				return;
			}
		}
	}

	/**
	 * Handles the "Update now" button.
	 *
	 * @return void
	 */
	public function handle_refresh(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to update the exchange rate.', 'costa-rica-kit-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::REFRESH_ACTION );

		$result = $this->service->refresh() ? 'success' : 'error';

		wp_safe_redirect( add_query_arg( self::RESULT_ARG, $result, Plugin::get_settings_url( self::SECTION ) ) );
		exit;
	}

	/**
	 * URL of the "Update now" action.
	 *
	 * @return string
	 */
	public static function get_refresh_url(): string {
		return wp_nonce_url( add_query_arg( 'action', self::REFRESH_ACTION, admin_url( 'admin-post.php' ) ), self::REFRESH_ACTION );
	}

	/**
	 * Renders the current rate, the last error and the "Update now" button.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return void
	 */
	public function render_status_field( array $field ): void {
		$rate   = $this->service->get_current();
		$status = $this->service->get_repository()->get_status();
		$title  = isset( $field['title'] ) ? (string) $field['title'] : '';
		?>
		<tr>
			<th scope="row" class="titledesc"><?php echo esc_html( $title ); ?></th>
			<td class="forminp">
				<?php if ( null === $rate ) : ?>
					<p><?php esc_html_e( 'No exchange rate yet.', 'costa-rica-kit-for-woocommerce' ); ?></p>
				<?php else : ?>
					<p>
						<?php
						printf(
							/* translators: 1: sell rate, 2: buy rate, both in colones. */
							esc_html__( 'Sell: %1$s · Buy: %2$s', 'costa-rica-kit-for-woocommerce' ),
							'<strong>' . esc_html( self::format_rate( $rate->sell ) ) . '</strong>',
							'<strong>' . esc_html( self::format_rate( $rate->buy ) ) . '</strong>'
						);
						?>
					</p>
					<p class="description">
						<?php
						printf(
							/* translators: 1: date of the rate, 2: source name, 3: date and time of the last update. */
							esc_html__( 'Rate of %1$s from %2$s, updated %3$s.', 'costa-rica-kit-for-woocommerce' ),
							esc_html( self::format_date( $rate->date ) ),
							esc_html( $this->service->get_source_label( $rate->source ) ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $rate->fetched_at ) )
						);
						?>
					</p>
					<?php if ( $this->service->is_stale() ) : ?>
						<p class="description" style="color:#d63638;">
							<?php
							printf(
								/* translators: %d: maximum age in hours. */
								esc_html( _n( 'This rate is older than %d hour.', 'This rate is older than %d hours.', $this->service->get_settings()->get_max_age_hours(), 'costa-rica-kit-for-woocommerce' ) ),
								(int) $this->service->get_settings()->get_max_age_hours()
							);
							?>
						</p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( '' !== $status['error_code'] ) : ?>
					<p class="description" style="color:#d63638;">
						<?php
						printf(
							/* translators: 1: date and time, 2: error message. */
							esc_html__( 'Last update attempt (%1$s) failed: %2$s', 'costa-rica-kit-for-woocommerce' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['attempted_at'] ) ),
							esc_html( self::get_error_message( $status['error_code'] ) )
						);
						?>
					</p>
				<?php endif; ?>

				<p>
					<a class="button" href="<?php echo esc_url( self::get_refresh_url() ); ?>"><?php esc_html_e( 'Update now', 'costa-rica-kit-for-woocommerce' ); ?></a>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Admin notices: result of "Update now", and an outdated or missing rate.
	 *
	 * @return void
	 */
	public function render_notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$on_section = $this->is_settings_section();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$result = $on_section && isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : '';

		if ( 'success' === $result ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Exchange rate updated.', 'costa-rica-kit-for-woocommerce' ) );
		} elseif ( 'error' === $result ) {
			$status = $this->service->get_repository()->get_status();
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: error message. */
						__( 'The exchange rate could not be updated: %s The last good rate is kept.', 'costa-rica-kit-for-woocommerce' ),
						self::get_error_message( $status['error_code'] )
					)
				)
			);
		}

		if ( ! $this->service->is_stale() ) {
			return;
		}

		// In colón stores the rate is informative only, so the warning stays on the settings section.
		if ( 'CRC' === get_woocommerce_currency() && ! $on_section ) {
			return;
		}

		$message = null === $this->service->get_current()
			? __( 'Costa Rica Kit: there is no exchange rate yet.', 'costa-rica-kit-for-woocommerce' )
			: sprintf(
				/* translators: %d: maximum age in hours. */
				_n( 'Costa Rica Kit: the exchange rate is older than %d hour.', 'Costa Rica Kit: the exchange rate is older than %d hours.', $this->service->get_settings()->get_max_age_hours(), 'costa-rica-kit-for-woocommerce' ),
				$this->service->get_settings()->get_max_age_hours()
			);

		if ( 'CRC' !== get_woocommerce_currency() ) {
			$message .= ' ' . __( 'SINPE Móvil is hidden at checkout until a current rate is available.', 'costa-rica-kit-for-woocommerce' );
		}

		printf(
			'<div class="notice notice-warning"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( $message ),
			esc_url( Plugin::get_settings_url( self::SECTION ) ),
			esc_html__( 'Review the exchange rate settings', 'costa-rica-kit-for-woocommerce' )
		);
	}

	/**
	 * Translated message for an update error code.
	 *
	 * @param string $code Error code.
	 * @return string
	 */
	public static function get_error_message( string $code ): string {
		switch ( $code ) {
			case 'missing_token':
				return __( 'the BCCR source needs a token.', 'costa-rica-kit-for-woocommerce' );
			case 'http_400':
				return __( 'the source rejected the request (400).', 'costa-rica-kit-for-woocommerce' );
			case 'http_401':
				return __( 'the BCCR token is invalid or expired (401). Generate a new one.', 'costa-rica-kit-for-woocommerce' );
			case 'http_403':
				return __( 'the BCCR token has no access to this service (403). Check your subscription.', 'costa-rica-kit-for-woocommerce' );
			case 'http_404':
				return __( 'the service address was not found (404).', 'costa-rica-kit-for-woocommerce' );
			case 'http_429':
				return __( 'too many requests (429). The next scheduled update will try again.', 'costa-rica-kit-for-woocommerce' );
			case 'network':
				return __( 'the service could not be reached. Check your server connection.', 'costa-rica-kit-for-woocommerce' );
			case 'invalid_response':
				return __( 'the service returned an unexpected response.', 'costa-rica-kit-for-woocommerce' );
			case 'no_data':
				return __( 'the service has no rate for the last days.', 'costa-rica-kit-for-woocommerce' );
		}

		if ( str_starts_with( $code, 'http_5' ) ) {
			return __( 'the service had an internal error. With the BCCR source this can also mean an invalid token.', 'costa-rica-kit-for-woocommerce' );
		}

		if ( str_starts_with( $code, 'http_' ) ) {
			/* translators: %s: HTTP status code. */
			return sprintf( __( 'the service answered with HTTP status %s.', 'costa-rica-kit-for-woocommerce' ), substr( $code, 5 ) );
		}

		return __( 'unknown error.', 'costa-rica-kit-for-woocommerce' );
	}

	/**
	 * A `Y-m-d` rate date in the site's date format, without shifting the day by time zone.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	public static function format_date( string $date ): string {
		$timestamp = strtotime( $date . ' 00:00:00 UTC' );

		return false === $timestamp ? $date : (string) wp_date( (string) get_option( 'date_format' ), $timestamp, new \DateTimeZone( 'UTC' ) );
	}

	/**
	 * Rate formatted for display, e.g. "₡456.04".
	 *
	 * @param float $rate Rate.
	 * @return string
	 */
	public static function format_rate( float $rate ): string {
		return '₡' . number_format_i18n( $rate, 2 );
	}

	/**
	 * Whether the current screen is the exchange rate settings section.
	 *
	 * @return bool
	 */
	private function is_settings_section(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only screen detection.
		return isset( $_GET['page'], $_GET['tab'], $_GET['section'] )
			&& 'wc-settings' === $_GET['page']
			&& Plugin::SETTINGS_TAB === $_GET['tab']
			&& self::SECTION === $_GET['section'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
