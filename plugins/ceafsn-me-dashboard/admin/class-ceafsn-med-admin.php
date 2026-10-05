<?php
/**
 * Admin menu, CRUD routing, and settings for CE-AFSN M&E Dashboard.
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_MED_Admin
 */
class CEAFSN_MED_Admin {

	/** @var string Admin page parent slug. Lands on the Overview dashboard. */
	const MENU_SLUG = 'ceafsn-med';

	/** @var string Metrics list/add/edit page slug. */
	const PAGE_METRICS = 'ceafsn-med-metrics';

	/** @var string Demographics list/add/edit page slug. */
	const PAGE_DEMOGRAPHICS = 'ceafsn-med-demographics';

	/** @var string Projects list/add/edit page slug. */
	const PAGE_PROJECTS = 'ceafsn-med-projects';

	/** @var string Settings page slug. */
	const PAGE_SETTINGS = 'ceafsn-med-settings';

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ceafsn_med_save_metric',      array( $this, 'handle_save_metric' ) );
		add_action( 'admin_post_ceafsn_med_delete_metric',    array( $this, 'handle_delete_metric' ) );
		add_action( 'admin_post_ceafsn_med_save_demo',        array( $this, 'handle_save_demographic' ) );
		add_action( 'admin_post_ceafsn_med_delete_demo',      array( $this, 'handle_delete_demographic' ) );
		add_action( 'admin_post_ceafsn_med_save_project',     array( $this, 'handle_save_project' ) );
		add_action( 'admin_post_ceafsn_med_delete_project',   array( $this, 'handle_delete_project' ) );
		add_action( 'admin_post_ceafsn_med_save_settings',    array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_med_export',           array( $this, 'handle_export' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the admin menu and sub-pages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'M&E Dashboard', 'ceafsn-med' ),
			__( 'M&E Dashboard', 'ceafsn-med' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_overview' ),
			'dashicons-chart-bar',
			30
		);

		// Overview — same slug as the parent so the sidebar item lands here.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Overview', 'ceafsn-med' ),
			__( 'Overview', 'ceafsn-med' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_overview' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Metrics', 'ceafsn-med' ),
			__( 'Metrics', 'ceafsn-med' ),
			'manage_options',
			self::PAGE_METRICS,
			array( $this, 'page_metrics' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Demographics', 'ceafsn-med' ),
			__( 'Demographics', 'ceafsn-med' ),
			'manage_options',
			self::PAGE_DEMOGRAPHICS,
			array( $this, 'page_demographics' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Projects', 'ceafsn-med' ),
			__( 'Projects', 'ceafsn-med' ),
			'manage_options',
			self::PAGE_PROJECTS,
			array( $this, 'page_projects' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-med' ),
			__( 'Settings', 'ceafsn-med' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( $this, 'page_settings' )
		);
	}

	// ---------------------------------------------------------------------------
	// Asset enqueuing
	// ---------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$plugin_pages = array(
			'toplevel_page_' . self::MENU_SLUG,
			'me-dashboard_page_' . self::PAGE_METRICS,
			'me-dashboard_page_' . self::PAGE_DEMOGRAPHICS,
			'me-dashboard_page_' . self::PAGE_PROJECTS,
			'me-dashboard_page_' . self::PAGE_SETTINGS,
		);

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ceafsn-med-admin',
			CEAFSN_MED_PLUGIN_URL . 'assets/css/ceafsn-med-admin.css',
			array(),
			CEAFSN_MED_VERSION
		);

		wp_enqueue_script(
			'ceafsn-med-admin',
			CEAFSN_MED_PLUGIN_URL . 'assets/js/ceafsn-med-admin.js',
			array( 'jquery' ),
			CEAFSN_MED_VERSION,
			true
		);
	}

	// ---------------------------------------------------------------------------
	// Page renderers (delegate to partials)
	// ---------------------------------------------------------------------------

