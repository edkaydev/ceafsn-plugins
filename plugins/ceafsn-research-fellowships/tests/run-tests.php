<?php
/**
 * Test runner for CE-AFSN Research Fellowships.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-research-fellowships/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_RF
 */

declare( strict_types=1 );

// WordPress is not installed here, so point ABSPATH at a temporary directory
// that contains just enough of wp-admin for the activator to load.
$fake_wp_admin = sys_get_temp_dir() . '/ceafsn-rf-fake-wp/wp-admin/includes';

if ( ! is_dir( $fake_wp_admin ) ) {
	mkdir( $fake_wp_admin, 0777, true );
}

file_put_contents(
	$fake_wp_admin . '/upgrade.php',
	"<?php\n"
	. "/**\n"
	. " * Minimal stand-in for wp-admin/includes/upgrade.php.\n"
	. " *\n"
	. " * Records the statements dbDelta() is given so tests can assert on the\n"
	. " * schema the plugin would create.\n"
	. " */\n"
	. "if ( ! function_exists( 'dbDelta' ) ) {\n"
	. "\tfunction dbDelta( \$queries ) {\n"
	. "\t\tforeach ( (array) \$queries as \$query ) {\n"
	. "\t\t\t\$GLOBALS['ceafsn_rf_dbdelta'][] = (string) \$query;\n"
	. "\t\t}\n"
	. "\t\treturn array();\n"
	. "\t}\n"
	. "}\n"
);

define( 'ABSPATH', dirname( $fake_wp_admin, 2 ) . '/' );

require_once __DIR__ . '/bootstrap.php';

/**
 * Minimal in-memory $wpdb replacement.
 *
 * Records every query so tests can assert on the SQL the plugin generates, and
 * replays canned result sets for reads.
 */
final class FakeRfWpdb {

	/** @var string Table prefix. */
	public string $prefix = 'wp_';

	/**
	 * Character set clause appended to CREATE TABLE statements.
	 *
	 * @return string Charset and collation.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/** @var int Insert id after the last insert. */
	public int $insert_id = 1;

	/** @var array<int,string> Every query the plugin ran. */
	public array $queries = array();

	/** @var array<int,mixed> Result queue for the next get_results() calls. */
	public array $results_queue = array();

	/** @var array<int,mixed> Queue for the next get_var() calls. */
	public array $var_queue = array();

	/** @var mixed Value returned by get_var() when the queue is empty. */
	public mixed $var_result = 0;

	/** @var mixed Value returned by the next get_row() call. */
	public mixed $row_result = null;

	/** @var array<int,mixed> Queue for the next get_col() calls. */
	public array $col_queue = array();

	/**
	 * Interpolate values into a query, mimicking $wpdb->prepare().
	 *
	 * @param string $query Query with %s / %d / %f placeholders.
	 * @param mixed  ...$args Values.
	 * @return string Prepared query.
	 */
	public function prepare( string $query, ...$args ): string {
		$index = 0;
		return (string) preg_replace_callback(
			'/%[sdf]/',
			function ( array $match ) use ( &$index, $args ): string {
				$value = $args[ $index++ ] ?? '';
				switch ( $match[0] ) {
					case '%d':
						return (string) (int) $value;
					case '%f':
						return (string) (float) $value;
					default:
						return "'" . addslashes( (string) $value ) . "'";
				}
			},
			$query
		);
	}

	/**
	 * Escape LIKE wildcards.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * Run a query and return the next queued result set.
	 *
	 * @param string $query SQL.
	 * @return array<int,object>
	 */
	public function get_results( string $query ): array {
		$this->queries[] = $query;
		return array_shift( $this->results_queue ) ?: array();
	}

	/**
	 * Run a query and return the next queued scalar.
	 *
	 * @param string $query SQL.
	 * @return mixed
	 */
	public function get_var( string $query ) {
		$this->queries[] = $query;
		if ( ! empty( $this->var_queue ) ) {
			return array_shift( $this->var_queue );
		}
		return $this->var_result;
	}

	/**
	 * Run a query and return the next queued row.
	 *
	 * @param string $query SQL.
	 * @return object|null
	 */
	public function get_row( string $query ) {
		$this->queries[] = $query;
		return $this->row_result;
	}

	/**
	 * Run a query and return the next queued column.
	 *
	 * @param string $query SQL.
	 * @return array<int,mixed>
	 */
	public function get_col( string $query ): array {
		$this->queries[] = $query;
		return array_shift( $this->col_queue ) ?: array();
	}

	/**
	 * Record an insert.
	 *
	 * @param string $table  Table.
	 * @param array  $data   Column data.
	 * @param array  $format Formats.
	 * @return int Always 1.
	 */
	public function insert( string $table, array $data, array $format = array() ): int {
		unset( $format );
		$this->queries[] = 'INSERT INTO ' . $table . ' ' . (string) json_encode( $data );
		return 1;
	}

	/**
	 * Record an update.
	 *
	 * @param string $table        Table.
	 * @param array  $data         Column data.
	 * @param array  $where        Where clause.
	 * @param array  $format       Formats.
	 * @param array  $where_format Where formats.
	 * @return int Always 1.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int {
		unset( $format, $where_format );
		$this->queries[] = 'UPDATE ' . $table . ' ' . (string) json_encode( $data ) . ' ' . (string) json_encode( $where );
		return 1;
	}

	/**
	 * Record a delete.
	 *
	 * @param string $table  Table.
	 * @param array  $where  Where clause.
	 * @param array  $format Formats.
	 * @return int Always 1.
	 */
	public function delete( string $table, array $where, array $format = array() ): int {
		unset( $format );
		$this->queries[] = 'DELETE FROM ' . $table . ' ' . (string) json_encode( $where );
		return 1;
	}

	/**
	 * Record a raw query.
	 *
	 * @param string $sql SQL.
	 * @return int Always 1.
	 */
	public function query( string $sql ): int {
		$this->queries[] = $sql;
		return 1;
	}

	/**
	 * Forget every recorded query and canned result.
	 *
	 * @return void
	 */
	public function reset_state(): void {
		$this->insert_id    = 1;
		$this->queries      = array();
		$this->results_queue = array();
		$this->var_queue    = array();
		$this->var_result   = 0;
		$this->row_result   = null;
		$this->col_queue    = array();
	}
}

// -----------------------------------------------------------------------------
// Assertions
// -----------------------------------------------------------------------------

