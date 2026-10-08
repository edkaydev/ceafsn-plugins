<?php
/**
 * Test runner for CE-AFSN Grants & Funding.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-grants-funding/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_GF
 */

declare( strict_types=1 );

// This file is a CLI test harness: it must never execute over HTTP. Release
// ZIPs exclude tests/, and this guard makes the file inert if it is ever
// deployed by mistake.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( 1 );
}

// WordPress is not installed here, so point ABSPATH at a temporary directory
// that contains just enough of wp-admin for the activator to load.
$fake_wp_admin = sys_get_temp_dir() . '/ceafsn-gf-fake-wp/wp-admin/includes';

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
	. "\t\t\t\$GLOBALS['ceafsn_gf_dbdelta'][] = (string) \$query;\n"
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
final class FakeGfWpdb {

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
		$this->insert_id     = 1;
		$this->queries       = array();
		$this->results_queue = array();
		$this->var_queue     = array();
		$this->var_result    = 0;
		$this->row_result    = null;
		$this->col_queue     = array();
	}
}

// -----------------------------------------------------------------------------
// Assertions
// -----------------------------------------------------------------------------

$GLOBALS['ceafsn_gf_test_pass']    = 0;
$GLOBALS['ceafsn_gf_test_fail']    = 0;
$GLOBALS['ceafsn_gf_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_gf_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ok( bool $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_gf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_gf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_gf_test_current']}] {$message}\n";
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
		++$GLOBALS['ceafsn_gf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_gf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_gf_test_current']}] {$message}\n"
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
		++$GLOBALS['ceafsn_gf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_gf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_gf_test_current']}] {$message}\n       missing: {$needle}\n       in: {$haystack}\n";
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
		++$GLOBALS['ceafsn_gf_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_gf_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_gf_test_current']}] {$message}\n       found: {$needle}\n";
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
$fixture_dir = sys_get_temp_dir() . '/ceafsn-gf-fixtures';

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
	CEAFSN_GF_Test_State::add_attachment( $id, $path, $mime );
}

/**
 * Build a grant row.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return object Grant row.
 */
function grant_row( array $overrides = array() ): object {
	return (object) array_merge(
		array(
			'grant_id'             => 1,
			'title'                => 'Early-Career Nutrition Research Grant',
			'award_range'          => 'USD 5,000-10,000',
			'deadline'             => '2099-06-01 14:00:00',
			'deadline_timezone'    => 'UTC',
			'target_beneficiaries' => 'Early-career researchers in food security.',
			'eligibility'          => 'Open to researchers affiliated with a CE-AFSN member institution.',
			'call_pdf_id'          => 11,
			'application_url'      => 'https://example.test/apply',
			'funding_institution'  => 'CE-AFSN Trust',
			'contact'              => 'grants@example.test',
			'show_contact'         => 1,
			'duplicate_note'       => '',
			'duplicate_ok'         => 0,
			'grant_status'         => 'open',
			'status'               => 'published',
			'created_at'           => '2025-01-01 00:00:00',
			'updated_at'           => '2025-01-01 00:00:00',
			'updated_by'           => 1,
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
function grant_row_data( array $overrides = array() ): array {
	return (array) grant_row( $overrides );
}

/**
 * Clear recorded state and register the plugin's hooks again.
 *
 * @param bool $as_admin Whether to simulate an admin request.
 * @return void
 */
function ceafsn_gf_test_reload( bool $as_admin = false ): void {
	CEAFSN_GF_Test_State::reset();

	if ( $as_admin ) {
		update_option( '__is_admin', 1 );
	}

	ceafsn_gf_init();
}

/**
 * Run a helper script in its own process and decode its JSON report.
 *
 * @param string $script Script file name inside tests/.
 * @param array  $args   Command line arguments.
 * @return array<string,mixed> Decoded report, or an empty array on failure.
 */
function ceafsn_gf_test_run_case( string $script, array $args ): array {
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
function ceafsn_gf_test_run_uninstall_case( string $case ): array {
	$report = ceafsn_gf_test_run_case( 'uninstall-cases.php', array( $case ) );

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

$plugin_source = (string) file_get_contents( $plugin_dir . '/ceafsn-grants-funding.php' );
require_once $plugin_dir . '/ceafsn-grants-funding.php';

global $wpdb;
$wpdb = new FakeGfWpdb();

section( 'Plugin bootstrap' );

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', $plugin_source, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_GF_VERSION' ), 'CEAFSN_GF_VERSION defined' );
ok( defined( 'CEAFSN_GF_PLUGIN_DIR' ), 'CEAFSN_GF_PLUGIN_DIR defined' );
is_same( '1.0.0', CEAFSN_GF_VERSION, 'version constant matches header' );

test( 'all plugin classes loaded' );
foreach ( array( 'CEAFSN_GF_DB', 'CEAFSN_GF_Validator', 'CEAFSN_GF_Activator', 'CEAFSN_GF_Admin', 'CEAFSN_GF_Public' ) as $class ) {
	ok( class_exists( $class ), "class {$class} exists" );
}

test( 'the shortcode is registered on plugins_loaded' );
ok( isset( CEAFSN_GF_Test_State::$actions['plugins_loaded'] ), 'bootstrap registers a plugins_loaded callback' );
ceafsn_gf_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_GF_Test_State::$shortcodes['ceafsn_grants'] ), '[ceafsn_grants] registered' );

test( 'translations are loaded on init' );
ok( isset( CEAFSN_GF_Test_State::$actions['init'] ), 'init hook registered' );
ceafsn_gf_test_fire_action( 'init' );
ok( in_array( 'ceafsn-gf', CEAFSN_GF_Test_State::$textdomains, true ), 'ceafsn-gf text domain loaded' );

// -----------------------------------------------------------------------------
section( 'Deadline: local-to-UTC conversion' );

test( 'a local deadline converts to UTC using the given timezone' );
is_same( '2025-06-01 14:00:00', CEAFSN_GF_DB::normalize_deadline_to_utc( '2025-06-01T17:00', 'Africa/Nairobi' ), 'Nairobi is UTC+3, so 17:00 local is 14:00 UTC' );
is_same( '2025-06-01 17:00:00', CEAFSN_GF_DB::normalize_deadline_to_utc( '2025-06-01T17:00', 'UTC' ), 'UTC input passes through unchanged' );

test( 'seconds are accepted but optional' );
is_same( '2025-06-01 17:00:30', CEAFSN_GF_DB::normalize_deadline_to_utc( '2025-06-01T17:00:30', 'UTC' ), 'a value with seconds is accepted' );

test( 'an empty or malformed deadline becomes null, never a guess' );
is_same( null, CEAFSN_GF_DB::normalize_deadline_to_utc( '', 'UTC' ), 'an empty value is null' );
is_same( null, CEAFSN_GF_DB::normalize_deadline_to_utc( 'not a date', 'UTC' ), 'garbage input is null' );
is_same( null, CEAFSN_GF_DB::normalize_deadline_to_utc( '2025-02-30T10:00', 'UTC' ), 'a calendar date that does not exist is null, not silently rolled forward' );

test( 'an unparseable timezone falls back to UTC rather than failing silently' );
is_same( '2025-06-01 17:00:00', CEAFSN_GF_DB::normalize_deadline_to_utc( '2025-06-01T17:00', 'Not/ARealZone' ), 'an invalid timezone name is treated as UTC' );

test( 'the round trip back to a local input value matches what was entered' );
$utc = CEAFSN_GF_DB::normalize_deadline_to_utc( '2025-06-01T17:00', 'Africa/Nairobi' );
CEAFSN_GF_Test_State::$options['timezone_string'] = 'Africa/Nairobi';
is_same( '2025-06-01T17:00', CEAFSN_GF_DB::utc_to_local_input( (string) $utc ), 'converting back to the site timezone recovers the original wall-clock time' );
unset( CEAFSN_GF_Test_State::$options['timezone_string'] );
is_same( '', CEAFSN_GF_DB::utc_to_local_input( '' ), 'an empty stored value round-trips to an empty field' );

// -----------------------------------------------------------------------------
section( 'Document validation' );

test( 'a well-formed PDF passes' );
$result = CEAFSN_GF_Validator::inspect_bytes( make_pdf( 3 ) );
ok( $result['valid'], 'valid PDF reported as valid' );
is_same( 3, $result['pages'], 'page count read correctly' );

test( 'an empty file is rejected' );
$result = CEAFSN_GF_Validator::inspect_bytes( '' );
ok( ! $result['valid'], 'empty file is not valid' );

test( 'a non-PDF file is rejected' );
$result = CEAFSN_GF_Validator::inspect_bytes( 'just some text, no PDF here' );
ok( ! $result['valid'], 'plain text is not a valid PDF' );
has_substring( 'is not a PDF', implode( ' ', $result['errors'] ), 'the signature error is reported' );

test( 'a truncated PDF is rejected' );
$result = CEAFSN_GF_Validator::inspect_bytes( (string) file_get_contents( $truncated_pdf ) );
ok( ! $result['valid'], 'truncated PDF is not valid' );

test( 'page count falls back to the page tree /Count' );
is_same( 7, CEAFSN_GF_Validator::count_pages( '%PDF-1.4 /Type /Pages /Count 7 %%EOF' ), '/Count used when no page objects are visible' );

test( 'attachment validation reads the real file' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$result = CEAFSN_GF_Validator::validate_attachment( 11 );
ok( $result['valid'], 'a real attachment validates' );
is_same( 3, $result['pages'], 'page count read from disk' );

test( 'no attachment ID is rejected with a clear message' );
CEAFSN_GF_Test_State::reset();
$result = CEAFSN_GF_Validator::validate_attachment( 0 );
ok( ! $result['valid'], 'zero attachment ID is invalid' );
has_substring( 'No Official Call PDF is attached', implode( ' ', $result['errors'] ), 'error names the missing attachment' );

test( 'a deleted attachment is rejected' );
CEAFSN_GF_Test_State::reset();
attach( 12, $fixture_dir . '/does-not-exist.pdf' );
ok( ! CEAFSN_GF_Validator::validate_attachment( 12 )['valid'], 'attachment with no file on disk is invalid' );

test( 'a non-PDF attachment is rejected' );
CEAFSN_GF_Test_State::reset();
attach( 13, $not_a_pdf, 'text/plain' );
$result = CEAFSN_GF_Validator::validate_attachment( 13 );
ok( ! $result['valid'], 'a text file is not accepted' );
has_substring( 'not a PDF', implode( ' ', $result['errors'] ), 'error names the type problem' );

test( 'a zero byte attachment is rejected' );
CEAFSN_GF_Test_State::reset();
attach( 14, $empty_pdf );
ok( ! CEAFSN_GF_Validator::validate_attachment( 14 )['valid'], 'a zero byte file is rejected' );

test( 'application URLs must be http or https and well formed' );
ok( CEAFSN_GF_Validator::is_valid_application_url( 'https://example.test/apply' ), 'a normal https URL is valid' );
ok( ! CEAFSN_GF_Validator::is_valid_application_url( '' ), 'an empty URL is invalid' );
ok( ! CEAFSN_GF_Validator::is_valid_application_url( 'javascript:alert(1)' ), 'a javascript: URL is rejected outright' );
ok( ! CEAFSN_GF_Validator::is_valid_application_url( 'ftp://example.test/apply' ), 'a non-http(s) scheme is rejected' );

// -----------------------------------------------------------------------------
section( 'Publish gating' );

CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );

test( 'a clean, complete record has no publish blockers' );
is_same( array(), CEAFSN_GF_Validator::publish_blockers( grant_row_data() ), 'no blockers for a well-formed record' );

test( 'each required text field is reported when missing' );
$cases = array(
	'title'               => 'A title is required.',
	'award_range'         => 'An award range is required.',
	'funding_institution' => 'The funding institution is required.',
	'eligibility'         => 'Eligibility criteria are required',
);
foreach ( $cases as $field => $message ) {
	$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( $field => '' ) ) );
	has_substring( $message, implode( ' ', $errors ), "empty {$field} is reported" );
}

