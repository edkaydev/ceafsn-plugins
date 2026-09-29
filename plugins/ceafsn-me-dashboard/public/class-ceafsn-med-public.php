<?php
/**
 * Front-end shortcode handler for CE-AFSN M&E Dashboard.
 *
 * Registers [ceafsn_me_dashboard] and enqueues public assets.
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_MED_Public
 */
class CEAFSN_MED_Public {

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_me_dashboard', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the [ceafsn_me_dashboard] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes (unused currently).
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		// Enqueue public assets when the shortcode is actually rendered.
		$this->enqueue_public_assets();

		// Fetch data — public records only.
		$metrics      = CEAFSN_MED_DB::get_metrics( true );
		$demographics = CEAFSN_MED_DB::get_demographics( true );

		// Projects: support basic filter from query string (safe — enum validated in DB layer).
		$project_args = array(
			'per_page'     => 20,
			'page'         => max( 1, absint( $_GET['med_page'] ?? 1 ) ),
			'status'       => sanitize_key( $_GET['med_status'] ?? '' ),
			'search'       => sanitize_text_field( wp_unslash( $_GET['med_search'] ?? '' ) ),
		);
		$project_data = CEAFSN_MED_DB::get_projects( $project_args );

		// Preview mode flag.
		$preview_mode = '1' === get_option( 'ceafsn_med_preview_mode', '0' );

		// Pass data into the template via a local scope array.
		$template_data = array(
			'metrics'      => $metrics,
			'demographics' => $demographics,
			'projects'     => $project_data['items'],
			'total'        => $project_data['total'],
			'per_page'     => $project_args['per_page'],
			'current_page' => $project_args['page'],
			'preview_mode' => $preview_mode,
			'filter_status' => $project_args['status'],
			'filter_search' => $project_args['search'],
		);

		ob_start();
		// Extract into local scope for the template.
		extract( $template_data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require CEAFSN_MED_PLUGIN_DIR . 'public/partials/dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue public CSS and JS.
	 * Called on shortcode render so assets only load on pages using the shortcode.
	 */
	private function enqueue_public_assets(): void {
		if ( ! wp_style_is( 'ceafsn-med-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-med-public',
				CEAFSN_MED_PLUGIN_URL . 'assets/css/ceafsn-med-public.css',
				array(),
				CEAFSN_MED_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-med-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-med-public',
				CEAFSN_MED_PLUGIN_URL . 'assets/js/ceafsn-med-public.js',
				array(),
				CEAFSN_MED_VERSION,
				true
			);
		}
	}
}
