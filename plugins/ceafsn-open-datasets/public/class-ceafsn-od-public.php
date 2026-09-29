<?php
/**
 * Front-end shortcode handler for CE-AFSN Open Datasets.
 *
 * Registers [ceafsn_open_datasets] and enqueues public assets.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_OD_Public
 */
class CEAFSN_OD_Public {

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_open_datasets', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [ceafsn_open_datasets] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		// Enqueue public assets when the shortcode is actually rendered.
		$this->enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'per_page'  => 20,
				'category'  => '',
				'file_type' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ceafsn_open_datasets'
		);

		// Filters, sort, and pagination come from the query string and are
		// re-validated against allow lists in the DB layer before use. The
		// query string always wins so a shared link behaves the same for
		// every visitor, but a shortcode attribute pre-filters the view.
		$orderby = CEAFSN_OD_Request::key( 'od_sort', 'last_updated' );
		$order   = CEAFSN_OD_Request::key( 'od_order', 'desc' );

		$args = array(
			'published_only' => true,
			'per_page'       => self::clamp_per_page( $atts['per_page'] ),
			'page'           => max( 1, CEAFSN_OD_Request::int( 'od_page', 1 ) ),
			'category'       => self::filter_value( CEAFSN_OD_Request::text( 'od_category' ), (string) ( $atts['category'] ?? '' ) ),
			'file_type'      => self::filter_value( CEAFSN_OD_Request::text( 'od_file_type' ), (string) ( $atts['file_type'] ?? '' ) ),
			'search'         => CEAFSN_OD_Request::text( 'od_search' ),
			'orderby'        => $orderby,
			'order'          => $order,
		);

		$data = CEAFSN_OD_DB::get_datasets( $args );

		// Resolve the download target per row. A published record whose file
		// has been deleted keeps its row but shows an honest unavailable
		// message rather than a dead link.
		$rows = array();
		foreach ( $data['items'] as $row ) {
			$url      = CEAFSN_OD_DB::resolve_download_url( $row );
			$rows[]   = array(
				'row'        => $row,
				'download'   => $url,
				'has_file'   => '' !== $url,
				'size_label' => CEAFSN_OD_Validator::format_size( (int) ( $row->file_size ?? 0 ) ),
			);
		}

		$sortable      = CEAFSN_OD_DB::sortable_columns();
		$template_data = array(
			'rows'           => $rows,
			'total'          => $data['total'],
			'categories'     => CEAFSN_OD_DB::get_categories(),
			'file_types'     => CEAFSN_OD_DB::file_type_labels(),
			'sortable'       => $sortable,
			'per_page'       => (int) $args['per_page'],
			'current_page'   => (int) $args['page'],
			'orderby'        => in_array( $orderby, $sortable, true ) ? $orderby : 'last_updated',
			'order'          => 'asc' === $order ? 'asc' : 'desc',
			'filter_cat'     => (string) $args['category'],
			'filter_type'    => (string) $args['file_type'],
			'filter_search'  => (string) $args['search'],
			'show_contact'   => (bool) get_option( 'ceafsn_od_show_contact', 0 ),
			'base_url'       => get_permalink(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template receives an explicit, fixed variable set.
		extract( $template_data, EXTR_SKIP );
		require CEAFSN_OD_PLUGIN_DIR . 'public/partials/datasets.php';
		return (string) ob_get_clean();
	}

	/**
	 * Pick a filter value: the query string wins, otherwise the shortcode
	 * attribute is used as the default view.
	 *
	 * Both arguments arrive already unslashed — the query side through
	 * CEAFSN_OD_Request, the attribute side through shortcode_atts() — so this
	 * only has to choose and sanitise.
	 *
	 * @param mixed $query    Query string value.
	 * @param mixed $fallback Shortcode attribute value.
	 * @return string Sanitised value.
	 */
	public static function filter_value( $query, $fallback ): string {
		$raw = ( '' !== (string) $query ) ? $query : $fallback;
		return sanitize_text_field( (string) $raw );
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
			return 20;
		}
		return min( 100, $per_page );
	}

	/**
	 * Enqueue public CSS and JS.
	 */
	private function enqueue_public_assets(): void {
		if ( ! wp_style_is( 'ceafsn-od-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-od-public',
				CEAFSN_OD_PLUGIN_URL . 'assets/css/ceafsn-od-public.css',
				array(),
				CEAFSN_OD_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-od-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-od-public',
				CEAFSN_OD_PLUGIN_URL . 'assets/js/ceafsn-od-public.js',
				array(),
				CEAFSN_OD_VERSION,
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
	 * Sorting always returns to page 1; staying on page 3 of a newly ordered
	 * result set would show an empty table.
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
					'od_sort'  => $column,
					'od_order' => $next,
					'od_page'  => 1,
				)
			),
			'aria_sort' => $aria,
			'next'      => $next,
		);
	}
}