test( 'a missing deadline blocks publishing; nothing is invented' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'deadline' => null ) ) );
has_substring( 'A deadline is required', implode( ' ', $errors ), 'a null deadline is reported, not defaulted' );

test( 'the call PDF is required, unlike the fellowships plugin' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'call_pdf_id' => 0 ) ) );
has_substring( 'No Official Call PDF is attached', implode( ' ', $errors ), 'a missing call PDF blocks publishing outright' );

test( 'an invalid call PDF blocks publishing' );
CEAFSN_GF_Test_State::reset();
attach( 20, $not_a_pdf, 'text/plain' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'call_pdf_id' => 20 ) ) );
has_substring( 'not a PDF', implode( ' ', $errors ), 'a broken call document is reported' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );

test( 'a shared call PDF blocks publishing until it is confirmed' );
$GLOBALS['wpdb']->var_result = 2;
$errors                      = CEAFSN_GF_Validator::publish_blockers( grant_row_data() );
has_substring( 'already attached to 2 other records', implode( ' ', $errors ), 'the sharing problem is reported with the count' );

test( 'a confirmed shared document still needs a note' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'duplicate_ok' => 1 ) ) );
has_substring( 'A note is required', implode( ' ', $errors ), 'confirming without a note is not enough' );

test( 'a confirmed shared document with a note is allowed' );
is_same(
	array(),
	CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'duplicate_ok' => 1, 'duplicate_note' => 'Joint call between two partner grants.' ) ) ),
	'shared plus note passes'
);
$GLOBALS['wpdb']->var_result = 0;

test( 'a record does not count itself as a duplicate' );
is_same( array(), CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'grant_id' => 1 ) ) ), 'the excluded record is left out of the count' );

test( 'an invalid application URL is reported' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'application_url' => 'javascript:alert(1)' ) ) );
has_substring( 'full http or https address', implode( ' ', $errors ), 'the application link is validated' );

test( 'showing the contact with nothing entered is rejected' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'contact' => '', 'show_contact' => 1 ) ) );
has_substring( 'only after entering one', implode( ' ', $errors ), 'approval without a contact value is refused' );

test( 'an invalid grant_status or status value is reported' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'grant_status' => 'bogus' ) ) );
has_substring( 'Invalid status value.', implode( ' ', $errors ), 'grant_status is validated' );
$errors = CEAFSN_GF_Validator::publish_blockers( grant_row_data( array( 'status' => 'bogus' ) ) );
has_substring( 'Invalid record state.', implode( ' ', $errors ), 'status is validated' );

// -----------------------------------------------------------------------------
section( 'Data layer' );

test( 'the enum allow lists match the specification' );
is_same( array( 'draft', 'published', 'archived' ), CEAFSN_GF_DB::statuses(), 'three record states' );
is_same( array( 'open', 'closed', 'upcoming', 'archived' ), CEAFSN_GF_DB::grant_statuses(), 'four grant statuses' );
is_same( array( 'title', 'deadline', 'funding_institution' ), CEAFSN_GF_DB::sortable_columns(), 'the sortable columns are fixed' );

test( 'create_tables emits a schema keyed off both enums' );
CEAFSN_GF_Test_State::reset();
$GLOBALS['ceafsn_gf_dbdelta'] = array();
CEAFSN_GF_DB::create_tables();
$schema = implode( "\n", $GLOBALS['ceafsn_gf_dbdelta'] );
has_substring( 'wp_ceafsn_gf_grants', $schema, 'the table name carries the prefix' );
has_substring( "ENUM('draft','published','archived')", $schema, 'the record-state enum matches statuses()' );
has_substring( "ENUM('open','closed','upcoming','archived')", $schema, 'the grant-status enum matches grant_statuses()' );
is_same( CEAFSN_GF_DB::SCHEMA_VERSION, get_option( 'ceafsn_gf_db_version' ), 'the schema version option is recorded' );

