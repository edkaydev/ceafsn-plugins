<?php
/**
 * Front-end shortcode handler for CE-AFSN Grants & Funding.
 *
 * Registers [ceafsn_grants] and enqueues public assets.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_GF_Public
 */
class CEAFSN_GF_Public {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_grants', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [ceafsn_grants] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		$this->enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'per_page'            => 12,
				'funding_institution' => '',
				'status'              => '',
				'view'                => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ceafsn_grants'
		);

		// The query string wins over the shortcode attribute, so a shared link
		// behaves the same for every visitor. An absent or unrecognised value
		// falls back to the attribute, and then to the card grid.
		$requested_view = sanitize_key( $_GET['gf_view'] ?? '' );
		$view           = '' !== $requested_view
			? self::normalize_view( $requested_view )
			: self::normalize_view( (string) $atts['view'] );

		$query_institution = sanitize_text_field( wp_unslash( $_GET['gf_institution'] ?? '' ) );
		$query_status      = sanitize_key( $_GET['gf_status'] ?? '' );

		$now_utc = gmdate( 'Y-m-d H:i:s' );

		$args = array(
			'published_only'      => true,
			'per_page'            => self::clamp_per_page( $atts['per_page'] ),
			'page'                => max( 1, absint( $_GET['gf_page'] ?? 1 ) ),
			'funding_institution' => '' !== $query_institution ? $query_institution : (string) $atts['funding_institution'],
			'search'              => sanitize_text_field( wp_unslash( $_GET['gf_search'] ?? '' ) ),
			'orderby'             => sanitize_key( $_GET['gf_sort'] ?? 'deadline' ),
			'order'               => sanitize_key( $_GET['gf_order'] ?? 'asc' ),
		);

		$status_filter = '' !== $query_status ? $query_status : sanitize_key( (string) $atts['status'] );
		if ( in_array( $status_filter, CEAFSN_GF_DB::grant_statuses(), true ) ) {
			$args['grant_status'] = $status_filter;
		} else {
			$status_filter = '';
		}

		$data = CEAFSN_GF_DB::get_grants( $args );

		// Closed opportunities are hidden by default unless the visitor asked for
		// them specifically, in which case an explicit request wins.
		$show_closed = self::show_closed_by_default();

		$rows = array();
		foreach ( $data['items'] as $row ) {
			$card = self::build_card( $row, $now_utc );

			if ( '' === $status_filter && ! $show_closed && 'closed' === $card['status'] ) {
				continue;
			}

			$rows[] = $card;
		}

		$sortable = CEAFSN_GF_DB::sortable_columns();

		$template_data = array(
			'rows'               => $rows,
			'total'              => count( $rows ),
			'institutions'       => CEAFSN_GF_DB::get_institutions(),
			'statuses'           => CEAFSN_GF_DB::grant_statuses(),
			'sortable'           => $sortable,
			'per_page'           => (int) $args['per_page'],
			'current_page'       => (int) $args['page'],
			'orderby'            => in_array( $args['orderby'], $sortable, true ) ? $args['orderby'] : 'deadline',
			'order'              => 'desc' === $args['order'] ? 'desc' : 'asc',
			'view'               => $view,
			'filter_institution' => (string) $args['funding_institution'],
			'filter_status'      => $status_filter,
			'filter_search'      => (string) $args['search'],
			'base_url'           => get_permalink(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template receives an explicit, fixed variable set.
		extract( $template_data, EXTR_SKIP );
		require CEAFSN_GF_PLUGIN_DIR . 'public/partials/grants.php';
		return (string) ob_get_clean();
	}

	/**
	 * Whether closed opportunities remain listed by default.
	 *
	 * @return bool True when closed opportunities are shown.
	 */
	public static function show_closed_by_default(): bool {
		return (bool) get_option( CEAFSN_GF_Activator::SHOW_CLOSED_OPTION, true );
	}

	/**
	 * Coerce a view name into one of the two supported layouts.
	 *
	 * @param string $view Raw value.
	 * @return string 'cards' or 'table'.
	 */
	public static function normalize_view( string $view ): string {
		return 'table' === $view ? 'table' : 'cards';
	}

	/**
	 * Human label for a grant status.
	 *
	 * @param string $status Status key.
	 * @return string Translated label.
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'open'     => __( 'Open', 'ceafsn-gf' ),
			'upcoming' => __( 'Upcoming', 'ceafsn-gf' ),
			'closed'   => __( 'Closed', 'ceafsn-gf' ),
			'archived' => __( 'Archived', 'ceafsn-gf' ),
		);

		return (string) ( $labels[ $status ] ?? $status );
	}

	/**
	 * Build a card model from a database row.
	 *
	 * Status is read directly from the record, never recalculated here: the
	 * administrator sets it, and the front end's only job is to be honest
	 * about whether that status is still backed by a working Apply link and,
	 * separately, whether the deadline has already passed.
	 *
	 * @param object $row     Database row.
	 * @param string $now_utc Current moment as "Y-m-d H:i:s" UTC.
	 * @return array<string,mixed>
	 */
	public static function build_card( object $row, string $now_utc ): array {
		$status = (string) ( $row->grant_status ?? '' );
		$status = in_array( $status, CEAFSN_GF_DB::grant_statuses(), true ) ? $status : 'upcoming';

		$pdf_id  = (int) ( $row->call_pdf_id ?? 0 );
		$pdf_url = self::document_url( $pdf_id );
		$app_url = trim( (string) ( $row->application_url ?? '' ) );
		$app_ok  = CEAFSN_GF_Validator::is_valid_application_url( $app_url );

		$is_open = ( 'open' === $status );

		// The Apply button is only ever a real link when the status is Open
		// *and* a usable address exists. Anything else is rendered as an inert
		// element with an explanation, never as a link to nowhere.
		$can_apply = $is_open && $app_ok;

		$deadline_raw    = (string) ( $row->deadline ?? '' );
		$deadline_passed = '' !== $deadline_raw && $deadline_raw < $now_utc;

		return array(
			'row'                => $row,
			'status'             => $status,
			'status_label'       => self::status_label( $status ),
			'is_open'            => $is_open,
			'can_apply'          => $can_apply,
			'application_url'    => $app_ok ? $app_url : '',
			'has_app_url'        => $app_ok,
			'pdf_url'            => $pdf_url,
			'has_pdf'            => $pdf_id > 0 && '' !== $pdf_url,
			'pdf_label'          => $pdf_id > 0 && '' === $pdf_url
				? __( 'Call document currently unavailable', 'ceafsn-gf' )
				: __( 'View the Official Call PDF', 'ceafsn-gf' ),
			'contact'            => ! empty( $row->show_contact ) ? (string) ( $row->contact ?? '' ) : '',
			'is_shared_document' => ! empty( $row->duplicate_ok ),
			'deadline_iso'       => '' !== $deadline_raw ? str_replace( ' ', 'T', $deadline_raw ) : '',
			'deadline_passed'    => $deadline_passed,
		);
	}

	/**
	 * Resolve an attachment ID to a public file URL.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return string URL, or an empty string when the file is gone.
	 */
	public static function document_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Format a stored UTC deadline for display in the site timezone.
	 *
	 * The machine-readable value stays in the <time datetime> attribute, so a
	 * screen reader and a machine get the unambiguous value while a person gets
	 * a readable one named with its timezone.
	 *
	 * @param string $utc_datetime Stored "Y-m-d H:i:s" UTC value.
	 * @param bool   $with_timezone Whether to name the timezone.
	 * @return string Display string, empty when there is no usable deadline.
	 */
	public static function format_deadline( string $utc_datetime, bool $with_timezone = true ): string {
		$utc_datetime = trim( $utc_datetime );

		if ( '' === $utc_datetime ) {
			return '';
		}

		try {
			$date = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
			$date->setTimezone( wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}

		$format    = trim( (string) get_option( 'date_format', 'F j, Y' ) . ' ' . (string) get_option( 'time_format', 'g:i a' ) );
		$formatted = function_exists( 'wp_date' )
			? (string) wp_date( $format, $date->getTimestamp() )
			: $date->format( $format );

		if ( ! $with_timezone ) {
			return $formatted;
		}

		return sprintf(
			/* translators: 1: formatted deadline, 2: site timezone name. */
			__( '%1$s (%2$s time)', 'ceafsn-gf' ),
			$formatted,
			(string) wp_timezone_string()
		);
	}

	/**
	 * Clamp the per_page shortcode attribute into a safe range.
	 *
	 * @param mixed $value Raw attribute value.
	 * @return int Clamped value.
	 */
	public static function clamp_per_page( $value ): int {
		$per_page = (int) $value;
		if ( $per_page < 1 ) {
			return 12;
		}
		return min( 48, $per_page );
	}

	/**
	 * Enqueue public CSS and JS.
	 *
	 * @return void
	 */
	private function enqueue_public_assets(): void {
		if ( ! wp_style_is( 'ceafsn-gf-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-gf-public',
				CEAFSN_GF_PLUGIN_URL . 'assets/css/ceafsn-gf-public.css',
				array(),
				CEAFSN_GF_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-gf-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-gf-public',
				CEAFSN_GF_PLUGIN_URL . 'assets/js/ceafsn-gf-public.js',
				array(),
				CEAFSN_GF_VERSION,
				true
			);
		}
	}

	/**
	 * Render the Apply control for one card.
	 *
	 * A live link, or an inert element with the reason. There is no third state
	 * where a visitor sees a button that quietly does nothing.
	 *
	 * @param array<string,mixed> $card Card model.
	 * @return void
	 */
	public static function render_apply( array $card ): void {
		if ( ! empty( $card['can_apply'] ) ) {
			printf(
				'<a class="ceafsn-gf-apply ceafsn-gf-apply--active" href="%1$s" rel="noopener noreferrer">%2$s</a>',
				esc_url( (string) $card['application_url'] ),
				esc_html__( 'Apply now', 'ceafsn-gf' )
			);
			return;
		}

		$reason = '';
		if ( ! empty( $card['is_open'] ) && empty( $card['has_app_url'] ) ) {
			// Open by status, but nothing to apply to. Say which half is missing.
			$reason = __( 'Application details will be published shortly.', 'ceafsn-gf' );
		} elseif ( ! empty( $card['deadline_passed'] ) ) {
			$reason = __( 'The deadline has passed.', 'ceafsn-gf' );
		}

		printf(
			'<span class="ceafsn-gf-apply ceafsn-gf-apply--inactive" role="button" aria-disabled="true"%1$s>%2$s</span>',
			'' !== $reason ? ' title="' . esc_attr( $reason ) . '"' : '',
			esc_html( empty( $card['is_open'] ) ? __( 'Applications closed', 'ceafsn-gf' ) : __( 'Apply', 'ceafsn-gf' ) )
		);

		if ( '' !== $reason ) {
			printf( '<span class="ceafsn-gf-apply__reason">%s</span>', esc_html( $reason ) );
		}
	}

	/**
	 * Build a URL for the current page with some query args overridden.
	 *
	 * @param string              $base_url Base URL.
	 * @param array<string,mixed> $args     Query args to merge.
	 * @return string
	 */
	public static function page_url( string $base_url, array $args ): string {
		return add_query_arg( $args, $base_url );
	}

	/**
	 * Build the sort link for a column header.
	 *
	 * @param string $base_url Base URL.
	 * @param string $column   Column key.
	 * @param string $orderby  Active column.
	 * @param string $order    Active order.
	 * @return array{url: string, aria_sort: string, next: string}
	 */
	public static function sort_link( string $base_url, string $column, string $orderby, string $order ): array {
		$is_active = $column === $orderby;
		$next      = ( $is_active && 'asc' === $order ) ? 'desc' : 'asc';

		// aria-sort describes the state the table is in now, not the next state.
		$aria = 'none';
		if ( $is_active ) {
			$aria = ( 'asc' === $order ) ? 'ascending' : 'descending';
		}

		return array(
			'url'       => self::page_url(
				$base_url,
				array(
					'gf_sort'  => $column,
					'gf_order' => $next,
				)
			),
			'aria_sort' => $aria,
			'next'      => $next,
		);
	}
}
