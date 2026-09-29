<?php
/**
 * Admin menu, CRUD routing, and settings for CE-AFSN Grants & Funding.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_GF_Admin
 */
class CEAFSN_GF_Admin {

	/** @var string Admin page parent slug. */
	const MENU_SLUG = 'ceafsn-gf';

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
		add_action( 'admin_post_ceafsn_gf_save_grant',    array( $this, 'handle_save_grant' ) );
		add_action( 'admin_post_ceafsn_gf_delete_grant',  array( $this, 'handle_delete_grant' ) );
		add_action( 'admin_post_ceafsn_gf_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_gf_export',        array( $this, 'handle_export' ) );
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
			__( 'Grants & Funding', 'ceafsn-gf' ),
			__( 'Grants & Funding', 'ceafsn-gf' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_grants' ),
			'dashicons-money-alt',
			34
		);

		$this->page_hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'All Grants', 'ceafsn-gf' ),
			__( 'All Grants', 'ceafsn-gf' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_grants' )
		);

		$this->page_hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-gf' ),
			__( 'Settings', 'ceafsn-gf' ),
			'manage_options',
			'ceafsn-gf-settings',
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
			'ceafsn-gf-admin',
			CEAFSN_GF_PLUGIN_URL . 'assets/css/ceafsn-gf-admin.css',
			array(),
			CEAFSN_GF_VERSION
		);

		wp_enqueue_script(
			'ceafsn-gf-admin',
			CEAFSN_GF_PLUGIN_URL . 'assets/js/ceafsn-gf-admin.js',
			array( 'jquery' ),
			CEAFSN_GF_VERSION,
			true
		);

		// The media frame's title and the accepted type are needed in JS. The
		// type is sent to the frame as a filter, which is what stops the picker
		// offering a file the save handler would later reject.
		wp_localize_script(
			'ceafsn-gf-admin',
			'ceafsnGfAdmin',
			array(
				'frameTitle'  => __( 'Select the Official Call PDF', 'ceafsn-gf' ),
				'frameButton' => __( 'Use this document', 'ceafsn-gf' ),
				'mimeType'    => 'application/pdf',
			)
		);
	}

	// ---------------------------------------------------------------------------
	// Page renderers (delegate to partials)
	// ---------------------------------------------------------------------------

	/**
	 * Grants list / add / edit page.
	 *
	 * @return void
	 */
	public function page_grants(): void {
		$this->require_manage_options();

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( 'edit' === $action && $id > 0 ) {
			$row = CEAFSN_GF_DB::get_grant( $id );
		}

		$validation = null;
		if ( null !== $row ) {
			$validation = CEAFSN_GF_Validator::validate_attachment( (int) ( $row->call_pdf_id ?? 0 ) );
		}

		$duplicate_count = 0;
		$shared_with     = array();
		if ( null !== $row ) {
			$duplicate_count = CEAFSN_GF_DB::attachment_usage_count( (int) $row->call_pdf_id, (int) $row->grant_id );
			$shared_with     = CEAFSN_GF_DB::get_attached_others( (int) $row->call_pdf_id, (int) $row->grant_id );
		}

		$result = CEAFSN_GF_DB::get_grants( array( 'per_page' => 200 ) );
		$items  = $result['items'];

		require CEAFSN_GF_PLUGIN_DIR . 'admin/partials/grants.php';
	}

	/**
	 * Settings page (display, export, uninstall).
	 *
	 * @return void
	 */
	public function page_settings(): void {
		$this->require_manage_options();
		require CEAFSN_GF_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a grant record.
	 *
	 * A record can only be saved as `published` when its Official Call PDF
	 * passes validation and any sharing with another record has been confirmed.
	 * Attempting to publish a record that fails downgrades it to draft and
	 * reports why, rather than silently failing.
	 *
	 * @return void
	 */
	public function handle_save_grant(): void {
		$this->require_manage_options();
		check_admin_referer( 'ceafsn_gf_grant_nonce', 'ceafsn_gf_nonce' );

		$id   = absint( $_POST['grant_id'] ?? 0 );
		$data = $this->extract_grant_fields();

		// publish_blockers() needs to know which record is being edited so it
		// does not count that record as one of its own duplicates.
		$data['grant_id'] = $id;

		$errors = $this->validate_grant( $data );
		if ( ! empty( $errors ) ) {
			$this->redirect_back_with_error( implode( ' ', $errors ) );
			return;
		}

		if ( 'published' === $data['status'] ) {
			$blockers = CEAFSN_GF_Validator::publish_blockers( $data );
			if ( ! empty( $blockers ) ) {
				$data['status'] = 'draft';
				$this->persist_grant( $data, $id );

				$this->redirect_back_with_error(
					implode( ' ', $blockers ) . ' ' . __( 'The record was saved as a draft instead.', 'ceafsn-gf' )
				);
				return;
			}
		}

		$this->persist_grant( $data, $id );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'saved' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a grant record.
	 *
	 * @return void
	 */
	public function handle_delete_grant(): void {
		$this->require_manage_options();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_gf_delete_grant_' . $id );

		if ( $id > 0 ) {
			CEAFSN_GF_DB::delete_grant( $id );
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
		$this->require_manage_options();
		check_admin_referer( 'ceafsn_gf_settings_nonce', 'ceafsn_gf_nonce' );

		// Both boxes are explicit opt-ins, so an unticked box is stored as false
		// rather than left at whatever it was before.
		update_option(
			CEAFSN_GF_Activator::SHOW_CLOSED_OPTION,
			isset( $_POST['ceafsn_gf_show_closed'] )
		);

		update_option( 'ceafsn_gf_uninstall_delete_data', isset( $_POST['ceafsn_gf_uninstall_delete_data'] ) );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'ceafsn-gf-settings', 'saved' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Stream a JSON export of all grant records.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$this->require_manage_options();
		check_admin_referer( 'ceafsn_gf_export_nonce', 'ceafsn_gf_nonce' );

		$data     = CEAFSN_GF_DB::export_all();
		$filename = 'ceafsn-gf-export-' . gmdate( 'Y-m-d' ) . '.json';

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
	 * Extract and sanitise grant fields from $_POST.
	 *
	 * The deadline timezone is never taken from the form: the specification
	 * treats it as a fixed fact about the site, not a per-record choice, so it
	 * is stamped from the current WordPress timezone setting at save time.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_grant_fields(): array {
		return array(
			'title'                => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'award_range'          => sanitize_text_field( wp_unslash( $_POST['award_range'] ?? '' ) ),
			'deadline'             => sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) ),
			'deadline_timezone'    => wp_timezone_string(),
			'target_beneficiaries' => sanitize_textarea_field( wp_unslash( $_POST['target_beneficiaries'] ?? '' ) ),
			'eligibility'          => sanitize_textarea_field( wp_unslash( $_POST['eligibility'] ?? '' ) ),
			'call_pdf_id'          => absint( $_POST['call_pdf_id'] ?? 0 ),
			'application_url'      => esc_url_raw( wp_unslash( $_POST['application_url'] ?? '' ) ),
			'funding_institution'  => sanitize_text_field( wp_unslash( $_POST['funding_institution'] ?? '' ) ),
			'contact'              => sanitize_text_field( wp_unslash( $_POST['contact'] ?? '' ) ),
			'show_contact'         => isset( $_POST['show_contact'] ) ? 1 : 0,
			'duplicate_note'       => sanitize_textarea_field( wp_unslash( $_POST['duplicate_note'] ?? '' ) ),
			'duplicate_ok'         => isset( $_POST['duplicate_ok'] ) ? 1 : 0,
			'grant_status'         => sanitize_key( $_POST['grant_status'] ?? 'upcoming' ),
			'status'               => sanitize_key( $_POST['status'] ?? 'draft' ),
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
	private function persist_grant( array $data, int $id ) {
		return $id > 0
			? CEAFSN_GF_DB::update_grant( $id, $data )
			: CEAFSN_GF_DB::insert_grant( $data );
	}

	// ---------------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------------

	/**
	 * Validate grant fields on every save, draft or published.
	 *
	 * These are the checks that catch a typo while the admin is still looking at
	 * the form. The publish-only rules live in CEAFSN_GF_Validator.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return array<int,string>
	 */
	private function validate_grant( array $data ): array {
		$errors = array();

		if ( '' === trim( (string) ( $data['title'] ?? '' ) ) ) {
			$errors[] = __( 'Title is required.', 'ceafsn-gf' );
		}

		if ( '' === trim( (string) ( $data['eligibility'] ?? '' ) ) ) {
			$errors[] = __( 'Eligibility criteria are required.', 'ceafsn-gf' );
		}

		if ( ! in_array( (string) ( $data['grant_status'] ?? '' ), CEAFSN_GF_DB::grant_statuses(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-gf' );
		}

		if ( ! in_array( (string) ( $data['status'] ?? '' ), CEAFSN_GF_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid record state.', 'ceafsn-gf' );
		}

		$deadline_raw = trim( (string) ( $data['deadline'] ?? '' ) );
		if ( '' !== $deadline_raw && null === CEAFSN_GF_DB::normalize_deadline_to_utc( $deadline_raw, (string) ( $data['deadline_timezone'] ?? 'UTC' ) ) ) {
			$errors[] = __( 'The deadline is not a valid date and time.', 'ceafsn-gf' );
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
	private function require_manage_options(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-gf' ), 403 );
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
			add_query_arg( 'ceafsn_gf_error', rawurlencode( $message ), $back )
		);
		exit;
	}
}
