<?php
/**
 * Field and document validation for CE-AFSN Research Fellowships.
 *
 * The byte-inspection rules take a string and return a result, so they can be
 * tested without WordPress or a database. The publish rules take the prepared
 * field array and answer one question: may this record be shown to the public?
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_RF_Validator
 */
class CEAFSN_RF_Validator {

	/** @var string The byte every PDF file starts with. */
	const SIGNATURE = '%PDF-';

	/**
	 * Inspect raw PDF bytes for a call document.
	 *
	 * The call PDF is optional, so this is less strict than the publications
	 * plugin: an unreadable or empty file is reported, but nothing here gates
	 * publishing. A broken attachment is a missing document, not a false claim
	 * about one that does not exist.
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
				'errors' => array( __( 'The attached call file is empty or could not be read.', 'ceafsn-rf' ) ),
			);
		}

		if ( ! str_starts_with( $raw, self::SIGNATURE ) ) {
			$errors[] = __( 'The attached call file is not a PDF.', 'ceafsn-rf' );
		}

		if ( ! str_contains( $raw, '%%EOF' ) ) {
			$errors[] = __( 'The call PDF appears to be truncated or corrupt.', 'ceafsn-rf' );
		}

		$pages = self::count_pages( $raw );
		if ( $pages < 1 ) {
			$errors[] = __( 'The page count could not be read from the call PDF.', 'ceafsn-rf' );
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
	 * Validate a Media Library attachment used as the call PDF.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return array{valid: bool, pages: int, errors: array<int,string>}
	 */
	public static function validate_attachment( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return array(
				'valid'  => false,
				'pages'  => 0,
				'errors' => array( __( 'No call PDF is attached to this record.', 'ceafsn-rf' ) ),
			);
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return array(
				'valid'  => false,
				'pages'  => 0,
				'errors' => array( __( 'The call PDF is missing from the media library.', 'ceafsn-rf' ) ),
			);
		}

