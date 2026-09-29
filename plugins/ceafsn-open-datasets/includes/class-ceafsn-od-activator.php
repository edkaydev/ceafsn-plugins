<?php
/**
 * Activation and deactivation hooks for CE-AFSN Open Datasets.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_OD_Activator
 */
class CEAFSN_OD_Activator {

	/** @var string Admin page slug this plugin owns. */
	const PAGE_SLUG = 'ceafsn-od';

	/** @var string Media modal sub-action used by the dataset file picker. */
	const PICKER_SUB_ACTION = 'ceafsn-od-dataset-picker';

	/**
	 * Plugin activation.
	 *
	 * - Creates or upgrades the dataset table.
	 *
	 * The upload restriction is not registered here. A filter added during
	 * activation only exists for that one request, so it would silently stop
	 * working on the next page load. It is registered on every admin request by
	 * ceafsn_od_register_upload_filter() instead.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verify the current user has permission to activate plugins.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Create / upgrade the table.
		CEAFSN_OD_DB::create_tables();
	}

	/**
	 * Plugin deactivation.
	 *
	 * Deactivation deliberately does NOT delete any data. Data removal requires
	 * an explicit admin action in the Uninstall tab.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		remove_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ) );
	}

	/**
	 * Register the scoped upload restriction for this request.
	 *
	 * Registered on every admin request rather than only at activation, because
	 * the filter has to be in place before the media modal asks for the allowed
	 * MIME list. The filter itself is a no-op outside the dataset screen.
	 *
	 * @return void
	 */
	public static function register_upload_filter(): void {
		add_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ) );
	}

	/**
	 * Whether the current request is this plugin's dataset editor screen.
	 *
	 * @return bool True when the request targets the dataset admin page.
	 */
	public static function is_dataset_screen(): bool {
		if ( ! is_admin() || ! CEAFSN_OD_Request::has( 'page' ) ) {
			return false;
		}

		return self::PAGE_SLUG === CEAFSN_OD_Request::key( 'page' );
	}

	/**
	 * Restrict uploads to dataset file types, but only on this plugin's screen.
	 *
	 * A global `upload_mimes` filter would silently break every other upload on
	 * the site — images for pages, plugin assets, other plugins' imports. Instead
	 * the restriction is applied only while the dataset editor is on screen.
	 *
	 * The authoritative check is server-side validation in CEAFSN_OD_Validator;
	 * this filter is a usability guard that keeps the file picker honest.
	 *
	 * @param array<string,string> $mimes Allowed mime types keyed by extension group.
	 * @return array<string,string>
	 */
	public static function restrict_upload_mimes( array $mimes ): array {
		if ( ! self::is_dataset_screen() ) {
			return $mimes;
		}

		unset( $mimes );

		// WordPress keys these by extension group, e.g. 'csv|txt' and 'zip|x-zip'.
		// An empty result is treated as "allow everything", so the list is built
		// from scratch rather than filtered out of $mimes.
		$allowed = array(
			'csv|txt' => 'text/csv',
			'zip|x-zip' => 'application/zip',
			'xlsx|xlsm|xlsb' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xls'  => 'application/vnd.ms-excel',
		);

		return $allowed;
	}
}
