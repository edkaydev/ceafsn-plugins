<?php
/**
 * Admin menu, CRUD routing, and settings for CE-AFSN Research Fellowships.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_RF_Admin
 */
class CEAFSN_RF_Admin {

	/** @var string Admin page parent slug. */
	const MENU_SLUG = 'ceafsn-rf';

	/**
	 * Settings sub-page slug.
	 *
	 * Linked from the Display cards and the Export buttons, so it lives in a
	 * constant instead of being repeated as a literal in the partials.
	 *
	 * @var string
	 */
	const SETTINGS_SLUG = 'ceafsn-rf-settings';

	/**
	 * Hook suffixes returned when the menu was registered.
	 *
	 * Empty before admin_menu has fired, which is also the safe state: nothing
	 * matches an empty list, so assets stay unloaded if the ordering changes.
	 *
	 * @var array<int,string>
	 */
	private array $page_hooks = array();

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ceafsn_rf_save_fellowship', array( $this, 'handle_save_fellowship' ) );
		add_action( 'admin_post_ceafsn_rf_delete_fellowship', array( $this, 'handle_delete_fellowship' ) );
		add_action( 'admin_post_ceafsn_rf_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_rf_export', array( $this, 'handle_export' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the admin menu and sub-pages.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		$this->page_hooks[] = add_menu_page(
			__( 'Research Fellowships', 'ceafsn-rf' ),
			__( 'Research Fellowships', 'ceafsn-rf' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_fellowships' ),
			'dashicons-welcome-learn-more',
			33
		);

		$this->page_hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'All Opportunities', 'ceafsn-rf' ),
			__( 'All Opportunities', 'ceafsn-rf' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_fellowships' )
		);

		$this->page_hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-rf' ),
			__( 'Settings', 'ceafsn-rf' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'page_settings' )
		);
	}

	/**
	 * Enqueue admin CSS and JS, and the media library, on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// The hook suffix is built from the menu slug by core, and the settings
		// submenu slug is not derived from this class, so the real suffixes
		// returned at registration are compared instead of guessing the string.
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'ceafsn-rf-admin',
			CEAFSN_RF_PLUGIN_URL . 'assets/css/ceafsn-rf-admin.css',
			array(),
			CEAFSN_RF_VERSION
		);

		wp_enqueue_script(
			'ceafsn-rf-admin',
			CEAFSN_RF_PLUGIN_URL . 'assets/js/ceafsn-rf-admin.js',
			array( 'jquery' ),
			CEAFSN_RF_VERSION,
			true
		);

		// The media frame's title and the accepted type are needed in JS. The
		// type is sent to the frame as a filter, which is what stops the picker
		// offering an image that the save handler would later reject.
		wp_localize_script(
			'ceafsn-rf-admin',
			'ceafsnRfAdmin',
			array(
				'frameTitle'  => __( 'Select the call document (PDF)', 'ceafsn-rf' ),
				'frameButton' => __( 'Use this document', 'ceafsn-rf' ),
				'mimeType'    => 'application/pdf',
			)
		);
	}

	// ---------------------------------------------------------------------------
	// Page renderers (delegate to partials)
	// ---------------------------------------------------------------------------

	/**
	 * Fellowships list / add / edit page.
	 *
	 * @return void
	 */
	public function page_fellowships(): void {
		$this->require_edit();

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( 'edit' === $action && $id > 0 ) {
			$row = CEAFSN_RF_DB::get_fellowship( $id );
		}

		// The status the record will actually show, worked out on the same code
		// path the front end uses, so the editor never disagrees with the page.
		$derived = null;
		if ( null !== $row ) {
			$derived = CEAFSN_RF_Status::derive( $row );
		}

		$validation = null;
		if ( null !== $row ) {
			$validation = CEAFSN_RF_Validator::validate_attachment( (int) ( $row->call_pdf_id ?? 0 ) );
		}

		$result = CEAFSN_RF_DB::get_fellowships( array( 'per_page' => 200 ) );
		$items  = $result['items'];

		require CEAFSN_RF_PLUGIN_DIR . 'admin/partials/fellowships.php';
	}