	/**
	 * Overview dashboard — the page the sidebar item lands on.
	 *
	 * Aggregates real record counts for the KPI tiles and lists the records
	 * that still need a human decision. Counts are always derived from stored
	 * data; nothing is estimated or invented.
	 */
	public function page_overview(): void {
		$this->require_edit();

		$metrics      = CEAFSN_MED_DB::get_metrics( false );
		$demographics = CEAFSN_MED_DB::get_demographics( false );
		$projects     = CEAFSN_MED_DB::get_projects( array( 'per_page' => 200 ) );
		$project_rows = $projects['items'];

		$public_metrics = 0;
		$private_metrics = 0;
		foreach ( $metrics as $metric ) {
			if ( 'public' === $metric->visibility ) {
				++$public_metrics;
			} else {
				++$private_metrics;
			}
		}

		$active_projects = 0;
		$needs_verification = array();
		foreach ( $project_rows as $project ) {
			if ( 'active' === $project->status ) {
				++$active_projects;
			}
			if ( 'verified' !== $project->verification_status ) {
				$needs_verification[] = $project;
			}
		}

		// Periods present in the metric set, newest first, for the coverage list.
		$periods = array();
		foreach ( $metrics as $metric ) {
			$period = trim( (string) $metric->reporting_period );
			if ( '' !== $period ) {
				$periods[ $period ] = isset( $periods[ $period ] ) ? $periods[ $period ] + 1 : 1;
			}
		}
		arsort( $periods );

		require CEAFSN_MED_PLUGIN_DIR . 'admin/partials/overview.php';
	}

	/**
	 * Metrics list / add / edit page.
	 */
	public function page_metrics(): void {
		$this->require_edit();
		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( in_array( $action, array( 'edit', 'add' ), true ) && $id > 0 ) {
			$row = CEAFSN_MED_DB::get_metric( $id );
		}

		$items = CEAFSN_MED_DB::get_metrics( false );
		require CEAFSN_MED_PLUGIN_DIR . 'admin/partials/metrics.php';
	}

	/**
	 * Demographics list / add / edit page.
	 */
	public function page_demographics(): void {
		$this->require_edit();
		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( in_array( $action, array( 'edit', 'add' ), true ) && $id > 0 ) {
			$row = CEAFSN_MED_DB::get_demographic( $id );
		}

		$items = CEAFSN_MED_DB::get_demographics( false );
		require CEAFSN_MED_PLUGIN_DIR . 'admin/partials/demographics.php';
	}

	/**
	 * Projects list / add / edit page.
	 */
	public function page_projects(): void {
		$this->require_edit();
		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( in_array( $action, array( 'edit', 'add' ), true ) && $id > 0 ) {
			$row = CEAFSN_MED_DB::get_project( $id );
		}

		$result = CEAFSN_MED_DB::get_projects( array( 'per_page' => 50 ) );
		$items  = $result['items'];
		require CEAFSN_MED_PLUGIN_DIR . 'admin/partials/projects.php';
	}

