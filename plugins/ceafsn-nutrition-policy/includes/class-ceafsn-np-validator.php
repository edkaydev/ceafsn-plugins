<?php
/**
 * PDF validation for CE-AFSN Nutrition Policy.
 *
 * A policy record may only be published when its attached document is a real,
 * readable PDF. This class is deliberately pure where it can be: the byte
 * inspection rules take a string and return a result, so they can be tested
 * without WordPress or a database.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_NP_Validator
 */
class CEAFSN_NP_Validator {

	/** @var string The byte every PDF file starts with. */
	const SIGNATURE = '%PDF-';

	/** @var string Option holding filenames that are known placeholders. */
	const PLACEHOLDER_OPTION = 'ceafsn_np_placeholder_files';

	/**
	 * Validate raw PDF bytes.
	 *
	 * Checks, in order: the %PDF signature, a non-zero body, and a page count
	 * that can actually be read. No file system or WordPress access is needed.
	 *
	 * @param string $raw Raw file contents.
	 * @return array{valid: bool, pages: int, errors: array<int,string>}
	 */
	public static function inspect_bytes( string $raw ): array {
		$errors = array();

		if ( '' === $raw ) {
			return array(
				'valid'  => false,
				'pages'  => 0,
				'errors' => array( __( 'The attached file is empty or could not be read.', 'ceafsn-np' ) ),
			);
		}

		if ( ! str_starts_with( $raw, self::SIGNATURE ) ) {
			$errors[] = __( 'The attached file is not a PDF.', 'ceafsn-np' );
		}

		// A well-formed PDF ends with %%EOF. A truncated upload does not.
		if ( ! str_contains( $raw, '%%EOF' ) ) {
			$errors[] = __( 'The PDF appears to be truncated or corrupt.', 'ceafsn-np' );
		}

		$pages = self::count_pages( $raw );
		if ( $pages < 1 ) {
			$errors[] = __( 'The PDF page count could not be read. The file may be damaged.', 'ceafsn-np' );
		}

		return array(
			'valid'  => empty( $errors ),
			'pages'  => $pages,
			'errors' => $errors,
		);
	}

	/**
	 * Count page objects in a PDF.
	 *
	 * Counts `/Type /Page` objects that are not `/Pages`, which is the page tree
	 * node. Falls back to the page tree `/Count` value when no page objects are
	 * directly visible (for example when the file is compressed).
	 *
	 * @param string $raw Raw file contents.
	 * @return int Page count, 0 when it cannot be determined.
	 */
	public static function count_pages( string $raw ): int {
		$count = preg_match_all( '#/Type\s*/Page(?![sA-Za-z])#', $raw );
		if ( is_int( $count ) && $count > 0 ) {
			return $count;
		}

		// Look for the root page tree, e.g. /Type /Pages /Count 12.
		if ( preg_match( '#/Type\s*/Pages[^>]*?/Count\s+(\d+)#s', $raw, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * Filenames that are known to be placeholders and must not be published.
	 *
	 * @return array<int,string>
	 */
	public static function placeholder_filenames(): array {
		$configured = get_option( self::PLACEHOLDER_OPTION, array() );

		if ( ! is_array( $configured ) ) {
			$configured = array();
		}

		$configured[] = 'ceafsn.pdf';

		return array_values( array_unique( array_map( 'strtolower', array_map( 'strval', $configured ) ) ) );
	}

	/**
	 * Check whether a filename is a known placeholder.
	 *
	 * @param string $filename File name or path.
	 * @return bool True when the file is a known placeholder.
	 */
	public static function is_placeholder_filename( string $filename ): bool {
		$basename = strtolower( basename( (string) wp_basename( $filename ) ) );
		return in_array( $basename, self::placeholder_filenames(), true );
	}

	/**
	 * Validate a Media Library attachment.
	 *
	 * Resolves the attachment to a file on disk and runs the byte checks. A
	 * record that points at a deleted or non-PDF attachment fails validation, so
	 * it can never reach the published state.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return array{valid: bool, pages: int, errors: array<int,string>}
	 */
	public static function validate_attachment( int $attachment_id ): array {
		$fail = static function ( string $message ): array {
			return array(
				'valid'  => false,
				'pages'  => 0,
				'errors' => array( $message ),
			);
		};

		if ( $attachment_id <= 0 ) {
			return $fail( __( 'No PDF is attached to this record.', 'ceafsn-np' ) );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return $fail( __( 'The attached PDF is missing from the media library.', 'ceafsn-np' ) );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime ) {
			return $fail( __( 'The attached file is not a PDF. Allowed type: application/pdf.', 'ceafsn-np' ) );
		}

		if ( self::is_placeholder_filename( $path ) ) {
			return $fail(
				sprintf(
					/* translators: %s: placeholder file name. */
					__( '"%s" is a known placeholder file. Attach the real policy document.', 'ceafsn-np' ),
					basename( (string) wp_basename( $path ) )
				)
			);
		}

		$size = (int) filesize( $path );
		if ( $size < 1 ) {
			return $fail( __( 'The attached PDF is zero bytes.', 'ceafsn-np' ) );
		}

		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, validated path from get_attached_file().

		return self::inspect_bytes( $raw );
	}

	/**
	 * Validate a record's attachment and return publish-blocking errors.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return array<int,string> Errors; empty when the document may be published.
	 */
	public static function publish_blockers( int $attachment_id ): array {
		$result = self::validate_attachment( $attachment_id );
		return $result['valid'] ? array() : $result['errors'];
	}
}
