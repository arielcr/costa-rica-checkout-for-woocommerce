<?php
/**
 * Integration tests: exchange rate module with WordPress, WooCommerce and Action Scheduler.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\ExchangeRateAdmin;
use CRKit\Modules\ExchangeRate\ExchangeRateSettings;
use CRKit\Modules\ExchangeRate\RateRepository;
use CRKit\Modules\ExchangeRate\Scheduler;
use CRKit\Plugin;
use WC_Admin_Settings;
use WP_UnitTestCase;

/**
 * Tests scheduling, refresh, fallback, notices and settings of the exchange rate.
 *
 * @coversNothing
 */
final class ExchangeRateTest extends WP_UnitTestCase {

	/**
	 * HTTP requests intercepted by pre_http_request.
	 *
	 * @var array<int, string>
	 */
	private array $requests = array();

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
	}

	public function set_up(): void {
		parent::set_up();
		$this->requests = array();
		delete_option( RateRepository::RATE_OPTION );
		delete_option( RateRepository::STATUS_OPTION );
		delete_option( ExchangeRateSettings::OPTION );
		delete_option( Plugin::MODULES_OPTION );
		Scheduler::unschedule();
	}

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		update_option( 'woocommerce_currency', 'USD' );
		parent::tear_down();
	}

	public function test_activation_schedules_and_deactivation_unschedules_the_refresh(): void {
		crkit_plugin()->activate();
		$this->assertTrue( as_has_scheduled_action( Scheduler::HOOK, array(), Scheduler::GROUP ) );

		crkit_plugin()->deactivate();
		$this->assertFalse( as_has_scheduled_action( Scheduler::HOOK, array(), Scheduler::GROUP ) );
	}

	public function test_toggling_the_module_schedules_and_unschedules_the_refresh(): void {
		update_option( Plugin::MODULES_OPTION, array( 'exchange_rate' => 'no' ) );
		$this->assertFalse( as_has_scheduled_action( Scheduler::HOOK, array(), Scheduler::GROUP ) );

		update_option( Plugin::MODULES_OPTION, array( 'exchange_rate' => 'yes' ) );
		$this->assertTrue( as_has_scheduled_action( Scheduler::HOOK, array(), Scheduler::GROUP ) );

		update_option( Plugin::MODULES_OPTION, array( 'exchange_rate' => 'no' ) );
		$this->assertFalse( as_has_scheduled_action( Scheduler::HOOK, array(), Scheduler::GROUP ) );
	}

	public function test_recurring_action_runs_twice_a_day(): void {
		Scheduler::schedule();
		$action_id = as_next_scheduled_action( Scheduler::HOOK, array(), Scheduler::GROUP );
		$this->assertIsInt( $action_id );

		$actions  = as_get_scheduled_actions(
			array(
				'hook'   => Scheduler::HOOK,
				'group'  => Scheduler::GROUP,
				'status' => \ActionScheduler_Store::STATUS_PENDING,
			)
		);
		$schedule = reset( $actions )->get_schedule();

		$this->assertSame( 12 * HOUR_IN_SECONDS, $schedule->get_recurrence() );
	}

	public function test_scheduled_refresh_fetches_hacienda_and_updates_the_cache(): void {
		$this->fake_http( 200, $this->fixture( 'hacienda/tc-2026-10-31.json' ) );

		do_action( Scheduler::HOOK );

		$this->assertSame( array( 'https://api.hacienda.go.cr/indicadores/tc' ), $this->requests );
		$this->assertSame( 456.04, crkit_get_exchange_rate( 'sell' ) );
		$this->assertSame( 449.7, crkit_get_exchange_rate( 'buy' ) );
		$this->assertEqualsWithDelta( 4560.4, crkit_convert_to_crc( 10.0, 'USD' ), 0.0001 );
		$this->assertSame( 'no', $this->get_autoload( RateRepository::RATE_OPTION ) );
	}

	public function test_failed_refresh_keeps_the_last_good_rate_and_records_the_error(): void {
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-10-31', 'hacienda', time() ) )->to_array(), false );
		$this->fake_http( 503, '' );

		do_action( Scheduler::HOOK );

		$this->assertSame( 456.04, crkit_get_exchange_rate( 'sell' ) );
		$status = ( new RateRepository() )->get_status();
		$this->assertSame( 'http_503', $status['error_code'] );
		$this->assertSame( 'hacienda', $status['source'] );
	}

	public function test_reading_the_rate_never_makes_http_requests(): void {
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-10-31', 'hacienda', time() - 100 * HOUR_IN_SECONDS ) )->to_array(), false );
		$this->fake_http( 200, $this->fixture( 'hacienda/tc-2026-10-31.json' ) );

		$this->assertNull( crkit_get_exchange_rate( 'sell' ), 'Outdated rates are not usable.' );
		$this->assertNull( crkit_convert_to_crc( 10.0, 'USD' ) );
		$this->assertSame( array(), $this->requests );
	}

	public function test_bccr_source_without_token_records_missing_token(): void {
		update_option( ExchangeRateSettings::OPTION, array( 'source' => 'bccr' ) );
		$this->fake_http( 200, '{}' );

		do_action( Scheduler::HOOK );

		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'missing_token', ( new RateRepository() )->get_status()['error_code'] );
	}

	public function test_api_returns_null_when_the_module_is_disabled(): void {
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-10-31', 'hacienda', time() ) )->to_array(), false );
		update_option( Plugin::MODULES_OPTION, array( 'exchange_rate' => 'no' ) );

		$this->assertNull( crkit_get_exchange_rate() );
		$this->assertNull( crkit_convert_to_crc( 10.0, 'USD' ) );
		$this->assertSame( 100.0, crkit_convert_to_crc( 100.0, 'CRC' ) );
	}

	public function test_settings_section_is_listed_and_sanitized_on_save(): void {
		$page = $this->get_settings_page();
		$this->assertArrayHasKey( 'exchange_rate', $page->get_sections() );

		$fields = $page->get_settings_for_section( 'exchange_rate' );
		WC_Admin_Settings::save_fields(
			$fields,
			array(
				ExchangeRateSettings::OPTION => array(
					'source'         => 'evil<script>',
					'bccr_token'     => " abc.def \n",
					'manual_enabled' => '1',
					'manual_rate'    => '-5',
					'max_age_hours'  => '0',
				),
			)
		);

		$saved = get_option( ExchangeRateSettings::OPTION );
		$this->assertSame( 'hacienda', $saved['source'] );
		$this->assertSame( 'abc.def', $saved['bccr_token'] );
		$this->assertSame( 'yes', $saved['manual_enabled'] );
		$this->assertSame( '', $saved['manual_rate'] );
		$this->assertSame( '48', $saved['max_age_hours'] );
	}

	public function test_manual_rate_saved_from_settings_is_used(): void {
		WC_Admin_Settings::save_fields(
			$this->get_settings_page()->get_settings_for_section( 'exchange_rate' ),
			array(
				ExchangeRateSettings::OPTION => array(
					'manual_enabled' => '1',
					'manual_rate'    => '505.25',
				),
			)
		);

		$this->assertSame( 505.25, crkit_get_exchange_rate( 'sell' ) );
		$this->assertSame( 505.25, crkit_get_exchange_rate( 'buy' ) );
	}

	public function test_outdated_rate_shows_a_notice_in_a_dollar_store(): void {
		update_option( 'woocommerce_currency', 'USD' );
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-10-01', 'hacienda', time() - 72 * HOUR_IN_SECONDS ) )->to_array(), false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = $this->render_notices();

		$this->assertStringContainsString( 'older than 48 hours', $output );
		$this->assertStringContainsString( 'SINPE Móvil is hidden', $output );
	}

	public function test_no_notice_outside_the_settings_in_a_colon_store(): void {
		update_option( 'woocommerce_currency', 'CRC' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( '', $this->render_notices() );
	}

	public function test_no_notice_for_users_who_cannot_manage_woocommerce(): void {
		update_option( 'woocommerce_currency', 'USD' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( '', $this->render_notices() );
	}

	private function render_notices(): string {
		$admin = new ExchangeRateAdmin( crkit_exchange_rate_service() );
		ob_start();
		$admin->render_notices();
		return (string) ob_get_clean();
	}

	private function fake_http( int $status, string $body ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, array $args, string $url ) use ( $status, $body ) {
				$this->requests[] = $url;
				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => $status,
						'message' => '',
					),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
	}

	private function fixture( string $path ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.
	}

	private function get_autoload( string $option ): string {
		global $wpdb;
		$autoload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test assertion.
		return in_array( $autoload, array( 'no', 'off', 'auto-off' ), true ) ? 'no' : $autoload;
	}

	private function get_settings_page(): \WC_Settings_Page {
		foreach ( apply_filters( 'woocommerce_get_settings_pages', array() ) as $page ) {
			if ( $page instanceof \CRKit\Admin\SettingsPage ) {
				return $page;
			}
		}
		$this->fail( 'Settings page not registered.' );
	}
}
