<?php
/**
 * Modules that store personal data.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Personal data exporters and erasers must keep working after a module is disabled, because the
 * data it stored (receipts, ID numbers) stays in the site. The plugin registers them for every module.
 */
interface PersonalDataInterface {

	/**
	 * Registers the privacy policy text and the personal data exporters and erasers.
	 *
	 * @return void
	 */
	public function register_privacy_tools(): void;
}