	/**
	 * Settings page (display, export, uninstall).
	 *
	 * @return void
	 */
	public function page_settings(): void {
		$this->require_edit();
		require CEAFSN_RF_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a fellowship record.
	 *
	 * A record can only be saved as `published` when it passes validation.
	 * Attempting to publish an unverified record downgrades it to draft and
	 * reports why, rather than silently failing.
	 *
	 * @return void
	 */
	public function handle_save_fellowship(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_rf_fellowship_nonce', 'ceafsn_rf_nonce' );

		$id   = absint( $_POST['fellowship_id'] ?? 0 );
		$data = $this->extract_fellowship_fields();

		$errors = $this->validate_fellowship( $data );
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

		if ( 'published' === $data['status'] ) {
			$blockers = CEAFSN_RF_Validator::publish_blockers( $data );
			if ( ! empty( $blockers ) ) {
				$data['status'] = 'draft';
				$this->persist_fellowship( $data, $id );

				$this->redirect_back_with_error(
					implode( ' ', $blockers ) . ' ' . __( 'The opportunity was saved as a draft instead.', 'ceafsn-rf' )
				);
				return;
			}
		}

		$this->persist_fellowship( $data, $id );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a fellowship record.
	 *
	 * @return void
	 */
	public function handle_delete_fellowship(): void {
		$this->require_edit();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_rf_delete_fellowship_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_RF_DB::get_fellowship( $id );
			CEAFSN_RF_DB::delete_fellowship( $id );
			$this->audit( 'delete', 'fellowship', $id, $ceafsn_before, null );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'deleted' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_rf_settings_nonce', 'ceafsn_rf_nonce' );

		$this->persist_settings( wp_unslash( $_POST ) );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::SETTINGS_SLUG, 'saved' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Write the settings owned by the tab that posted the form.
	 *
	 * Each settings tab posts its own form and declares which options it owns
	 * with a `ceafsn_rf_settings_scope` hidden field. Only those options are
	 * written, so saving one tab cannot reset a checkbox that lives on another.
	 * Within a scope a checkbox is still an explicit opt-in, so an unticked box
	 * is stored as false rather than left at whatever it was before.
	 *
	 * @param array<string,mixed> $post Unslashed POST data.
	 * @return void
	 */
	private function persist_settings( array $post ): void {
		$scope = isset( $post['ceafsn_rf_settings_scope'] ) && is_string( $post['ceafsn_rf_settings_scope'] )
			? sanitize_key( $post['ceafsn_rf_settings_scope'] )
			: '';

		if ( 'display' === $scope ) {
			update_option(
				CEAFSN_RF_Activator::SHOW_CLOSED_OPTION,
				isset( $post['ceafsn_rf_show_closed'] )
			);
		}

		if ( 'uninstall' === $scope ) {
			update_option( 'ceafsn_rf_uninstall_delete_data', isset( $post['ceafsn_rf_uninstall_delete_data'] ) );
		}
	}

	/**
	 * Stream a JSON export of all fellowship records.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_rf_export_nonce', 'ceafsn_rf_nonce' );

		$data     = CEAFSN_RF_DB::export_all();
		$filename = 'ceafsn-rf-export-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON for download.
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	// ---------------------------------------------------------------------------
	// Field extraction helpers
	// ---------------------------------------------------------------------------

	/**
	 * Extract and sanitise fellowship fields from $_POST.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_fellowship_fields(): array {
		return array(
			'title'           => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'track_domain'    => sanitize_text_field( wp_unslash( $_POST['track_domain'] ?? '' ) ),
			'duration'        => sanitize_text_field( wp_unslash( $_POST['duration'] ?? '' ) ),
			'eligibility'     => sanitize_textarea_field( wp_unslash( $_POST['eligibility'] ?? '' ) ),
			'host_supervisor' => sanitize_text_field( wp_unslash( $_POST['host_supervisor'] ?? '' ) ),
			'stipend_info'    => sanitize_textarea_field( wp_unslash( $_POST['stipend_info'] ?? '' ) ),
			'show_stipend'    => isset( $_POST['show_stipend'] ) ? 1 : 0,
			'opening_date'    => sanitize_text_field( wp_unslash( $_POST['opening_date'] ?? '' ) ),
			'closing_date'    => sanitize_text_field( wp_unslash( $_POST['closing_date'] ?? '' ) ),
			'application_url' => esc_url_raw( wp_unslash( $_POST['application_url'] ?? '' ) ),
			'call_pdf_id'     => absint( $_POST['call_pdf_id'] ?? 0 ),
			'contact_email'   => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
			'show_contact'    => isset( $_POST['show_contact'] ) ? 1 : 0,
			'status_override' => isset( $_POST['status_override'] ) ? 1 : 0,
			'override_note'   => sanitize_textarea_field( wp_unslash( $_POST['override_note'] ?? '' ) ),
			'status_manual'   => sanitize_key( $_POST['status_manual'] ?? 'upcoming' ),
			'status'          => sanitize_key( $_POST['status'] ?? 'draft' ),
		);
	}

	// ---------------------------------------------------------------------------
	// Persistence helpers
	// ---------------------------------------------------------------------------

	/**
	 * Store the record, updating it when an ID is known.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @param int                 $id   Record ID, or 0 for a new record.
	 * @return int|false Result of the write.
	 */
	private function persist_fellowship( array $data, int $id ) {
		$ceafsn_before = $id > 0 ? CEAFSN_RF_DB::get_fellowship( $id ) : null;
		$ceafsn_saved  = $id > 0
			? CEAFSN_RF_DB::update_fellowship( $id, $data )
			: CEAFSN_RF_DB::insert_fellowship( $data );

		$this->audit(
			$id > 0 ? 'update' : 'create',
			'fellowship',
			(int) $ceafsn_saved,
			$ceafsn_before,
			CEAFSN_RF_DB::get_fellowship( (int) $ceafsn_saved )
		);

		return $ceafsn_saved;
	}

	// ---------------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------------

	/**
	 * Validate fellowship fields on every save, draft or published.
	 *
	 * These are the checks that catch a typo while the admin is still looking at
	 * the form. The publish-only rules live in CEAFSN_RF_Validator.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return array<int,string>
	 */
	private function validate_fellowship( array $data ): array {
		$errors = array();

		if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
			$errors[] = __( 'Title is required.', 'ceafsn-rf' );
		}

		if ( '' === trim( (string) ( $data['eligibility'] ?? '' ) ) ) {
			$errors[] = __( 'Eligibility criteria are required.', 'ceafsn-rf' );
		}

		if ( ! in_array( (string) ( $data['status_manual'] ?? '' ), CEAFSN_RF_Status::manual_values(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-rf' );
		}

		if ( ! in_array( (string) ( $data['status'] ?? '' ), CEAFSN_RF_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid record state.', 'ceafsn-rf' );
		}

		return $errors;
	}

	// ---------------------------------------------------------------------------
	// Utility
	// ---------------------------------------------------------------------------

	/**
	 * Abort with a 403 if the current user cannot manage options.
	 *
	 * @return void
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-rf' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may publish or verify a record.
	 */
	private function require_approve(): void {
		if ( ! $this->can( self::CAP_APPROVE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-rf' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may change site-wide settings.
	 */
	private function require_manage(): void {
		if ( ! $this->can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-rf' ), 403 );
		}
	}

	/**
	 * Redirect back to the previous admin page with an error notice.
	 *
	 * @param string $message Error message (plain text).
	 * @return void
	 */
	private function redirect_back_with_error( string $message ): void {
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::MENU_SLUG );
		wp_safe_redirect(
			add_query_arg( 'ceafsn_rf_error', rawurlencode( $message ), $back )
		);
		exit;
	}
}