$GLOBALS['ceafsn_rf_test_pass']    = 0;
$GLOBALS['ceafsn_rf_test_fail']    = 0;
$GLOBALS['ceafsn_rf_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_rf_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ok( bool $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_rf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_rf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_rf_test_current']}] {$message}\n";
}

/**
 * Assert two values are identical.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Failure message.
 */
function is_same( $expected, $actual, string $message ): void {
	if ( $expected === $actual ) {
		++$GLOBALS['ceafsn_rf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_rf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_rf_test_current']}] {$message}\n"
		. '       expected: ' . var_export( $expected, true ) . "\n"
		. '       actual:   ' . var_export( $actual, true ) . "\n";
}

/**
 * Assert a string contains a substring.
 *
 * @param string $needle   Substring.
 * @param string $haystack String to search.
 * @param string $message  Failure message.
 */
function has_substring( string $needle, string $haystack, string $message ): void {
	if ( str_contains( $haystack, $needle ) ) {
		++$GLOBALS['ceafsn_rf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_rf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_rf_test_current']}] {$message}\n       missing: {$needle}\n       in: {$haystack}\n";
}

/**
 * Assert a string does not contain a substring.
 *
 * @param string $needle   Substring.
 * @param string $haystack String to search.
 * @param string $message  Failure message.
 */
function lacks_substring( string $needle, string $haystack, string $message ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		++$GLOBALS['ceafsn_rf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_rf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_rf_test_current']}] {$message}\n       found: {$needle}\n";
}

/**
 * Print a section heading.
 *
 * @param string $title Section title.
 */
function section( string $title ): void {
	echo "\n{$title}\n";
}

// -----------------------------------------------------------------------------
// Fixtures
// -----------------------------------------------------------------------------

$plugin_dir  = dirname( __DIR__ );
$fixture_dir = sys_get_temp_dir() . '/ceafsn-rf-fixtures';

if ( ! is_dir( $fixture_dir ) ) {
	mkdir( $fixture_dir, 0777, true );
}

/**
 * Build a minimal but well-formed PDF for tests.
 *
 * @param int $pages Number of page objects.
 * @return string Raw PDF bytes.
 */
function make_pdf( int $pages = 3 ): string {
	$body = "%PDF-1.4\n";
	for ( $i = 0; $i < $pages; $i++ ) {
		$body .= "1 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n";
	}
	$body .= "trailer\n<< /Root 2 0 R >>\n%%EOF\n";
	return $body;
}

/**
 * Write a fixture file and return its path.
 *
 * @param string $name     File name.
 * @param string $contents File contents.
 * @return string Absolute path.
 */
function fixture( string $name, string $contents ): string {
	global $fixture_dir;
	$path = $fixture_dir . '/' . $name;
	file_put_contents( $path, $contents );
	return $path;
}

$valid_pdf     = fixture( 'call-document.pdf', make_pdf( 3 ) );
$truncated_pdf = fixture( 'truncated.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Page >>\nendobj\n" );
$not_a_pdf     = fixture( 'notes.txt', "This is a plain text file pretending to be a document.\n" );
$empty_pdf     = fixture( 'empty.pdf', '' );

/**
 * Register a fixture as a Media Library attachment.
 *
 * @param int    $id   Attachment ID.
 * @param string $path Absolute path.
 * @param string $mime MIME type.
 */
function attach( int $id, string $path, string $mime = 'application/pdf' ): void {
	CEAFSN_RF_Test_State::add_attachment( $id, $path, $mime );
}

/**
 * Build a fellowship row.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return object Fellowship row.
 */
function fellowship_row( array $overrides = array() ): object {
	return (object) array_merge(
		array(
			'fellowship_id'     => 1,
			'title'             => 'Regional Nutrition Research Fellowship',
			'track_domain'      => 'Nutrition',
			'duration'          => '12 months',
			'eligibility'       => 'Open to early-career researchers based in the region.',
			'host_supervisor'   => 'Dr. A. Mwangi',
			'stipend_info'      => 'USD 1,200 per month.',
			'show_stipend'      => 1,
			'opening_date'      => '2025-01-01',
			'closing_date'      => '2099-01-01',
			'application_url'   => 'https://example.test/apply',
			'call_pdf_id'       => 11,
			'contact_email'     => 'fellowships@example.test',
			'show_contact'      => 1,
			'status_override'   => 0,
			'override_note'     => '',
			'status_manual'     => 'upcoming',
			'status'            => 'published',
			'created_at'        => '2025-01-01 00:00:00',
			'updated_at'        => '2025-01-01 00:00:00',
			'updated_by'        => 1,
		),
		$overrides
	);
}

/**
 * Build the flat record array the validator and admin layer expect.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return array<string,mixed>
 */
function fellowship_row_data( array $overrides = array() ): array {
	return (array) fellowship_row( $overrides );
}

/**
 * Clear recorded state and register the plugin's hooks again.
 *
 * @param bool $as_admin Whether to simulate an admin request.
 * @return void
 */
function ceafsn_rf_test_reload( bool $as_admin = false ): void {
	CEAFSN_RF_Test_State::reset();

	if ( $as_admin ) {
		update_option( '__is_admin', 1 );
	}

	ceafsn_rf_init();
}

/**
 * Run a helper script in its own process and decode its JSON report.
 *
 * uninstall.php ends the request early in some branches, so each case runs
 * where returning or falling through is the expected outcome rather than a
 * failure of the test suite.
 *
 * @param string $script Script file name inside tests/.
 * @param array  $args   Command line arguments.
 * @return array<string,mixed> Decoded report, or an empty array on failure.
 */
function ceafsn_rf_test_run_case( string $script, array $args ): array {
	$command = sprintf(
		'%s -d error_reporting=0 %s %s 2>/dev/null',
		escapeshellarg( PHP_BINARY ),
		escapeshellarg( __DIR__ . '/' . $script ),
		implode( ' ', array_map( 'escapeshellarg', $args ) )
	);

	$json = json_decode( (string) shell_exec( $command ), true );

	return is_array( $json ) ? $json : array();
}

/**
 * Run one uninstall.php case in a child process.
 *
 * @param string $case Case name: no-context, flag-off, or flag-on.
 * @return array{case: string, queries: array<int,string>, options: array<string,mixed>}
 */
function ceafsn_rf_test_run_uninstall_case( string $case ): array {
	$report = ceafsn_rf_test_run_case( 'uninstall-cases.php', array( $case ) );

	if ( ! isset( $report['queries'] ) || ! is_array( $report['queries'] ) ) {
		ok( false, "the uninstall case {$case} returned no usable report" );
		return array(
			'case'    => $case,
			'queries' => array(),
			'options' => array(),
		);
	}

	return array(
		'case'    => isset( $report['case'] ) ? (string) $report['case'] : $case,
		'queries' => array_map( 'strval', $report['queries'] ),
		'options' => isset( $report['options'] ) && is_array( $report['options'] ) ? $report['options'] : array(),
	);
}

// -----------------------------------------------------------------------------
// Load plugin
// -----------------------------------------------------------------------------

$plugin_source = (string) file_get_contents( $plugin_dir . '/ceafsn-research-fellowships.php' );
require_once $plugin_dir . '/ceafsn-research-fellowships.php';

global $wpdb;
$wpdb = new FakeRfWpdb();

section( 'Plugin bootstrap' );

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', $plugin_source, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_RF_VERSION' ), 'CEAFSN_RF_VERSION defined' );
ok( defined( 'CEAFSN_RF_PLUGIN_DIR' ), 'CEAFSN_RF_PLUGIN_DIR defined' );
is_same( '1.0.0', CEAFSN_RF_VERSION, 'version constant matches header' );

