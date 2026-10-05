<?php
/**
 * Admin menu, CRUD routing, and settings for CE-AFSN Nutrition Policy.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_NP_Admin
 */
class CEAFSN_NP_Admin {

	/** @var string Admin page parent slug. Lands on the Overview dashboard. */
	const MENU_SLUG = 'ceafsn-np';

	/** @var string Policies list/add/edit page slug. */
	const PAGE_POLICIES = 'ceafsn-np-policies';

	/** @var string Settings page slug. */
	const PAGE_SETTINGS = 'ceafsn-np-settings';

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ceafsn_np_save_policy',   array( $this, 'handle_save_policy' ) );
		add_action( 'admin_post_ceafsn_np_delete_policy', array( $this, 'handle_delete_policy' ) );
		add_action( 'admin_post_ceafsn_np_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_np_export',        array( $this, 'handle_export' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the admin menu and sub-pages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Nutrition Policy', 'ceafsn-np' ),
			__( 'Nutrition Policy', 'ceafsn-np' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_overview' ),
			'dashicons-media-document',
			31
		);

		// Overview — same slug as the parent so the sidebar item lands here.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Overview', 'ceafsn-np' ),
			__( 'Overview', 'ceafsn-np' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_overview' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Policies', 'ceafsn-np' ),
			__( 'Policies', 'ceafsn-np' ),
			'manage_options',
			self::PAGE_POLICIES,
			array( $this, 'page_policies' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-np' ),
			__( 'Settings', 'ceafsn-np' ),
			'manage_options',
			self::PAGE_SETTINGS,
			array( $this, 'page_settings' )
		);
	}

	// ---------------------------------------------------------------------------
	// Asset enqueuing
	// ---------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS, and the media library, on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$plugin_pages = array(
			'toplevel_page_' . self::MENU_SLUG,
			'nutrition-policy_page_' . self::PAGE_POLICIES,
			'nutrition-policy_page_' . self::PAGE_SETTINGS,
		);

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'ceafsn-np-admin',
			CEAFSN_NP_PLUGIN_URL . 'assets/css/ceafsn-np-admin.css',
			array(),
			CEAFSN_NP_VERSION
		);

		wp_enqueue_script(
			'ceafsn-np-admin',
			CEAFSN_NP_PLUGIN_URL . 'assets/js/ceafsn-np-admin.js',
			array( 'jquery' ),
			CEAFSN_NP_VERSION,
			true
		);
	}

	// ---------------------------------------------------------------------------
	// Page renderers (delegate to partials)
	// ---------------------------------------------------------------------------

	/**
	 * Overview dashboard — the page the sidebar item lands on.
	 *
	 * Every figure is counted from stored rows. Duplicate PDF detection is done
	 * in PHP by grouping the attachment IDs already fetched, rather than issuing
	 * one `attachment_usage_count()` query per row.
	 */
	public function page_overview(): void {
		$this->require_edit();

		$result = CEAFSN_NP_DB::get_policies( array( 'per_page' => 200 ) );
		$items  = $result['items'];

		$published = 0;
		$missing_pdf = array();
		$by_attachment = array();

		foreach ( $items as $item ) {
			if ( 'published' === (string) $item->status ) {
				++$published;
			}

			$attachment_id = (int) $item->pdf_attachment_id;
			if ( $attachment_id < 1 ) {
				$missing_pdf[] = $item;
				continue;
			}

			if ( ! isset( $by_attachment[ $attachment_id ] ) ) {
				$by_attachment[ $attachment_id ] = array();
			}
			$by_attachment[ $attachment_id ][] = $item;
		}

		// Only attachments shared by more than one record are a problem.
		$shared_documents = array();
		foreach ( $by_attachment as $attachment_id => $group ) {
			if ( count( $group ) > 1 ) {
				$shared_documents[] = array(
					'attachment_id' => (int) $attachment_id,
					'filename'       => (string) ( $group[0]->pdf_filename ?? '' ),
					'records'        => $group,
				);
			}
		}

		$topics = CEAFSN_NP_DB::get_topics();

		require CEAFSN_NP_PLUGIN_DIR . 'admin/partials/overview.php';
	}

	/**
	 * Policies list / add / edit page.
	 */
	public function page_policies(): void {
		$this->require_edit();

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( in_array( $action, array( 'edit', 'add' ), true ) && $id > 0 ) {
			$row = CEAFSN_NP_DB::get_policy( $id );
		}

		$validation = null;
		if ( null !== $row ) {
			$validation = CEAFSN_NP_Validator::validate_attachment( (int) $row->pdf_attachment_id );
		}

		$duplicate_count = 0;
		if ( null !== $row ) {
			$duplicate_count = CEAFSN_NP_DB::attachment_usage_count( (int) $row->pdf_attachment_id, (int) $row->policy_id );
		}

		$result = CEAFSN_NP_DB::get_policies( array( 'per_page' => 100 ) );
		$items  = $result['items'];
		$topics = CEAFSN_NP_DB::get_topics();

		require CEAFSN_NP_PLUGIN_DIR . 'admin/partials/policies.php';
	}

	/**
	 * Settings page (export + uninstall).
	 */
	public function page_settings(): void {
		$this->require_edit();
		require CEAFSN_NP_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a policy record.
	 *
	 * A record can only be saved as `published` when its PDF passes validation.
	 * Attempting to publish an invalid document downgrades the record to draft
	 * and reports why, rather than silently failing.
	 */
	public function handle_save_policy(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_np_policy_nonce', 'ceafsn_np_nonce' );

		$id   = absint( $_POST['policy_id'] ?? 0 );
		$data = $this->extract_policy_fields();

		$errors = $this->validate_policy( $data );
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

		// Publishing is gated on a valid, readable, non-placeholder PDF.
		if ( 'published' === $data['status'] ) {
			$blockers = CEAFSN_NP_Validator::publish_blockers( (int) $data['pdf_attachment_id'] );
			if ( ! empty( $blockers ) ) {
				$data['status'] = 'draft';

				if ( $id > 0 ) {
					CEAFSN_NP_DB::update_policy( $id, $data );
				} else {
					CEAFSN_NP_DB::insert_policy( $data );
				}

				$this->redirect_back_with_error(
					implode( ' ', $blockers ) . ' ' . __( 'The record was saved as a draft instead.', 'ceafsn-np' )
				);
				return;
			}
		}

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_NP_DB::get_policy( $id );
			CEAFSN_NP_DB::update_policy( $id, $data );
			$this->audit( 'update', 'policy', $id, $ceafsn_before, CEAFSN_NP_DB::get_policy( $id ) );
		} else {
			$ceafsn_new = CEAFSN_NP_DB::insert_policy( $data );
			$this->audit( 'create', 'policy', (int) $ceafsn_new, null, CEAFSN_NP_DB::get_policy( (int) $ceafsn_new ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_POLICIES, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a policy record.
	 */
	public function handle_delete_policy(): void {
		$this->require_edit();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_np_delete_policy_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_NP_DB::get_policy( $id );
			CEAFSN_NP_DB::delete_policy( $id );
			$this->audit( 'delete', 'policy', $id, $ceafsn_before, null );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_POLICIES, 'deleted' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Write the settings owned by the tab that posted the form.
	 *
	 * Each settings tab posts its own form and declares which options it owns
	 * with a `ceafsn_nutrition_settings_scope` hidden field. Only those options are
	 * written, so saving one tab cannot reset a checkbox that lives on another.
	 *
	 * @param array<string,mixed> $post Unslashed POST data.
	 * @return void
	 */
	private function persist_settings( array $post ): void {
		$scope = isset( $post['ceafsn_np_settings_scope'] ) && is_string( $post['ceafsn_np_settings_scope'] )
			? sanitize_key( $post['ceafsn_np_settings_scope'] )
			: '';

		if ( 'general' === $scope ) {
			$raw = isset( $post['ceafsn_np_placeholder_files'] )
				? sanitize_textarea_field( $post['ceafsn_np_placeholder_files'] )
				: '';

			$files = array_values( array_filter( array_map( 'sanitize_file_name', preg_split( '/[\r\n,]+/', $raw ) ?: array() ) ) );
			$files = array_values( array_unique( $files ) );

			update_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION, $files );
		}

		// Uninstall delete-data flag — explicit checkbox only.
		if ( 'uninstall' === $scope ) {
			$uninstall = isset( $post['ceafsn_np_uninstall_delete_data'] );
			update_option( 'ceafsn_np_uninstall_delete_data', $uninstall );
		}

	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_np_settings_nonce', 'ceafsn_np_nonce' );

		$this->persist_settings( wp_unslash( $_POST ) );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::PAGE_SETTINGS, 'saved' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Stream a JSON export of all policy records.
	 */
	public function handle_export(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_np_export_nonce', 'ceafsn_np_nonce' );

		$data     = CEAFSN_NP_DB::export_all();
		$filename = 'ceafsn-np-export-' . gmdate( 'Y-m-d' ) . '.json';

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
	 * Extract and sanitize policy fields from $_POST.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_policy_fields(): array {
		$attachment_id = absint( $_POST['pdf_attachment_id'] ?? 0 );
		$filename      = '';

		// The filename is only ever derived from the attachment itself, never
		// from the submitted value, so a crafted form cannot store a path.
		if ( $attachment_id > 0 ) {
			$stored = get_attached_file( $attachment_id );
			if ( $stored ) {
				$filename = basename( (string) wp_basename( $stored ) );
			}
		}

		return array(
			'title'                 => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'           => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'publication_date'      => sanitize_text_field( wp_unslash( $_POST['publication_date'] ?? '' ) ),
			'topic'                 => sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) ),
			'authoring_institution' => sanitize_text_field( wp_unslash( $_POST['authoring_institution'] ?? '' ) ),
			'pdf_attachment_id'     => $attachment_id,
			'pdf_filename'          => $filename,
			'source_url'            => esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) ),
			'status'                => sanitize_key( $_POST['status'] ?? 'draft' ),
		);
	}

	// ---------------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------------

	/**
	 * Validate policy fields. Returns an array of error strings.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return array<int,string>
	 */
	private function validate_policy( array $data ): array {
		$errors = array();

		if ( empty( $data['title'] ) ) {
			$errors[] = __( 'Title is required.', 'ceafsn-np' );
		}
		if ( empty( $data['description'] ) ) {
			$errors[] = __( 'Description is required.', 'ceafsn-np' );
		}
		if ( empty( $data['topic'] ) ) {
			$errors[] = __( 'Topic is required.', 'ceafsn-np' );
		}
		if ( empty( $data['authoring_institution'] ) ) {
			$errors[] = __( 'Authoring institution is required.', 'ceafsn-np' );
		}
		if ( empty( $data['pdf_attachment_id'] ) ) {
			$errors[] = __( 'A PDF attachment is required.', 'ceafsn-np' );
		}

		$date = (string) ( $data['publication_date'] ?? '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$errors[] = __( 'Publication date must be a valid date (YYYY-MM-DD).', 'ceafsn-np' );
		}

		if ( ! in_array( $data['status'], CEAFSN_NP_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-np' );
		}

		if ( ! empty( $data['source_url'] ) && ! filter_var( (string) $data['source_url'], FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'Source URL is not a valid URL.', 'ceafsn-np' );
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-np' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may publish or verify a record.
	 */
	private function require_approve(): void {
		if ( ! $this->can( self::CAP_APPROVE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-np' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may change site-wide settings.
	 */
	private function require_manage(): void {
		if ( ! $this->can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-np' ), 403 );
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
			add_query_arg( 'ceafsn_np_error', rawurlencode( $message ), $back )
		);
		exit;
	}
}
