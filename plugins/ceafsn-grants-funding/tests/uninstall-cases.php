<?php
/**
 * Uninstall handler test cases, run as separate processes.
 *
 * uninstall.php returns early when it is loaded outside the WordPress uninstall
 * context, so each case runs in its own process. The parent suite in
 * run-tests.php invokes this file with one of the case names below.
 *
 * Usage: php tests/uninstall-cases.php <case>
 *
 * @package CEAFSN_GF
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';

$case     = $argv[1] ?? '';
$flag_on  = ( 'flag-on' === $case );
$reported = $case;

switch ( $case ) {
	case 'no-context':
		// WP_UNINSTALL_PLUGIN is deliberately left undefined.
		break;

	case 'flag-off':
	case 'flag-on':
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'ceafsn-grants-funding/ceafsn-grants-funding.php' );
		}

		// Both cases start from the same site: a plugin that has been used, with
		// its options set. Only the opt-in differs.
		update_option( 'ceafsn_gf_db_version', '1.0.0' );
		update_option( 'ceafsn_gf_show_closed', true );

		if ( $flag_on ) {
			update_option( 'ceafsn_gf_uninstall_delete_data', true );
		}
		break;

	default:
		echo "unknown case\n";
		exit( 2 );
}

/**
 * In-memory $wpdb that records queries instead of touching a database.
 */
class UninstallGfWpdb {

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

$wpdb = new UninstallGfWpdb();

// Written from a shutdown function so the no-context case, which returns from
// inside uninstall.php without going through the rest of this script, reports
// the same shape as the cases that fall through normally.
register_shutdown_function(
	static function () use ( $wpdb, $reported ): void {
		echo (string) json_encode(
			array(
				'case'    => $reported,
				'queries' => $wpdb->recorded,
				'options' => CEAFSN_GF_Test_State::$options,
			)
		);
	}
);

ob_start();
require dirname( __DIR__ ) . '/uninstall.php';
ob_end_clean();
