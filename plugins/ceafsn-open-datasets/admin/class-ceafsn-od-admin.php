<?php
/**
 * Admin menu, CRUD routing, and settings for CE-AFSN Open Datasets.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_OD_Admin
 */
class CEAFSN_OD_Admin {

	/** @var string Admin page parent slug. */
	const MENU_SLUG = 'ceafsn-od';

	/** @var string Settings sub-page slug. */
	const SETTINGS_SLUG = 'ceafsn-od-settings';

	/**
	 * Register WordPress hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ceafsn_od_save_dataset',   array( $this, 'handle_save_dataset' ) );
		add_action( 'admin_post_ceafsn_od_delete_dataset', array( $this, 'handle_delete_dataset' ) );
		add_action( 'admin_post_ceafsn_od_save_settings',  array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_od_export',         array( $this, 'handle_export' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the admin menu and sub-pages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Open Datasets', 'ceafsn-od' ),
			__( 'Open Datasets', 'ceafsn-od' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_datasets' ),
			'dashicons-database',
			31
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Datasets', 'ceafsn-od' ),
			__( 'Datasets', 'ceafsn-od' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'page_datasets' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-od' ),
			__( 'Settings', 'ceafsn-od' ),
			'manage_options',
			self::SETTINGS_SLUG,
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
			'open-datasets_page_' . self::SETTINGS_SLUG,
		);

		if ( ! in_array( $hook_suffix, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'ceafsn-od-admin',
			CEAFSN_OD_PLUGIN_URL . 'assets/css/ceafsn-od-admin.css',
			array(),
			CEAFSN_OD_VERSION
		);

		wp_enqueue_script(
			'ceafsn-od-admin',
			CEAFSN_OD_PLUGIN_URL . 'assets/js/ceafsn-od-admin.js',
			array( 'jquery', 'wp-i18n' ),
			CEAFSN_OD_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'ceafsn-od-admin', 'ceafsn-od' );
		}
	}

	// ---------------------------------------------------------------------------
	// Page renderers (delegate to partials)
	// ---------------------------------------------------------------------------

	/**
	 * Datasets list / add / edit page.
	 */
	public function page_datasets(): void {
		$this->require_edit();

		$action = CEAFSN_OD_Request::key( 'action', 'list' );
		$id     = CEAFSN_OD_Request::int( 'id' );

		$row = null;
		if ( in_array( $action, array( 'edit', 'add' ), true ) && $id > 0 ) {
			$row = CEAFSN_OD_DB::get_dataset( $id );
		}

		$validation = null;
		if ( null !== $row ) {
			$validation = $this->validate_row_download( $row );
		}

		$result    = CEAFSN_OD_DB::get_datasets( array( 'per_page' => 100 ) );
		$items     = $result['items'];
		$categories = CEAFSN_OD_DB::get_categories();

		require CEAFSN_OD_PLUGIN_DIR . 'admin/partials/datasets.php';
	}

