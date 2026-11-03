<?php
/**
 * Integration tests: exchange rate shortcode and block.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Integration;

use CRKit\Modules\ExchangeRate\ExchangeRate;
use CRKit\Modules\ExchangeRate\RateRepository;
use WP_UnitTestCase;

/**
 * Tests the public display of the stored rate.
 *
 * @coversNothing
 */
final class ExchangeRateShortcodeTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		delete_option( 'crkit_exchange_rate_settings' );
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-11-02', 'bccr', time() ) )->to_array(), false );
	}

	public function test_shortcode_shows_both_rates_with_date_and_source(): void {
		$html = do_shortcode( '[crkit_exchange_rate]' );

		$this->assertStringContainsString( 'Sell: ₡456.04 · Buy: ₡449.70', $html );
		$this->assertStringContainsString( 'November 2, 2026', $html );
		$this->assertStringContainsString( 'BCCR', $html );
	}

	public function test_shortcode_attributes(): void {
		$html = do_shortcode( '[crkit_exchange_rate type="buy" show_date="no" show_source="no"]' );

		$this->assertStringContainsString( 'Buy: ₡449.70', $html );
		$this->assertStringNotContainsString( 'Sell', $html );
		$this->assertStringNotContainsString( 'crkit-exchange-rate__details', $html );
	}

	public function test_outdated_rate_is_marked(): void {
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-10-01', 'hacienda', time() - 100 * HOUR_IN_SECONDS ) )->to_array(), false );

		$this->assertStringContainsString( 'is-outdated', do_shortcode( '[crkit_exchange_rate]' ) );
	}

	public function test_outdated_rate_can_be_hidden(): void {
		update_option( RateRepository::RATE_OPTION, ( new ExchangeRate( 449.7, 456.04, '2026-10-01', 'hacienda', time() - 100 * HOUR_IN_SECONDS ) )->to_array(), false );

		$this->assertSame( '', do_shortcode( '[crkit_exchange_rate hide_outdated="yes"]' ) );
		$this->assertSame( '', do_blocks( '<!-- wp:crkit/exchange-rate {"hideOutdated":true} /-->' ) );
		$this->assertStringContainsString( 'is-outdated', do_blocks( '<!-- wp:crkit/exchange-rate /-->' ), 'Shown by default.' );
	}

	public function test_hide_outdated_keeps_a_current_rate(): void {
		$this->assertStringContainsString( 'Sell: ₡456.04', do_shortcode( '[crkit_exchange_rate hide_outdated="yes"]' ) );
	}

	public function test_nothing_is_shown_without_a_rate(): void {
		delete_option( RateRepository::RATE_OPTION );

		$this->assertSame( '', do_shortcode( '[crkit_exchange_rate]' ) );
	}

	public function test_block_renders_with_the_block_wrapper(): void {
		$this->assertTrue( \WP_Block_Type_Registry::get_instance()->is_registered( 'crkit/exchange-rate' ) );

		$html = do_blocks( '<!-- wp:crkit/exchange-rate {"type":"sell","showSource":false} /-->' );

		$this->assertStringContainsString( 'wp-block-crkit-exchange-rate', $html );
		$this->assertStringContainsString( 'Sell: ₡456.04', $html );
		$this->assertStringNotContainsString( 'BCCR', $html );
	}

	public function test_reading_the_rate_makes_no_http_request(): void {
		$requests = 0;
		add_filter(
			'pre_http_request',
			static function ( $preempt ) use ( &$requests ) {
				++$requests;
				return $preempt;
			}
		);

		do_shortcode( '[crkit_exchange_rate]' );

		$this->assertSame( 0, $requests );
	}
}
