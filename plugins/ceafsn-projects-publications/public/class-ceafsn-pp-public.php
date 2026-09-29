<?php
/**
 * Front-end shortcode handler for CE-AFSN Projects & Publications.
 *
 * Registers [ceafsn_projects_pubs] and enqueues public assets.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_PP_Public
 */
class CEAFSN_PP_Public {

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_projects_pubs', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [ceafsn_projects_pubs] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		// Enqueue public assets when the shortcode is actually rendered.
		$this->enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'per_page'     => 12,
				'content_type' => '',
				'view'         => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ceafsn_projects_pubs'
		);

		// Filters, view, and pagination come from the query string and are
		// re-validated against allow lists before use. The shortcode attribute
		// is only the default, so a shared link with a filter behaves the same
		// for every visitor.
		$orderby = sanitize_key( $_GET['pp_sort'] ?? 'publication_date' );
		$order   = sanitize_key( $_GET['pp_order'] ?? 'desc' );

		$query_type = sanitize_text_field( wp_unslash( $_GET['pp_type'] ?? '' ) );

		$view = self::normalize_view( sanitize_key( $_GET['pp_view'] ?? '' ) );
		if ( '' === $view ) {
			$view = self::normalize_view( (string) $atts['view'] );
		}

		$args = array(
			'published_only' => true,
			'per_page'       => self::clamp_per_page( $atts['per_page'] ),
			'page'           => max( 1, absint( $_GET['pp_page'] ?? 1 ) ),
			'content_type'   => '' !== $query_type ? $query_type : (string) $atts['content_type'],
			'project_status' => sanitize_key( $_GET['pp_status'] ?? '' ),
			'year'           => absint( $_GET['pp_year'] ?? 0 ),
			'search'         => sanitize_text_field( wp_unslash( $_GET['pp_search'] ?? '' ) ),
			'orderby'        => $orderby,
			'order'          => $order,
		);

		// Members-only records are only ever included when the current visitor
		// actually holds the membership capability. The DB layer excludes them
		// otherwise, so a crafted query string cannot reveal them.
		$is_member        = is_user_logged_in() && current_user_can( 'read' ) && self::can_view_members_only();
		$args['include_members_only'] = $is_member;

		$data = CEAFSN_PP_DB::get_publications( $args );

		$rows = array();
		foreach ( $data['items'] as $row ) {
			$rows[] = self::build_card( $row, $is_member );
		}

		$sortable = CEAFSN_PP_DB::sortable_columns();

		$template_data = array(
			'rows'          => $rows,
			'total'         => $data['total'],
			'years'         => CEAFSN_PP_DB::get_years(),
			'content_types' => CEAFSN_PP_DB::content_types(),
			'statuses'      => CEAFSN_PP_DB::project_statuses(),
			'sortable'      => $sortable,
			'per_page'      => (int) $args['per_page'],
			'current_page'  => (int) $args['page'],
			'orderby'       => in_array( $orderby, $sortable, true ) ? $orderby : 'publication_date',
			'order'         => 'asc' === $order ? 'asc' : 'desc',
			'view'          => $view,
			'filter_type'   => (string) $args['content_type'],
			'filter_status' => (string) $args['project_status'],
			'filter_year'   => (int) $args['year'],
			'filter_search' => (string) $args['search'],
			'is_member'     => $is_member,
			'base_url'      => get_permalink(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template receives an explicit, fixed variable set.
		extract( $template_data, EXTR_SKIP );
		require CEAFSN_PP_PLUGIN_DIR . 'public/partials/publications.php';
		return (string) ob_get_clean();
	}

	/**
	 * Whether the current visitor may see members-only records.
	 *
	 * Filterable so a site with its own membership system can hook in without
	 * editing the plugin.
	 *
	 * @return bool True when members-only records are visible.
	 */
	public static function can_view_members_only(): bool {
		return (bool) apply_filters( 'ceafsn_pp_can_view_members_only', current_user_can( 'manage_options' ) );
	}

	/**
	 * Coerce a view name into one of the two supported layouts.
	 *
	 * @param string $view Raw value.
	 * @return string 'grid' or 'list'.
	 */
	public static function normalize_view( string $view ): string {
		return 'list' === $view ? 'list' : 'grid';
	}

	/**
	 * Human label for a content type.
	 *
	 * @param string $type Content type key.
	 * @return string Translated label.
	 */
	public static function type_label( string $type ): string {
		$labels = array(
			'report'             => __( 'Report', 'ceafsn-pp' ),
			'annual_report'      => __( 'Annual Report', 'ceafsn-pp' ),
			'policy_brief'       => __( 'Policy Brief', 'ceafsn-pp' ),
			'working_paper'      => __( 'Working Paper', 'ceafsn-pp' ),
			'strategic_document' => __( 'Strategic Document', 'ceafsn-pp' ),
			'project'            => __( 'Project', 'ceafsn-pp' ),
		);

		return (string) ( $labels[ $type ] ?? $type );
	}

	/**
	 * Human label for a project status.
	 *
	 * @param string $status Project status key.
	 * @return string Translated label.
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'in_progress'  => __( 'In Progress', 'ceafsn-pp' ),
			'completed'    => __( 'Completed', 'ceafsn-pp' ),
			'under_review' => __( 'Under Review', 'ceafsn-pp' ),
			'archived'     => __( 'Archived', 'ceafsn-pp' ),
		);

		return (string) ( $labels[ $status ] ?? $status );
	}

	/**
	 * Accessible label for a document link.
	 *
	 * "View Document" on its own repeats the same words for every card, so a
	 * screen reader user hears a list of identical links. The label names the
	 * record instead.
	 *
	 * @param object $row  Database row.
	 * @param array  $card Card model.
	 * @return string Label.
	 */
	public static function document_label( object $row, array $card ): string {
		$parts = array( (string) $row->title );

		if ( '' !== $card['file_size'] ) {
			$parts[] = $card['file_size'];
		}

		if ( '' !== $card['page_label'] ) {
			$parts[] = $card['page_label'];
		}

		return sprintf(
			/* translators: 1: record title, 2: document details. */
			__( 'View document: %1$s (%2$s), opens in a new tab', 'ceafsn-pp' ),
			$parts[0],
			implode( ', ', array_slice( $parts, 1 ) ) ?: __( 'PDF', 'ceafsn-pp' )
		);
	}

	/**
	 * Build a card model from a database row.
	 *
	 * @param object $row        Database row.
	 * @param bool   $is_member  Whether the viewer may see members-only records.
	 * @return array<string,mixed>
	 */
	public static function build_card( object $row, bool $is_member ): array {
		$pdf_id   = (int) $row->pdf_attachment_id;
		$cover_id = (int) $row->cover_image_id;
		$alt      = (string) $row->cover_image_alt;

		return array(
			'row'            => $row,
			'pdf_url'        => self::document_url( $pdf_id ),
			'has_pdf'        => $pdf_id > 0 && '' !== self::document_url( $pdf_id ),
			'cover_url'      => $cover_id > 0 ? (string) wp_get_attachment_url( $cover_id ) : '',
			'cover_alt'      => $alt,
			'has_cover'      => $cover_id > 0 && '' !== (string) wp_get_attachment_url( $cover_id ),
			'file_size'      => self::file_size_label( $pdf_id ),
			'page_label'     => (int) $row->page_count > 0
				? sprintf(
					/* translators: %d: number of pages. */
					_n( '%d page', '%d pages', (int) $row->page_count, 'ceafsn-pp' ),
					(int) $row->page_count
				)
				: '',
			'is_duplicate'   => 1 === (int) $row->duplicate_ok,
			'is_scanned'     => 1 === (int) $row->scanned,
			'members_only'   => 'members_only' === (string) $row->access_level,
			'locked'         => 'members_only' === (string) $row->access_level && ! $is_member,
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
	 * Human-readable file size for an attachment.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return string Label, or an empty string when the size is unknown.
	 */
	public static function file_size_label( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}

		$size = (int) filesize( $path );
		if ( $size < 1 ) {
			return '';
		}

		if ( $size < 1024 ) {
			/* translators: %d: file size in bytes. */
			return sprintf( __( '%d B', 'ceafsn-pp' ), $size );
		}

		$kb = $size / 1024;
		if ( $kb < 1024 ) {
			/* translators: %s: file size in kilobytes. */
			return sprintf( __( '%s KB', 'ceafsn-pp' ), number_format_i18n( $kb, 0 ) );
		}

		/* translators: %s: file size in megabytes. */
		return sprintf( __( '%s MB', 'ceafsn-pp' ), number_format_i18n( $kb / 1024, 1 ) );
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
	 */
	private function enqueue_public_assets(): void {
		if ( ! wp_style_is( 'ceafsn-pp-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-pp-public',
				CEAFSN_PP_PLUGIN_URL . 'assets/css/ceafsn-pp-public.css',
				array(),
				CEAFSN_PP_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-pp-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-pp-public',
				CEAFSN_PP_PLUGIN_URL . 'assets/js/ceafsn-pp-public.js',
				array(),
				CEAFSN_PP_VERSION,
				true
			);
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
					'pp_sort'  => $column,
					'pp_order' => $next,
				)
			),
			'aria_sort' => $aria,
			'next'      => $next,
		);
	}
}
