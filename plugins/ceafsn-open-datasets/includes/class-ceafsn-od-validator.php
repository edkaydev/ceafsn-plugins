<?php
/**
 * Dataset file validation for CE-AFSN Open Datasets.
 *
 * A dataset record may only be published when it offers a real, readable
 * download. The byte inspection rules take a string and return a result, so
 * they can be tested without WordPress, a database, or a network.
 *
 * Two delivery routes are supported and both must resolve to a real file:
 *
 *   1. A Media Library attachment (CSV, ZIP, or XLSX).
 *   2. An external download URL, probed with a HEAD request.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_OD_Validator
 */
class CEAFSN_OD_Validator {

	/** @var string The two bytes every ZIP container starts with. */
	const ZIP_MAGIC = 'PK';

	/** @var string Local file header record marker. */
	const ZIP_LOCAL_FILE = "PK\x03\x04";

	/** @var string End of central directory record marker. */
	const ZIP_END_OF_CENTRAL_DIRECTORY = "PK\x05\x06";

	/** @var array<int,string> File types this plugin accepts. */
	const FILE_TYPES = array( 'csv', 'zip', 'xlsx', 'other' );

	/**
	 * MIME types accepted for each file type.
	 *
	 * @var array<string,array<int,string>>
	 */
	const ALLOWED_MIME_TYPES = array(
		'csv'   => array( 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/vnd.ms-excel' ),
		'zip'   => array( 'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'multipart/x-zip' ),
		'xlsx'  => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream' ),
		'other' => array(),
	);

	/**
	 * Validate raw dataset bytes against a declared file type.
	 *
	 * Checks signature or structure, non-zero body, and readability. No file
	 * system or WordPress access is needed.
	 *
	 * @param string $raw       Raw file contents.
	 * @param string $file_type One of the self::FILE_TYPES values.
	 * @return array{valid: bool, entries: int, errors: array<int,string>}
	 */
	public static function inspect_bytes( string $raw, string $file_type = 'csv' ): array {
		$errors = array();

		if ( '' === $raw ) {
			return array(
				'valid'   => false,
				'entries' => 0,
				'errors'  => array( __( 'The dataset file is empty or could not be read.', 'ceafsn-od' ) ),
			);
		}

		$file_type = in_array( $file_type, self::FILE_TYPES, true ) ? $file_type : 'other';
		$entries   = 0;

		switch ( $file_type ) {
			case 'csv':
				$header = self::read_header_row( $raw );
				if ( null === $header ) {
					$errors[] = __( 'The CSV header row could not be read. The first row must contain at least one column name.', 'ceafsn-od' );
				} else {
					$entries = self::count_data_rows( $raw );
				}
				break;

			case 'zip':
				$entries = self::count_zip_entries( $raw );
				if ( ! str_starts_with( $raw, self::ZIP_MAGIC ) ) {
					$errors[] = __( 'The attached file is not a ZIP archive.', 'ceafsn-od' );
				} elseif ( ! str_contains( $raw, self::ZIP_END_OF_CENTRAL_DIRECTORY ) ) {
					$errors[] = __( 'The ZIP archive appears to be truncated or corrupt.', 'ceafsn-od' );
				} elseif ( $entries < 1 ) {
					$errors[] = __( 'The ZIP archive contains no files.', 'ceafsn-od' );
				}
				break;

			case 'xlsx':
				$entries = self::count_zip_entries( $raw );
				if ( ! str_starts_with( $raw, self::ZIP_MAGIC ) ) {
					$errors[] = __( 'The attached file is not a valid XLSX workbook.', 'ceafsn-od' );
				} elseif ( ! str_contains( $raw, '[Content_Types].xml' ) ) {
					$errors[] = __( 'The XLSX workbook is missing [Content_Types].xml and cannot be opened.', 'ceafsn-od' );
				}
				break;

			case 'other':
			default:
				// No structure to verify. The record still has to point at a real
				// file with a non-zero size; the "other" label is informational.
				$entries = 1;
				break;
		}

		return array(
			'valid'   => empty( $errors ),
			'entries' => $entries,
			'errors'  => $errors,
		);
	}

	/**
	 * Read the header row of a CSV file.
	 *
	 * Returns the column names, or null when the header cannot be read. A CSV
	 * is text; if the first bytes are NUL or invalid UTF-8 the file is a binary
	 * payload wearing a .csv extension.
	 *
	 * @param string $raw Raw file contents.
	 * @return array<int,string>|null Column names, or null when unreadable.
	 */
	public static function read_header_row( string $raw ): ?array {
		$line = self::first_line( $raw );
		if ( null === $line ) {
			return null;
		}

		// Strip a UTF-8 byte order mark, which spreadsheet tools add.
		if ( str_starts_with( $line, "\xEF\xBB\xBF" ) ) {
			$line = substr( $line, 3 );
		}

		if ( '' === trim( $line ) ) {
			return null;
		}

		// A binary file parsed as CSV produces control characters in the header.
		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $line ) ) {
			return null;
		}

		$delimiter = self::detect_delimiter( $line );
		$columns   = str_getcsv( $line, $delimiter );
		$columns   = is_array( $columns ) ? $columns : array();

		// Reject a header made entirely of empty cells.
		if ( ! array_filter( array_map( 'trim', $columns ), static fn( $c ) => '' !== $c ) ) {
			return null;
		}

		return array_map( 'trim', $columns );
	}

