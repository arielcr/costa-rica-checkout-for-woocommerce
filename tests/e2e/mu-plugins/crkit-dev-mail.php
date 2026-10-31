<?php
/**
 * Development only (mapped by .wp-env.json, never shipped): saves every email as an HTML file
 * in wp-content/uploads/crkit-dev-mail/ instead of sending it, so they can be reviewed.
 *
 * @package CRKit\Tests
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, array $atts ) {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'crkit-dev-mail';
		wp_mkdir_p( $dir );

		$to      = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];
		$name    = gmdate( 'Ymd-His' ) . '-' . sanitize_title( (string) $atts['subject'] ) . '.html';
		$headers = '<!-- To: ' . esc_html( $to ) . ' | Subject: ' . esc_html( (string) $atts['subject'] ) . " -->\n";
		$body    = (string) $atts['message'];

		if ( false === stripos( $body, '<html' ) ) {
			$body = '<pre>' . esc_html( $body ) . '</pre>';
		}

		file_put_contents( $dir . '/' . $name, $headers . $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Development helper.

		return true;
	},
	10,
	2
);