test( 'an injection attempt in the sort column falls back to the default' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'orderby' => 'title; DROP TABLE x' ) );
has_substring( 'ORDER BY deadline', (string) $wpdb->queries[1], 'the sort column is replaced with the default' );
lacks_substring( 'DROP TABLE', (string) $wpdb->queries[1], 'the injected text never reaches the query' );

test( 'sort direction defaults to ascending (soonest deadline first)' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array() );
has_substring( 'ORDER BY deadline ASC', (string) $wpdb->queries[1], 'the nearest deadline sorts first by default' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'order' => 'desc' ) );
has_substring( 'ORDER BY deadline DESC', (string) $wpdb->queries[1], 'desc is honoured explicitly' );

test( 'the front end only ever queries published records via published_only' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'published_only' => true ) );
has_substring( "status = 'published'", (string) $wpdb->queries[0], 'published_only restricts the count' );
has_substring( "status = 'published'", (string) $wpdb->queries[1], 'published_only restricts the rows' );

test( 'a grant_status filter is applied when valid, ignored when not' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'grant_status' => 'open' ) );
has_substring( "grant_status = 'open'", (string) $wpdb->queries[1], 'a valid grant_status filters the query' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'grant_status' => 'bogus' ) );
lacks_substring( 'grant_status =', (string) $wpdb->queries[1], 'an invalid value adds no clause' );

test( 'a funding institution filter is bound as a parameter' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'funding_institution' => 'CE-AFSN Trust' ) );
has_substring( "funding_institution = 'CE-AFSN Trust'", (string) $wpdb->queries[1], 'the institution filter reaches the query' );

test( 'a free-text search covers title, eligibility, institution, and beneficiaries' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'search' => 'nutrition' ) );
has_substring( "title LIKE '%nutrition%'", (string) $wpdb->queries[1], 'title is searched' );
has_substring( "eligibility LIKE '%nutrition%'", (string) $wpdb->queries[1], 'eligibility is searched' );
has_substring( "funding_institution LIKE '%nutrition%'", (string) $wpdb->queries[1], 'institution is searched' );
has_substring( "target_beneficiaries LIKE '%nutrition%'", (string) $wpdb->queries[1], 'beneficiaries is searched' );

