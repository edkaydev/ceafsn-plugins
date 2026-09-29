<?php
/**
 * Date-driven opportunity status for CE-AFSN Research Fellowships.
 *
 * An opportunity's status is normally derived from its opening and closing
 * dates, so a deadline that passes closes the listing without anyone having to
 * edit it. The only way to depart from that is an explicit admin override with
 * a written note.
 *
 * The rule this plugin exists to enforce: an opportunity is never shown as Open
 * unless a closing date proves applications are still being accepted, or an
 * administrator has overridden the calculation and said why. An opportunity
 * whose opening date has passed but which has no closing date is therefore
 * `unconfirmed`, not Open — "no deadline is recorded" is not evidence that
 * applications are open, and claiming otherwise is the failure this plugin
 * prevents.
 *
 * Every function here takes the "today" value as an argument so the logic can
 * be tested on any date without touching the clock.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_RF_Status
 */
class CEAFSN_RF_Status {

	/** @var string Applications are being accepted. */
	const OPEN = 'open';

	/** @var string The closing date has passed. */
	const CLOSED = 'closed';

	/** @var string The opening date has not arrived. */
	const UPCOMING = 'upcoming';

	/** @var string Neither in the archive nor in the current cycle. */
	const ARCHIVED = 'archived';

	/**
	 * Shown when the dates do not prove an opportunity is open.
	 *
	 * This is a display state, not an editorial one: it exists so a listing is
	 * never advertised as Open on the strength of a missing closing date. It is
	 * deliberately not one of the four editorial values, because an
	 * administrator cannot type it and cannot accidentally publish it.
	 */
	const UNCONFIRMED = 'unconfirmed';

	/**
	 * Every status the public page can display.
	 *
	 * @return array<int,string>
	 */
	public static function all(): array {
		return array( self::OPEN, self::UPCOMING, self::CLOSED, self::ARCHIVED, self::UNCONFIRMED );
	}

	/**
	 * The values an administrator may set by hand.
	 *
	 * `unconfirmed` is not among them: it is what the dates produce, not
	 * something a person decides.
	 *
	 * @return array<int,string>
	 */
	public static function manual_values(): array {
		return array( self::OPEN, self::UPCOMING, self::CLOSED, self::ARCHIVED );
	}

	/**
	 * Today's date in the site's timezone.
	 *
	 * Deadlines are dates a person reads on a calendar, so they are compared
	 * against the site's local day rather than UTC. A site in UTC+3 must not
	 * close an application on the evening before the printed deadline.
	 *
	 * @return string Y-m-d in the site timezone.
	 */
	public static function today(): string {
		if ( function_exists( 'wp_timezone' ) && function_exists( 'current_datetime' ) ) {
			$now = current_datetime( 'now', wp_timezone() );

			if ( is_object( $now ) && method_exists( $now, 'format' ) ) {
				return (string) $now->format( 'Y-m-d' );
			}
		}

		// Outside WordPress, or before the timezone helpers are available.
		return gmdate( 'Y-m-d' );
	}