test( 'all plugin classes loaded' );
foreach ( array( 'CEAFSN_RF_Status', 'CEAFSN_RF_DB', 'CEAFSN_RF_Validator', 'CEAFSN_RF_Activator', 'CEAFSN_RF_Admin', 'CEAFSN_RF_Public' ) as $class ) {
	ok( class_exists( $class ), "class {$class} exists" );
}

test( 'the shortcode is registered on plugins_loaded' );
ok( isset( CEAFSN_RF_Test_State::$actions['plugins_loaded'] ), 'bootstrap registers a plugins_loaded callback' );
ceafsn_rf_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_RF_Test_State::$shortcodes['ceafsn_fellowships'] ), '[ceafsn_fellowships] registered' );

test( 'translations are loaded on init' );
ok( isset( CEAFSN_RF_Test_State::$actions['init'] ), 'init hook registered' );
ceafsn_rf_test_fire_action( 'init' );
ok( in_array( 'ceafsn-rf', CEAFSN_RF_Test_State::$textdomains, true ), 'ceafsn-rf text domain loaded' );

// -----------------------------------------------------------------------------
section( 'Status: date-driven derivation' );

test( 'an override is always Open, regardless of dates' );
$result = CEAFSN_RF_Status::derive(
	array( 'status_override' => 1, 'opening_date' => '', 'closing_date' => '2020-01-01' ),
	'2025-06-01'
);
is_same( 'open', $result['status'], 'override wins even with a closing date in the past' );
is_same( 'override', $result['reason'], 'the reason names the override' );

test( 'a future opening date is Upcoming' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '2025-07-01', 'closing_date' => '2025-08-01' ), '2025-06-01' );
is_same( 'upcoming', $result['status'], 'not yet open' );
is_same( 'opening_date_future', $result['reason'], 'reason names the future opening date' );

test( 'a past closing date is Closed' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-05-01' ), '2025-06-01' );
is_same( 'closed', $result['status'], 'closing date has passed' );
is_same( 'closing_date_passed', $result['reason'], 'reason names the passed closing date' );

test( 'between the dates is Open' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-12-01' ), '2025-06-01' );
is_same( 'open', $result['status'], 'within the application window' );
is_same( 'within_dates', $result['reason'], 'reason names the dates' );

test( 'a closing date with no opening date is Open until it passes' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '', 'closing_date' => '2025-12-01' ), '2025-06-01' );
is_same( 'open', $result['status'], 'a closing date alone is enough evidence' );

test( 'an opening date with no closing date is Unconfirmed, never Open' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '2025-01-01', 'closing_date' => '' ), '2025-06-01' );
is_same( 'unconfirmed', $result['status'], 'no closing date means no proof applications are open' );
is_same( 'no_closing_date', $result['reason'], 'reason names the missing closing date' );

test( 'a contradictory pair of dates favours the future opening date' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '2025-07-01', 'closing_date' => '2025-01-01' ), '2025-06-01' );
is_same( 'upcoming', $result['status'], 'opening-in-future is checked before closing-in-past' );

test( 'no dates at all falls back to the manual status' );
$result = CEAFSN_RF_Status::derive( array( 'opening_date' => '', 'closing_date' => '', 'status_manual' => 'closed' ), '2025-06-01' );
is_same( 'closed', $result['status'], 'the manual value is used' );
is_same( 'manual', $result['reason'], 'reason names the manual path' );

test( 'an invalid manual value normalises to upcoming' );
is_same( 'upcoming', CEAFSN_RF_Status::normalize_manual( 'bogus' ), 'unknown manual values become upcoming' );
is_same( 'closed', CEAFSN_RF_Status::normalize_manual( 'closed' ), 'a real manual value is kept' );

test( 'readable_date rejects malformed and zero dates' );
is_same( '2025-06-01', CEAFSN_RF_Status::readable_date( '2025-06-01' ), 'a real date is kept' );
is_same( '', CEAFSN_RF_Status::readable_date( '' ), 'an empty value stays empty' );
is_same( '', CEAFSN_RF_Status::readable_date( null ), 'a null value is empty' );
is_same( '', CEAFSN_RF_Status::readable_date( '0000-00-00' ), 'the MySQL zero date is empty' );
is_same( '', CEAFSN_RF_Status::readable_date( '01/06/2025' ), 'a non-ISO date is rejected' );

test( 'labels and reason text exist for every status' );
is_same( 'Open', CEAFSN_RF_Status::label( 'open' ), 'open label' );
is_same( 'Upcoming', CEAFSN_RF_Status::label( 'upcoming' ), 'upcoming label' );
is_same( 'Closed', CEAFSN_RF_Status::label( 'closed' ), 'closed label' );
is_same( 'Archived', CEAFSN_RF_Status::label( 'archived' ), 'archived label' );
is_same( 'Status not confirmed', CEAFSN_RF_Status::label( 'unconfirmed' ), 'unconfirmed label' );

test( 'reason_text explains every non-Open reason and nothing for Open' );
has_substring( 'no closing date is recorded', strtolower( CEAFSN_RF_Status::reason_text( 'no_closing_date' ) ), 'no_closing_date is explained' );
has_substring( 'not opened yet', CEAFSN_RF_Status::reason_text( 'opening_date_future' ), 'opening_date_future is explained' );
has_substring( 'closing date has passed', CEAFSN_RF_Status::reason_text( 'closing_date_passed' ), 'closing_date_passed is explained' );
is_same( '', CEAFSN_RF_Status::reason_text( 'within_dates' ), 'an Open reason has no explanation text' );
is_same( '', CEAFSN_RF_Status::reason_text( 'unknown' ), 'an unrecognised reason has no explanation text' );