	/**
	 * Settings page (preview mode toggle + export + uninstall).
	 */
	public function page_settings(): void {
		$this->require_edit();
		require CEAFSN_MED_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers — Metrics
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a metric.
	 */
	public function handle_save_metric(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_med_metric_nonce', 'ceafsn_med_nonce' );

		$id   = absint( $_POST['metric_id'] ?? 0 );
		$data = $this->extract_metric_fields();

		$errors = $this->validate_metric( $data );
		if ( ! empty( $errors ) ) {
			$this->redirect_back_with_error( implode( ' ', $errors ) );
			return;
		}

		// Publishing is an approval action, distinct from editing the record. A
		// user who may edit but not approve has the record stored as a draft
		// rather than meeting a hard failure, so their work is never discarded
		// and the reason travels with the redirect.
		$ceafsn_blocked_publish = false;
		if ( 'published' === (string) ( $data['status'] ?? '' ) && ! $this->can( self::CAP_APPROVE ) ) {
			$data['status'] = 'draft';
			$ceafsn_blocked_publish = true;
		}

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_MED_DB::get_metric( $id );
			CEAFSN_MED_DB::update_metric( $id, $data );
			$this->audit( 'update', 'metric', $id, $ceafsn_before, CEAFSN_MED_DB::get_metric( $id ) );
		} else {
			$ceafsn_new = CEAFSN_MED_DB::insert_metric( $data );
			$this->audit( 'create', 'metric', (int) $ceafsn_new, null, CEAFSN_MED_DB::get_metric( (int) $ceafsn_new ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_METRICS, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a metric.
	 */
	public function handle_delete_metric(): void {
		$this->require_edit();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_med_delete_metric_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_MED_DB::get_metric( $id );
			CEAFSN_MED_DB::delete_metric( $id );
			$this->audit( 'delete', 'metric', $id, $ceafsn_before, null );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_METRICS, 'deleted' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ---------------------------------------------------------------------------
	// Form handlers — Demographics
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a demographic group.
	 */
	public function handle_save_demographic(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_med_demo_nonce', 'ceafsn_med_nonce' );

		$id   = absint( $_POST['group_id'] ?? 0 );
		$data = $this->extract_demographic_fields();

		$errors = $this->validate_demographic( $data );
		if ( ! empty( $errors ) ) {
			$this->redirect_back_with_error( implode( ' ', $errors ) );
			return;
		}

		// Publishing is an approval action, distinct from editing the record. A
		// user who may edit but not approve has the record stored as a draft
		// rather than meeting a hard failure, so their work is never discarded
		// and the reason travels with the redirect.
		$ceafsn_blocked_publish = false;
		if ( 'published' === (string) ( $data['status'] ?? '' ) && ! $this->can( self::CAP_APPROVE ) ) {
			$data['status'] = 'draft';
			$ceafsn_blocked_publish = true;
		}

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_MED_DB::get_demographic( $id );
			CEAFSN_MED_DB::update_demographic( $id, $data );
			$this->audit( 'update', 'demographic', $id, $ceafsn_before, CEAFSN_MED_DB::get_demographic( $id ) );
		} else {
			$ceafsn_new = CEAFSN_MED_DB::insert_demographic( $data );
			$this->audit( 'create', 'demographic', (int) $ceafsn_new, null, CEAFSN_MED_DB::get_demographic( (int) $ceafsn_new ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_DEMOGRAPHICS, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a demographic group.
	 */
	public function handle_delete_demographic(): void {
		$this->require_edit();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_med_delete_demo_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_MED_DB::get_demographic( $id );
			CEAFSN_MED_DB::delete_demographic( $id );
			$this->audit( 'delete', 'demographic', $id, $ceafsn_before, null );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_DEMOGRAPHICS, 'deleted' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ---------------------------------------------------------------------------
	// Form handlers — Projects
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a project.
	 */
	public function handle_save_project(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_med_project_nonce', 'ceafsn_med_nonce' );

		$id   = absint( $_POST['project_id'] ?? 0 );
		$data = $this->extract_project_fields();

		$errors = $this->validate_project( $data );
		if ( ! empty( $errors ) ) {
			$this->redirect_back_with_error( implode( ' ', $errors ) );
			return;
		}

		// Publishing is an approval action, distinct from editing the record. A
		// user who may edit but not approve has the record stored as a draft
		// rather than meeting a hard failure, so their work is never discarded
		// and the reason travels with the redirect.
		$ceafsn_blocked_publish = false;
		if ( 'published' === (string) ( $data['status'] ?? '' ) && ! $this->can( self::CAP_APPROVE ) ) {
			$data['status'] = 'draft';
			$ceafsn_blocked_publish = true;
		}

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_MED_DB::get_project( $id );
			CEAFSN_MED_DB::update_project( $id, $data );
			$this->audit( 'update', 'project', $id, $ceafsn_before, CEAFSN_MED_DB::get_project( $id ) );
		} else {
			$ceafsn_new = CEAFSN_MED_DB::insert_project( $data );
			$this->audit( 'create', 'project', (int) $ceafsn_new, null, CEAFSN_MED_DB::get_project( (int) $ceafsn_new ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_PROJECTS, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a project.
	 */
	public function handle_delete_project(): void {
		$this->require_edit();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_med_delete_project_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_MED_DB::get_project( $id );
			CEAFSN_MED_DB::delete_project( $id );
			$this->audit( 'delete', 'project', $id, $ceafsn_before, null );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_PROJECTS, 'deleted' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ---------------------------------------------------------------------------
	// Form handlers — Settings
	// ---------------------------------------------------------------------------

	/**
	 * Write the settings owned by the tab that posted the form.
	 *
	 * Each settings tab posts its own form and declares which options it owns
	 * with a `ceafsn_me_settings_scope` hidden field. Only those options are
	 * written, so saving one tab cannot reset a checkbox that lives on another.
	 *
	 * @param array<string,mixed> $post Unslashed POST data.
	 * @return void
	 */
	private function persist_settings( array $post ): void {
		$scope = isset( $post['ceafsn_med_settings_scope'] ) && is_string( $post['ceafsn_med_settings_scope'] )
			? sanitize_key( $post['ceafsn_med_settings_scope'] )
			: '';

		if ( 'settings' === $scope ) {
			// Preview mode toggle.
			$preview_mode = isset( $post['ceafsn_med_preview_mode'] ) ? '1' : '0';
			update_option( 'ceafsn_med_preview_mode', $preview_mode );
		}

		// Uninstall delete-data flag — require explicit checkbox + confirmation text.
		if ( 'uninstall' === $scope ) {
			$uninstall = isset( $post['ceafsn_med_uninstall_delete_data'] ) ? true : false;
			update_option( 'ceafsn_med_uninstall_delete_data', $uninstall );
		}

	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_med_settings_nonce', 'ceafsn_med_nonce' );

		$this->persist_settings( wp_unslash( $_POST ) );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SETTINGS, 'saved' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ---------------------------------------------------------------------------
	// Export handler
	// ---------------------------------------------------------------------------

	/**
	 * Stream a JSON export of all plugin data.
	 */
	public function handle_export(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_med_export_nonce', 'ceafsn_med_nonce' );

		$data     = CEAFSN_MED_DB::export_all();
		$filename = 'ceafsn-med-export-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	// ---------------------------------------------------------------------------
	// Field extraction helpers
	// ---------------------------------------------------------------------------

	/**
	 * Extract and sanitize metric fields from $_POST.
	 *
	 * @return array<string,string>
	 */
	private function extract_metric_fields(): array {
		return array(
			'label'            => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'value'            => sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ),
			'unit'             => sanitize_text_field( wp_unslash( $_POST['unit'] ?? '' ) ),
			'definition'       => sanitize_textarea_field( wp_unslash( $_POST['definition'] ?? '' ) ),
			'source'           => sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ),
			'reporting_period' => sanitize_text_field( wp_unslash( $_POST['reporting_period'] ?? '' ) ),
			'visibility'       => sanitize_key( $_POST['visibility'] ?? 'private' ),
		);
	}

	/**
	 * Extract and sanitize demographic fields from $_POST.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_demographic_fields(): array {
		return array(
			'label'            => sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ),
			'value'            => (float) ( $_POST['value'] ?? 0 ),
			'value_type'       => sanitize_key( $_POST['value_type'] ?? 'count' ),
			'reporting_period' => sanitize_text_field( wp_unslash( $_POST['reporting_period'] ?? '' ) ),
			'source'           => sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ),
			'visibility'       => sanitize_key( $_POST['visibility'] ?? 'private' ),
		);
	}

	/**
	 * Extract and sanitize project fields from $_POST.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_project_fields(): array {
		return array(
			'title'                  => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'principal_investigator' => sanitize_text_field( wp_unslash( $_POST['principal_investigator'] ?? '' ) ),
			'target_region'          => sanitize_text_field( wp_unslash( $_POST['target_region'] ?? '' ) ),
			'status'                 => sanitize_key( $_POST['status'] ?? 'active' ),
			'verification_status'    => sanitize_key( $_POST['verification_status'] ?? 'pending' ),
			'last_updated'           => sanitize_text_field( wp_unslash( $_POST['last_updated'] ?? '' ) ),
			'source_url'             => esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) ),
		);
	}

	// ---------------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------------

	/**
	 * Validate metric fields. Returns array of error strings.
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,string>
	 */
	private function validate_metric( array $data ): array {
		$errors = array();

		if ( empty( $data['label'] ) ) {
			$errors[] = __( 'Label is required.', 'ceafsn-med' );
		}
		if ( empty( $data['value'] ) ) {
			$errors[] = __( 'Value is required.', 'ceafsn-med' );
		}
		if ( empty( $data['source'] ) ) {
			$errors[] = __( 'Source is required.', 'ceafsn-med' );
		}
		if ( empty( $data['reporting_period'] ) ) {
			$errors[] = __( 'Reporting period is required.', 'ceafsn-med' );
		}
		if ( ! in_array( $data['visibility'], array( 'public', 'private' ), true ) ) {
			$errors[] = __( 'Invalid visibility value.', 'ceafsn-med' );
		}

		return $errors;
	}

	/**
	 * Validate demographic fields.
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,string>
	 */
	private function validate_demographic( array $data ): array {
		$errors = array();

		if ( empty( $data['label'] ) ) {
			$errors[] = __( 'Label is required.', 'ceafsn-med' );
		}
		if ( ! is_numeric( $data['value'] ) ) {
			$errors[] = __( 'Value must be a number.', 'ceafsn-med' );
		}
		if ( ! in_array( $data['value_type'], array( 'count', 'percentage' ), true ) ) {
			$errors[] = __( 'Value type must be count or percentage.', 'ceafsn-med' );
		}
		if ( empty( $data['reporting_period'] ) ) {
			$errors[] = __( 'Reporting period is required.', 'ceafsn-med' );
		}
		if ( empty( $data['source'] ) ) {
			$errors[] = __( 'Source is required.', 'ceafsn-med' );
		}

		return $errors;
	}

	/**
	 * Validate project fields.
	 *
	 * @param array<string,mixed> $data
	 * @return array<int,string>
	 */
	private function validate_project( array $data ): array {
		$errors = array();

		if ( empty( $data['title'] ) ) {
			$errors[] = __( 'Title is required.', 'ceafsn-med' );
		}
		if ( empty( $data['principal_investigator'] ) ) {
			$errors[] = __( 'Principal investigator is required.', 'ceafsn-med' );
		}
		if ( ! in_array( $data['status'], array( 'active', 'completed', 'suspended' ), true ) ) {
			$errors[] = __( 'Invalid project status.', 'ceafsn-med' );
		}
		if ( ! in_array( $data['verification_status'], array( 'verified', 'pending', 'unverified' ), true ) ) {
			$errors[] = __( 'Invalid verification status.', 'ceafsn-med' );
		}

		$date = $data['last_updated'] ?? '';
		if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$errors[] = __( 'Last updated must be a valid date (YYYY-MM-DD).', 'ceafsn-med' );
		}

		if ( ! empty( $data['source_url'] ) && ! filter_var( $data['source_url'], FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'Source URL is not a valid URL.', 'ceafsn-med' );
		}

		return $errors;
	}

	// ---------------------------------------------------------------------------
	// Utility
	// ---------------------------------------------------------------------------

	/**
	 * Abort with a 403 if the current user cannot manage options.
	 */
	/**
	 * Capability names, mirrored here so this class never has to reference
	 * CEAFSN_Caps. Referencing the shared constant in an argument list would
	 * fatal when the optional library is absent, which would defeat the very
	 * fallback that keeps the plugin working on its own.
	 */
	private const CAP_EDIT    = 'ceafsn_edit';
	private const CAP_APPROVE = 'ceafsn_approve';
	private const CAP_MANAGE  = 'ceafsn_manage';

	/**
	 * Record a write in the shared audit log.
	 *
	 * A no-op when the shared library is absent, so this plugin stays usable on
	 * its own. Reducing the pair to a redacted diff happens inside the log class,
	 * which keeps that rule in one place instead of at every call site.
	 *
	 * @param string                $action      create, update, or delete.
	 * @param string                $entity_type Record type.
	 * @param int                   $entity_id   Record id.
	 * @param array<string,mixed>|object|null $before Previous row.
	 * @param array<string,mixed>|object|null $after  New row.
	 * @return void
	 */
	private function audit( string $action, string $entity_type, int $entity_id, array|object|null $before, array|object|null $after ): void {
		if ( ! class_exists( 'CEAFSN_Audit_Log' ) ) {
			return;
		}

		CEAFSN_Audit_Log::record( $action, $entity_type, $entity_id, $before, $after );
	}

	/**
	 * Whether the current user holds a CE-AFSN capability.
	 *
	 * `manage_options` remains a fallback, and is used on its own when the
	 * shared library is not installed, so no administrator loses access to data
	 * they could previously reach.
	 *
	 * @param string $cap Capability name.
	 * @return bool True when allowed.
	 */
	private function can( string $cap ): bool {
		if ( class_exists( 'CEAFSN_Caps' ) ) {
			return CEAFSN_Caps::can( $cap );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Abort with a 403 unless the user may work with research records.
	 *
	 * Reading and editing the admin screens used to need `manage_options`,
	 * which is a site-administration capability and left an editor unable to
	 * maintain the research data they were hired to maintain.
	 */
	private function require_edit(): void {
		if ( ! $this->can( self::CAP_EDIT ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-med' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may publish or verify a record.
	 */
	private function require_approve(): void {
		if ( ! $this->can( self::CAP_APPROVE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-med' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may change site-wide settings.
	 */
	private function require_manage(): void {
		if ( ! $this->can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-med' ), 403 );
		}
	}

	/**
	 * Redirect back to the previous admin page with an error notice.
	 *
	 * @param string $message Error message (plain text).
	 */
	private function redirect_back_with_error( string $message ): void {
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::MENU_SLUG );
		wp_safe_redirect(
			add_query_arg( 'ceafsn_med_error', rawurlencode( $message ), $back )
		);
		exit;
	}
}