	/**
	 * Count data rows in a CSV, excluding the header.
	 *
	 * @param string $raw Raw file contents.
	 * @return int Row count.
	 */
	public static function count_data_rows( string $raw ): int {
		$lines = self::meaningful_lines( $raw );
		return max( 0, count( $lines ) - 1 );
	}

	/**
	 * Count files in a ZIP archive.
	 *
	 * Counts local file header records, which appear once per stored entry.
	 *
	 * @param string $raw Raw file contents.
	 * @return int Entry count.
	 */
	public static function count_zip_entries( string $raw ): int {
		$count = substr_count( $raw, self::ZIP_LOCAL_FILE );
		return is_int( $count ) ? $count : 0;
	}

	/**
	 * Guess the delimiter used by a CSV line.
	 *
	 * @param string $line First line of the file.
	 * @return string Single character delimiter.
	 */
	public static function detect_delimiter( string $line ): string {
		$candidates = array( ',', ';', "\t", '|' );
		$best       = ',';
		$best_count = 0;

		foreach ( $candidates as $candidate ) {
			$count = substr_count( $line, $candidate );
			if ( $count > $best_count ) {
				$best       = $candidate;
				$best_count = $count;
			}
		}

		return $best;
	}

	/**
	 * Get the first line of a file, handling LF, CRLF, and a missing final newline.
	 *
	 * @param string $raw Raw file contents.
	 * @return string|null First line, or null when the file has no content.
	 */
	public static function first_line( string $raw ): ?string {
		if ( '' === $raw ) {
			return null;
		}

		if ( false !== strpos( $raw, "\n" ) ) {
			return explode( "\n", $raw, 2 )[0];
		}

		return $raw;
	}

	/**
	 * Get every non-blank line of a file.
	 *
	 * @param string $raw Raw file contents.
	 * @return array<int,string>
	 */
	public static function meaningful_lines( string $raw ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		$kept = array();
		foreach ( $lines as $line ) {
			if ( '' !== trim( $line ) ) {
				$kept[] = $line;
			}
		}

		return $kept;
	}

	/**
	 * Check whether a MIME type is accepted for a file type.
	 *
	 * @param string $mime      MIME type reported by WordPress.
	 * @param string $file_type One of the self::FILE_TYPES values.
	 * @return bool True when the MIME type is allowed.
	 */
	public static function is_allowed_mime( string $mime, string $file_type ): bool {
		if ( 'other' === $file_type ) {
			return true;
		}

		$allowed = self::ALLOWED_MIME_TYPES[ $file_type ] ?? array();

		return in_array( strtolower( trim( $mime ) ), $allowed, true );
	}

	/**
	 * Strip the parameters from a Content-Type header value.
	 *
	 * Servers routinely answer "text/csv; charset=UTF-8"; comparing that
	 * against the allow list verbatim would reject a correct file.
	 *
	 * @param string $header Raw header value.
	 * @return string Bare MIME type.
	 */
	public static function strip_mime_parameters( string $header ): string {
		$parts = explode( ';', $header );
		return strtolower( trim( (string) $parts[0] ) );
	}

	/**
	 * Validate a Media Library attachment.
	 *
	 * @param int    $attachment_id WP attachment ID.
	 * @param string $file_type     One of the self::FILE_TYPES values.
	 * @return array{valid: bool, entries: int, errors: array<int,string>}
	 */
	public static function validate_attachment( int $attachment_id, string $file_type = 'csv' ): array {
		$fail = static function ( string $message ): array {
			return array(
				'valid'   => false,
				'entries' => 0,
				'errors'  => array( $message ),
			);
		};

		if ( $attachment_id <= 0 ) {
			return $fail( __( 'No dataset file is attached to this record.', 'ceafsn-od' ) );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return $fail( __( 'The attached dataset file is missing from the media library.', 'ceafsn-od' ) );
		}

		$file_type = in_array( $file_type, self::FILE_TYPES, true ) ? $file_type : 'other';
		$mime      = (string) get_post_mime_type( $attachment_id );

		if ( ! self::is_allowed_mime( $mime, $file_type ) ) {
			return $fail(
				sprintf(
					/* translators: %s: MIME type. */
					__( 'The attached file has an unexpected type (%s).', 'ceafsn-od' ),
					'' !== $mime ? $mime : __( 'unknown', 'ceafsn-od' )
				)
			);
		}

		$size = (int) filesize( $path );
		if ( $size < 1 ) {
			return $fail( __( 'The attached dataset file is zero bytes.', 'ceafsn-od' ) );
		}

		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, path from get_attached_file().

		return self::inspect_bytes( $raw, $file_type );
	}

