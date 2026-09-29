<?php
/**
 * Uninstall handler test cases, run as separate processes.
 *
 * uninstall.php calls exit() when it is loaded outside the WordPress uninstall
 * context, so each case has to run in its own process. The parent suite in
 * run-tests.php invokes this file with one of the case names below.
 *
 * Usage: php tests/uninstall-cases.php <case>
 *
 * @package CEAFSN_MED
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$case = $argv[1] ?? '';

/**
 * Record a query from the plugin's $wpdb replacement.
 */
$GLOBALS['ceafsn_uninstall_queries'] = array();

/**
 * In-memory $wpdb that records queries instead of touching a database.
 */
class UninstallFakeWpdb {

	/** @var string Table prefix. */
	public string $prefix = 'wp_';

	/** @var array<int,string> Recorded queries. */
	public array $recorded = array();

	/**
	 * Record a raw query.
	 *
	 * @param string $sql SQL.
	 * @return int Always 1.
	 */
	public function query( string $sql ): int {
		$this->recorded[]                = $sql;
		$GLOBALS['ceafsn_uninstall_queries'][] = $sql;
		return 1;
	}
}

$uninstall_file = dirname( __DIR__ ) . '/uninstall.php';

switch ( $case ) {
	case 'no-context':
		// WP_UNINSTALL_PLUGIN deliberately left undefined.
		break;

	case 'flag-off':
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ceafsn-me-dashboard/ceafsn-me-dashboard.php' );
		}
		// Delete flag deliberately left unset.
		break;

	case 'flag-on':
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ceafsn-me-dashboard/ceafsn-me-dashboard.php' );
		}
		update_option( 'ceafsn_med_uninstall_delete_data', '1' );
		break;

	default:
		echo "unknown case\n";
		exit( 2 );
}

$GLOBALS['wpdb'] = new UninstallFakeWpdb();

ob_start();
require $uninstall_file;
ob_end_clean();

$queries = $GLOBALS['ceafsn_uninstall_queries'];

echo json_encode(
	array(
		'case'    => $case,
		'queries' => $queries,
		'options' => CEAFSN_Test_State::$options,
	)
);
