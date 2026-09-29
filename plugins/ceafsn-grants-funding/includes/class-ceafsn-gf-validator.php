<?php
/**
 * Field and document validation for CE-AFSN Grants & Funding.
 *
 * The byte-inspection rules take a string and return a result, so they can be
 * tested without WordPress or a database. The publish rules take the prepared
 * field array and answer one question: may this record be shown to the
 * public? Unlike the fellowships plugin, the call PDF here is required, not
 * optional — the specification treats "no document" the same as "no proof",
 * so a grant record with no real, unique call PDF is never published.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_GF_Validator
 */
class CEAFSN_GF_Validator {

	/** @var string The byte every PDF file starts with. */
	const SIGNATURE = '%PDF-';

	/**
	 * Inspect raw PDF bytes for a call document.
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
				'errors' => array( __( 'The attached call file is empty or could not be read.', 'ceafsn-gf' ) ),
			);
		}

		if ( ! str_starts_with( $raw, self::SIGNATURE ) ) {
			$errors[] = __( 'The attached call file is not a PDF.', 'ceafsn-gf' );
		}

		if ( ! str_contains( $raw, '%%EOF' ) ) {
			$errors[] = __( 'The call PDF appears to be truncated or corrupt.', 'ceafsn-gf' );
		}

		$pages = self::count_pages( $raw );
		if ( $pages < 1 ) {
			$errors[] = __( 'The page count could not be read from the call PDF.', 'ceafsn-gf' );
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
	 * @param string $raw Raw file contents.
	 * @return int Page count, 0 when it cannot be determined.
	 */
	public static function count_pages( string $raw ): int {
		$count = preg_match_all( '#/Type\s*/Page(?![sA-Za-z])#', $raw );
		if ( is_int( $count ) && $count > 0 ) {
			return $count;
		}

		if ( preg_match( '#/Type\s*/Pages[^>]*?/Count\s+(\d+)#s', $raw, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * Build a single-error validation result.
	 *
	 * @param string $message Error message.
	 * @return array{valid: bool, pages: int, errors: array<int,string>}
	 */
	private static function fail( string $message ): array {
		return array(
			'valid'  => false,
			'pages'  => 0,
			'errors' => array( $message ),
		);
	}

	/**
	 * Validate a Media Library attachment used as the call PDF.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return array{valid: bool, pages: int, errors: array<int,string>}
	 */
	public static function validate_attachment( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return self::fail( __( 'No Official Call PDF is attached to this record.', 'ceafsn-gf' ) );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return self::fail( __( 'The Official Call PDF is missing from the media library.', 'ceafsn-gf' ) );
		}

		if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			return self::fail( __( 'The attached call file is not a PDF. Allowed type: application/pdf.', 'ceafsn-gf' ) );
		}

		$size = (int) filesize( $path );
		if ( $size < 1 ) {
			return self::fail( __( 'The Official Call PDF is zero bytes.', 'ceafsn-gf' ) );
		}

		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, path from get_attached_file().

		return self::inspect_bytes( $raw );
	}

	/**
	 * Whether a string is an http or https URL.
	 *
	 * A scheme check happens before anything else, so a `javascript:` or `data:`
	 * URL is rejected without a request ever being considered.
	 *
	 * @param string $url Raw URL.
	 * @return bool True when the URL is a well-formed http(s) address.
	 */
	public static function is_valid_application_url( string $url ): bool {
		$url = trim( $url );

		if ( '' === $url ) {
			return false;
		}

		if ( 1 !== preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );

		return is_string( $host ) && '' !== $host;
	}

	/**
	 * How many other records share a call PDF, excluding one record.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $exclude_id    Record to exclude.
	 * @return int Count.
	 */
	public static function shared_attachment_count( int $attachment_id, int $exclude_id = 0 ): int {
		return CEAFSN_GF_DB::attachment_usage_count( $attachment_id, $exclude_id );
	}

	/**
	 * Everything that must hold before a record may be published.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @return array<int,string> Errors; empty when the record may be published.
	 */
	public static function publish_blockers( array $data ): array {
		$errors = array();

		if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
			$errors[] = __( 'A title is required.', 'ceafsn-gf' );
		}

		if ( '' === trim( (string) ( $data['award_range'] ?? '' ) ) ) {
			$errors[] = __( 'An award range is required. Use "Not disclosed" if the amount is unknown.', 'ceafsn-gf' );
		}

		if ( '' === trim( (string) ( $data['funding_institution'] ?? '' ) ) ) {
			$errors[] = __( 'The funding institution is required.', 'ceafsn-gf' );
		}

		if ( '' === trim( (string) ( $data['eligibility'] ?? '' ) ) ) {
			$errors[] = __( 'Eligibility criteria are required so a visitor can tell whether they can apply.', 'ceafsn-gf' );
		}

		if ( empty( $data['deadline'] ) ) {
			$errors[] = __( 'A deadline is required. Enter the real application deadline; nothing here is invented.', 'ceafsn-gf' );
		}

		// The call PDF is required, not optional: a grant with no real document
		// behind it is exactly the failure mode this plugin exists to prevent.
		$pdf    = absint( $data['call_pdf_id'] ?? 0 );
		$result = CEAFSN_GF_Validator::validate_attachment( $pdf );
		if ( ! $result['valid'] ) {
			$errors = array_merge( $errors, $result['errors'] );
		} else {
			// Two records pointing at one file is not allowed silently.
			$shared_with = self::shared_attachment_count( $pdf, (int) ( $data['grant_id'] ?? 0 ) );
			if ( $shared_with > 0 && empty( $data['duplicate_ok'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: number of other records using the same document. */
					_n(
						'The same call PDF is already attached to %d other record. Tick "This document is intentionally shared" and add a note explaining why.',
						'The same call PDF is already attached to %d other records. Tick "This document is intentionally shared" and add a note explaining why.',
						$shared_with,
						'ceafsn-gf'
					),
					$shared_with
				);
			}

			if ( $shared_with > 0 && ! empty( $data['duplicate_ok'] ) && '' === trim( (string) ( $data['duplicate_note'] ?? '' ) ) ) {
				$errors[] = __( 'A note is required when a call PDF is intentionally shared with another record.', 'ceafsn-gf' );
			}
		}

		$url = trim( (string) ( $data['application_url'] ?? '' ) );
		if ( '' !== $url && ! self::is_valid_application_url( $url ) ) {
			$errors[] = __( 'The application link must be a full http or https address.', 'ceafsn-gf' );
		}

		// A contact is stored but never published unless it was approved, and an
		// approval without a value would promise a contact that does not exist.
		if ( ! empty( $data['show_contact'] ) && '' === trim( (string) ( $data['contact'] ?? '' ) ) ) {
			$errors[] = __( 'Tick "Show the contact on the public page" only after entering one.', 'ceafsn-gf' );
		}

		if ( ! in_array( (string) ( $data['grant_status'] ?? '' ), CEAFSN_GF_DB::grant_statuses(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-gf' );
		}

		if ( ! in_array( (string) ( $data['status'] ?? '' ), CEAFSN_GF_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid record state.', 'ceafsn-gf' );
		}

		return $errors;
	}
}