		if ( 'application/pdf' !== get_post_mime_type( $attachment_id ) ) {
			return array(
				'valid'  => false,
				'pages'  => 0,
				'errors' => array( __( 'The attached call file is not a PDF. Allowed type: application/pdf.', 'ceafsn-rf' ) ),
			);
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
	 * Everything that must hold before a record may be published.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @return array<int,string> Errors; empty when the record may be published.
	 */
	public static function publish_blockers( array $data ): array {
		$errors = array();

		if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
			$errors[] = __( 'A title is required.', 'ceafsn-rf' );
		}

		if ( '' === trim( (string) ( $data['eligibility'] ?? '' ) ) ) {
			$errors[] = __( 'Eligibility criteria are required so a visitor can tell whether they can apply.', 'ceafsn-rf' );
		}

		$errors = array_merge( $errors, self::date_errors( $data ) );
		$errors = array_merge( $errors, self::open_justification_errors( $data ) );

		$url = trim( (string) ( $data['application_url'] ?? '' ) );
		if ( '' !== $url && ! self::is_valid_application_url( $url ) ) {
			$errors[] = __( 'The application link must be a full http or https address.', 'ceafsn-rf' );
		}

		$email = trim( (string) ( $data['contact_email'] ?? '' ) );
		if ( '' !== $email && ! is_email( $email ) ) {
			$errors[] = __( 'The contact email address is not valid.', 'ceafsn-rf' );
		}

		// A contact is stored but never published unless it was approved, and an
		// approval without an address would promise a contact that does not exist.
		if ( ! empty( $data['show_contact'] ) && '' === $email ) {
			$errors[] = __( 'Tick "Show the contact email on the public page" only after entering an address.', 'ceafsn-rf' );
		}

		// Same reasoning for the stipend: unapproved funding claims stay hidden.
		if ( ! empty( $data['show_stipend'] ) && '' === trim( (string) ( $data['stipend_info'] ?? '' ) ) ) {
			$errors[] = __( 'Tick "Show the funding information" only after entering it.', 'ceafsn-rf' );
		}

		$pdf = absint( $data['call_pdf_id'] ?? 0 );
		if ( $pdf > 0 ) {
			$result = self::validate_attachment( $pdf );
			if ( ! $result['valid'] ) {
				$errors = array_merge( $errors, $result['errors'] );
			}
		}

		if ( ! in_array( (string) ( $data['status_manual'] ?? '' ), CEAFSN_RF_Status::manual_values(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-rf' );
		}

		if ( ! in_array( (string) ( $data['status'] ?? '' ), CEAFSN_RF_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid record state.', 'ceafsn-rf' );
		}

		return $errors;
	}

	/**
	 * Date consistency checks.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @return array<int,string>
	 */
	private static function date_errors( array $data ): array {
		$errors  = array();
		$opening = CEAFSN_RF_Status::readable_date( $data['opening_date'] ?? '' );
		$closing = CEAFSN_RF_Status::readable_date( $data['closing_date'] ?? '' );

		// A date the admin typed that is not a real calendar date is reported
		// rather than quietly dropped, because a dropped closing date would
		// leave the record in the unconfirmed state for no visible reason.
		foreach ( array( 'opening_date' => $opening, 'closing_date' => $closing ) as $field => $value ) {
			$raw = trim( (string) ( $data[ $field ] ?? '' ) );
			if ( '' !== $raw && '' === $value ) {
				/* translators: %s: field name, for example "Closing date". */
				$errors[] = sprintf( __( '%s is not a valid date.', 'ceafsn-rf' ), 'closing_date' === $field ? __( 'Closing date', 'ceafsn-rf' ) : __( 'Opening date', 'ceafsn-rf' ) );
			}
		}

		// A window that ends before it starts cannot be open at any moment.
		if ( '' !== $opening && '' !== $closing && $closing < $opening ) {
			$errors[] = __( 'The closing date is before the opening date.', 'ceafsn-rf' );
		}

		return $errors;
	}

	/**
	 * Checks that back the "never Open without verification" rule.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @return array<int,string>
	 */
	private static function open_justification_errors( array $data ): array {
		$errors = array();

		$has_override = ! empty( $data['status_override'] );
		$note         = trim( (string) ( $data['override_note'] ?? '' ) );

		// An override that says nothing is not an audit trail. The note is the
		// record of why the dates were set aside.
		if ( $has_override && '' === $note ) {
			$errors[] = __( 'A status override needs a note explaining why the dates were set aside.', 'ceafsn-rf' );
		}

		// With no override, the status the page will show is the derived one.
		$status = CEAFSN_RF_Status::current( $data );

		// A record whose opening date has passed but which has no closing date
		// cannot be advertised as open, and cannot be published while the dates
		// leave it unconfirmed. Either a closing date is added, or the record is
		// left as a draft.
		if ( CEAFSN_RF_Status::UNCONFIRMED === $status ) {
			$errors[] = __( 'This opportunity has no closing date, so it cannot be confirmed open. Add a closing date, or set the status to Upcoming or Closed.', 'ceafsn-rf' );
		}

		// A manual status of "Open" with no dates at all is the same claim made
		// with less evidence: the derive path returns Open, so without this check
		// ticking one box would publish an unverified opportunity. The manual
		// field is a fallback for undated records, not a way to bypass dates.
		if ( ! $has_override
			&& CEAFSN_RF_Status::OPEN === $status
			&& CEAFSN_RF_Status::OPEN === CEAFSN_RF_Status::normalize_manual( (string) ( $data['status_manual'] ?? '' ) )
			&& '' === CEAFSN_RF_Status::readable_date( $data['opening_date'] ?? '' )
			&& '' === CEAFSN_RF_Status::readable_date( $data['closing_date'] ?? '' ) ) {
			$errors[] = __( 'A manual status of Open needs a closing date or a documented override. Until then the opportunity cannot be published as open.', 'ceafsn-rf' );
		}

		return $errors;
	}
}
