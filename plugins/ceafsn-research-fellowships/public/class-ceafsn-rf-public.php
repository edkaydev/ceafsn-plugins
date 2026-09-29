<?php
/**
 * Front-end shortcode handler for CE-AFSN Research Fellowships.
 *
 * Registers [ceafsn_fellowships] and enqueues public assets.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_RF_Public
 */
class CEAFSN_RF_Public {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_fellowships', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [ceafsn_fellowships] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		$this->enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'per_page'     => 12,
				'track_domain' => '',
				'status'       => '',
				'view'         => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ceafsn_fellowships'
		);

		// The query string wins over the shortcode attribute, so a shared link
		// behaves the same for every visitor. An absent or unrecognised value
		// falls back to the attribute, and then to the card grid.
		$requested_view = sanitize_key( $_GET['rf_view'] ?? '' );
		$view           = '' !== $requested_view
			? self::normalize_view( $requested_view )
			: self::normalize_view( (string) $atts['view'] );

		$query_track  = sanitize_text_field( wp_unslash( $_GET['rf_track'] ?? '' ) );
		$query_status = sanitize_key( $_GET['rf_status'] ?? '' );

		$today = CEAFSN_RF_Status::today();

		$args = array(
			'published_only' => true,
			'per_page'       => self::clamp_per_page( $atts['per_page'] ),
			'page'           => max( 1, absint( $_GET['rf_page'] ?? 1 ) ),
			'track_domain'   => '' !== $query_track ? $query_track : (string) $atts['track_domain'],
			'search'         => sanitize_text_field( wp_unslash( $_GET['rf_search'] ?? '' ) ),
			'orderby'        => sanitize_key( $_GET['rf_sort'] ?? 'closing_date' ),
			'order'          => sanitize_key( $_GET['rf_order'] ?? 'desc' ),
		);

		$status_filter = '' !== $query_status ? $query_status : sanitize_key( (string) $atts['status'] );

		$data = CEAFSN_RF_DB::get_fellowships( $args );

		// The status a visitor filters by is derived, not stored, so the rows are
		// filtered in PHP after the query rather than with a column the database
		// cannot compute. The page is a short list, and this keeps one definition
		// of "is it open" instead of two that can drift apart.
		$rows       = array();
		$statuses   = array();
		$show_closed = self::show_closed_by_default();

		foreach ( $data['items'] as $row ) {
			$card = self::build_card( $row, $today );

			$statuses[ $card['status'] ] = true;

			if ( ! $show_closed && CEAFSN_RF_Status::CLOSED === $card['status'] ) {
				continue;
			}

			if ( '' !== $status_filter && $status_filter !== $card['status'] ) {
				continue;
			}

			$rows[] = $card;
		}

		$sortable = CEAFSN_RF_DB::sortable_columns();

		$template_data = array(
			'rows'          => $rows,
			'total'         => count( $rows ),
			'tracks'        => CEAFSN_RF_DB::get_tracks(),
			'present'       => array_keys( $statuses ),
			'sortable'      => $sortable,
			'per_page'      => (int) $args['per_page'],
			'current_page'  => (int) $args['page'],
			'orderby'       => in_array( $args['orderby'], $sortable, true ) ? $args['orderby'] : 'closing_date',
			'order'         => 'asc' === $args['order'] ? 'asc' : 'desc',
			'view'          => $view,
			'filter_track'  => (string) $args['track_domain'],
			'filter_status' => $status_filter,
			'filter_search' => (string) $args['search'],
			'today'         => $today,
			'base_url'      => get_permalink(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template receives an explicit, fixed variable set.
		extract( $template_data, EXTR_SKIP );
		require CEAFSN_RF_PLUGIN_DIR . 'public/partials/fellowships.php';
		return (string) ob_get_clean();
	}

	/**
	 * Whether closed opportunities remain listed.
	 *
	 * Closed calls are often kept as a public record that the programme ran, so
	 * the default is to keep them — with a truthful status and a disabled Apply
	 * button rather than a dead link. An administrator can hide them instead.
	 *
	 * @return bool True when closed opportunities are shown.
	 */
	public static function show_closed_by_default(): bool {
		return (bool) get_option( CEAFSN_RF_Activator::SHOW_CLOSED_OPTION, true );
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
	 * Build a card model from a database row.
	 *
	 * @param object $row   Database row.
	 * @param string $today Today's date in the site timezone.
	 * @return array<string,mixed>
	 */
	public static function build_card( object $row, string $today ): array {
		$derived = CEAFSN_RF_Status::derive( $row, $today );
		$status  = (string) $derived['status'];

		$pdf_id   = (int) ( $row->call_pdf_id ?? 0 );
		$pdf_url  = self::document_url( $pdf_id );
		$app_url  = trim( (string) ( $row->application_url ?? '' ) );
		$app_ok   = CEAFSN_RF_Validator::is_valid_application_url( $app_url );

		// The Apply button is only ever a real link when applications are open
		// *and* a usable address exists. Anything else is rendered as an inert
		// element with an explanation, never as a link to nowhere.
		$can_apply = CEAFSN_RF_Status::accepts_applications( $status ) && $app_ok;

		return array(
			'row'           => $row,
			'status'        => $status,
			'status_label'  => CEAFSN_RF_Status::label( $status ),
			'status_reason' => CEAFSN_RF_Status::reason_text( (string) $derived['reason'] ),
			'is_override'   => ! empty( $row->status_override ),
			'is_open'       => CEAFSN_RF_Status::accepts_applications( $status ),
			'can_apply'     => $can_apply,
			'application_url' => $app_ok ? $app_url : '',
			'has_app_url'   => $app_ok,
			'pdf_url'       => $pdf_url,
			'has_pdf'       => $pdf_id > 0 && '' !== $pdf_url,
			'pdf_label'     => $pdf_id > 0 && '' === $pdf_url
				? __( 'Call document currently unavailable', 'ceafsn-rf' )
				: __( 'View the call document', 'ceafsn-rf' ),
			'contact_email' => ! empty( $row->show_contact ) ? (string) ( $row->contact_email ?? '' ) : '',
			'stipend'       => ! empty( $row->show_stipend ) ? (string) ( $row->stipend_info ?? '' ) : '',
			'opening_date'  => CEAFSN_RF_Status::readable_date( $row->opening_date ?? '' ),
			'closing_date'  => CEAFSN_RF_Status::readable_date( $row->closing_date ?? '' ),
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
	 * Format a stored date for display, with the site timezone named.
	 *
	 * The machine-readable value stays in the <time datetime> attribute, so a
	 * screen reader and a machine get the unambiguous value while a person gets
	 * a readable one.
	 *
	 * @param string $date Y-m-d date.
	 * @param bool   $with_timezone Whether to name the timezone.
	 * @return string Display string, empty when there is no date.
	 */
	public static function format_date( string $date, bool $with_timezone = true ): string {
		$date = CEAFSN_RF_Status::readable_date( $date );

		if ( '' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date . ' 00:00:00' );
		if ( false === $timestamp ) {
			return '';
		}

		// date_i18n respects the site locale for month and weekday names.
		$formatted = function_exists( 'date_i18n' )
			? (string) date_i18n( get_option( 'date_format', 'F j, Y' ), $timestamp )
			: gmdate( 'F j, Y', $timestamp );

		if ( ! $with_timezone || ! function_exists( 'wp_timezone_string' ) ) {
			return $formatted;
		}

		return sprintf(
			/* translators: 1: formatted date, 2: site timezone name. */
			__( '%1$s (%2$s time)', 'ceafsn-rf' ),
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
		if ( ! wp_style_is( 'ceafsn-rf-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-rf-public',
				CEAFSN_RF_PLUGIN_URL . 'assets/css/ceafsn-rf-public.css',
				array(),
				CEAFSN_RF_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-rf-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-rf-public',
				CEAFSN_RF_PLUGIN_URL . 'assets/js/ceafsn-rf-public.js',
				array(),
				CEAFSN_RF_VERSION,
				true
			);
		}
	}

	/**
	 * Render the Apply control for one card.
	 *
	 * A live link, or an inert element with the reason. There is no third state
	 * where a visitor sees a button that quietly does nothing, and no case where
	 * a closed call still links out to a form that will reject the submission.
	 *
	 * @param array<string,mixed> $card Card model.
	 * @return void
	 */
	public static function render_apply( array $card ): void {
		if ( ! empty( $card['can_apply'] ) ) {
			printf(
				'<a class="ceafsn-rf-apply ceafsn-rf-apply--active" href="%1$s" rel="noopener noreferrer">%2$s</a>',
				esc_url( (string) $card['application_url'] ),
				esc_html__( 'Apply now', 'ceafsn-rf' )
			);
			return;
		}

		$reason = '';
		if ( ! empty( $card['is_open'] ) && empty( $card['has_app_url'] ) ) {
			// Open by dates, but nothing to apply to. Say which half is missing.
			$reason = __( 'Application details will be published shortly.', 'ceafsn-rf' );
		} elseif ( ! empty( $card['status_reason'] ) ) {
			$reason = (string) $card['status_reason'];
		}

		printf(
			'<span class="ceafsn-rf-apply ceafsn-rf-apply--inactive" role="button" aria-disabled="true"%1$s>%2$s</span>',
			'' !== $reason ? ' title="' . esc_attr( $reason ) . '"' : '',
			esc_html( empty( $card['is_open'] ) ? __( 'Applications closed', 'ceafsn-rf' ) : __( 'Apply', 'ceafsn-rf' ) )
		);

		if ( '' !== $reason ) {
			printf( '<span class="ceafsn-rf-apply__reason">%s</span>', esc_html( $reason ) );
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
					'rf_sort'  => $column,
					'rf_order' => $next,
				)
			),
			'aria_sort' => $aria,
			'next'      => $next,
		);
	}
}
