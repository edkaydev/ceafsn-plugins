<?php
/**
 * PDF validation for CE-AFSN Projects & Publications.
 *
 * A record may only be published when its document is a real, readable PDF that
 * belongs to that record. The byte-inspection rules take a string and return a
 * result, so they can be tested without WordPress or a database.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_PP_Validator
 */
class CEAFSN_PP_Validator {

	/** @var string The byte every PDF file starts with. */
	const SIGNATURE = '%PDF-';

	/** @var string Option holding filenames that are known placeholders. */
	const PLACEHOLDER_OPTION = 'ceafsn_pp_placeholder_files';

	/**
	 * Inspect raw PDF bytes.
	 *
	 * Checks, in order: the %PDF signature, a non-empty body, a terminating
	 * %%EOF, a readable page count, and whether the file contains any extractable
	 * text. The text check is what separates a real document from a blank or
	 * image-only file, which the admin can override with the scanned flag.
	 *
	 * @param string $raw Raw file contents.
	 * @return array{valid: bool, pages: int, has_text: bool, errors: array<int,string>}
	 */
	public static function inspect_bytes( string $raw ): array {
		$errors = array();

		if ( '' === $raw ) {
			return array(
				'valid'    => false,
				'pages'    => 0,
				'has_text' => false,
				'errors'   => array( __( 'The attached file is empty or could not be read.', 'ceafsn-pp' ) ),
			);
		}

		if ( ! str_starts_with( $raw, self::SIGNATURE ) ) {
			$errors[] = __( 'The attached file is not a PDF.', 'ceafsn-pp' );
		}

		// A well-formed PDF ends with %%EOF. A truncated upload does not.
		if ( ! str_contains( $raw, '%%EOF' ) ) {
			$errors[] = __( 'The PDF appears to be truncated or corrupt.', 'ceafsn-pp' );
		}

		$pages = self::count_pages( $raw );
		if ( $pages < 1 ) {
			$errors[] = __( 'The PDF page count could not be read. The file may be damaged.', 'ceafsn-pp' );
		}

		$has_text = self::has_extractable_text( $raw );

		return array(
			'valid'    => empty( $errors ),
			'pages'    => $pages,
			'has_text' => $has_text,
			'errors'   => $errors,
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
	 * Whether a PDF contains any extractable text.
	 *
	 * Looks for the text operators a content stream uses to draw glyphs. This is
	 * a presence check, not a full text extraction: it answers "is this a blank
	 * file?", which is the question that matters when a publication library
	 * should not advertise an empty document.
	 *
	 * @param string $raw Raw file contents.
	 * @return bool True when at least one text-showing operator is present.
	 */
	public static function has_extractable_text( string $raw ): bool {
		// Tj / TJ / ' / " are the text-showing operators inside a BT ... ET block.
		return 1 === preg_match( '#\bT[jJ]\b#', $raw ) || 1 === preg_match( '#BT\b.*\bET\b#s', $raw );
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
	 * Build a single-error validation result.
	 *
	 * @param string $message Error message.
	 * @return array{valid: bool, pages: int, has_text: bool, errors: array<int,string>}
	 */
	private static function fail( string $message ): array {
		return array(
			'valid'    => false,
			'pages'    => 0,
			'has_text' => false,
			'errors'   => array( $message ),
		);
	}

	/**
	 * Validate a Media Library attachment.
	 *
	 * Resolves the attachment to a file on disk and runs the byte checks. A
	 * record that points at a deleted or non-PDF attachment fails validation, so
	 * it can never reach the published state.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return array{valid: bool, pages: int, has_text: bool, errors: array<int,string>}
	 */
	public static function validate_attachment( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return self::fail( __( 'No PDF is attached to this record.', 'ceafsn-pp' ) );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return self::fail( __( 'The attached PDF is missing from the media library.', 'ceafsn-pp' ) );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( 'application/pdf' !== $mime ) {
			return self::fail( __( 'The attached file is not a PDF. Allowed type: application/pdf.', 'ceafsn-pp' ) );
		}

		$size = (int) filesize( $path );
		if ( $size < 1 ) {
			return self::fail( __( 'The attached PDF is zero bytes.', 'ceafsn-pp' ) );
		}

		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, path from get_attached_file().

		return self::inspect_bytes( $raw );
	}

	/**
	 * Validate an external document URL.
	 *
	 * Only http and https are ever requested, and only from a privileged admin
	 * save. The response must be a 200 with a non-zero reported size, so a 404
	 * page or an empty error body cannot pass as a publication.
	 *
	 * @param string $url External URL.
	 * @return array{valid: bool, pages: int, has_text: bool, size: int, errors: array<int,string>}
	 */
	public static function validate_external_url( string $url ): array {
		$url = trim( $url );

		if ( '' === $url ) {
			return array(
				'valid'    => false,
				'pages'    => 0,
				'has_text' => false,
				'size'     => 0,
				'errors'   => array( __( 'No external document URL was provided.', 'ceafsn-pp' ) ),
			);
		}

		$parts = wp_parse_url( $url );
		$scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return array(
				'valid'    => false,
				'pages'    => 0,
				'has_text' => false,
				'size'     => 0,
				'errors'   => array( __( 'The document URL must start with http:// or https://.', 'ceafsn-pp' ) ),
			);
		}

		$response = wp_safe_remote_head(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 5,
				'user-agent'  => 'CE-AFSN-Publications/' . CEAFSN_PP_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'valid'    => false,
				'pages'    => 0,
				'has_text' => false,
				'size'     => 0,
				'errors'   => array( __( 'The document URL could not be reached from the server.', 'ceafsn-pp' ) ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'valid'    => false,
				'pages'    => 0,
				'has_text' => false,
				'size'     => 0,
				'errors'   => array(
					sprintf(
						/* translators: %d: HTTP status code. */
						__( 'The document URL returned HTTP %d instead of 200.', 'ceafsn-pp' ),
						$code
					),
				),
			);
		}

		$size = (int) wp_remote_retrieve_header( $response, 'content-length' );
		if ( $size < 1 ) {
			return array(
				'valid'    => false,
				'pages'    => 0,
				'has_text' => false,
				'size'     => 0,
				'errors'   => array( __( 'The document URL did not report a file size.', 'ceafsn-pp' ) ),
			);
		}

		return array(
			'valid'    => true,
			'pages'    => 0,
			'has_text' => true,
			'size'     => $size,
			'errors'   => array(),
		);
	}

	/**
	 * Everything that must hold before a record may be published.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @return array<int,string> Errors; empty when the record may be published.
	 */
	public static function publish_blockers( array $data ): array {
		$attachment_id = (int) ( $data['pdf_attachment_id'] ?? 0 );
		$errors        = array();

		$result = self::validate_attachment( $attachment_id );
		if ( ! $result['valid'] ) {
			return $result['errors'];
		}

		$path = (string) get_attached_file( $attachment_id );

		// A placeholder is the file the old site used everywhere. Publishing it
		// is allowed, but only when the admin has explicitly said so and the file
		// still passes every other check.
		if ( self::is_placeholder_filename( $path ) && empty( $data['duplicate_ok'] ) ) {
			$errors[] = sprintf(
				/* translators: %s: placeholder file name. */
				__( '"%s" is a known placeholder document. Tick "Confirm this document is intentional" to publish it anyway.', 'ceafsn-pp' ),
				basename( (string) wp_basename( $path ) )
			);
		}

		// An admin-entered page count that disagrees with the file means one of
		// the two is wrong, and neither can be trusted.
		$entered = (int) ( $data['page_count'] ?? 0 );
		if ( $entered > 0 && $entered !== $result['pages'] ) {
			$errors[] = sprintf(
				/* translators: 1: entered page count, 2: page count read from the PDF. */
				__( 'The page count you entered (%1$d) does not match the document (%2$d pages). Correct the field or attach the right file.', 'ceafsn-pp' ),
				$entered,
				(int) $result['pages']
			);
		}

		// A document with no extractable text is usually a blank or broken file.
		// A scanned document is a legitimate exception the admin declares.
		if ( ! $result['has_text'] && empty( $data['scanned'] ) ) {
			$errors[] = __( 'No text could be read from this PDF. If it is a scanned document, tick "This is a scanned document".', 'ceafsn-pp' );
		}

		// Two records pointing at one file is the failure mode this plugin exists
		// to stop, so it is never allowed silently.
		$shared_with = self::shared_attachment_count( $attachment_id, (int) ( $data['publication_id'] ?? 0 ) );
		if ( $shared_with > 0 && empty( $data['duplicate_ok'] ) ) {
			$errors[] = sprintf(
				/* translators: %d: number of other records using the same document. */
				_n(
					'The same document is already attached to %d other record. Tick "This document is intentionally shared" and add a note explaining why.',
					'The same document is already attached to %d other records. Tick "This document is intentionally shared" and add a note explaining why.',
					$shared_with,
					'ceafsn-pp'
				),
				$shared_with
			);
		}

		// A shared or placeholder document must say why. The note is the record
		// of the decision, so it is not optional.
		$needs_note = ( $shared_with > 0 || self::is_placeholder_filename( $path ) );
		if ( $needs_note && ! empty( $data['duplicate_ok'] ) && '' === trim( (string) ( $data['duplicate_note'] ?? '' ) ) ) {
			$errors[] = __( 'A note is required when a document is shared or is a known placeholder.', 'ceafsn-pp' );
		}

		return $errors;
	}

	/**
	 * How many other records share an attachment, excluding one record.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $exclude_id    Record to exclude.
	 * @return int Count.
	 */
	public static function shared_attachment_count( int $attachment_id, int $exclude_id = 0 ): int {
		return CEAFSN_PP_DB::attachment_usage_count( $attachment_id, $exclude_id );
	}
}