test( 'pagination is applied with LIMIT and OFFSET' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::get_grants( array( 'per_page' => 5, 'page' => 3 ) );
has_substring( 'LIMIT 5 OFFSET 10', (string) $wpdb->queries[1], 'page 3 at 5 per page skips the first ten rows' );

test( 'get_institutions drops empty values' );
CEAFSN_GF_Test_State::reset();
$wpdb->col_queue = array( array( 'CE-AFSN Trust', '', 'Regional Fund' ) );
$institutions = CEAFSN_GF_DB::get_institutions();
is_same( array( 'CE-AFSN Trust', 'Regional Fund' ), $institutions, 'blank institutions are filtered out' );

test( 'attachment_usage_count and get_attached_others exclude the record itself' );
CEAFSN_GF_Test_State::reset();
$wpdb->var_result = 3;
is_same( 3, CEAFSN_GF_DB::attachment_usage_count( 11, 1 ), 'the count is read from the query' );
has_substring( 'grant_id <> 1', (string) $wpdb->queries[0], 'the record itself is excluded from the count' );
CEAFSN_GF_Test_State::reset();
$wpdb->results_queue = array( array( (object) array( 'grant_id' => 2, 'title' => 'Other Grant' ) ) );
$others = CEAFSN_GF_DB::get_attached_others( 11, 1 );
is_same( 1, count( $others ), 'the other sharing record is returned' );

test( 'export_all requests a large page size rather than paging' );
CEAFSN_GF_Test_State::reset();
$wpdb->results_queue = array( array( grant_row(), grant_row( array( 'grant_id' => 2 ) ) ) );
$exported = CEAFSN_GF_DB::export_all();
is_same( 2, count( $exported ), 'both records are returned' );
has_substring( 'LIMIT 9999', (string) $wpdb->queries[1], 'export requests a large page size' );

test( 'an out-of-range enum is coerced on insert, not rejected' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::insert_grant( grant_row_data( array( 'grant_status' => 'not-real', 'status' => 'not-real' ) ) );
$inserted = json_decode( substr( (string) $wpdb->queries[0], strpos( (string) $wpdb->queries[0], '{' ) ), true );
is_same( 'upcoming', $inserted['grant_status'], 'an invalid grant_status falls back to upcoming' );
is_same( 'draft', $inserted['status'], 'an invalid record state falls back to draft' );

test( 'a new record is inserted and an existing one updated' );
CEAFSN_GF_Test_State::reset();
CEAFSN_GF_DB::insert_grant( grant_row_data() );
has_substring( 'INSERT INTO', (string) $wpdb->queries[0], 'insert_grant issues an INSERT' );
$wpdb->reset_state();
CEAFSN_GF_DB::update_grant( 7, grant_row_data() );
has_substring( 'UPDATE', (string) $wpdb->queries[0], 'update_grant issues an UPDATE' );
has_substring( '"grant_id":7', (string) $wpdb->queries[0], 'the update is scoped to that record' );

// -----------------------------------------------------------------------------
section( 'Admin: field extraction, validation, persistence' );

$admin     = new CEAFSN_GF_Admin();
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

	if ( 'extract_grant_fields' === $method ) {
		return $reflection->invoke( $instance );
	}

	return null === $id
		? $reflection->invoke( $instance, $data )
		: $reflection->invoke( $instance, $data, $id );
}

test( 'a complete record validates' );
is_same( array(), call_admin( $admin_ref, 'validate_grant', grant_row_data() ), 'no errors for a complete record' );

test( 'missing required fields are each reported' );
$cases = array(
	'title'       => 'Title is required.',
	'eligibility' => 'Eligibility criteria are required.',
);
foreach ( $cases as $field => $message ) {
	$errors = call_admin( $admin_ref, 'validate_grant', array_merge( grant_row_data(), array( $field => '' ) ) );
	has_substring( $message, implode( ' ', $errors ), "empty {$field} is reported" );
}

test( 'an unknown grant_status or status is rejected' );
$errors = call_admin( $admin_ref, 'validate_grant', array_merge( grant_row_data(), array( 'grant_status' => 'bogus' ) ) );
has_substring( 'Invalid status value.', implode( ' ', $errors ), 'grant_status is validated' );
$errors = call_admin( $admin_ref, 'validate_grant', array_merge( grant_row_data(), array( 'status' => 'bogus' ) ) );
has_substring( 'Invalid record state.', implode( ' ', $errors ), 'status is validated' );

test( 'a malformed deadline is rejected at save time' );
$errors = call_admin( $admin_ref, 'validate_grant', array_merge( grant_row_data(), array( 'deadline' => 'not a date', 'deadline_timezone' => 'UTC' ) ) );
has_substring( 'not a valid date and time', implode( ' ', $errors ), 'a garbled deadline is caught before it reaches the database layer' );

test( 'field extraction strips tags, coerces values, and stamps the site timezone' );
CEAFSN_GF_Test_State::$options['timezone_string'] = 'Africa/Nairobi';
$_POST = array(
	'title'               => '<script>alert(1)</script>Regional <b>Grant</b>',
	'award_range'         => 'Not disclosed',
	'deadline'             => '2025-06-01T17:00',
	'funding_institution' => 'CE-AFSN Trust',
	'eligibility'         => 'Open to all early-career researchers.',
	'call_pdf_id'         => '11abc',
	'application_url'     => 'https://example.test/apply',
	'contact'             => 'grants@example.test',
	'grant_status'        => 'open',
	'status'              => 'draft',
);
$extracted = call_admin( $admin_ref, 'extract_grant_fields' );
lacks_substring( '<script>', $extracted['title'], 'script tags are stripped from the title' );
is_same( 11, $extracted['call_pdf_id'], 'a non-numeric attachment id coerces to an integer' );
is_same( 'Africa/Nairobi', $extracted['deadline_timezone'], 'the timezone is stamped from the site setting, not the form' );
$_POST = array();
unset( CEAFSN_GF_Test_State::$options['timezone_string'] );

test( 'an unchecked checkbox is stored as zero' );
$_POST = array(
	'title'        => 'X',
	'eligibility'  => 'Y',
	'grant_status' => 'upcoming',
	'status'       => 'draft',
);
$extracted = call_admin( $admin_ref, 'extract_grant_fields' );
is_same( 0, $extracted['show_contact'], 'an unticked contact box is 0' );
is_same( 0, $extracted['duplicate_ok'], 'an unticked sharing box is 0' );
$_POST = array();

test( 'a new record is inserted and an existing one updated through persist_grant' );
CEAFSN_GF_Test_State::reset();
call_admin( $admin_ref, 'persist_grant', grant_row_data(), 0 );
has_substring( 'INSERT INTO', (string) $wpdb->queries[0], 'a record with no ID is inserted' );
$wpdb->reset_state();
call_admin( $admin_ref, 'persist_grant', grant_row_data(), 7 );
// The write is preceded by a read-back so the audit log can capture the
// previous state, so the write is located by content rather than position.
$gf_write = '';
foreach ( (array) $wpdb->queries as $ceafsn_q ) {
	if ( str_contains( (string) $ceafsn_q, 'UPDATE' ) ) {
		$gf_write = (string) $ceafsn_q;
		break;
	}
}
has_substring( 'UPDATE', $gf_write, 'a record with an ID is updated' );
has_substring( '"grant_id":7', $gf_write, 'the update is scoped to that record' );

// -----------------------------------------------------------------------------
section( 'Activator: scoped upload restriction' );

test( 'is_plugin_screen only matches this plugin\'s admin page' );
CEAFSN_GF_Test_State::reset();
update_option( '__is_admin', 1 );
$_GET['page'] = 'ceafsn-gf';
ok( CEAFSN_GF_Activator::is_plugin_screen(), 'this plugin\'s page is recognised' );
$_GET['page'] = 'some-other-plugin';
ok( ! CEAFSN_GF_Activator::is_plugin_screen(), 'a different admin page is not' );
unset( $_GET['page'] );

test( 'the upload filter only restricts uploads on this plugin\'s screen' );
CEAFSN_GF_Test_State::reset();
update_option( '__is_admin', 1 );
$_GET['page'] = 'ceafsn-gf';
$restricted   = CEAFSN_GF_Activator::restrict_upload_mimes( array( 'pdf' => 'application/pdf', 'png' => 'image/png' ) );
is_same( array( 'pdf' => 'application/pdf' ), $restricted, 'only PDF is allowed on the plugin screen' );
$_GET['page'] = 'elsewhere';
$unrestricted = CEAFSN_GF_Activator::restrict_upload_mimes( array( 'pdf' => 'application/pdf', 'png' => 'image/png' ) );
is_same( array( 'pdf' => 'application/pdf', 'png' => 'image/png' ), $unrestricted, 'other screens keep every upload type' );
unset( $_GET['page'] );

// -----------------------------------------------------------------------------
section( 'Public: card model and rendering helpers' );

test( 'build_card reflects Open status with a working Apply link' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$now  = '2025-06-01 00:00:00';
$card = CEAFSN_GF_Public::build_card( grant_row( array( 'grant_status' => 'open' ) ), $now );
is_same( 'open', $card['status'], 'status is read directly from the record' );
ok( $card['can_apply'], 'open plus a valid URL means the visitor can apply' );
ok( $card['has_pdf'], 'the attached call document resolves to a URL' );
ok( ! $card['deadline_passed'], 'the fixture deadline is far in the future' );

test( 'build_card shows Open with no way to apply when there is no URL' );
$card = CEAFSN_GF_Public::build_card( grant_row( array( 'grant_status' => 'open', 'application_url' => '' ) ), $now );
ok( $card['is_open'], 'still open by status' );
ok( ! $card['can_apply'], 'nothing to link to yet' );

test( 'build_card reports a passed deadline honestly even if status still says Open' );
$card = CEAFSN_GF_Public::build_card( grant_row( array( 'grant_status' => 'open', 'deadline' => '2020-01-01 00:00:00' ) ), $now );
ok( $card['deadline_passed'], 'a deadline before "now" is flagged as passed' );
is_same( 'open', $card['status'], 'the stored status is never silently overridden' );

test( 'build_card hides the contact unless approved' );
$card = CEAFSN_GF_Public::build_card( grant_row( array( 'show_contact' => 0 ) ), $now );
is_same( '', $card['contact'], 'contact is hidden without approval' );

test( 'build_card reports a missing call document honestly' );
CEAFSN_GF_Test_State::reset();
$card = CEAFSN_GF_Public::build_card( grant_row( array( 'call_pdf_id' => 999 ) ), $now );
ok( ! $card['has_pdf'], 'a document that resolves to no URL is not offered' );
has_substring( 'currently unavailable', $card['pdf_label'], 'the label explains the document is gone' );

test( 'build_card exposes the shared-document flag' );
$card = CEAFSN_GF_Public::build_card( grant_row( array( 'duplicate_ok' => 1 ) ), $now );
ok( $card['is_shared_document'], 'a confirmed shared document is flagged for the template' );

test( 'render_apply prints an active link only when the status is Open and a URL exists' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$open_card = CEAFSN_GF_Public::build_card( grant_row( array( 'grant_status' => 'open' ) ), $now );
ob_start();
CEAFSN_GF_Public::render_apply( $open_card );
$html = (string) ob_get_clean();
has_substring( 'ceafsn-gf-apply--active', $html, 'the active class is used' );
has_substring( 'Apply now', $html, 'the call to action reads Apply now' );

test( 'render_apply explains a closed status with a passed deadline' );
$closed_card = CEAFSN_GF_Public::build_card( grant_row( array( 'grant_status' => 'closed', 'deadline' => '2020-01-01 00:00:00' ) ), $now );
ob_start();
CEAFSN_GF_Public::render_apply( $closed_card );
$html = (string) ob_get_clean();
has_substring( 'Applications closed', $html, 'the closed label is used' );
has_substring( 'deadline has passed', $html, 'the reason is shown to the visitor' );

test( 'sort_link reports the current direction and the next one' );
$link = CEAFSN_GF_Public::sort_link( 'https://example.test/scholarships-grants/', 'deadline', 'deadline', 'asc' );
is_same( 'ascending', $link['aria_sort'], 'the active ascending column is announced' );
is_same( 'desc', $link['next'], 'clicking again would reverse it' );
$link = CEAFSN_GF_Public::sort_link( 'https://example.test/scholarships-grants/', 'title', 'deadline', 'asc' );
is_same( 'none', $link['aria_sort'], 'an inactive column announces nothing' );

test( 'clamp_per_page keeps requests within a safe range' );
is_same( 12, CEAFSN_GF_Public::clamp_per_page( 0 ), 'zero falls back to the default' );
is_same( 48, CEAFSN_GF_Public::clamp_per_page( 500 ), 'an excessive value is capped' );

test( 'normalize_view only recognises table, everything else is cards' );
is_same( 'table', CEAFSN_GF_Public::normalize_view( 'table' ), 'table is recognised' );
is_same( 'cards', CEAFSN_GF_Public::normalize_view( 'grid' ), 'an unknown value falls back to cards' );

test( 'document_url resolves a real attachment and nothing else' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
ok( '' !== CEAFSN_GF_Public::document_url( 11 ), 'a real attachment resolves to a URL' );
is_same( '', CEAFSN_GF_Public::document_url( 0 ), 'no attachment id resolves to nothing' );
is_same( '', CEAFSN_GF_Public::document_url( 404 ), 'a missing attachment resolves to nothing' );

test( 'format_deadline renders in the site timezone and can name it' );
CEAFSN_GF_Test_State::reset();
is_same( '', CEAFSN_GF_Public::format_deadline( '' ), 'no deadline renders nothing' );
CEAFSN_GF_Test_State::$options['timezone_string'] = 'UTC';
$plain = CEAFSN_GF_Public::format_deadline( '2025-06-01 14:00:00', false );
has_substring( '2025', $plain, 'the year appears in the formatted deadline' );
lacks_substring( 'time)', $plain, 'the timezone is omitted when not requested' );
$with_tz = CEAFSN_GF_Public::format_deadline( '2025-06-01 14:00:00', true );
has_substring( 'time)', $with_tz, 'the timezone is named by default' );
unset( CEAFSN_GF_Test_State::$options['timezone_string'] );

test( 'show_closed_by_default reads the option with a true default' );
CEAFSN_GF_Test_State::reset();
ok( CEAFSN_GF_Public::show_closed_by_default(), 'closed grants stay listed until an admin turns this off' );
update_option( CEAFSN_GF_Activator::SHOW_CLOSED_OPTION, false );
ok( ! CEAFSN_GF_Public::show_closed_by_default(), 'the option is honoured once set' );

// -----------------------------------------------------------------------------
section( 'Public: shortcode end to end' );

test( 'the shortcode shows the specified empty state when nothing is published' );
CEAFSN_GF_Test_State::reset();
$wpdb->var_result    = 0;
$wpdb->results_queue = array( array() );
$wpdb->col_queue     = array( array() );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
has_substring( 'No funding opportunities are currently open.', $html, 'the exact empty state message from the specification is shown' );

test( 'the shortcode renders a published, open grant as a card' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 1;
$wpdb->results_queue = array( array( grant_row( array( 'grant_status' => 'open' ) ) ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
has_substring( 'Early-Career Nutrition Research Grant', $html, 'the title is rendered' );
has_substring( 'ceafsn-gf-badge--status-open', $html, 'the open badge class is applied' );
has_substring( 'Apply now', $html, 'the active apply link is rendered' );

test( 'the table view renders the same rows with sortable headers' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 1;
$wpdb->results_queue = array( array( grant_row() ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array( 'view' => 'table' ) );
has_substring( '<table class="ceafsn-gf-table">', $html, 'the table layout is used' );
has_substring( 'aria-sort=', $html, 'sortable headers announce their state' );
has_substring( 'class="ceafsn-gf-table-wrap" tabindex="0" role="region"', $html, 'the table sits in a wrapper that scrolls with the keyboard' );
has_substring( 'data-label="Institution"', $html, 'each cell carries the label the card layout reads on a phone' );
has_substring( 'data-label="Apply"', $html, 'including the action column' );

// -----------------------------------------------------------------------------
// Regression: the closed-call filter is derived from the deadline and cannot be
// expressed in SQL, so paging in the query under-filled every page and left
// records 13+ unreachable behind an unlinked offset.
// -----------------------------------------------------------------------------

/**
 * Build N distinct published, open calls.
 *
 * @param int $count How many rows to build.
 * @return array<int,object>
 */
function gf_open_rows( int $count ): array {
	$rows = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$rows[] = grant_row(
			array(
				'grant_id'     => $i,
				'title'        => 'Funding Call ' . $i,
				'grant_status' => 'open',
			)
		);
	}
	return $rows;
}

test( 'the visible total counts every reachable record, not one page' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 30;
$wpdb->results_queue = array( gf_open_rows( 30 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
is_same( 12, substr_count( $html, 'ceafsn-gf-badge--status-open' ), 'page one holds a full twelve cards, not thirty' );
has_substring( 'Page 1 of 3', $html, 'the record count reflects all thirty records' );
has_substring( 'rel="next"', $html, 'a next control is offered' );
lacks_substring( 'rel="prev"', $html, 'there is no previous control on page one' );

test( 'page two is reachable and returns the next slice' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 30;
$wpdb->results_queue = array( gf_open_rows( 30 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$_GET['gf_page'] = '2';
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
is_same( 12, substr_count( $html, 'ceafsn-gf-badge--status-open' ), 'page two is full' );
has_substring( 'Page 2 of 3', $html, 'the current page is reported' );
has_substring( 'rel="prev"', $html, 'page two links back' );
has_substring( 'Funding Call 13', $html, 'page two starts at the thirteenth record' );
unset( $_GET['gf_page'] );

test( 'the last page holds the remainder, not a full page' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 30;
$wpdb->results_queue = array( gf_open_rows( 30 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$_GET['gf_page'] = '3';
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
is_same( 6, substr_count( $html, 'ceafsn-gf-badge--status-open' ), 'thirty records across twelve per page leaves six on page three' );
lacks_substring( 'rel="next"', $html, 'the last page has no next control' );
unset( $_GET['gf_page'] );

test( 'an out-of-range page clamps to the last page instead of showing nothing' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 30;
$wpdb->results_queue = array( gf_open_rows( 30 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$_GET['gf_page'] = '99';
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
has_substring( 'Page 3 of 3', $html, 'a stale deep link lands on the final page' );
is_same( 6, substr_count( $html, 'ceafsn-gf-badge--status-open' ), 'the final page still shows its records' );
unset( $_GET['gf_page'] );

test( 'no pagination control is rendered when everything fits on one page' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 3;
$wpdb->results_queue = array( gf_open_rows( 3 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
lacks_substring( 'ceafsn-pagination', $html, 'a single-page list is not given a pointless control' );

test( 'closed calls are dropped before the count is taken' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
// Closed calls hidden by default, so the visible set is only the open ones.
CEAFSN_GF_Test_State::$options[ CEAFSN_GF_Activator::SHOW_CLOSED_OPTION ] = 0;
$mixed = array_merge(
	gf_open_rows( 20 ),
	array(
		grant_row( array( 'grant_id' => 90, 'title' => 'Closed Call A', 'grant_status' => 'closed' ) ),
		grant_row( array( 'grant_id' => 91, 'title' => 'Closed Call B', 'grant_status' => 'closed' ) ),
	)
);
$wpdb->var_result    = 22;
$wpdb->results_queue = array( $mixed );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
is_same( 12, substr_count( $html, 'ceafsn-gf-badge--status-open' ), 'only the twenty open calls are counted and sliced' );
has_substring( 'Page 1 of 2', $html, 'the total reflects the filtered set, not the raw row count' );
lacks_substring( 'Closed Call A', $html, 'a hidden closed call never reaches the output' );
lacks_substring( 'Closed Call B', $html, 'neither hidden closed call reaches the output' );
unset( CEAFSN_GF_Test_State::$options[ CEAFSN_GF_Activator::SHOW_CLOSED_OPTION ] );

test( 'the table view also paginates' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 30;
$wpdb->results_queue = array( gf_open_rows( 30 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array( 'view' => 'table' ) );
is_same( 13, substr_count( $html, '<tr>' ), 'the table shows one header row plus twelve records' );
has_substring( 'ceafsn-pagination', $html, 'pagination is available in the table layout too' );

test( 'array-valued query args are not cast into hidden inputs' );
CEAFSN_GF_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result    = 30;
$wpdb->results_queue = array( gf_open_rows( 30 ) );
$wpdb->col_queue     = array( array( 'CE-AFSN Trust' ) );
$_GET['ceafsn-gf-filter'] = array( 'a', 'b' );
$_GET['utm_source']       = 'newsletter';
$carried = CEAFSN_GF_Public::passthrough_args( array( 'gf_page' ) );
is_same( array( 'utm_source' => 'newsletter' ), $carried, 'only scalar args are carried through' );
$public = new CEAFSN_GF_Public();
$html   = $public->render_shortcode( array() );
has_substring( 'name="utm_source" value="newsletter"', $html, 'the unrelated scalar arg survives the filter form' );
lacks_substring( 'name="ceafsn-gf-filter"', $html, 'an array arg cannot become a hidden input' );
unset( $_GET['ceafsn-gf-filter'], $_GET['utm_source'] );

test( 'the public stylesheet paints no single side of an element' );
$guard_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-gf-public.css' );
is_same(
	0,
	(int) preg_match( '/border-(top|right|bottom|left)(-\w+)?\s*:/', $guard_css ),
	'no rule paints one side of an element with a border'
);

// -----------------------------------------------------------------------------
section( 'Uninstall' );

test( 'uninstall.php returns immediately with no uninstall context' );
$report = ceafsn_gf_test_run_uninstall_case( 'no-context' );
is_same( array(), $report['queries'], 'nothing is touched outside the WordPress uninstall context' );

test( 'uninstall.php keeps records when the opt-in is off' );
$report = ceafsn_gf_test_run_uninstall_case( 'flag-off' );
is_same( array(), $report['queries'], 'no DROP TABLE runs without the opt-in' );
ok( ! array_key_exists( 'ceafsn_gf_uninstall_delete_data', $report['options'] ), 'the opt-in option itself is cleared either way' );

test( 'uninstall.php drops the table only when the opt-in is on' );
$report = ceafsn_gf_test_run_uninstall_case( 'flag-on' );
ok( ! empty( $report['queries'] ), 'a query ran' );
has_substring( 'DROP TABLE', implode( ' ', $report['queries'] ), 'the table is dropped when the admin opted in' );
has_substring( 'wp_ceafsn_gf_grants', implode( ' ', $report['queries'] ), 'the correct table is targeted' );
ok( ! array_key_exists( 'ceafsn_gf_db_version', $report['options'] ), 'the schema version option is cleared too' );

// -----------------------------------------------------------------------------
section( 'Admin: branded layout' );

global $plugin_dir;

$gf_grants_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/grants.php' );
$gf_admin_css      = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-gf-admin.css' );

test( 'every admin partial is scoped to the plugin wrapper' );
has_substring( 'class="wrap ceafsn-gf-wrap"', $gf_grants_partial, 'the grants screen uses the scoped wrapper' );

test( 'the shared design system is scoped so it cannot leak into other plugins' );
ok(
	! preg_match( '/(^|\})\s*(body|html|p|h1|h2|h3|table|ul|ol|label|input)\s*\{/m', $gf_admin_css ),
	'no bare element selectors in the admin stylesheet'
);
has_substring( '.ceafsn-gf-wrap', $gf_admin_css, 'admin styles are scoped to the wrapper' );
ok(
	substr_count( $gf_admin_css, '{' ) === substr_count( $gf_admin_css, '}' ),
	'the admin stylesheet braces are balanced'
);

test( 'the list screen uses the shared summary and table components' );
foreach (
	array(
		'ceafsn-hero',
		'ceafsn-app',
		'ceafsn-app__main',
		'ceafsn-app__rail',
		'ceafsn-kpi',
		'ceafsn-stepper',
		'ceafsn-card',
		'ceafsn-card__head',
		'ceafsn-card__body',
		'ceafsn-table',
		'ceafsn-cta',
		'ceafsn-ticks',
		'ceafsn-empty',
	) as $gf_component
) {
	has_substring( $gf_component, $gf_grants_partial, "the list uses {$gf_component}" );
	has_substring( $gf_component, $gf_admin_css, "the stylesheet defines {$gf_component}" );
}

test( 'the grant vocabulary has its own badge modifiers' );
foreach ( array( 'grant-open', 'grant-upcoming', 'grant-closed', 'grant-archived' ) as $gf_modifier ) {
	has_substring( 'ceafsn-badge--' . $gf_modifier, $gf_admin_css, "the stylesheet styles {$gf_modifier}" );
}
foreach ( array( 'state-draft', 'state-published', 'state-archived' ) as $gf_modifier ) {
	has_substring( 'ceafsn-badge--' . $gf_modifier, $gf_admin_css, "the stylesheet styles {$gf_modifier}" );
}
has_substring( 'ceafsn-badge--grant-<?php echo esc_attr', $gf_grants_partial, 'the opportunity badge is chosen from the record' );
has_substring( 'ceafsn-badge--state-<?php echo esc_attr', $gf_grants_partial, 'the record-state badge is chosen from the record' );

test( 'the empty state does not invent records' );
has_substring( 'No grant records yet', $gf_grants_partial, 'the empty list explains itself' );
has_substring( 'Add the first grant', $gf_grants_partial, 'the empty state offers the next action' );

test( 'the list reports what is on screen instead of counting blindly' );
has_substring( 'number_format_i18n( $gf_total )', $gf_grants_partial, 'counts are formatted for the locale' );
has_substring( 'ceafsn-gf-flag', $gf_grants_partial, 'an Open grant past its deadline is flagged rather than hidden' );
has_substring( 'ceafsn-gf-shared-list', $gf_grants_partial, 'records sharing a document are listed by name' );
has_substring( 'ceafsn-gf-shared-list', $gf_admin_css, 'the shared-document list is styled' );

test( 'one form wraps every card on the add and edit screen' );
ok(
	substr_count( $gf_grants_partial, '<form' ) === substr_count( $gf_grants_partial, '</form>' ),
	'the form tags balance'
);
is_same( 1, substr_count( $gf_grants_partial, '<form' ), 'the record form is a single form' );
has_substring( 'ceafsn-form__spaced', $gf_grants_partial, 'the form uses the shared spacing modifier' );
has_substring( 'ceafsn-form__actions', $gf_grants_partial, 'the save controls are inside the form' );

test( 'every field the save handler reads is on the form' );
foreach (
	array(
		'title',
		'award_range',
		'funding_institution',
		'deadline',
		'target_beneficiaries',
		'eligibility',
		'call_pdf_id',
		'application_url',
		'contact',
		'show_contact',
		'duplicate_ok',
		'duplicate_note',
		'grant_status',
		'status',
	) as $gf_field
) {
	has_substring( 'name="' . $gf_field . '"', $gf_grants_partial, "the form posts {$gf_field}" );
}

test( 'the media picker keeps the IDs the script relies on' );
foreach (
	array(
		'ceafsn-gf-pdf-id',
		'ceafsn-gf-pdf-field',
		'ceafsn-gf-media-button',
		'ceafsn-gf-media-clear',
	) as $gf_element
) {
	has_substring( $gf_element, $gf_grants_partial, "the markup provides {$gf_element}" );

	$gf_script = (string) file_get_contents( $plugin_dir . '/assets/js/ceafsn-gf-admin.js' );
	has_substring( $gf_element, $gf_script, "the script addresses {$gf_element}" );
}

test( 'the picker no longer depends on a table cell being an ancestor' );
$gf_script = (string) file_get_contents( $plugin_dir . '/assets/js/ceafsn-gf-admin.js' );
lacks_substring( "closest( 'td' )", $gf_script, 'the media script does not walk up to a table cell' );
lacks_substring( 'closest( "td" )', $gf_script, 'the media script does not walk up to a table cell' );

test( 'deleting a record asks first' );
has_substring( 'ceafsn-gf-delete-link', $gf_grants_partial, 'the delete action is marked' );
has_substring( 'ceafsn-gf-delete-link', $gf_script, 'the script handles the delete action' );
has_substring( 'window.confirm(', $gf_script, 'deletion is confirmed before it happens' );

// -----------------------------------------------------------------------------
section( 'Admin: settings scopes and tabs' );

$gf_set_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );

test( 'the settings screen shows the administrator which shortcode to use' );
has_substring( '[ceafsn_grants]', $gf_set_partial, 'the exact shortcode is shown' );
foreach ( array( 'per_page', 'funding_institution', 'status', 'view' ) as $gf_attribute ) {
	has_substring( '<code>' . $gf_attribute . '</code>', $gf_set_partial, "the shortcode card documents {$gf_attribute}" );
}
foreach ( array( 'ceafsn-embed__code', 'ceafsn-embed__table', 'ceafsn-embed__caption' ) as $gf_class ) {
	has_substring( $gf_class, $gf_set_partial, "the shortcode card uses {$gf_class}" );
}
ok( str_contains( $gf_set_partial, "'display' === \$gf_active_tab" ), 'the shortcode has its own read-only tab' );
has_substring( 'ceafsn-embed', $gf_admin_css, 'the stylesheet carries the shared embed component' );

test( 'every settings tab is reachable' );
foreach ( array( 'display', 'general', 'export', 'uninstall' ) as $gf_tab ) {
	ok(
		1 === preg_match( "/'" . preg_quote( $gf_tab, '/' ) . "'\\s*=>/", $gf_set_partial ),
		"the tab nav offers {$gf_tab}"
	);
}
has_substring( 'ceafsn-tabs__tab--active', $gf_set_partial, 'the open tab is marked' );
has_substring( 'aria-current="page"', $gf_set_partial, 'the open tab announces itself' );

/**
 * Call GF's private settings writer with a given POST body.
 *
 * @param ReflectionClass $class Admin class.
 * @param array           $post  POST body to save.
 * @return void
 */
function save_gf_settings( ReflectionClass $class, array $post ): void {
	$method = $class->getMethod( 'persist_settings' );
	$method->setAccessible( true );
	$method->invoke( $class->newInstanceWithoutConstructor(), $post );
}

test( 'saving the General tab leaves the uninstall opt-in untouched' );
CEAFSN_GF_Test_State::$options = array(
	CEAFSN_GF_Activator::SHOW_CLOSED_OPTION  => true,
	'ceafsn_gf_uninstall_delete_data'         => true,
);
save_gf_settings( $admin_ref, array( 'ceafsn_gf_settings_scope' => 'general' ) );
is_same( false, get_option( CEAFSN_GF_Activator::SHOW_CLOSED_OPTION ), 'an unticked display box is saved as false' );
is_same( true, get_option( 'ceafsn_gf_uninstall_delete_data' ), 'the uninstall opt-in on another tab survives' );

test( 'saving the Uninstall tab leaves the display default untouched' );
CEAFSN_GF_Test_State::$options = array(
	CEAFSN_GF_Activator::SHOW_CLOSED_OPTION  => true,
	'ceafsn_gf_uninstall_delete_data'         => false,
);
save_gf_settings( $admin_ref, array( 'ceafsn_gf_settings_scope' => 'uninstall', 'ceafsn_gf_uninstall_delete_data' => '1' ) );
is_same( true, get_option( 'ceafsn_gf_uninstall_delete_data' ), 'the opt-in saves as true' );
is_same( true, get_option( CEAFSN_GF_Activator::SHOW_CLOSED_OPTION ), 'the display default is not reset' );

test( 'a missing or unknown scope writes nothing' );
CEAFSN_GF_Test_State::$options = array(
	CEAFSN_GF_Activator::SHOW_CLOSED_OPTION  => true,
	'ceafsn_gf_uninstall_delete_data'         => false,
);
save_gf_settings( $admin_ref, array( 'ceafsn_gf_show_closed' => '1', 'ceafsn_gf_uninstall_delete_data' => '1' ) );
is_same( true, get_option( CEAFSN_GF_Activator::SHOW_CLOSED_OPTION ), 'no scope changes nothing' );
is_same( false, get_option( 'ceafsn_gf_uninstall_delete_data' ), 'the opt-in is not silently enabled' );
save_gf_settings( $admin_ref, array( 'ceafsn_gf_settings_scope' => 'bogus', 'ceafsn_gf_uninstall_delete_data' => '1' ) );
is_same( false, get_option( 'ceafsn_gf_uninstall_delete_data' ), 'an unknown scope changes nothing' );

test( 'the editable settings forms declare the scope they own' );
has_substring( 'name="ceafsn_gf_settings_scope"', $gf_set_partial, 'the scope field is posted' );
has_substring( "esc_attr( \$gf_is_general ? 'general' : 'uninstall' )", $gf_set_partial, 'the scope follows the open tab' );

// -----------------------------------------------------------------------------
section( 'Admin: partials render' );

/**
 * Render an admin partial the way the admin class does, and hand back the HTML.
 *
 * Reading a partial as a string proves nothing about whether it runs. Every
 * screen is rendered here instead, because an undefined stub or a misspelled
 * variable is invisible to a substring check and fatal on a real page.
 *
 * @param string               $file Partial path.
 * @param array<string,mixed>  $vars Variables the admin class puts in scope.
 * @return string
 */
function render_gf_partial( string $file, array $vars = array() ): string {
	// The partials read $_GET directly, the way WordPress hands it over.
	$before_get = $_GET;

	extract( $vars );
	ob_start();

	try {
		require $file;
	} finally {
		$html = (string) ob_get_clean();
		$_GET = $before_get;
	}

	return $html;
}

test( 'the grants screen renders in every mode' );
$gf_partial = $plugin_dir . '/admin/partials/grants.php';

$modes = array(
	'an empty list'    => array(
		'action'          => 'list',
		'row'             => null,
		'items'           => array(),
		'validation'      => null,
		'duplicate_count' => 0,
		'shared_with'     => array(),
	),
	'a populated list' => array(
		'action'          => 'list',
		'row'             => null,
		'items'           => array(
			grant_row( array( 'grant_id' => 1 ) ),
			grant_row( array( 'grant_id' => 2, 'grant_status' => 'upcoming', 'status' => 'draft', 'call_pdf_id' => 0 ) ),
			grant_row( array( 'grant_id' => 3, 'deadline' => '2001-01-01 00:00:00' ) ),
		),
		'validation'      => null,
		'duplicate_count' => 0,
		'shared_with'     => array(),
	),
	'add'              => array(
		'action'          => 'add',
		'row'             => null,
		'items'           => array(),
		'validation'      => null,
		'duplicate_count' => 0,
		'shared_with'     => array(),
	),
	'edit'             => array(
		'action'          => 'edit',
		'row'             => grant_row( array( 'duplicate_ok' => 1 ) ),
		'items'           => array( grant_row() ),
		'validation'      => array( 'valid' => true, 'pages' => 4, 'errors' => array() ),
		'duplicate_count' => 1,
		'shared_with'     => array( grant_row( array( 'grant_id' => 9, 'title' => 'Other record' ) ) ),
	),
);

foreach ( $modes as $label => $vars ) {
	ok( strlen( render_gf_partial( $gf_partial, $vars ) ) > 500, "the {$label} screen renders" );
}

test( 'the summary counts only the records on screen' );
$html = render_gf_partial( $gf_partial, $modes['a populated list'] );
has_substring( '<span class="ceafsn-kpi__value">3</span>', $html, 'three records are counted' );
has_substring( 'ceafsn-kpi--alert', $html, 'a passed deadline raises the alert tile' );
is_same( 1, substr_count( $html, 'ceafsn-gf-flag' ), 'only the overdue record is flagged' );

test( 'a missing call document is called out rather than hidden' );
has_substring( 'ceafsn-badge--warn', $html, 'the record with no PDF is marked missing' );
has_substring( 'ceafsn-dot--danger', $html, 'and it carries the danger dot' );

test( 'the edit screen reports a shared document by name' );
$html = render_gf_partial( $gf_partial, $modes['edit'] );
has_substring( 'ceafsn-gf-shared-list', $html, 'the shared-document warning lists the other records' );
has_substring( 'Other record', $html, 'the other record is named' );
has_substring( 'Call PDF verified', $html, 'a valid attachment is reported' );

test( 'the record form posts every field in one form' );
$html = render_gf_partial( $gf_partial, $modes['edit'] );
is_same( 1, substr_count( $html, '<form' ), 'one form is rendered' );
is_same( 1, substr_count( $html, '</form>' ), 'and it is closed' );
foreach ( array( 'ceafsn-gf-pdf-id', 'ceafsn-gf-pdf-field', 'ceafsn-gf-media-button', 'ceafsn-gf-media-clear' ) as $id ) {
	has_substring( 'id="' . $id . '"', $html, "the rendered form carries {$id}" );
}
has_substring( 'value="11"', $html, 'the saved attachment ID is carried into the form' );

test( 'every settings tab renders' );
$gf_set_file = $plugin_dir . '/admin/partials/settings.php';

foreach ( array( 'display', 'general', 'export', 'uninstall' ) as $tab ) {
	$_GET['tab'] = $tab;
	ok( strlen( render_gf_partial( $gf_set_file ) ) > 400, "the {$tab} tab renders" );
}

test( 'an unknown settings tab falls back to Display' );
$_GET['tab'] = 'bogus';
$html = render_gf_partial( $gf_set_file );
has_substring( '[ceafsn_grants]', $html, 'the fallback tab is the shortcode card' );

test( 'the General tab posts its own scope' );
$_GET['tab'] = 'general';
$html = render_gf_partial( $gf_set_file );
has_substring( 'value="general"', $html, 'the General tab declares its scope' );
lacks_substring( 'ceafsn_gf_uninstall_delete_data', $html, 'the General tab does not carry the uninstall opt-in' );

test( 'the Uninstall tab posts its own scope' );
$_GET['tab'] = 'uninstall';
$html = render_gf_partial( $gf_set_file );
has_substring( 'value="uninstall"', $html, 'the Uninstall tab declares its scope' );
lacks_substring( 'ceafsn_gf_show_closed', $html, 'the Uninstall tab does not carry the display default' );

$_GET = array();

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------


// -----------------------------------------------------------------------------
section( 'Translation and status labels' );

test( 'the textdomain is loaded on init, not before' );
$gf_boot = (string) file_get_contents( $plugin_dir . '/ceafsn-grants-funding.php' );
has_substring( "add_action( 'init', 'ceafsn_gf_load_textdomain' );", $gf_boot, 'the loader waits for init' );
ok( ! preg_match( "/add_action\\(\\s*'plugins_loaded'[^)]*load_textdomain/", $gf_boot ), 'WP 6.7 deprecated loading a textdomain earlier than init, so nothing registers it on plugins_loaded' );
has_substring( "'ceafsn-gf',", $gf_boot, 'the domain string is ceafsn-gf' );

test( 'every stored enum value has a translated label' );
foreach ( array( 'draft', 'published', 'archived' ) as $gf_key ) {
	$gf_label = CEAFSN_GF_DB::label( CEAFSN_GF_DB::status_labels(), $gf_key );
	ok( '' !== $gf_label, 'status_labels() returns a non-empty label for "' . $gf_key . '"' );
	ok( $gf_label !== $gf_key, 'and "' . $gf_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'open', 'closed', 'upcoming', 'archived' ) as $gf_key ) {
	$gf_label = CEAFSN_GF_DB::label( CEAFSN_GF_DB::grant_status_labels(), $gf_key );
	ok( '' !== $gf_label, 'grant_status_labels() returns a non-empty label for "' . $gf_key . '"' );
	ok( $gf_label !== $gf_key, 'and "' . $gf_key . '" is not shown as a raw machine key' );
}

test( 'each label map covers the schema exactly' );
is_same( array( 'draft', 'published', 'archived' ), array_keys( CEAFSN_GF_DB::status_labels() ), 'status_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'open', 'closed', 'upcoming', 'archived' ), array_keys( CEAFSN_GF_DB::grant_status_labels() ), 'grant_status_labels() has one label per stored value, no more and no fewer' );

test( 'an unrecognised key falls back to the key itself' );
is_same( 'Draft', CEAFSN_GF_DB::label( CEAFSN_GF_DB::status_labels(), 'draft' ), 'a known key returns its translated label' );
is_same( 'not_a_status', CEAFSN_GF_DB::label( CEAFSN_GF_DB::status_labels(), 'not_a_status' ), 'an unknown key is shown verbatim, so a missing label is obvious instead of silently English' );

test( 'no partial renders a stored enum through ucfirst()' );
$gf_seen = 0;
foreach ( glob( $plugin_dir . '/admin/partials/*.php' ) ?: array() as $gf_partial ) {
	$gf_seen++;
	ok( ! str_contains( (string) file_get_contents( $gf_partial ), 'ucfirst(' ), basename( $gf_partial ) . ' does not title-case a stored value' );
}
foreach ( glob( $plugin_dir . '/public/partials/*.php' ) ?: array() as $gf_partial ) {
	$gf_seen++;
	ok( ! str_contains( (string) file_get_contents( $gf_partial ), 'ucfirst(' ), basename( $gf_partial ) . ' does not title-case a stored value' );
}
ok( $gf_seen > 0, 'the partials were actually scanned' );

// -----------------------------------------------------------------------------
section( 'Schema migration engine' );

test( 'maybe_upgrade is a no-op when the stored version is current' );
CEAFSN_GF_Test_State::reset();
$GLOBALS['ceafsn_gf_dbdelta'] = array();
update_option( 'ceafsn_gf_db_version', CEAFSN_GF_DB::SCHEMA_VERSION );
is_same( false, CEAFSN_GF_DB::maybe_upgrade(), 'a current schema is left alone' );
is_same( array(), (array) $GLOBALS['ceafsn_gf_dbdelta'], 'and no schema work runs, so admin_init stays cheap' );

test( 'maybe_upgrade does nothing on a downgrade' );
CEAFSN_GF_Test_State::reset();
$GLOBALS['ceafsn_gf_dbdelta'] = array();
update_option( 'ceafsn_gf_db_version', '99.0.0' );
is_same( false, CEAFSN_GF_DB::maybe_upgrade(), 'a newer stored schema is never downgraded' );
is_same( '99.0.0', get_option( 'ceafsn_gf_db_version' ), 'and the stored version is untouched' );

test( 'maybe_upgrade applies dbDelta when the stored version is behind' );
CEAFSN_GF_Test_State::reset();
$GLOBALS['ceafsn_gf_dbdelta'] = array();
is_same( false, get_option( 'ceafsn_gf_db_version', false ), 'no version is recorded on a site that never activated the plugin' );
is_same( true, CEAFSN_GF_DB::maybe_upgrade(), 'a missing schema takes the upgrade path' );
ok( ! empty( (array) $GLOBALS['ceafsn_gf_dbdelta'] ), 'the schema is created through dbDelta' );
is_same( CEAFSN_GF_DB::SCHEMA_VERSION, get_option( 'ceafsn_gf_db_version' ), 'and the version option is brought up to date' );

test( 'maybe_upgrade repairs an older installed version' );
CEAFSN_GF_Test_State::reset();
$GLOBALS['ceafsn_gf_dbdelta'] = array();
update_option( 'ceafsn_gf_db_version', '0.9.0' );
is_same( true, CEAFSN_GF_DB::maybe_upgrade(), 'a version bump is applied' );
ok( ! empty( (array) $GLOBALS['ceafsn_gf_dbdelta'] ), 'dbDelta runs so missing columns and indexes are added' );
is_same( CEAFSN_GF_DB::SCHEMA_VERSION, get_option( 'ceafsn_gf_db_version' ), 'and the option ends at the current version' );

test( 'the migration is hooked to admin_init, not activation alone' );
$med_bootstrap = (string) file_get_contents( $plugin_dir . '/ceafsn-grants-funding.php' );
has_substring( "add_action( 'admin_init', array( 'CEAFSN_GF_DB', 'maybe_upgrade' ) );", $med_bootstrap, 'plugin updates reach existing sites because the hook is on admin_init' );

$pass = $GLOBALS['ceafsn_gf_test_pass'];
$fail = $GLOBALS['ceafsn_gf_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
printf( "%d assertions, %d passed, %d failed\n", $pass + $fail, $pass, $fail );

exit( $fail > 0 ? 1 : 0 );
