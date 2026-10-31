<?php
/**
 * Unit tests for phone number handling.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit;

use CRKit\Support\Phone;

/**
 * Tests normalization, validation and formatting of Costa Rican numbers.
 *
 * @covers \CRKit\Support\Phone
 */
final class PhoneTest extends TestCase {

	/**
	 * Inputs and their normalized value.
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public static function provide_numbers(): array {
		return array(
			'plain'                => array( '88887777', '88887777', true ),
			'hyphen'               => array( '8888-7777', '88887777', true ),
			'spaces'               => array( ' 8888 7777 ', '88887777', true ),
			'country code'         => array( '+506 8888-7777', '88887777', true ),
			'country code no plus' => array( '506 88887777', '88887777', true ),
			'parentheses and dots' => array( '(506) 8888.7777', '88887777', true ),
			'seven digits'         => array( '888-7777', '8887777', false ),
			'nine digits'          => array( '8888-77771', '888877771', false ),
			'letters'              => array( 'abcd-efgh', '', false ),
			'other country code'   => array( '+1 305 555 0101', '13055550101', false ),
		);
	}

	/**
	 * Normalization strips separators and the +506 prefix; only 8 digits are valid.
	 *
	 * @dataProvider provide_numbers
	 *
	 * @param string $input      Raw number.
	 * @param string $normalized Expected normalized number.
	 * @param bool   $valid      Expected validity.
	 */
	public function test_normalize_and_validate( string $input, string $normalized, bool $valid ): void {
		$this->assertSame( $normalized, Phone::normalize( $input ) );
		$this->assertSame( $valid, Phone::is_valid( $input ) );
	}

	public function test_format(): void {
		$this->assertSame( '8888-7777', Phone::format( '+506 88887777' ) );
		$this->assertSame( '8887777', Phone::format( '888-7777' ) );
	}
}
