<?php
/**
 * Admin menu, CRUD routing, and settings for CE-AFSN Projects & Publications.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_PP_Admin
 */
class CEAFSN_PP_Admin {

	/** @var string Admin page parent slug. */
	const MENU_SLUG = 'ceafsn-pp';

	/** @var string Admin page slug for the settings screen. */
	const SETTINGS_SLUG = 'ceafsn-pp-settings';

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ceafsn_pp_save_publication',   array( $this, 'handle_save_publication' ) );
		add_action( 'admin_post_ceafsn_pp_delete_publication', array( $this, 'handle_delete_publication' ) );
		add_action( 'admin_post_ceafsn_pp_save_settings',      array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_pp_export',             array( $this, 'handle_export' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the admin menu and sub-pages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Projects & Publications', 'ceafsn-pp' ),
			__( 'Projects & Publications', 'ceafsn-pp' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_publications' ),
			'dashicons-book-alt',
			32
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'All Records', 'ceafsn-pp' ),
			__( 'All Records', 'ceafsn-pp' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_publications' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-pp' ),
			__( 'Settings', 'ceafsn-pp' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'page_settings' )
		);
	}

	/**
	 * Enqueue admin CSS and JS, and the media library, on plugin pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$plugin_pages = array(
			'toplevel_page_' . self::MENU_SLUG,
			'projects-publications_page_ceafsn-pp-settings',
		);

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'ceafsn-pp-admin',
			CEAFSN_PP_PLUGIN_URL . 'assets/css/ceafsn-pp-admin.css',
			array(),
			CEAFSN_PP_VERSION
		);

		wp_enqueue_script(
			'ceafsn-pp-admin',
			CEAFSN_PP_PLUGIN_URL . 'assets/js/ceafsn-pp-admin.js',
			array( 'jquery' ),
			CEAFSN_PP_VERSION,
			true
		);
	}

	// ---------------------------------------------------------------------------
	// Page renderers (delegate to partials)
	// ---------------------------------------------------------------------------

	/**
	 * Publications list / add / edit page.
	 */
	public function page_publications(): void {
		$this->require_edit();

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		$row = null;
		if ( in_array( $action, array( 'edit' ), true ) && $id > 0 ) {
			$row = CEAFSN_PP_DB::get_publication( $id );
		}

		$validation = null;
		if ( null !== $row ) {
			$validation = CEAFSN_PP_Validator::validate_attachment( (int) $row->pdf_attachment_id );
		}

		$duplicate_count = 0;
		$shared_with     = array();
		if ( null !== $row ) {
			$duplicate_count = CEAFSN_PP_DB::attachment_usage_count( (int) $row->pdf_attachment_id, (int) $row->publication_id );
			$shared_with     = CEAFSN_PP_DB::get_attached_others( (int) $row->pdf_attachment_id, (int) $row->publication_id );
		}

		$result = CEAFSN_PP_DB::get_publications(
			array(
				'per_page'             => 200,
				'include_members_only' => true,
			)
		);
		$items = $result['items'];

		require CEAFSN_PP_PLUGIN_DIR . 'admin/partials/publications.php';
	}

	/**
	 * Settings page (placeholders, redirect, export, uninstall).
	 */
	public function page_settings(): void {
		$this->require_edit();
		require CEAFSN_PP_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a publication record.
	 *
	 * A record can only be saved as `published` when its document passes
	 * validation. Attempting to publish an invalid document downgrades the
	 * record to draft and reports why, rather than silently failing.
	 */
	public function handle_save_publication(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_pp_publication_nonce', 'ceafsn_pp_nonce' );

		$id   = absint( $_POST['publication_id'] ?? 0 );
		$data = $this->extract_publication_fields();

		// publish_blockers() needs to know which record is being edited so it
		// does not count that record as one of its own duplicates.
		$data['publication_id'] = $id;

		$errors = $this->validate_publication( $data );
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

		// The page count is read from the document, never from the form alone, so
		// a card never shows a number the PDF does not have. An admin who typed a
		// different number is already being sent back with an error below, so by
		// this point the measured value is the only honest one to keep.
		$data = $this->apply_measured_page_count( $data );

		// Publishing is gated on a valid, readable, non-placeholder document
		// that belongs to this record.
		if ( 'published' === $data['status'] ) {
			$blockers = CEAFSN_PP_Validator::publish_blockers( $data );
			if ( ! empty( $blockers ) ) {
				$data['status'] = 'draft';
				$this->persist_publication( $data, $id );

				$this->redirect_back_with_error(
					implode( ' ', $blockers ) . ' ' . __( 'The record was saved as a draft instead.', 'ceafsn-pp' )
				);
				return;
			}
		}

		$this->persist_publication( $data, $id );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a publication record.
	 */
	public function handle_delete_publication(): void {
		$this->require_edit();
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'ceafsn_pp_delete_publication_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_PP_DB::get_publication( $id );
			CEAFSN_PP_DB::delete_publication( $id );
			$this->audit( 'delete', 'publication', $id, $ceafsn_before, null );
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
	 * Write the settings owned by the tab that posted the form.
	 *
	 * Each settings tab posts its own form and declares which options it owns
	 * with a `ceafsn_projects_settings_scope` hidden field. Only those options are
	 * written, so saving one tab cannot reset a checkbox that lives on another.
	 *
	 * @param array<string,mixed> $post Unslashed POST data.
	 * @return void
	 */
	private function persist_settings( array $post ): void {
		$scope = isset( $post['ceafsn_pp_settings_scope'] ) && is_string( $post['ceafsn_pp_settings_scope'] )
			? sanitize_key( $post['ceafsn_pp_settings_scope'] )
			: '';

		if ( 'placeholders' === $scope ) {
			$raw = isset( $post['ceafsn_pp_placeholder_files'] )
				? sanitize_textarea_field( $post['ceafsn_pp_placeholder_files'] )
				: '';

			$files = array_values( array_filter( array_map( 'sanitize_file_name', preg_split( '/[\r\n,]+/', $raw ) ?: array() ) ) );
			$files = array_values( array_unique( $files ) );

			update_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION, $files );
		}

		// Legacy redirect: on unless the box is explicitly unticked.
		if ( 'routes' === $scope ) {
			update_option(
				CEAFSN_PP_Activator::REDIRECT_OPTION,
				isset( $post['ceafsn_pp_legacy_redirect'] )
			);
		}

		// Uninstall delete-data flag — explicit checkbox only.
		if ( 'uninstall' === $scope ) {
			update_option( 'ceafsn_pp_uninstall_delete_data', isset( $post['ceafsn_pp_uninstall_delete_data'] ) );
		}

	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_pp_settings_nonce', 'ceafsn_pp_nonce' );

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
	 * Stream a JSON export of all publication records.
	 */
	public function handle_export(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_pp_export_nonce', 'ceafsn_pp_nonce' );

		$data     = CEAFSN_PP_DB::export_all();
		$filename = 'ceafsn-pp-export-' . gmdate( 'Y-m-d' ) . '.json';

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
	 * Extract and sanitize publication fields from $_POST.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_publication_fields(): array {
		$cover_id = absint( $_POST['cover_image_id'] ?? 0 );
		$pdf_id   = absint( $_POST['pdf_attachment_id'] ?? 0 );

		// A cover image is decorative unless the alt text says what it shows.
		// Rather than inventing a description from the file name, the alt text
		// is required whenever a cover is attached.
		$cover_alt = sanitize_text_field( wp_unslash( $_POST['cover_image_alt'] ?? '' ) );

		if ( $cover_id > 0 && '' === $cover_alt ) {
			$cover_alt = '';
		}

		return array(
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'content_type'       => sanitize_key( $_POST['content_type'] ?? 'report' ),
			'executive_summary'  => sanitize_textarea_field( wp_unslash( $_POST['executive_summary'] ?? '' ) ),
			'author_institution' => sanitize_text_field( wp_unslash( $_POST['author_institution'] ?? '' ) ),
			'publication_date'   => sanitize_text_field( wp_unslash( $_POST['publication_date'] ?? '' ) ),
			'project_status'     => sanitize_key( $_POST['project_status'] ?? 'in_progress' ),
			'cover_image_id'     => $cover_id,
			'cover_image_alt'    => $cover_alt,
			'pdf_attachment_id'  => $pdf_id,
			'page_count'         => absint( $_POST['page_count'] ?? 0 ),
			'doi_citation'       => esc_url_raw( wp_unslash( $_POST['doi_citation'] ?? '' ) ),
			'access_level'       => sanitize_key( $_POST['access_level'] ?? 'public' ),
			'duplicate_note'     => sanitize_textarea_field( wp_unslash( $_POST['duplicate_note'] ?? '' ) ),
			'scanned'            => isset( $_POST['scanned'] ) ? 1 : 0,
			'duplicate_ok'       => isset( $_POST['duplicate_ok'] ) ? 1 : 0,
			'status'             => sanitize_key( $_POST['status'] ?? 'draft' ),
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
	private function persist_publication( array $data, int $id ) {
		$ceafsn_before = $id > 0 ? CEAFSN_PP_DB::get_publication( $id ) : null;
		$ceafsn_saved  = $id > 0
			? CEAFSN_PP_DB::update_publication( $id, $data )
			: CEAFSN_PP_DB::insert_publication( $data );

		$this->audit(
			$id > 0 ? 'update' : 'create',
			'publication',
			(int) $ceafsn_saved,
			$ceafsn_before,
			CEAFSN_PP_DB::get_publication( (int) $ceafsn_saved )
		);

		return $ceafsn_saved;
	}

	/**
	 * Replace an empty page count with the number of pages in the document.
	 *
	 * Page count is measured, not trusted: the admin can be wrong, and a card
	 * that claims four pages for a ten-page report is worse than a blank field.
	 * A value the admin typed is left alone here, because a value that disagrees
	 * with the document is reported back to them rather than silently corrected.
	 *
	 * @param array<string,mixed> $data Prepared record fields.
	 * @return array<string,mixed> Fields with a measured page count.
	 */
	private function apply_measured_page_count( array $data ): array {
		if ( 0 !== (int) ( $data['page_count'] ?? 0 ) ) {
			return $data;
		}

		$measured = CEAFSN_PP_Validator::validate_attachment( (int) ( $data['pdf_attachment_id'] ?? 0 ) );

		if ( $measured['valid'] ) {
			$data['page_count'] = (int) $measured['pages'];
		}

		return $data;
	}

	// ---------------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------------

	/**
	 * Validate publication fields. Returns an array of error strings.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return array<int,string>
	 */
	private function validate_publication( array $data ): array {
		$errors = array();

		if ( empty( $data['title'] ) ) {
			$errors[] = __( 'Title is required.', 'ceafsn-pp' );
		}
		if ( empty( $data['author_institution'] ) ) {
			$errors[] = __( 'Author or institution is required.', 'ceafsn-pp' );
		}
		if ( empty( $data['pdf_attachment_id'] ) ) {
			$errors[] = __( 'A PDF attachment is required.', 'ceafsn-pp' );
		}
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $data['publication_date'] ?? '' ) ) ) {
			$errors[] = __( 'Publication date must be a valid date (YYYY-MM-DD).', 'ceafsn-pp' );
		}

		if ( ! in_array( $data['content_type'], CEAFSN_PP_DB::content_types(), true ) ) {
			$errors[] = __( 'Invalid content type.', 'ceafsn-pp' );
		}
		if ( ! in_array( $data['project_status'], CEAFSN_PP_DB::project_statuses(), true ) ) {
			$errors[] = __( 'Invalid project status.', 'ceafsn-pp' );
		}
		if ( ! in_array( $data['access_level'], CEAFSN_PP_DB::access_levels(), true ) ) {
			$errors[] = __( 'Invalid access level.', 'ceafsn-pp' );
		}
		if ( ! in_array( $data['status'], CEAFSN_PP_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-pp' );
		}

		// A cover image needs alt text, otherwise a screen reader announces the
		// cover as an unlabelled image.
		if ( ! empty( $data['cover_image_id'] ) ) {
			if ( '' === trim( (string) $data['cover_image_alt'] ) ) {
				$errors[] = __( 'Describe the cover image in the alt text field.', 'ceafsn-pp' );
			}

			// The picker can be bypassed by posting any attachment ID, and the
			// cover is rendered as an <img>, so the ID is checked to be a real
			// image rather than trusted because it arrived in the form.
			$cover_mime = get_post_mime_type( (int) $data['cover_image_id'] );

			if ( ! is_string( $cover_mime ) || ! str_starts_with( $cover_mime, 'image/' ) ) {
				$errors[] = __( 'The cover must be an image from the media library.', 'ceafsn-pp' );
			}
		}

		if ( ! empty( $data['doi_citation'] ) && ! filter_var( (string) $data['doi_citation'], FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'DOI / citation must be a valid URL.', 'ceafsn-pp' );
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-pp' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may publish or verify a record.
	 */
	private function require_approve(): void {
		if ( ! $this->can( self::CAP_APPROVE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-pp' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may change site-wide settings.
	 */
	private function require_manage(): void {
		if ( ! $this->can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-pp' ), 403 );
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
			add_query_arg( 'ceafsn_pp_error', rawurlencode( $message ), $back )
		);
		exit;
	}
}