	/**
	 * Settings page (export + uninstall).
	 */
	public function page_settings(): void {
		$this->require_edit();
		require CEAFSN_OD_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers
	// ---------------------------------------------------------------------------

	/**
	 * Handle save (add/edit) for a dataset record.
	 *
	 * A record can only be saved as `published` when its download target passes
	 * validation. Attempting to publish an invalid target downgrades the record
	 * to draft and reports why, rather than silently failing.
	 */
	public function handle_save_dataset(): void {
		$this->require_edit();
		check_admin_referer( 'ceafsn_od_dataset_nonce', 'ceafsn_od_nonce' );

		$id   = CEAFSN_OD_Request::int( 'dataset_id', 0, 'POST' );
		$data = $this->extract_dataset_fields();

		$errors = $this->validate_dataset( $data );
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

		// Publishing is gated on a download target that actually resolves.
		if ( 'published' === $data['status'] ) {
			$target = CEAFSN_OD_Validator::verify_download_target(
				(int) $data['file_attachment_id'],
				(string) $data['download_url'],
				(string) $data['file_type']
			);

			if ( ! empty( $target['errors'] ) ) {
				$data['status'] = 'draft';

				if ( $id > 0 ) {
					CEAFSN_OD_DB::update_dataset( $id, $data );
				} else {
					CEAFSN_OD_DB::insert_dataset( $data );
				}

				$this->redirect_back_with_error(
					implode( ' ', $target['errors'] ) . ' ' . __( 'The record was saved as a draft instead.', 'ceafsn-od' )
				);
				return;
			}

			// The size was just measured or reported by the server, so store
			// that rather than whatever the form claimed.
			$data['file_size'] = (int) $target['size'];
		}

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_OD_DB::get_dataset( $id );
			CEAFSN_OD_DB::update_dataset( $id, $data );
			$this->audit( 'update', 'dataset', $id, $ceafsn_before, CEAFSN_OD_DB::get_dataset( $id ) );
		} else {
			$ceafsn_new = CEAFSN_OD_DB::insert_dataset( $data );
			$this->audit( 'create', 'dataset', (int) $ceafsn_new, null, CEAFSN_OD_DB::get_dataset( (int) $ceafsn_new ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => self::MENU_SLUG, 'saved' => '1', 'published_blocked' => $ceafsn_blocked_publish ? '1' : '0' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete for a dataset record.
	 */
	public function handle_delete_dataset(): void {
		$this->require_edit();
		$id = CEAFSN_OD_Request::int( 'id' );
		check_admin_referer( 'ceafsn_od_delete_dataset_' . $id );

		if ( $id > 0 ) {
			$ceafsn_before = CEAFSN_OD_DB::get_dataset( $id );
			CEAFSN_OD_DB::delete_dataset( $id );
			$this->audit( 'delete', 'dataset', $id, $ceafsn_before, null );
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
	 * with a `ceafsn_open_settings_scope` hidden field. Only those options are
	 * written, so saving one tab cannot reset a checkbox that lives on another.
	 *
	 * @param array<string,mixed> $post Unslashed POST data.
	 * @return void
	 */
	private function persist_settings( array $post ): void {
		$scope = isset( $post['ceafsn_od_settings_scope'] ) && is_string( $post['ceafsn_od_settings_scope'] )
			? sanitize_key( $post['ceafsn_od_settings_scope'] )
			: '';

		if ( 'display' === $scope ) {
			// Contact visibility is an explicit checkbox only.
			update_option( 'ceafsn_od_show_contact', isset( $post['ceafsn_od_show_contact'] ) ? 1 : 0 );

			// Allow "Other" file types in the media picker.
			update_option( 'ceafsn_od_allow_other_files', isset( $post['ceafsn_od_allow_other_files'] ) ? 1 : 0 );
		}

		// Uninstall delete-data flag — explicit checkbox only.
		if ( 'uninstall' === $scope ) {
			update_option( CEAFSN_OD_DB::UNINSTALL_OPTION, isset( $post['ceafsn_od_uninstall_delete_data'] ) );
		}
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_od_settings_nonce', 'ceafsn_od_nonce' );

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
	 * Stream a JSON export of all dataset records.
	 */
	public function handle_export(): void {
		$this->require_manage();
		check_admin_referer( 'ceafsn_od_export_nonce', 'ceafsn_od_nonce' );

		$data     = CEAFSN_OD_DB::export_all();
		$filename = 'ceafsn-od-export-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	// ---------------------------------------------------------------------------
	// Field extraction helpers
	// ---------------------------------------------------------------------------

	/**
	 * Extract and sanitize dataset fields from $_POST.
	 *
	 * The file size is never trusted from the form. It is recalculated from the
	 * attachment on disk, so a crafted form cannot advertise a fake size.
	 *
	 * @return array<string,mixed>
	 */
	private function extract_dataset_fields(): array {
		$attachment_id = CEAFSN_OD_Request::int( 'file_attachment_id', 0, 'POST' );
		$file_size     = 0;

		if ( $attachment_id > 0 ) {
			$path = get_attached_file( $attachment_id );
			if ( $path && is_readable( $path ) ) {
				$size = filesize( $path );
				$file_size = is_int( $size ) && $size > 0 ? $size : 0;
			}
		}

		$uninstall = CEAFSN_OD_Request::has( 'ceafsn_od_uninstall_delete_data', 'POST' );

		return array(
			'name'               => CEAFSN_OD_Request::text( 'name', '', 'POST' ),
			'description'        => CEAFSN_OD_Request::textarea( 'description', '', 'POST' ),
			'category'           => CEAFSN_OD_Request::text( 'category', '', 'POST' ),
			'coverage_area'      => CEAFSN_OD_Request::text( 'coverage_area', '', 'POST' ),
			'last_updated'       => CEAFSN_OD_Request::text( 'last_updated', '', 'POST' ),
			'file_attachment_id' => $attachment_id,
			'download_url'       => CEAFSN_OD_Request::url( 'download_url', '', 'POST' ),
			'file_type'          => CEAFSN_OD_Request::key( 'file_type', 'csv', 'POST' ),
			'file_size'          => $file_size,
			'data_license'       => CEAFSN_OD_Request::text( 'data_license', '', 'POST' ),
			'methodology_url'    => CEAFSN_OD_Request::url( 'methodology_url', '', 'POST' ),
			'contact_owner'      => CEAFSN_OD_Request::text( 'contact_owner', '', 'POST' ),
			'status'             => CEAFSN_OD_Request::key( 'status', 'draft', 'POST' ),
			'uninstall_flag'     => $uninstall,
		);
	}

	// ---------------------------------------------------------------------------
	// Validation helpers
	// ---------------------------------------------------------------------------

	/**
	 * Validate dataset fields. Returns an array of error strings.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return array<int,string>
	 */
	private function validate_dataset( array $data ): array {
		$errors = array();

		if ( empty( $data['name'] ) ) {
			$errors[] = __( 'Dataset name is required.', 'ceafsn-od' );
		}
		if ( empty( $data['description'] ) ) {
			$errors[] = __( 'Description is required.', 'ceafsn-od' );
		}
		if ( empty( $data['category'] ) ) {
			$errors[] = __( 'Category / sector is required.', 'ceafsn-od' );
		}
		if ( empty( $data['coverage_area'] ) ) {
			$errors[] = __( 'Coverage area is required.', 'ceafsn-od' );
		}
		if ( empty( $data['data_license'] ) ) {
			$errors[] = __( 'Data license is required.', 'ceafsn-od' );
		}

		// Exactly one download route is required, and both may not be empty.
		$attachment_id = (int) ( $data['file_attachment_id'] ?? 0 );
		$download_url  = (string) ( $data['download_url'] ?? '' );

		if ( $attachment_id <= 0 && '' === $download_url ) {
			$errors[] = __( 'Attach a dataset file or enter a download URL. One of the two is required.', 'ceafsn-od' );
		}

		if ( $attachment_id > 0 && '' !== $download_url ) {
			$errors[] = __( 'Provide either a file attachment or a download URL, not both. The attachment takes precedence, so remove the URL.', 'ceafsn-od' );
		}

		if ( $attachment_id > 0 && '' === $download_url && 0 === (int) ( $data['file_size'] ?? 0 ) ) {
			$errors[] = __( 'The attached file is zero bytes or could not be read.', 'ceafsn-od' );
		}

		if ( '' !== $download_url && ! filter_var( $download_url, FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'Download URL is not a valid URL.', 'ceafsn-od' );
		}

		$date = (string) ( $data['last_updated'] ?? '' );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$errors[] = __( 'Last updated must be a valid date (YYYY-MM-DD).', 'ceafsn-od' );
		}

		if ( ! in_array( (string) ( $data['status'] ?? '' ), CEAFSN_OD_DB::statuses(), true ) ) {
			$errors[] = __( 'Invalid status value.', 'ceafsn-od' );
		}

		if ( ! in_array( (string) ( $data['file_type'] ?? '' ), CEAFSN_OD_Validator::FILE_TYPES, true ) ) {
			$errors[] = __( 'Invalid file type.', 'ceafsn-od' );
		}

		// "Other" bypasses every structural check, so it is only offered when an
		// administrator has explicitly opted in.
		if ( 'other' === (string) ( $data['file_type'] ?? '' ) && ! self::other_files_allowed() ) {
			$errors[] = __( 'The "other" file type is not enabled. Enable it in Settings, or choose CSV, ZIP, or XLSX.', 'ceafsn-od' );
		}

		if ( ! empty( $data['methodology_url'] ) && ! filter_var( (string) $data['methodology_url'], FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'Methodology / data dictionary URL is not a valid URL.', 'ceafsn-od' );
		}

		return $errors;
	}

	/**
	 * Validate an existing row's download target for the admin warning banner.
	 *
	 * @param object $row Dataset row.
	 * @return array{valid: bool, entries: int, errors: array<int,string>}
	 */
	private function validate_row_download( object $row ): array {
		$attachment_id = (int) ( $row->file_attachment_id ?? 0 );
		$file_type     = (string) ( $row->file_type ?? 'other' );

		if ( $attachment_id > 0 ) {
			return CEAFSN_OD_Validator::validate_attachment( $attachment_id, $file_type );
		}

		$url = (string) ( $row->download_url ?? '' );
		if ( '' === $url ) {
			return array(
				'valid'   => false,
				'entries' => 0,
				'errors'  => array( __( 'This record has no download target.', 'ceafsn-od' ) ),
			);
		}

		// The external probe result is shaped like a byte result so the
		// admin partial can treat both routes identically.
		$result           = CEAFSN_OD_Validator::validate_external_url( $url );
		$result['entries'] = $result['valid'] ? 1 : 0;

		return $result;
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-od' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may publish or verify a record.
	 */
	private function require_approve(): void {
		if ( ! $this->can( self::CAP_APPROVE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-od' ), 403 );
		}
	}

	/**
	 * Abort with a 403 unless the user may change site-wide settings.
	 */
	private function require_manage(): void {
		if ( ! $this->can( self::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ceafsn-od' ), 403 );
		}
	}

	/**
	 * Whether the "other" file type has been enabled in Settings.
	 *
	 * @return bool
	 */
	public static function other_files_allowed(): bool {
		return (bool) get_option( 'ceafsn_od_allow_other_files', 0 );
	}

	/**
	 * Redirect back to the previous admin page with an error notice.
	 *
	 * @param string $message Error message (plain text).
	 */
	private function redirect_back_with_error( string $message ): void {
		$back = wp_get_referer() ?: admin_url( 'admin.php?page=' . self::MENU_SLUG );
		wp_safe_redirect(
			add_query_arg( 'ceafsn_od_error', rawurlencode( $message ), $back )
		);
		exit;
	}
}