test( 'accepts_applications is true only for Open' );
ok( CEAFSN_RF_Status::accepts_applications( 'open' ), 'open accepts applications' );
ok( ! CEAFSN_RF_Status::accepts_applications( 'upcoming' ), 'upcoming does not' );
ok( ! CEAFSN_RF_Status::accepts_applications( 'closed' ), 'closed does not' );
ok( ! CEAFSN_RF_Status::accepts_applications( 'unconfirmed' ), 'unconfirmed does not' );

test( 'manual_values excludes unconfirmed, all() includes it' );
ok( ! in_array( 'unconfirmed', CEAFSN_RF_Status::manual_values(), true ), 'an admin cannot set unconfirmed by hand' );
ok( in_array( 'unconfirmed', CEAFSN_RF_Status::all(), true ), 'the public page can still display it' );

test( 'today() reads the site timezone' );
CEAFSN_RF_Test_State::$options['timezone_string'] = 'Africa/Nairobi';
CEAFSN_RF_Test_State::$today                      = '2025-06-15';
is_same( '2025-06-15', CEAFSN_RF_Status::today(), 'today() reflects the configured site date' );
CEAFSN_RF_Test_State::$today                      = '';
unset( CEAFSN_RF_Test_State::$options['timezone_string'] );

// -----------------------------------------------------------------------------
section( 'Document and URL validation' );

test( 'a well-formed PDF passes' );
$result = CEAFSN_RF_Validator::inspect_bytes( make_pdf( 3 ) );
ok( $result['valid'], 'valid PDF reported as valid' );
is_same( array(), $result['errors'], 'no errors for a valid PDF' );
is_same( 3, $result['pages'], 'page count read correctly' );

test( 'an empty file is rejected' );
$result = CEAFSN_RF_Validator::inspect_bytes( '' );
ok( ! $result['valid'], 'empty file is not valid' );
has_substring( 'empty or could not be read', implode( ' ', $result['errors'] ), 'the empty-file error is reported' );

test( 'a non-PDF file is rejected' );
$result = CEAFSN_RF_Validator::inspect_bytes( 'just some text, no PDF here' );
ok( ! $result['valid'], 'plain text is not a valid PDF' );
has_substring( 'is not a PDF', implode( ' ', $result['errors'] ), 'the signature error is reported' );

test( 'a truncated PDF is rejected' );
$result = CEAFSN_RF_Validator::inspect_bytes( (string) file_get_contents( $truncated_pdf ) );
ok( ! $result['valid'], 'truncated PDF is not valid' );
has_substring( 'truncated', implode( ' ', $result['errors'] ), 'errors mention the truncation' );

test( 'page count falls back to the page tree /Count' );
is_same( 9, CEAFSN_RF_Validator::count_pages( '%PDF-1.4 /Type /Pages /Count 9 %%EOF' ), '/Count used when no page objects are visible' );

test( 'the page tree node itself is not counted as a page' );
is_same( 2, CEAFSN_RF_Validator::count_pages( "%PDF-1.4\n/Type /Page\n/Type /Page\n/Type /Pages\n%%EOF" ), '/Type /Pages is excluded' );

test( 'attachment validation reads the real file' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );
$result = CEAFSN_RF_Validator::validate_attachment( 11 );
ok( $result['valid'], 'a real attachment validates' );
is_same( 3, $result['pages'], 'page count read from disk' );

test( 'no attachment ID is rejected with a clear message' );
CEAFSN_RF_Test_State::reset();
$result = CEAFSN_RF_Validator::validate_attachment( 0 );
ok( ! $result['valid'], 'zero attachment ID is invalid' );
has_substring( 'No call PDF is attached', implode( ' ', $result['errors'] ), 'error names the missing attachment' );

test( 'a deleted attachment is rejected' );
CEAFSN_RF_Test_State::reset();
attach( 12, $fixture_dir . '/does-not-exist.pdf' );
$result = CEAFSN_RF_Validator::validate_attachment( 12 );
ok( ! $result['valid'], 'attachment with no file on disk is invalid' );
has_substring( 'missing from the media library', implode( ' ', $result['errors'] ), 'error names the missing file' );

test( 'a non-PDF attachment is rejected' );
CEAFSN_RF_Test_State::reset();
attach( 13, $not_a_pdf, 'text/plain' );
$result = CEAFSN_RF_Validator::validate_attachment( 13 );
ok( ! $result['valid'], 'a text file is not accepted' );
has_substring( 'not a PDF', implode( ' ', $result['errors'] ), 'error names the type problem' );

test( 'a zero byte attachment is rejected' );
CEAFSN_RF_Test_State::reset();
attach( 14, $empty_pdf );
ok( ! CEAFSN_RF_Validator::validate_attachment( 14 )['valid'], 'a zero byte file is rejected' );

test( 'application URLs must be http or https and well formed' );
ok( CEAFSN_RF_Validator::is_valid_application_url( 'https://example.test/apply' ), 'a normal https URL is valid' );
ok( CEAFSN_RF_Validator::is_valid_application_url( 'http://example.test/apply' ), 'plain http is accepted too' );
ok( ! CEAFSN_RF_Validator::is_valid_application_url( '' ), 'an empty URL is invalid' );
ok( ! CEAFSN_RF_Validator::is_valid_application_url( 'javascript:alert(1)' ), 'a javascript: URL is rejected outright' );
ok( ! CEAFSN_RF_Validator::is_valid_application_url( 'ftp://example.test/apply' ), 'a non-http(s) scheme is rejected' );
ok( ! CEAFSN_RF_Validator::is_valid_application_url( 'example.test/apply' ), 'a URL with no scheme is rejected' );

// -----------------------------------------------------------------------------
section( 'Publish gating' );

CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );

test( 'a clean record with a closing date has no publish blockers' );
is_same( array(), CEAFSN_RF_Validator::publish_blockers( fellowship_row_data() ), 'no blockers for a well-formed record' );

test( 'a missing title is reported' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'title' => '' ) ) );
has_substring( 'A title is required.', implode( ' ', $errors ), 'title is required' );

test( 'missing eligibility is reported' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'eligibility' => '' ) ) );
has_substring( 'Eligibility criteria are required', implode( ' ', $errors ), 'eligibility is required' );

test( 'a malformed date is reported by field name' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'closing_date' => '01/06/2099' ) ) );
has_substring( 'Closing date is not a valid date.', implode( ' ', $errors ), 'the closing date field is named' );

