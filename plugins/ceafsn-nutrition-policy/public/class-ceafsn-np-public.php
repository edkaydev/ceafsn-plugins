<?php
/**
 * Front-end shortcode handler for CE-AFSN Nutrition Policy.
 *
 * Registers [ceafsn_policy_table] and enqueues public assets.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_NP_Public
 */
class CEAFSN_NP_Public {

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_policy_table', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [ceafsn_policy_table] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		// Enqueue public assets when the shortcode is actually rendered.
		$this->enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'per_page' => 20,
				'topic'    => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ceafsn_policy_table'
		);

		// Filters, sort, and pagination all come from the query string and are
		// re-validated against allow lists in the DB layer before use. The topic
		// attribute is the default view, so a shared link with a filter in the
		// query string always behaves the same for every visitor.
		$orderby = sanitize_key( $_GET['np_sort'] ?? 'publication_date' );
		$order   = sanitize_key( $_GET['np_order'] ?? 'desc' );

		$query_topic = sanitize_text_field( wp_unslash( $_GET['np_topic'] ?? '' ) );

		$args = array(
			'published_only' => true,
			'per_page'       => self::clamp_per_page( $atts['per_page'] ),
			'page'           => max( 1, absint( $_GET['np_page'] ?? 1 ) ),
			'topic'          => '' !== $query_topic ? $query_topic : (string) $atts['topic'],
			'search'         => sanitize_text_field( wp_unslash( $_GET['np_search'] ?? '' ) ),
			'orderby'        => $orderby,
			'order'          => $order,
		);

		$data = CEAFSN_NP_DB::get_policies( $args );

		// Resolve the attachment URL per row; a record whose PDF is gone keeps
		// its row but shows an honest "document unavailable" message.
		$rows = array();
		foreach ( $data['items'] as $row ) {
			$pdf_url = self::pdf_url( (int) $row->pdf_attachment_id );
			$rows[]  = array(
				'row'     => $row,
				'pdf_url' => $pdf_url,
				'has_pdf' => '' !== $pdf_url,
			);
		}

		$sortable        = CEAFSN_NP_DB::sortable_columns();
		$template_data   = array(
			'rows'          => $rows,
			'total'         => $data['total'],
			'topics'        => CEAFSN_NP_DB::get_topics(),
			'sortable'      => $sortable,
			'per_page'      => (int) $args['per_page'],
			'current_page'  => (int) $args['page'],
			'orderby'       => in_array( $orderby, $sortable, true ) ? $orderby : 'publication_date',
			'order'         => 'asc' === $order ? 'asc' : 'desc',
			'filter_topic'  => (string) $args['topic'],
			'filter_search' => (string) $args['search'],
			'base_url'      => get_permalink(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template receives an explicit, fixed variable set.
		extract( $template_data, EXTR_SKIP );
		require CEAFSN_NP_PLUGIN_DIR . 'public/partials/policies.php';
		return (string) ob_get_clean();
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
	 * Resolve an attachment ID to a public file URL.
	 *
	 * @param int $attachment_id WP attachment ID.
	 * @return string URL, or an empty string when the file is gone.
	 */
	public static function pdf_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Enqueue public CSS and JS.
	 */
	private function enqueue_public_assets(): void {
		if ( ! wp_style_is( 'ceafsn-np-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-np-public',
				CEAFSN_NP_PLUGIN_URL . 'assets/css/ceafsn-np-public.css',
				array(),
				CEAFSN_NP_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-np-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-np-public',
				CEAFSN_NP_PLUGIN_URL . 'assets/js/ceafsn-np-public.js',
				array(),
				CEAFSN_NP_VERSION,
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
					'np_sort'  => $column,
					'np_order' => $next,
				)
			),
			'aria_sort' => $aria,
			'next'      => $next,
		);
	}
}
