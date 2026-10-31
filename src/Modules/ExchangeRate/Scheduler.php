<?php
/**
 * Background refresh of the exchange rate with Action Scheduler.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\ExchangeRate;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules a recurring refresh twice a day in the `crkit` group.
 */
final class Scheduler {

	/**
	 * Action hook.
	 */
	public const HOOK = 'crkit_refresh_exchange_rate';

	/**
	 * Action Scheduler group of the plugin.
	 */
	public const GROUP = 'crkit';

	/**
	 * Interval: 12 hours, in seconds.
	 */
	public const INTERVAL = 43200;

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
	 * Registers the action callback and the self-healing check.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( self::class, 'ensure_scheduled' ) );
	}

	/**
	 * Runs the refresh.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->service->refresh();
	}

	/**
	 * Schedules the recurring action if it is missing (for example, after a database restore).
	 * Runs on admin requests only, to keep storefront requests free of extra queries.
	 *
	 * @return void
	 */
	public static function ensure_scheduled(): void {
		if ( wp_doing_ajax() || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			self::schedule();
		}
	}

	/**
	 * Schedules the recurring action, starting now.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( time(), self::INTERVAL, self::HOOK, array(), self::GROUP, true );
		}
	}

	/**
	 * Removes the recurring action.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}
}