test( 'a closing date before the opening date is rejected' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'opening_date' => '2025-06-01', 'closing_date' => '2025-01-01' ) ) );
has_substring( 'closing date is before the opening date', implode( ' ', $errors ), 'the window cannot run backwards' );

test( 'an opportunity with no closing date cannot be published as open' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'opening_date' => '2020-01-01', 'closing_date' => '' ) ) );
has_substring( 'cannot be confirmed open', implode( ' ', $errors ), 'unconfirmed status blocks publishing' );

test( 'a manual Open status with no dates and no override is rejected' );
$errors = CEAFSN_RF_Validator::publish_blockers(
	fellowship_row_data( array( 'opening_date' => '', 'closing_date' => '', 'status_manual' => 'open', 'status_override' => 0 ) )
);
has_substring( 'needs a closing date or a documented override', implode( ' ', $errors ), 'manual Open needs evidence' );

test( 'an override with no note is rejected' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'status_override' => 1, 'override_note' => '' ) ) );
has_substring( 'needs a note', implode( ' ', $errors ), 'override without a note is an incomplete audit trail' );

test( 'an override with a note is accepted even without dates' );
$errors = CEAFSN_RF_Validator::publish_blockers(
	fellowship_row_data( array( 'opening_date' => '', 'closing_date' => '', 'status_override' => 1, 'override_note' => 'Deadline extended by the funder; confirmed by email 2025-06-01.' ) )
);
is_same( array(), $errors, 'a documented override is sufficient' );

test( 'an invalid application URL is reported' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'application_url' => 'javascript:alert(1)' ) ) );
has_substring( 'full http or https address', implode( ' ', $errors ), 'the application link is validated' );

test( 'an invalid contact email is reported' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'contact_email' => 'not-an-email' ) ) );
has_substring( 'not valid', implode( ' ', $errors ), 'the contact email is validated' );

test( 'showing the contact email with no address is rejected' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'contact_email' => '', 'show_contact' => 1 ) ) );
has_substring( 'only after entering an address', implode( ' ', $errors ), 'approval without an address is refused' );

test( 'showing the stipend with no info is rejected' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'stipend_info' => '', 'show_stipend' => 1 ) ) );
has_substring( 'only after entering it', implode( ' ', $errors ), 'approval without stipend text is refused' );

test( 'an invalid call PDF blocks publishing when one is attached' );
CEAFSN_RF_Test_State::reset();
attach( 20, $not_a_pdf, 'text/plain' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'call_pdf_id' => 20 ) ) );
has_substring( 'not a PDF', implode( ' ', $errors ), 'a broken call document is reported' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );

test( 'no call PDF at all is not a publish blocker' );
is_same( array(), CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'call_pdf_id' => 0 ) ) ), 'the call PDF is optional' );

test( 'an invalid status_manual or status value is reported' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'status_manual' => 'bogus' ) ) );
has_substring( 'Invalid status value.', implode( ' ', $errors ), 'status_manual is validated' );
$errors = CEAFSN_RF_Validator::publish_blockers( fellowship_row_data( array( 'status' => 'bogus' ) ) );
has_substring( 'Invalid record state.', implode( ' ', $errors ), 'status is validated' );

// -----------------------------------------------------------------------------
section( 'Date normalisation' );

test( 'normalize_optional_date keeps a real date and rejects everything else' );
is_same( '2025-06-01', CEAFSN_RF_DB::normalize_optional_date( '2025-06-01' ), 'a real date is kept' );
is_same( null, CEAFSN_RF_DB::normalize_optional_date( '' ), 'an empty string becomes null' );
is_same( null, CEAFSN_RF_DB::normalize_optional_date( '01/06/2025' ), 'a non-ISO string becomes null' );
is_same( null, CEAFSN_RF_DB::normalize_optional_date( '2025-02-30' ), 'a calendar date that does not exist becomes null' );
is_same( null, CEAFSN_RF_DB::normalize_optional_date( '0000-00-00' ), 'the MySQL zero date becomes null' );

// -----------------------------------------------------------------------------
section( 'Data layer' );

test( 'the enum allow lists match the specification' );
is_same( array( 'draft', 'published', 'archived' ), CEAFSN_RF_DB::statuses(), 'three record states' );
is_same( array( 'title', 'closing_date', 'opening_date' ), CEAFSN_RF_DB::sortable_columns(), 'the sortable columns are fixed' );

test( 'create_tables emits a schema keyed off the status enums' );
CEAFSN_RF_Test_State::reset();
$GLOBALS['ceafsn_rf_dbdelta'] = array();
CEAFSN_RF_DB::create_tables();
$schema = implode( "\n", $GLOBALS['ceafsn_rf_dbdelta'] );
has_substring( 'wp_ceafsn_rf_fellowships', $schema, 'the table name carries the prefix' );
has_substring( "ENUM('draft','published','archived')", $schema, 'the record-state enum matches statuses()' );
has_substring( "ENUM('open','upcoming','closed','archived')", $schema, 'the manual-status enum matches manual_values()' );
is_same( '1.0.0', get_option( 'ceafsn_rf_db_version' ), 'the schema version option is recorded' );

test( 'an injection attempt in the sort column falls back to the default' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'orderby' => 'title; DROP TABLE x' ) );
has_substring( 'ORDER BY closing_date DESC', (string) $wpdb->queries[1], 'the sort column is replaced with the default' );
lacks_substring( 'DROP TABLE', (string) $wpdb->queries[1], 'the injected text never reaches the query' );

test( 'sort direction is a literal, never a parameter' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'order' => 'asc' ) );
has_substring( 'ORDER BY closing_date ASC', (string) $wpdb->queries[1], 'asc is honoured' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'order' => 'sideways' ) );
has_substring( 'ORDER BY closing_date DESC', (string) $wpdb->queries[1], 'anything unrecognised becomes desc' );

test( 'a sortable column is honoured' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'orderby' => 'title' ) );
has_substring( 'ORDER BY title', (string) $wpdb->queries[1], 'title is in the allow list' );

test( 'the front end only ever queries published records via published_only' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'published_only' => true ) );
has_substring( "status = 'published'", (string) $wpdb->queries[0], 'published_only restricts the count' );
has_substring( "status = 'published'", (string) $wpdb->queries[1], 'published_only restricts the rows' );

test( 'an unknown status filter adds no clause' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'status' => 'deleted' ) );
lacks_substring( "status = 'deleted'", (string) $wpdb->queries[0], 'a value outside the allow list is ignored' );