	/**
	 * Normalise a stored date, or return an empty string when there is none.
	 *
	 * @param mixed $value Raw date value.
	 * @return string Y-m-d, or '' when the value is not a usable date.
	 */
	public static function readable_date( $value ): string {
		$value = trim( (string) $value );

		// MySQL renders an empty DATE as all zeroes; a null column arrives as null.
		if ( '' === $value || '0000-00-00' === $value || 0 === strpos( $value, '0000-00' ) ) {
			return '';
		}

		return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * Work out the status an opportunity should display, and why.
	 *
	 * The order matters. An opening date in the future wins over a closing date
	 * in the past, because contradictory dates are rejected on save and, if one
	 * ever reached this function, the future opening date is the more
	 * informative signal.
	 *
	 * @param array<string,mixed>|object $record Record fields or row.
	 * @param string|null                $today  Today's date; defaults to the site date.
	 * @return array{status: string, reason: string}
	 */
	public static function derive( $record, ?string $today = null ): array {
		$record = (array) $record;
		$today  = self::readable_date( $today ?? '' ) ?: self::today();

		$opening  = self::readable_date( $record['opening_date'] ?? '' );
		$closing  = self::readable_date( $record['closing_date'] ?? '' );
		$override = ! empty( $record['status_override'] );

		// An explicit override is the only route to Open that ignores the dates.
		if ( $override ) {
			return array(
				'status' => self::OPEN,
				'reason' => 'override',
			);
		}

		if ( '' !== $opening && $opening > $today ) {
			return array(
				'status' => self::UPCOMING,
				'reason' => 'opening_date_future',
			);
		}

		if ( '' !== $closing && $closing < $today ) {
			return array(
				'status' => self::CLOSED,
				'reason' => 'closing_date_passed',
			);
		}

		// Between the two dates: the application window is open, and a closing
		// date exists to prove it.
		if ( '' !== $closing ) {
			return array(
				'status' => self::OPEN,
				'reason' => 'within_dates',
			);
		}

		// The opening date has arrived (or was never set) but no closing date
		// exists, so there is no evidence applications are still accepted.
		if ( '' !== $opening ) {
			return array(
				'status' => self::UNCONFIRMED,
				'reason' => 'no_closing_date',
			);
		}

		// No dates at all: the administrator's own setting stands. Publishing
		// this as Open is still refused, so `open` here can only reach the page
		// after the validator has demanded an override.
		return array(
			'status' => self::normalize_manual( (string) ( $record['status_manual'] ?? '' ) ),
			'reason' => 'manual',
		);
	}

	/**
	 * The status an opportunity displays, without the reason.
	 *
	 * @param array<string,mixed>|object $record Record fields or row.
	 * @param string|null                $today  Today's date.
	 * @return string Status key.
	 */
	public static function current( $record, ?string $today = null ): string {
		$derived = self::derive( $record, $today );

		return (string) $derived['status'];
	}

	/**
	 * Coerce a stored manual value into one a person may set.
	 *
	 * @param string $value Raw value.
	 * @return string One of the manual values.
	 */
	public static function normalize_manual( string $value ): string {
		return in_array( $value, self::manual_values(), true )
			? $value
			: self::UPCOMING;
	}

	/**
	 * Human label for a status.
	 *
	 * @param string $status Status key.
	 * @return string Translated label.
	 */
	public static function label( string $status ): string {
		$labels = array(
			self::OPEN        => __( 'Open', 'ceafsn-rf' ),
			self::UPCOMING    => __( 'Upcoming', 'ceafsn-rf' ),
			self::CLOSED      => __( 'Closed', 'ceafsn-rf' ),
			self::ARCHIVED    => __( 'Archived', 'ceafsn-rf' ),
			self::UNCONFIRMED => __( 'Status not confirmed', 'ceafsn-rf' ),
		);

		return (string) ( $labels[ $status ] ?? $status );
	}

	/**
	 * Explain, in one sentence, why an opportunity is not Open.
	 *
	 * Shown next to a disabled Apply button so a visitor is told the reason
	 * rather than left to guess why they cannot apply.
	 *
	 * @param string $reason Reason code from derive().
	 * @return string Translated explanation, empty when the status is Open.
	 */
	public static function reason_text( string $reason ): string {
		switch ( $reason ) {
			case 'no_closing_date':
				return __( 'No closing date is recorded, so we cannot confirm that applications are still being accepted.', 'ceafsn-rf' );

			case 'opening_date_future':
				return __( 'Applications have not opened yet.', 'ceafsn-rf' );

			case 'closing_date_passed':
				return __( 'The closing date has passed.', 'ceafsn-rf' );

			case 'archived':
				return __( 'This opportunity is archived.', 'ceafsn-rf' );

			default:
				return '';
		}
	}

	/**
	 * Whether applications can be sent for this status.
	 *
	 * @param string $status Status key.
	 * @return bool True only for Open.
	 */
	public static function accepts_applications( string $status ): bool {
		return self::OPEN === $status;
	}
}
