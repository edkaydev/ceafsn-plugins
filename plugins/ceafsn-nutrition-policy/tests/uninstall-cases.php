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
 * @package CEAFSN_NP
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$case = $argv[1] ?? '';

/**
 * In-memory $wpdb that records queries instead of touching a database.
 */
class UninstallNpWpdb {

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
		$this->recorded[] = $sql;
		return 1;
	}
}

switch ( $case ) {
	case 'no-context':
		// WP_UNINSTALL_PLUGIN deliberately left undefined.
		break;

	case 'flag-off':
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ceafsn-nutrition-policy/ceafsn-nutrition-policy.php' );
		}
		// Delete flag deliberately left unset.
		break;

	case 'flag-on':
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ceafsn-nutrition-policy/ceafsn-nutrition-policy.php' );
		}
		update_option( 'ceafsn_np_uninstall_delete_data', '1' );
		break;

	default:
		echo "unknown case\n";
		exit( 2 );
}

$wpdb = new UninstallNpWpdb();

ob_start();
require dirname( __DIR__ ) . '/uninstall.php';
ob_end_clean();

echo (string) json_encode(
	array(
		'case'    => $case,
		'queries' => $wpdb->recorded,
		'options' => CEAFSN_NP_Test_State::$options,
	)
);