test( 'a track filter is bound as a parameter' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'track_domain' => 'Nutrition' ) );
has_substring( "track_domain = 'Nutrition'", (string) $wpdb->queries[1], 'the track filter reaches the query' );

test( 'a free-text search covers title, track, eligibility, and host' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'search' => 'food' ) );
has_substring( "title LIKE '%food%'", (string) $wpdb->queries[1], 'title is searched' );
has_substring( "eligibility LIKE '%food%'", (string) $wpdb->queries[1], 'eligibility is searched' );
has_substring( "host_supervisor LIKE '%food%'", (string) $wpdb->queries[1], 'host/supervisor is searched' );

test( 'pagination is applied with LIMIT and OFFSET' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::get_fellowships( array( 'per_page' => 5, 'page' => 3 ) );
has_substring( 'LIMIT 5 OFFSET 10', (string) $wpdb->queries[1], 'page 3 at 5 per page skips the first ten rows' );

test( 'get_tracks drops empty values and returns a plain string list' );
CEAFSN_RF_Test_State::reset();
$wpdb->col_queue = array( array( 'Nutrition', '', 'Health Systems' ) );
$tracks = CEAFSN_RF_DB::get_tracks();
is_same( array( 'Nutrition', 'Health Systems' ), $tracks, 'blank tracks are filtered out' );
has_substring( "status = 'published'", (string) $wpdb->queries[0], 'only published records contribute tracks' );

test( 'export_all returns every queried record' );
CEAFSN_RF_Test_State::reset();
$wpdb->results_queue = array( array( fellowship_row(), fellowship_row( array( 'fellowship_id' => 2 ) ) ) );
$exported = CEAFSN_RF_DB::export_all();
is_same( 2, count( $exported ), 'both records are returned' );
has_substring( 'LIMIT 9999', (string) $wpdb->queries[1], 'export requests a large page size rather than paging' );

test( 'an out-of-range enum is coerced on insert, not rejected' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::insert_fellowship( fellowship_row_data( array( 'status_manual' => 'not-a-real-value', 'status' => 'not-a-real-value' ) ) );
$inserted = json_decode( substr( (string) $wpdb->queries[0], strpos( (string) $wpdb->queries[0], '{' ) ), true );
is_same( 'upcoming', $inserted['status_manual'], 'an invalid manual value falls back to upcoming' );
is_same( 'draft', $inserted['status'], 'an invalid record state falls back to draft' );

test( 'checkbox-style fields are coerced to 0 or 1' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::insert_fellowship( fellowship_row_data( array( 'show_stipend' => 'yes', 'show_contact' => 0, 'status_override' => '1' ) ) );
$inserted = json_decode( substr( (string) $wpdb->queries[0], strpos( (string) $wpdb->queries[0], '{' ) ), true );
is_same( 1, $inserted['show_stipend'], 'a truthy value becomes 1' );
is_same( 0, $inserted['show_contact'], 'a falsy value becomes 0' );
is_same( 1, $inserted['status_override'], 'a string "1" becomes 1' );

test( 'a new record is inserted and an existing one updated' );
CEAFSN_RF_Test_State::reset();
CEAFSN_RF_DB::insert_fellowship( fellowship_row_data() );
has_substring( 'INSERT INTO', (string) $wpdb->queries[0], 'insert_fellowship issues an INSERT' );
$wpdb->reset_state();
CEAFSN_RF_DB::update_fellowship( 7, fellowship_row_data() );
has_substring( 'UPDATE', (string) $wpdb->queries[0], 'update_fellowship issues an UPDATE' );
has_substring( '"fellowship_id":7', (string) $wpdb->queries[0], 'the update is scoped to that record' );

// -----------------------------------------------------------------------------
section( 'Admin: field extraction, validation, persistence' );

$admin     = new CEAFSN_RF_Admin();
$admin_ref = new ReflectionClass( $admin );

/**
 * Invoke a private method through reflection.
 *
 * @param ReflectionClass $class  Class with the method.
 * @param string          $method Method name.
 * @param array           $data   First argument, when the method takes one.
 * @param int|null        $id     Second argument, when the method takes one.
 * @return mixed
 */
function call_admin( ReflectionClass $class, string $method, array $data = array(), ?int $id = null ) {
	$reflection = $class->getMethod( $method );
	$reflection->setAccessible( true );
	$instance = $class->newInstanceWithoutConstructor();

	if ( 'extract_fellowship_fields' === $method ) {
		return $reflection->invoke( $instance );
	}

	return null === $id
		? $reflection->invoke( $instance, $data )
		: $reflection->invoke( $instance, $data, $id );
}

test( 'a complete record validates' );
is_same( array(), call_admin( $admin_ref, 'validate_fellowship', fellowship_row_data() ), 'no errors for a complete record' );

test( 'missing required fields are each reported' );
$cases = array(
	'title'       => 'Title is required.',
	'eligibility' => 'Eligibility criteria are required.',
);
foreach ( $cases as $field => $message ) {
	$errors = call_admin( $admin_ref, 'validate_fellowship', array_merge( fellowship_row_data(), array( $field => '' ) ) );
	has_substring( $message, implode( ' ', $errors ), "empty {$field} is reported" );
}

test( 'an unknown status_manual or status is rejected' );
$errors = call_admin( $admin_ref, 'validate_fellowship', array_merge( fellowship_row_data(), array( 'status_manual' => 'bogus' ) ) );
has_substring( 'Invalid status value.', implode( ' ', $errors ), 'status_manual is validated' );
$errors = call_admin( $admin_ref, 'validate_fellowship', array_merge( fellowship_row_data(), array( 'status' => 'bogus' ) ) );
has_substring( 'Invalid record state.', implode( ' ', $errors ), 'status is validated' );

test( 'field extraction strips tags and coerces values' );
$_POST = array(
	'title'           => '<script>alert(1)</script>Regional <b>Fellowship</b>',
	'track_domain'    => 'Nutrition',
	'eligibility'     => 'Open to all early-career researchers.',
	'call_pdf_id'     => '11abc',
	'opening_date'    => '2025-01-01',
	'closing_date'    => '2025-12-01',
	'application_url' => 'https://example.test/apply',
	'contact_email'   => 'fellowships@example.test',
	'status_manual'   => 'upcoming',
	'status'          => 'draft',
);
$extracted = call_admin( $admin_ref, 'extract_fellowship_fields' );
lacks_substring( '<script>', $extracted['title'], 'script tags are stripped from the title' );
is_same( 11, $extracted['call_pdf_id'], 'a non-numeric attachment id coerces to an integer' );
$_POST = array();

test( 'an unchecked checkbox is stored as zero' );
$_POST = array(
	'title'         => 'X',
	'eligibility'   => 'Y',
	'status_manual' => 'upcoming',
	'status'        => 'draft',
);
$extracted = call_admin( $admin_ref, 'extract_fellowship_fields' );
is_same( 0, $extracted['show_stipend'], 'an unticked stipend box is 0' );
is_same( 0, $extracted['show_contact'], 'an unticked contact box is 0' );
is_same( 0, $extracted['status_override'], 'an unticked override box is 0' );
$_POST = array();

test( 'a new record is inserted and an existing one updated through persist_fellowship' );
CEAFSN_RF_Test_State::reset();
call_admin( $admin_ref, 'persist_fellowship', fellowship_row_data(), 0 );
has_substring( 'INSERT INTO', (string) $wpdb->queries[0], 'a record with no ID is inserted' );
$wpdb->reset_state();
call_admin( $admin_ref, 'persist_fellowship', fellowship_row_data(), 7 );
has_substring( 'UPDATE', (string) $wpdb->queries[0], 'a record with an ID is updated' );
has_substring( '"fellowship_id":7', (string) $wpdb->queries[0], 'the update is scoped to that record' );

// -----------------------------------------------------------------------------
section( 'Activator: scoped upload restriction' );

test( 'is_plugin_screen only matches this plugin\'s admin page' );
CEAFSN_RF_Test_State::reset();
update_option( '__is_admin', 1 );
$_GET['page'] = 'ceafsn-rf';
ok( CEAFSN_RF_Activator::is_plugin_screen(), 'this plugin\'s page is recognised' );
$_GET['page'] = 'some-other-plugin';
ok( ! CEAFSN_RF_Activator::is_plugin_screen(), 'a different admin page is not' );
unset( $_GET['page'] );

test( 'the upload filter only restricts uploads on this plugin\'s screen' );
CEAFSN_RF_Test_State::reset();
update_option( '__is_admin', 1 );
$_GET['page'] = 'ceafsn-rf';
$restricted   = CEAFSN_RF_Activator::restrict_upload_mimes( array( 'pdf' => 'application/pdf', 'png' => 'image/png' ) );
is_same( array( 'pdf' => 'application/pdf' ), $restricted, 'only PDF is allowed on the plugin screen' );
$_GET['page'] = 'elsewhere';
$unrestricted = CEAFSN_RF_Activator::restrict_upload_mimes( array( 'pdf' => 'application/pdf', 'png' => 'image/png' ) );
is_same( array( 'pdf' => 'application/pdf', 'png' => 'image/png' ), $unrestricted, 'other screens keep every upload type' );
unset( $_GET['page'] );

// -----------------------------------------------------------------------------
section( 'Public: card model and rendering helpers' );

test( 'build_card reflects Open status with a working Apply link' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );
$row  = fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-12-01' ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
is_same( 'open', $card['status'], 'status is open within the window' );
ok( $card['can_apply'], 'a valid URL while open means the visitor can apply' );
ok( $card['has_pdf'], 'the attached call document resolves to a URL' );

test( 'build_card shows Open with no way to apply when there is no URL' );
$row  = fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-12-01', 'application_url' => '' ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
ok( $card['is_open'], 'still open by date' );
ok( ! $card['can_apply'], 'nothing to link to yet' );
ok( ! $card['has_app_url'], 'the missing URL is reported' );

test( 'build_card shows Closed with an explanatory reason' );
$row  = fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-02-01' ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
is_same( 'closed', $card['status'], 'the closing date has passed' );
ok( ! $card['can_apply'], 'a closed opportunity cannot be applied to' );
has_substring( 'closing date has passed', $card['status_reason'], 'the reason explains why' );

test( 'build_card shows Unconfirmed rather than a false Open' );
$row  = fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '' ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
is_same( 'unconfirmed', $card['status'], 'no closing date means unconfirmed, never open' );
ok( ! $card['is_open'], 'unconfirmed does not accept applications' );

test( 'build_card honours an admin override' );
$row  = fellowship_row( array( 'status_override' => 1, 'opening_date' => '', 'closing_date' => '2020-01-01' ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
is_same( 'open', $card['status'], 'the override forces Open' );
ok( $card['is_override'], 'the override flag is exposed to the template' );

test( 'build_card hides contact and stipend unless approved' );
$row  = fellowship_row( array( 'show_contact' => 0, 'show_stipend' => 0 ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
is_same( '', $card['contact_email'], 'contact is hidden without approval' );
is_same( '', $card['stipend'], 'stipend is hidden without approval' );

test( 'build_card reports a missing call document honestly' );
CEAFSN_RF_Test_State::reset();
$row  = fellowship_row( array( 'call_pdf_id' => 999 ) );
$card = CEAFSN_RF_Public::build_card( $row, '2025-06-01' );
ok( ! $card['has_pdf'], 'a document that resolves to no URL is not offered' );
has_substring( 'currently unavailable', $card['pdf_label'], 'the label explains the document is gone' );

test( 'render_apply prints an active link only when applications can be sent' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );
$open_card = CEAFSN_RF_Public::build_card( fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-12-01' ) ), '2025-06-01' );
ob_start();
CEAFSN_RF_Public::render_apply( $open_card );
$html = (string) ob_get_clean();
has_substring( 'ceafsn-rf-apply--active', $html, 'the active class is used' );
has_substring( 'Apply now', $html, 'the call to action reads Apply now' );

test( 'render_apply explains an open opportunity with no application URL' );
$card_no_url = CEAFSN_RF_Public::build_card( fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-12-01', 'application_url' => '' ) ), '2025-06-01' );
ob_start();
CEAFSN_RF_Public::render_apply( $card_no_url );
$html = (string) ob_get_clean();
has_substring( 'ceafsn-rf-apply--inactive', $html, 'the inactive class is used' );
has_substring( 'published shortly', $html, 'the missing-URL reason is shown' );

test( 'render_apply explains a closed opportunity' );
$closed_card = CEAFSN_RF_Public::build_card( fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2025-02-01' ) ), '2025-06-01' );
ob_start();
CEAFSN_RF_Public::render_apply( $closed_card );
$html = (string) ob_get_clean();
has_substring( 'Applications closed', $html, 'the closed label is used' );
has_substring( 'closing date has passed', $html, 'the reason is shown to the visitor' );

test( 'sort_link reports the current direction and the next one' );
$link = CEAFSN_RF_Public::sort_link( 'https://example.test/research-fellowships/', 'closing_date', 'closing_date', 'asc' );
is_same( 'ascending', $link['aria_sort'], 'the active ascending column is announced' );
is_same( 'desc', $link['next'], 'clicking again would reverse it' );
$link = CEAFSN_RF_Public::sort_link( 'https://example.test/research-fellowships/', 'closing_date', 'closing_date', 'desc' );
is_same( 'descending', $link['aria_sort'], 'the active descending column is announced' );
is_same( 'asc', $link['next'], 'clicking again would reverse it' );
$link = CEAFSN_RF_Public::sort_link( 'https://example.test/research-fellowships/', 'title', 'closing_date', 'desc' );
is_same( 'none', $link['aria_sort'], 'an inactive column announces nothing' );
is_same( 'asc', $link['next'], 'an inactive column starts ascending' );

test( 'clamp_per_page keeps requests within a safe range' );
is_same( 12, CEAFSN_RF_Public::clamp_per_page( 0 ), 'zero falls back to the default' );
is_same( 12, CEAFSN_RF_Public::clamp_per_page( -5 ), 'a negative value falls back to the default' );
is_same( 20, CEAFSN_RF_Public::clamp_per_page( 20 ), 'a normal value is kept' );
is_same( 48, CEAFSN_RF_Public::clamp_per_page( 500 ), 'an excessive value is capped' );

test( 'normalize_view only recognises table, everything else is cards' );
is_same( 'table', CEAFSN_RF_Public::normalize_view( 'table' ), 'table is recognised' );
is_same( 'cards', CEAFSN_RF_Public::normalize_view( 'grid' ), 'an unknown value falls back to cards' );
is_same( 'cards', CEAFSN_RF_Public::normalize_view( '' ), 'empty falls back to cards' );

test( 'document_url resolves a real attachment and nothing else' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );
ok( '' !== CEAFSN_RF_Public::document_url( 11 ), 'a real attachment resolves to a URL' );
is_same( '', CEAFSN_RF_Public::document_url( 0 ), 'no attachment id resolves to nothing' );
is_same( '', CEAFSN_RF_Public::document_url( 404 ), 'a missing attachment resolves to nothing' );

test( 'format_date renders a human date and can name the timezone' );
CEAFSN_RF_Test_State::reset();
is_same( '', CEAFSN_RF_Public::format_date( '' ), 'no date renders nothing' );
$plain = CEAFSN_RF_Public::format_date( '2025-03-14', false );
has_substring( '2025', $plain, 'the year appears in the formatted date' );
lacks_substring( 'time)', $plain, 'the timezone is omitted when not requested' );
$with_tz = CEAFSN_RF_Public::format_date( '2025-03-14', true );
has_substring( 'time)', $with_tz, 'the timezone is named by default' );

test( 'show_closed_by_default reads the option with a true default' );
CEAFSN_RF_Test_State::reset();
ok( CEAFSN_RF_Public::show_closed_by_default(), 'closed opportunities stay listed until an admin turns this off' );
update_option( CEAFSN_RF_Activator::SHOW_CLOSED_OPTION, false );
ok( ! CEAFSN_RF_Public::show_closed_by_default(), 'the option is honoured once set' );

// -----------------------------------------------------------------------------
section( 'Public: shortcode end to end' );

test( 'the shortcode shows the empty state when nothing is published' );
CEAFSN_RF_Test_State::reset();
$wpdb->var_result     = 0;
$wpdb->results_queue  = array( array() );
$wpdb->col_queue      = array( array() );
$public = new CEAFSN_RF_Public();
$html   = $public->render_shortcode( array() );
has_substring( 'no opportunities to show', $html, 'the empty state message is shown' );

test( 'the shortcode renders a published, open opportunity as a card' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 1;
$wpdb->results_queue = array( array( fellowship_row( array( 'opening_date' => '2025-01-01', 'closing_date' => '2099-01-01' ) ) ) );
$wpdb->col_queue     = array( array( 'Nutrition' ) );
$public = new CEAFSN_RF_Public();
$html   = $public->render_shortcode( array() );
has_substring( 'Regional Nutrition Research Fellowship', $html, 'the title is rendered' );
has_substring( 'ceafsn-rf-badge--status-open', $html, 'the open badge class is applied' );
has_substring( 'Apply now', $html, 'the active apply link is rendered' );

test( 'the table view renders the same rows with sortable headers' );
CEAFSN_RF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 1;
$wpdb->results_queue = array( array( fellowship_row() ) );
$wpdb->col_queue     = array( array( 'Nutrition' ) );
$public = new CEAFSN_RF_Public();
$html   = $public->render_shortcode( array( 'view' => 'table' ) );
has_substring( '<table class="ceafsn-rf-table">', $html, 'the table layout is used' );
has_substring( 'aria-sort=', $html, 'sortable headers announce their state' );

// -----------------------------------------------------------------------------
section( 'Uninstall' );

test( 'uninstall.php returns immediately with no uninstall context' );
$report = ceafsn_rf_test_run_uninstall_case( 'no-context' );
is_same( array(), $report['queries'], 'nothing is touched outside the WordPress uninstall context' );

test( 'uninstall.php keeps records when the opt-in is off' );
$report = ceafsn_rf_test_run_uninstall_case( 'flag-off' );
is_same( array(), $report['queries'], 'no DROP TABLE runs without the opt-in' );
ok( ! array_key_exists( 'ceafsn_rf_uninstall_delete_data', $report['options'] ), 'the opt-in option itself is cleared either way' );
ok( ! array_key_exists( 'ceafsn_rf_show_closed', $report['options'] ), 'plugin options carrying no content are cleared' );

test( 'uninstall.php drops the table only when the opt-in is on' );
$report = ceafsn_rf_test_run_uninstall_case( 'flag-on' );
ok( ! empty( $report['queries'] ), 'a query ran' );
has_substring( 'DROP TABLE', implode( ' ', $report['queries'] ), 'the table is dropped when the admin opted in' );
has_substring( 'wp_ceafsn_rf_fellowships', implode( ' ', $report['queries'] ), 'the correct table is targeted' );
ok( ! array_key_exists( 'ceafsn_rf_db_version', $report['options'] ), 'the schema version option is cleared too' );

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------

$pass = $GLOBALS['ceafsn_rf_test_pass'];
$fail = $GLOBALS['ceafsn_rf_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
printf( "%d assertions, %d passed, %d failed\n", $pass + $fail, $pass, $fail );

exit( $fail > 0 ? 1 : 0 );
