<?php
/**
 * Private storage for payment receipts.
 *
 * @package CRKit
 */

declare( strict_types=1 );

namespace CRKit\Modules\SinpeMovil;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stores receipts in `wp-content/uploads/crkit-receipts/` with random names, outside the Media Library.
 *
 * The folder gets an empty index.php and a deny-all .htaccess, but files are only ever served
 * through an authenticated PHP endpoint, because Nginx ignores .htaccess.
 */
final class ReceiptStorage {

	/**
	 * Folder name inside the uploads directory.
	 */
	public const DIR = 'crkit-receipts';

	/**
	 * Allowed extensions and their MIME types.
	 */
	public const ALLOWED_MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'pdf'          => 'application/pdf',
	);

	/**
	 * Absolute path of the receipts folder, without trailing slash.
	 *
	 * @return string
	 */
	public function get_dir(): string {
		$uploads = wp_upload_dir( null, false );

		return untrailingslashit( $uploads['basedir'] ) . '/' . self::DIR;
	}

	/**
	 * Creates the folder and its protection files if they are missing.
	 *
	 * @return bool Whether the folder exists and is protected.
	 */
	public function ensure_dir(): bool {
		$dir = $this->get_dir();

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$files = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "# Payment receipts are served by the plugin only.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
		);

		$filesystem = $this->get_filesystem();

		foreach ( $files as $name => $content ) {
			$path = $dir . '/' . $name;

			if ( ! file_exists( $path ) && ! $filesystem->put_contents( $path, $content, 0644 ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates and stores an uploaded file.
	 *
	 * @param array<string, mixed> $file           Entry of $_FILES.
	 * @param int                  $max_bytes      Maximum size in bytes.
	 * @param bool                 $is_http_upload False for files that did not come from an HTTP upload (CLI, tests).
	 * @return array{file: string, name: string, type: string, size: int, uploaded_at: int}|WP_Error
	 */
	public function store( array $file, int $max_bytes, bool $is_http_upload = true ): array|WP_Error {
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new WP_Error( 'too_large' );
		}

		if ( UPLOAD_ERR_OK !== $error || empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file' );
		}

		$tmp_name = $file['tmp_name'];
		$name     = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		$size     = (int) filesize( $tmp_name );

		if ( $size <= 0 ) {
			return new WP_Error( 'no_file' );
		}

		if ( $size > $max_bytes ) {
			return new WP_Error( 'too_large' );
		}

		$type = $this->validate_type( $tmp_name, $name );

		if ( null === $type ) {
			return new WP_Error( 'invalid_type' );
		}

		if ( ! $this->ensure_dir() ) {
			return new WP_Error( 'storage' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$dir        = $this->get_dir();
		$upload_dir = static function ( array $dirs ) use ( $dir ): array {
			$dirs['path']   = $dir;
			$dirs['url']    = '';
			$dirs['subdir'] = '/' . self::DIR;

			return $dirs;
		};

		$overrides = array(
			'test_form'                => false,
			'mimes'                    => self::ALLOWED_MIMES,
			'unique_filename_callback' => static fn( string $directory, string $filename, string $extension ): string => bin2hex( random_bytes( 16 ) ) . strtolower( $extension ),
		);

		$upload = array(
			'name'     => $name,
			'type'     => $type,
			'tmp_name' => $tmp_name,
			'error'    => UPLOAD_ERR_OK,
			'size'     => $size,
		);

		add_filter( 'upload_dir', $upload_dir );
		$result = $is_http_upload ? wp_handle_upload( $upload, $overrides ) : wp_handle_sideload( $upload, $overrides );
		remove_filter( 'upload_dir', $upload_dir );

		if ( isset( $result['error'] ) || empty( $result['file'] ) ) {
			return new WP_Error( 'storage' );
		}

		return array(
			'file'        => wp_basename( (string) $result['file'] ),
			'name'        => sanitize_file_name( $name ),
			'type'        => $type,
			'size'        => $size,
			'uploaded_at' => time(),
		);
	}

	/**
	 * Absolute path of a stored receipt, or null if it does not exist inside the folder.
	 *
	 * @param string $file Stored file name.
	 * @return string|null
	 */
	public function get_path( string $file ): ?string {
		$file = wp_basename( $file );

		if ( '' === $file || str_starts_with( $file, '.' ) || 'index.php' === $file ) {
			return null;
		}

		$path = $this->get_dir() . '/' . $file;

		return is_file( $path ) ? $path : null;
	}

	/**
	 * Deletes a stored receipt.
	 *
	 * @param string $file Stored file name.
	 * @return void
	 */
	public function delete( string $file ): void {
		$path = $this->get_path( $file );

		if ( null !== $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Real MIME type of an allowed file, or null.
	 *
	 * Checks the extension and the content with wp_check_filetype_and_ext(), then the content
	 * again with fileinfo, which also covers PDFs.
	 *
	 * @param string $tmp_name Path of the uploaded file.
	 * @param string $name     Original file name.
	 * @return string|null
	 */
	private function validate_type( string $tmp_name, string $name ): ?string {
		$check = wp_check_filetype_and_ext( $tmp_name, $name, self::ALLOWED_MIMES );

		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return null;
		}

		$real = $this->detect_mime( $tmp_name );

		if ( null === $real || $real !== $check['type'] ) {
			return null;
		}

		return $real;
	}

	/**
	 * MIME type detected from the file content.
	 *
	 * @param string $path File path.
	 * @return string|null
	 */
	private function detect_mime( string $path ): ?string {
		if ( ! function_exists( 'finfo_open' ) ) {
			$image = wp_get_image_mime( $path );

			return false === $image ? null : $image;
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = false === $finfo ? false : finfo_file( $finfo, $path );

		return is_string( $mime ) ? $mime : null;
	}

	/**
	 * Direct filesystem access for the protection files, regardless of FS_METHOD:
	 * the uploads folder is written by PHP itself, like any upload.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private function get_filesystem(): \WP_Filesystem_Direct {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		return new \WP_Filesystem_Direct( null );
	}
}