	/**
	 * Check that an external download URL returns HTTP 200.
	 *
	 * Uses a HEAD request so a large dataset is not downloaded during an admin
	 * save. Transport failures are reported as errors rather than silently
	 * treated as success.
	 *
	 * @param string $url External download URL.
	 * @return array{valid: bool, size: int, errors: array<int,string>}
	 */
	public static function validate_external_url( string $url, string $file_type = 'csv' ): array {
		$fail = static function ( string $message ): array {
			return array(
				'valid' => false,
				'size'  => 0,
				'errors' => array( $message ),
			);
		};

		if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $fail( __( 'The download URL is not a valid URL.', 'ceafsn-od' ) );
		}

		// Only HTTP(S) can be probed. filter_var() also accepts ftp and gopher,
		// which are not valid places to point a public download link.
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return $fail( __( 'The download URL must be an http:// or https:// address.', 'ceafsn-od' ) );
		}

		$response = wp_safe_remote_head( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $fail(
				sprintf(
					/* translators: %s: error message. */
					__( 'The download URL could not be reached: %s', 'ceafsn-od' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return $fail(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The download URL returned HTTP %d instead of 200.', 'ceafsn-od' ),
					$code
				)
			);
		}

		$size = (int) wp_remote_retrieve_header( $response, 'content-length' );

		if ( $size < 1 ) {
			return $fail( __( 'The download URL did not report a file size.', 'ceafsn-od' ) );
		}

		// The declared file type has to match what the server says it is
		// serving, otherwise the record advertises a CSV while offering a
		// spreadsheet binary under a .csv name.
		$declared = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( '' !== $declared && ! self::is_allowed_mime( self::strip_mime_parameters( $declared ), $file_type ) ) {
			return $fail(
				sprintf(
					/* translators: 1: declared file type, 2: content type reported by the server. */
					__( 'The download URL serves %2$s, which does not match the declared file type (%1$s).', 'ceafsn-od' ),
					strtoupper( $file_type ),
					$declared
				)
			);
		}

		return array(
			'valid'  => true,
			'size'   => $size,
			'errors' => array(),
		);
	}

	/**
	 * Validate a record's download target and return publish-blocking errors.
	 *
	 * A record must offer exactly one working download route. When both are
	 * present the attachment wins, because it is the route this plugin controls.
	 *
	 * @param int    $attachment_id WP attachment ID, 0 when none.
	 * @param string $download_url  External URL, '' when none.
	 * @param string $file_type     One of the self::FILE_TYPES values.
	 * @return array<int,string> Errors; empty when the record may be published.
	 */
	public static function publish_blockers( int $attachment_id, string $download_url, string $file_type ): array {
		$result = self::verify_download_target( $attachment_id, $download_url, $file_type );

		return $result['valid'] ? array() : $result['errors'];
	}

	/**
	 * Check a record's download target and report its real size.
	 *
	 * The attachment is preferred: when one is present the external URL is not
	 * probed at all, because the record will link to the file this plugin
	 * controls. The size is measured, never taken from the form, so a record
	 * never advertises a size its file does not have.
	 *
	 * @param int    $attachment_id Attachment ID, 0 when none.
	 * @param string $download_url  External URL, '' when none.
	 * @param string $file_type     One of the self::FILE_TYPES values.
	 * @return array{valid: bool, size: int, errors: array<int,string>}
	 */
	public static function verify_download_target( int $attachment_id, string $download_url, string $file_type ): array {
		if ( $attachment_id > 0 ) {
			$result = self::validate_attachment( $attachment_id, $file_type );
			$path   = get_attached_file( $attachment_id );
			$size   = is_string( $path ) && is_readable( $path ) ? (int) filesize( $path ) : 0;

			return array(
				'valid'  => $result['valid'],
				'size'   => $size,
				'errors' => $result['errors'],
			);
		}

		if ( '' !== $download_url ) {
			return self::validate_external_url( $download_url, $file_type );
		}

		return array(
			'valid'  => false,
			'size'   => 0,
			'errors' => array( __( 'Attach a dataset file or enter a download URL. One of the two is required.', 'ceafsn-od' ) ),
		);
	}

	/**
	 * Human-readable byte size.
	 *
	 * @param int $bytes Size in bytes, 0 when unknown.
	 * @return string Formatted size, or a dash when the size is unknown.
	 */
	public static function format_size( int $bytes ): string {
		if ( $bytes < 1 ) {
			return __( 'Not available', 'ceafsn-od' );
		}

		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$index = 0;
		$value = (float) $bytes;

		while ( $value >= 1024 && $index < count( $units ) - 1 ) {
			$value /= 1024;
			++$index;
		}

		// One decimal from KB upwards so a column of sizes lines up, and the
		// reader is never shown a precision the file does not have.
		return number_format_i18n( $value, 0 === $index ? 0 : 1 ) . ' ' . $units[ $index ];
	}
}
