<?php
/**
 * Unit tests for IdentificationType.
 *
 * @package CRKit\Tests
 */

namespace CRKit\Tests\Unit\Identification;

use CRKit\Modules\Identification\IdentificationType;
use CRKit\Tests\Unit\TestCase;

/**
 * Formats from Hacienda's "Anexos y Estructuras" v4.4 (ADR-0046).
 *
 * @covers \CRKit\Modules\Identification\IdentificationType
 */
final class IdentificationTypeTest extends TestCase {

	/**
	 * Type, raw number, normalized number and expected validity.
	 *
	 * @return array<string, array{string, string, string, bool}>
	 */
	public static function provide_numbers(): array {
		return array(
			'física, 9 digits'              => array( '01', '112340567', '112340567', true ),
			'física, hyphens and spaces'    => array( '01', ' 1-1234-0567 ', '112340567', true ),
			'física, en dash'               => array( '01', '1–1234–0567', '112340567', true ),
			'física, leading zero'          => array( '01', '012340567', '012340567', false ),
			'física, 8 digits'              => array( '01', '11234056', '11234056', false ),
			'física, 10 digits'             => array( '01', '1123405678', '1123405678', false ),
			'física, letters'               => array( '01', '1A2340567', '1A2340567', false ),
			'jurídica, 10 digits'           => array( '02', '3-101-123456', '3101123456', true ),
			'jurídica, alphanumeric'        => array( '02', '3-101-a00001', '3101A00001', true ),
			'jurídica, 9 characters'        => array( '02', '310112345', '310112345', false ),
			'jurídica, 11 characters'       => array( '02', '31011234567', '31011234567', false ),
			'jurídica, other characters'    => array( '02', '3101.12345', '3101.12345', false ),
			'DIMEX, 11 digits'              => array( '03', '15581234567', '15581234567', true ),
			'DIMEX, 12 digits'              => array( '03', '155812345678', '155812345678', true ),
			'DIMEX, leading zero'           => array( '03', '015581234567', '015581234567', false ),
			'DIMEX, 10 digits'              => array( '03', '1558123456', '1558123456', false ),
			'DIMEX, 13 digits'              => array( '03', '1558123456789', '1558123456789', false ),
			'NITE, 10 digits'               => array( '04', '3-002-123456', '3002123456', true ),
			'NITE, leading zero is allowed' => array( '04', '0123456789', '0123456789', true ),
			'NITE, 9 digits'                => array( '04', '300212345', '300212345', false ),
			'NITE, letters'                 => array( '04', '3002A23456', '3002A23456', false ),
			'unknown type'                  => array( '05', '112340567', '112340567', false ),
			'empty type'                    => array( '', '112340567', '112340567', false ),
		);
	}

	/**
	 * Normalization removes spaces and dashes and upper-cases letters; each type has its format.
	 *
	 * @dataProvider provide_numbers
	 *
	 * @param string $type       Type code.
	 * @param string $raw        Raw number.
	 * @param string $normalized Expected normalized number.
	 * @param bool   $valid      Expected validity.
	 */
	public function test_normalize_and_validate( string $type, string $raw, string $normalized, bool $valid ): void {
		$this->assertSame( $normalized, IdentificationType::normalize( $raw ) );
		$this->assertSame( $valid, IdentificationType::is_valid( $type, $raw ) );
	}

	public function test_types_use_hacienda_codes(): void {
		$this->assertSame(
			array(
				'01' => 'Cédula física',
				'02' => 'Cédula jurídica',
				'03' => 'DIMEX',
				'04' => 'NITE',
			),
			IdentificationType::get_labels()
		);
		$this->assertTrue( IdentificationType::exists( '03' ) );
		$this->assertFalse( IdentificationType::exists( '3' ) );
		$this->assertSame( '', IdentificationType::get_label( '99' ) );
	}

	public function test_an_empty_number_has_no_format_errors(): void {
		$this->assertSame( array(), IdentificationType::get_errors( '', '' ) );
		$this->assertSame( array(), IdentificationType::get_errors( '01', ' - ' ) );
	}

	public function test_a_number_needs_a_type(): void {
		$this->assertSame( array( 'crkit_id_type_missing' ), array_keys( IdentificationType::get_errors( '', '112340567' ) ) );
	}

	public function test_an_invalid_number_reports_the_format_of_its_type(): void {
		$errors = IdentificationType::get_errors( '03', '1234567890' );

		$this->assertSame( array( 'crkit_id_number_invalid' ), array_keys( $errors ) );
		$this->assertSame( 'A DIMEX has 11 or 12 digits and does not start with 0.', $errors['crkit_id_number_invalid'] );
		$this->assertSame( array(), IdentificationType::get_errors( '03', '15581234567' ) );
	}
}
