<?php
/**
 * Test runner for CE-AFSN Projects & Publications.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-projects-publications/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_PP
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
$fake_wp_admin = sys_get_temp_dir() . '/ceafsn-pp-fake-wp/wp-admin/includes';

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
	. "\t\t\t\$GLOBALS['ceafsn_pp_dbdelta'][] = (string) \$query;\n"
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
final class FakePpWpdb {

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

	/** @var array<int,array<string,mixed>> Values as actually stored, after $format casting. */
	public array $inserted_rows = array();

	/** @var array<int,array<string,mixed>> Values as actually stored, after $format casting. */
	public array $updated_rows = array();

	/** @var array<int,mixed> Result queue for the next get_results() calls. */
	public array $results_queue = array();

	/** @var array<int,mixed> Queue for the next get_var() calls. */
	public array $var_queue = array();

	/** @var mixed Value returned by get_var() when the queue is empty. */
	public mixed $var_result = 0;

	/** @var mixed Value returned by the next get_row() call. */
	public mixed $row_result = null;

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
	 * Record an insert, honouring the $format list exactly as $wpdb does.
	 *
	 * $wpdb matches $format entries to values *positionally* and casts each
	 * value with the given specifier before building the SQL. A fake that
	 * ignores $format cannot observe the corruption that a mismatched
	 * specifier causes, so this implementation reproduces the same cast.
	 *
	 * @param string $table  Table.
	 * @param array  $data   Column data.
	 * @param array  $format Formats.
	 * @return int Always 1.
	 * @throws RuntimeException When $format does not line up with $data.
	 */
	public function insert( string $table, array $data, array $format = array() ): int {
		$row = $this->coerce_row( $data, $format );
		$this->queries[]       = 'INSERT INTO ' . $table . ' ' . (string) json_encode( $row );
		$this->inserted_rows[] = $row;
		return 1;
	}

	/**
	 * Record an update, honouring the $format list exactly as $wpdb does.
	 *
	 * @param string $table        Table.
	 * @param array  $data         Column data.
	 * @param array  $where        Where clause.
	 * @param array  $format       Formats.
	 * @param array  $where_format Where formats.
	 * @return int Always 1.
	 * @throws RuntimeException When $format does not line up with $data.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int {
		$row = $this->coerce_row( $data, $format );
		$this->queries[]       = 'UPDATE ' . $table . ' ' . (string) json_encode( $row ) . ' ' . (string) json_encode( $where );
		$this->updated_rows[]  = $row;
		return 1;
	}

	/**
	 * Apply positional format specifiers to a column/value pair.
	 *
	 * Reproduces the cast $wpdb performs inside prepare(): a %d specifier on a
	 * non-numeric string yields 0, which is exactly how an ENUM value such as
	 * `in_progress` gets destroyed in the database.
	 *
	 * @param array<string,mixed> $data   Column data.
	 * @param array<int,string>   $format Format specifiers.
	 * @return array<string,mixed> Values after casting.
	 * @throws RuntimeException When the counts differ.
	 */
	private function coerce_row( array $data, array $format ): array {
		$values = array_values( $data );
		$keys   = array_keys( $data );

		if ( ! empty( $format ) && count( $format ) !== count( $values ) ) {
			throw new RuntimeException(
				sprintf(
					'$format has %d specifier(s) but %d value(s) were supplied; positional casting is ambiguous.',
					count( $format ),
					count( $values )
				)
			);
		}

		$row = array();
		foreach ( $values as $index => $value ) {
			$spec    = $format[ $index ] ?? ( is_int( $value ) ? '%d' : '%s' );
			$row[ $keys[ $index ] ] = match ( $spec ) {
				'%d'    => (int) $value,
				'%f'    => (float) $value,
				default => (string) $value,
			};
		}

		return $row;
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
		$this->inserted_rows = array();
		$this->updated_rows  = array();
		$this->results_queue = array();
		$this->var_queue     = array();
		$this->var_result    = 0;
		$this->row_result    = null;
	}
}

// -----------------------------------------------------------------------------
// Assertions
// -----------------------------------------------------------------------------

$GLOBALS['ceafsn_pp_test_pass']    = 0;
$GLOBALS['ceafsn_pp_test_fail']    = 0;
$GLOBALS['ceafsn_pp_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_pp_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ok( bool $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_pp_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_pp_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_pp_test_current']}] {$message}\n";
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
		++$GLOBALS['ceafsn_pp_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_pp_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_pp_test_current']}] {$message}\n"
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
		++$GLOBALS['ceafsn_pp_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_pp_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_pp_test_current']}] {$message}\n       missing: {$needle}\n";
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
		++$GLOBALS['ceafsn_pp_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_pp_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_pp_test_current']}] {$message}\n       found: {$needle}\n";
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
$fixture_dir = sys_get_temp_dir() . '/ceafsn-pp-fixtures';

if ( ! is_dir( $fixture_dir ) ) {
	mkdir( $fixture_dir, 0777, true );
}

/**
 * Build a minimal but well-formed PDF for tests.
 *
 * @param int  $pages Number of page objects.
 * @param bool $text  Whether to include a text-showing operator.
 * @return string Raw PDF bytes.
 */
function make_pdf( int $pages = 3, bool $text = true ): string {
	$body = "%PDF-1.4\n";
	for ( $i = 0; $i < $pages; $i++ ) {
		$body .= "1 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n";
	}
	if ( $text ) {
		$body .= "BT /F1 12 Tf 72 720 Td (Hello from the report) Tj ET\n";
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

$valid_pdf      = fixture( 'real-report.pdf', make_pdf( 4 ) );
$one_page_pdf   = fixture( 'single-page.pdf', make_pdf( 1 ) );
$scanned_pdf    = fixture( 'scanned-doc.pdf', make_pdf( 8, false ) );
$truncated_pdf  = fixture( 'truncated.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Page >>\nendobj\n" );
$not_a_pdf      = fixture( 'notes.txt', "This is a plain text file pretending to be a document.\n" );
$empty_pdf      = fixture( 'empty.pdf', '' );
$placeholder    = fixture( 'ceafsn.pdf', make_pdf( 1 ) );
$image_pdf      = fixture( 'scan.png', "\x89PNG\r\n\x1a\nnot a pdf at all" );
$cover_image    = fixture( 'cover.png', "\x89PNG\r\n\x1a\nfake image bytes" );
$large_pdf      = fixture( 'large-report.pdf', make_pdf( 4 ) . str_repeat( "% padding so this document is larger than a kilobyte\n", 20 ) );
$huge_pdf       = fixture( 'huge-report.pdf', make_pdf( 4 ) . str_repeat( "% padding to push this document past a megabyte\n", 200000 ) );
$empty_attached = fixture( 'empty-attachment.pdf', '' );

// Published to the global scope so the render helper can re-register the
// standard fixtures after it resets the shared state.
$GLOBALS['ceafsn_pp_valid_pdf']   = $valid_pdf;
$GLOBALS['ceafsn_pp_cover_image'] = $cover_image;

/**
 * Register a fixture as a Media Library attachment.
 *
 * @param int    $id    Attachment ID.
 * @param string $path  Absolute path.
 * @param string $mime  MIME type.
 */
function attach( int $id, string $path, string $mime = 'application/pdf' ): void {
	CEAFSN_PP_Test_State::add_attachment( $id, $path, $mime );
}

/**
 * Build a publication row.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return object Publication row.
 */
function publication_row( array $overrides = array() ): object {
	return (object) array_merge(
		array(
			'publication_id'     => 1,
			'title'              => 'Food Security in the Horn of Africa',
			'content_type'       => 'report',
			'executive_summary'  => 'A regional assessment of food security conditions.',
			'author_institution' => 'CE-AFSN Secretariat',
			'publication_date'   => '2025-03-14',
			'project_status'     => 'completed',
			'cover_image_id'     => 0,
			'cover_image_alt'    => '',
			'pdf_attachment_id'  => 11,
			'page_count'         => 4,
			'doi_citation'       => '',
			'access_level'       => 'public',
			'duplicate_note'     => '',
			'scanned'            => 0,
			'duplicate_ok'       => 0,
			'status'             => 'published',
		),
		$overrides
	);
}

/**
 * Build the flat record array the validator expects.
 *
 * publish_blockers() takes the prepared field array rather than a database row,
 * so a fixture row is converted the same way the save handler builds it.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return array<string,mixed>
 */
function publication_row_data( array $overrides = array() ): array {
	return (array) publication_row( $overrides );
}

/**
 * Clear recorded state and register the plugin's hooks again.
 *
 * Several tests need a fresh request, which in WordPress means the plugin's
 * plugins_loaded callback runs again. Resetting the state and firing the hook is
 * the same thing, without reloading any files.
 *
 * @return void
 */
function ceafsn_pp_test_reload( bool $as_admin = false ): void {
	CEAFSN_PP_Test_State::reset();

	if ( $as_admin ) {
		update_option( '__is_admin', 1 );
	}

	ceafsn_pp_init();
}

/**
 * Run a helper script in its own process and decode its JSON report.
 *
 * uninstall.php and the legacy redirect both end the request with exit, so each
 * case has to run where terminating the process is the expected outcome rather
 * than a failure of the test suite.
 *
 * @param string $script Script file name inside tests/.
 * @param array  $args   Command line arguments.
 * @return array<string,mixed> Decoded report, or an empty array on failure.
 */
function ceafsn_pp_test_run_case( string $script, array $args ): array {
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
 * @return array{queries: array<int,string>, options: array<string,mixed>, case: string}
 */
function ceafsn_pp_test_run_uninstall_case( string $case ): array {
	$report = ceafsn_pp_test_run_case( 'uninstall-cases.php', array( $case ) );

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

/**
 * Run one legacy redirect case in a child process.
 *
 * @param string $case Case name: legacy-404, legacy-page, or other-path.
 * @return array<string,mixed>
 */
function ceafsn_pp_test_run_redirect_case( string $case ): array {
	$report = ceafsn_pp_test_run_case( 'redirect-cases.php', array( $case ) );

	if ( ! isset( $report['redirects'] ) || ! is_array( $report['redirects'] ) ) {
		ok( false, "the redirect case {$case} returned no usable report" );
		return array(
			'case'      => $case,
			'redirects' => array(),
			'exited'    => false,
		);
	}

	return array(
		'case'      => isset( $report['case'] ) ? (string) $report['case'] : $case,
		'redirects' => $report['redirects'],
		'exited'    => ! empty( $report['exited'] ),
	);
}

/**
 * Every PHP file that actually ships to a site.
 *
 * The test harness is excluded: it is never served, and it has to contain canned
 * values and stubs that the shipped code must not.
 *
 * @return array<int,string> Absolute file paths.
 */
function ceafsn_pp_test_production_php_files(): array {
	global $plugin_dir;

	$files = array_values(
		array_filter(
			explode( "\n", (string) shell_exec( 'find ' . escapeshellarg( $plugin_dir ) . ' -name "*.php" -not -path "*/tests/*"' ) ),
			static fn( $file ): bool => is_string( $file ) && '' !== trim( $file )
		)
	);

	ok( count( $files ) > 5, 'the plugin ships PHP files (' . count( $files ) . ')' );

	return $files;
}

// -----------------------------------------------------------------------------
// Load plugin
// -----------------------------------------------------------------------------

$plugin_source = (string) file_get_contents( $plugin_dir . '/ceafsn-projects-publications.php' );
require_once $plugin_dir . '/ceafsn-projects-publications.php';

global $wpdb;
$wpdb = new FakePpWpdb();

section( 'Plugin bootstrap' );

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', $plugin_source, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_PP_VERSION' ), 'CEAFSN_PP_VERSION defined' );
ok( defined( 'CEAFSN_PP_PLUGIN_DIR' ), 'CEAFSN_PP_PLUGIN_DIR defined' );
is_same( '1.0.0', CEAFSN_PP_VERSION, 'version constant matches header' );

test( 'all plugin classes loaded' );
foreach ( array( 'CEAFSN_PP_DB', 'CEAFSN_PP_Validator', 'CEAFSN_PP_Activator', 'CEAFSN_PP_Admin', 'CEAFSN_PP_Public' ) as $class ) {
	ok( class_exists( $class ), "class {$class} exists" );
}

test( 'the shortcode is registered on plugins_loaded' );
ok( isset( CEAFSN_PP_Test_State::$actions['plugins_loaded'] ), 'bootstrap registers a plugins_loaded callback' );
ceafsn_pp_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_PP_Test_State::$shortcodes['ceafsn_projects_pubs'] ), '[ceafsn_projects_pubs] registered' );

test( 'translations are loaded on init' );
ok( isset( CEAFSN_PP_Test_State::$actions['init'] ), 'init hook registered' );
ceafsn_pp_test_fire_action( 'init' );
ok( in_array( 'ceafsn-pp', CEAFSN_PP_Test_State::$textdomains, true ), 'ceafsn-pp text domain loaded' );

// -----------------------------------------------------------------------------
section( 'PDF validation' );

test( 'a well-formed PDF passes' );
$result = CEAFSN_PP_Validator::inspect_bytes( make_pdf( 4 ) );
ok( $result['valid'], 'valid PDF reported as valid' );
is_same( array(), $result['errors'], 'no errors for a valid PDF' );
is_same( 4, $result['pages'], 'page count read correctly' );
ok( $result['has_text'], 'extractable text detected' );

test( 'a single page PDF passes and reports one page' );
$result = CEAFSN_PP_Validator::inspect_bytes( make_pdf( 1 ) );
ok( $result['valid'], 'one page PDF is valid' );
is_same( 1, $result['pages'], 'page count is 1' );

test( 'an empty file is rejected' );
$result = CEAFSN_PP_Validator::inspect_bytes( '' );
ok( ! $result['valid'], 'empty file is not valid' );
is_same( 0, $result['pages'], 'empty file has no pages' );

test( 'a non-PDF file is rejected' );
$result = CEAFSN_PP_Validator::inspect_bytes( 'just some text, no PDF here' );
ok( ! $result['valid'], 'plain text is not a valid PDF' );

test( 'a truncated PDF is rejected' );
$result = CEAFSN_PP_Validator::inspect_bytes( (string) file_get_contents( $truncated_pdf ) );
ok( ! $result['valid'], 'truncated PDF is not valid' );
has_substring( 'truncated', implode( ' ', $result['errors'] ), 'errors mention the truncation' );

test( 'a PDF with no readable page count is rejected' );
$result = CEAFSN_PP_Validator::inspect_bytes( "%PDF-1.4\n%%EOF\n" );
ok( ! $result['valid'], 'PDF with no page objects is not valid' );
is_same( 0, $result['pages'], 'unreadable page count reports zero' );

test( 'page count falls back to the page tree' );
is_same( 12, CEAFSN_PP_Validator::count_pages( '%PDF-1.4 /Type /Pages /Count 12 %%EOF' ), '/Count used when no page objects are visible' );

test( 'the page tree node is not counted as a page' );
is_same( 2, CEAFSN_PP_Validator::count_pages( "%PDF-1.4\n/Type /Page\n/Type /Page\n/Type /Pages\n%%EOF" ), '/Type /Pages is not counted' );

test( 'a PDF with no extractable text is reported as such' );
$result = CEAFSN_PP_Validator::inspect_bytes( make_pdf( 5, false ) );
ok( $result['valid'], 'the file itself is structurally valid' );
ok( ! $result['has_text'], 'no text operator means no extractable text' );
is_same( 5, $result['pages'], 'page count still readable' );

test( 'text detection recognises the TJ operator' );
ok( CEAFSN_PP_Validator::has_extractable_text( '%PDF-1.4 BT [(a)] TJ ET %%EOF' ), 'TJ counts as extractable text' );
ok( ! CEAFSN_PP_Validator::has_extractable_text( '%PDF-1.4 /Type /Page %%EOF' ), 'a page with no text block is not text' );

test( 'placeholder filenames are recognised' );
CEAFSN_PP_Test_State::reset();
ok( CEAFSN_PP_Validator::is_placeholder_filename( $placeholder ), 'ceafsn.pdf is a known placeholder' );
ok( CEAFSN_PP_Validator::is_placeholder_filename( '/uploads/2026/01/CEAFSN.PDF' ), 'placeholder match is case insensitive' );
ok( ! CEAFSN_PP_Validator::is_placeholder_filename( $valid_pdf ), 'a real report PDF is not a placeholder' );

test( 'extra placeholder names can be configured' );
CEAFSN_PP_Test_State::reset();
update_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION, array( 'draft-only.pdf' ) );
ok( CEAFSN_PP_Validator::is_placeholder_filename( 'draft-only.pdf' ), 'configured placeholder is recognised' );
ok( CEAFSN_PP_Validator::is_placeholder_filename( 'ceafsn.pdf' ), 'ceafsn.pdf stays blocked even after reconfiguration' );
CEAFSN_PP_Test_State::reset();

test( 'attachment validation reads the real file' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$result = CEAFSN_PP_Validator::validate_attachment( 11 );
ok( $result['valid'], 'a real attachment validates' );
is_same( 4, $result['pages'], 'page count read from disk' );

test( 'a missing attachment ID is rejected' );
CEAFSN_PP_Test_State::reset();
$result = CEAFSN_PP_Validator::validate_attachment( 0 );
ok( ! $result['valid'], 'zero attachment ID is invalid' );
has_substring( 'No PDF is attached', implode( ' ', $result['errors'] ), 'error names the missing attachment' );

test( 'a deleted attachment is rejected' );
CEAFSN_PP_Test_State::reset();
attach( 12, $fixture_dir . '/does-not-exist.pdf' );
ok( ! CEAFSN_PP_Validator::validate_attachment( 12 )['valid'], 'attachment with no file on disk is invalid' );

test( 'a non-PDF attachment is rejected' );
CEAFSN_PP_Test_State::reset();
attach( 13, $not_a_pdf, 'text/plain' );
$result = CEAFSN_PP_Validator::validate_attachment( 13 );
ok( ! $result['valid'], 'a text file is not accepted' );
has_substring( 'not a PDF', implode( ' ', $result['errors'] ), 'error names the type problem' );

test( 'an image renamed to .pdf is rejected' );
CEAFSN_PP_Test_State::reset();
attach( 14, $image_pdf, 'application/pdf' );
ok( ! CEAFSN_PP_Validator::validate_attachment( 14 )['valid'], 'a PNG with a .pdf name is rejected on its signature' );

test( 'a zero byte attachment is rejected' );
CEAFSN_PP_Test_State::reset();
attach( 16, $empty_pdf );
ok( ! CEAFSN_PP_Validator::validate_attachment( 16 )['valid'], 'a zero byte file is rejected' );

// -----------------------------------------------------------------------------
section( 'Publish gating' );

test( 'a clean record has no publish blockers' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 0;
is_same( array(), CEAFSN_PP_Validator::publish_blockers( publication_row_data() ), 'no blockers for a valid, unshared document' );

test( 'a page count that disagrees with the document blocks publishing' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 0;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'page_count' => 99 ) ) );
has_substring( 'page count', strtolower( implode( ' ', $blockers ) ), 'page count mismatch is reported' );
has_substring( '99', implode( ' ', $blockers ), 'the entered value is quoted back' );
has_substring( '4', implode( ' ', $blockers ), 'the measured value is quoted back' );

test( 'a page count of 0 is not treated as a mismatch' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 0;
is_same( array(), CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'page_count' => 0 ) ) ), 'an unknown page count does not block' );

test( 'a document with no readable text blocks publishing' );
CEAFSN_PP_Test_State::reset();
attach( 17, $scanned_pdf );
$wpdb->var_result = 0;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'pdf_attachment_id' => 17, 'page_count' => 0 ) ) );
has_substring( 'scanned', strtolower( implode( ' ', $blockers ) ), 'the scanned override is mentioned' );

test( 'the scanned flag allows a document with no text' );
CEAFSN_PP_Test_State::reset();
attach( 17, $scanned_pdf );
$wpdb->var_result = 0;
is_same( array(), CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'pdf_attachment_id' => 17, 'page_count' => 0, 'scanned' => 1 ) ) ), 'a declared scanned document passes' );

test( 'the placeholder blocks publishing until it is confirmed' );
CEAFSN_PP_Test_State::reset();
attach( 15, $placeholder );
$wpdb->var_result = 0;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'pdf_attachment_id' => 15, 'page_count' => 0 ) ) );
has_substring( 'placeholder', strtolower( implode( ' ', $blockers ) ), 'the placeholder problem is reported' );

test( 'a confirmed placeholder still needs a note' );
CEAFSN_PP_Test_State::reset();
attach( 15, $placeholder );
$wpdb->var_result = 0;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'pdf_attachment_id' => 15, 'page_count' => 0, 'duplicate_ok' => 1 ) ) );
has_substring( 'note is required', strtolower( implode( ' ', $blockers ) ), 'confirming without a note is not enough' );

test( 'a confirmed placeholder with a note is allowed' );
CEAFSN_PP_Test_State::reset();
attach( 15, $placeholder );
$wpdb->var_result = 0;
is_same( array(), CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'pdf_attachment_id' => 15, 'page_count' => 0, 'duplicate_ok' => 1, 'duplicate_note' => 'Legacy record, kept for the archive.' ) ) ), 'placeholder plus note passes' );

test( 'a shared document blocks publishing until it is confirmed' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 2;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data() );
has_substring( 'already attached', implode( ' ', $blockers ), 'the sharing problem is reported' );
has_substring( '2', implode( ' ', $blockers ), 'the number of other records is quoted' );

test( 'the singular and plural sharing messages both exist' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 1;
$one = CEAFSN_PP_Validator::publish_blockers( publication_row_data() );
$wpdb->var_result = 3;
$many = CEAFSN_PP_Validator::publish_blockers( publication_row_data() );
has_substring( '1 other record.', implode( ' ', $one ), 'one other record reads correctly' );
has_substring( '3 other records.', implode( ' ', $many ), 'three other records reads correctly' );

test( 'a confirmed shared document still needs a note' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 1;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'duplicate_ok' => 1 ) ) );
has_substring( 'note is required', strtolower( implode( ' ', $blockers ) ), 'a shared document requires a written reason' );

test( 'a confirmed shared document with a note is allowed' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 1;
is_same( array(), CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'duplicate_ok' => 1, 'duplicate_note' => 'Same PDF covers both language editions.' ) ) ), 'shared plus note passes' );

test( 'a record does not count itself as a duplicate' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$wpdb->var_result = 0;
is_same( array(), CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'publication_id' => 1 ) ) ), 'the excluded record is left out of the count' );

test( 'a broken document is reported before anything else' );
CEAFSN_PP_Test_State::reset();
attach( 12, $fixture_dir . '/missing.pdf' );
$wpdb->var_result = 5;
$blockers = CEAFSN_PP_Validator::publish_blockers( publication_row_data( array( 'pdf_attachment_id' => 12 ) ) );
is_same( 1, count( $blockers ), 'a missing file short-circuits the remaining checks' );
has_substring( 'missing from the media library', implode( ' ', $blockers ), 'the missing file is the only problem reported' );

/**
 * Render the admin editor form for a record.
 *
 * @param array<string,mixed> $row Row data, as the partial receives it.
 * @return string Rendered HTML.
 */
function render_admin_form( array $row ): string {
	$action        = 'edit';
	$id            = (int) ( $row['publication_id'] ?? 1 );
	$items         = array();
	$validation    = null;
	$duplicate_count = 0;
	$shared_with   = array();
	$row           = (object) $row;

	ob_start();
	global $plugin_dir;
	include $plugin_dir . '/admin/partials/publications.php';
	return (string) ob_get_clean();
}

/**
 * Read the value a picker field was rendered with.
 *
 * @param string $html Rendered admin HTML.
 * @param string $id   Field ID.
 * @return string Field value, or an empty string when the field is missing.
 */
function picker_value( string $html, string $id ): string {
	if ( 1 !== preg_match( '/id="' . preg_quote( $id, '/' ) . '"[^>]*value="([^"]*)"/s', $html, $found ) ) {
		return '';
	}

	return html_entity_decode( $found[1], ENT_QUOTES, 'UTF-8' );
}

// -----------------------------------------------------------------------------
section( 'Input validation' );

$admin     = new CEAFSN_PP_Admin();
$admin_ref = new ReflectionClass( $admin );

/**
 * Invoke a private method through reflection.
 *
 * @param ReflectionClass $class  Class with the method.
 * @param string          $method Method name.
 * @param array           $data   Input data.
 * @return array<string,mixed>
 */
function call_admin( ReflectionClass $class, string $method, array $data = array(), ?int $id = null ) {
	$reflection = $class->getMethod( $method );
	$reflection->setAccessible( true );
	$instance   = $class->newInstanceWithoutConstructor();

	return null === $id
		? $reflection->invoke( $instance, $data )
		: $reflection->invoke( $instance, $data, $id );
}

$complete = array(
	'title'              => 'Food Security in the Horn of Africa',
	'content_type'       => 'report',
	'executive_summary'  => 'A regional assessment.',
	'author_institution' => 'CE-AFSN Secretariat',
	'publication_date'   => '2025-03-14',
	'project_status'     => 'completed',
	'cover_image_id'     => 0,
	'cover_image_alt'    => '',
	'pdf_attachment_id'  => 11,
	'page_count'         => 4,
	'doi_citation'       => '',
	'access_level'       => 'public',
	'duplicate_note'     => '',
	'scanned'            => 0,
	'duplicate_ok'       => 0,
	'status'             => 'published',
);

test( 'the editor shows the attached file name, not the alt text' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
attach( 42, $cover_image, 'image/png' );
$html  = render_admin_form( array_merge( $complete, array( 'cover_image_id' => 42, 'cover_image_alt' => 'Chart of regional yields' ) ) );
$field = picker_value( $html, 'ceafsn-pp-cover-field' );
is_same( 'cover.png', $field, 'the cover picker shows the attachment filename' );
has_substring( 'value="Chart of regional yields"', $html, 'the alt text still has its own field' );

test( 'a record with no attachments shows empty picker fields' );
CEAFSN_PP_Test_State::reset();
$html = render_admin_form( array_merge( $complete, array( 'cover_image_id' => 0, 'pdf_attachment_id' => 0 ) ) );
has_substring( 'id="ceafsn-pp-cover-field"', $html, 'the cover field is still rendered' );
is_same( '', picker_value( $html, 'ceafsn-pp-cover-field' ), 'the cover field is empty' );
is_same( '', picker_value( $html, 'ceafsn-pp-pdf-field' ), 'the document field is empty' );

test( 'an attached document is confirmed in the picker' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$html = render_admin_form( $complete );
is_same( 'Document attached', picker_value( $html, 'ceafsn-pp-pdf-field' ), 'the document field confirms the attachment' );

test( 'a deleted cover attachment is reported by ID rather than as empty' );
CEAFSN_PP_Test_State::reset();
$html = render_admin_form( array_merge( $complete, array( 'cover_image_id' => 99 ) ) );
has_substring( 'value="#99"', $html, 'a missing cover file shows its ID' );

test( 'a complete record validates' );
is_same( array(), call_admin( $admin_ref, 'validate_publication', $complete ), 'no errors for a complete record' );

test( 'missing required fields are each reported' );
$cases = array(
	'title'             => 'Title is required.',
	'author_institution' => 'Author or institution is required.',
	'pdf_attachment_id' => 'A PDF attachment is required.',
);
foreach ( $cases as $field => $message ) {
	$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( $field => '' ) ) );
	has_substring( $message, implode( ' ', $errors ), "empty {$field} is reported" );
}

test( 'a malformed publication date is rejected' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'publication_date' => '14/03/2025' ) ) );
has_substring( 'valid date', implode( ' ', $errors ), 'a non-ISO date is rejected' );

test( 'an unknown content type is rejected' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'content_type' => 'blog_post' ) ) );
has_substring( 'Invalid content type', implode( ' ', $errors ), 'a value outside the allow list is rejected' );

test( 'an unknown project status is rejected' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'project_status' => 'pending' ) ) );
has_substring( 'Invalid project status', implode( ' ', $errors ), 'a value outside the allow list is rejected' );

test( 'an unknown access level is rejected' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'access_level' => 'secret' ) ) );
has_substring( 'Invalid access level', implode( ' ', $errors ), 'a value outside the allow list is rejected' );

test( 'a cover image with no alt text is rejected' );
CEAFSN_PP_Test_State::add_attachment( 42, $cover_image, 'image/png' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'cover_image_id' => 42, 'cover_image_alt' => '' ) ) );
has_substring( 'alt text', implode( ' ', $errors ), 'an unlabelled cover image is rejected' );

test( 'a cover image with alt text is accepted' );
is_same( array(), call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'cover_image_id' => 42, 'cover_image_alt' => 'Field staff collecting data in Kajiado.' ) ) ), 'a described cover image passes' );

test( 'a cover that is not an image is rejected' );
CEAFSN_PP_Test_State::add_attachment( 43, $valid_pdf, 'application/pdf' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'cover_image_id' => 43, 'cover_image_alt' => 'The report cover.' ) ) );
has_substring( 'must be an image', implode( ' ', $errors ), 'a posted non-image attachment ID cannot become a cover' );

test( 'a cover that no longer exists is rejected' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'cover_image_id' => 404, 'cover_image_alt' => 'A cover that was deleted.' ) ) );
has_substring( 'must be an image', implode( ' ', $errors ), 'a deleted cover attachment is refused' );

test( 'a malformed citation URL is rejected' );
$errors = call_admin( $admin_ref, 'validate_publication', array_merge( $complete, array( 'doi_citation' => 'not a url' ) ) );
has_substring( 'valid URL', implode( ' ', $errors ), 'a broken citation URL is rejected' );

test( 'field extraction strips tags and coerces values' );
$_POST = array(
	'title'              => '<script>alert(1)</script>Regional <b>Report</b>',
	'content_type'       => 'report',
	'author_institution' => 'CE-AFSN',
	'publication_date'   => '2025-03-14',
	'project_status'     => 'completed',
	'pdf_attachment_id'  => '11abc',
	'page_count'         => '-4',
	'access_level'       => 'public',
	'status'             => 'published',
);
$extracted = call_admin( $admin_ref, 'extract_publication_fields' );
lacks_substring( '<script>', $extracted['title'], 'script tags are stripped from the title' );
is_same( 11, $extracted['pdf_attachment_id'], 'a non-numeric attachment id coerces to an integer' );
is_same( 4, $extracted['page_count'], 'a negative page count becomes a positive integer' );
$_POST = array();

test( 'an unchecked checkbox is stored as zero' );
$_POST = array( 'title' => 'X', 'author_institution' => 'Y', 'publication_date' => '2025-01-01', 'pdf_attachment_id' => 11, 'content_type' => 'report', 'project_status' => 'in_progress', 'access_level' => 'public', 'status' => 'draft' );
$extracted = call_admin( $admin_ref, 'extract_publication_fields' );
is_same( 0, $extracted['scanned'], 'an unticked scanned box is 0' );
is_same( 0, $extracted['duplicate_ok'], 'an unticked confirmation box is 0' );
$_POST = array();

test( 'date normalisation falls back to today' );
is_same( gmdate( 'Y-m-d' ), CEAFSN_PP_DB::normalize_date( 'not a date' ), 'a bad date becomes today' );
is_same( '2025-03-14', CEAFSN_PP_DB::normalize_date( '2025-03-14' ), 'a good date is kept' );

test( 'an empty page count is replaced with the measured value' );
CEAFSN_PP_Test_State::reset();
attach( 11, $valid_pdf );
$measured = call_admin( $admin_ref, 'apply_measured_page_count', array_merge( $complete, array( 'page_count' => 0 ) ) );
is_same( 4, (int) $measured['page_count'], 'the four pages in the document are stored' );

test( 'a page count the admin typed is left for the validator to check' );
$measured = call_admin( $admin_ref, 'apply_measured_page_count', array_merge( $complete, array( 'page_count' => 99 ) ) );
is_same( 99, (int) $measured['page_count'], 'the entered value is not silently overwritten' );

test( 'a missing document leaves the page count empty' );
CEAFSN_PP_Test_State::reset();
$measured = call_admin( $admin_ref, 'apply_measured_page_count', array_merge( $complete, array( 'page_count' => 0, 'pdf_attachment_id' => 12 ) ) );
is_same( 0, (int) $measured['page_count'], 'nothing is invented for a document that cannot be read' );

test( 'a new record is inserted and an existing one updated' );
CEAFSN_PP_Test_State::reset();
call_admin( $admin_ref, 'persist_publication', $complete, 0 );
has_substring( 'INSERT INTO', (string) $wpdb->queries[0], 'a record with no ID is inserted' );
$wpdb->reset_state();
call_admin( $admin_ref, 'persist_publication', $complete, 7 );
// The write is preceded by a read-back so the audit log can capture the
// previous state, so the write is located by content rather than position.
$pp_write = '';
foreach ( (array) $wpdb->queries as $ceafsn_q ) {
	if ( str_contains( (string) $ceafsn_q, 'UPDATE' ) ) {
		$pp_write = (string) $ceafsn_q;
		break;
	}
}
has_substring( 'UPDATE', $pp_write, 'a record with an ID is updated' );
has_substring( '"publication_id":7', $pp_write, 'the update is scoped to that record' );

// -----------------------------------------------------------------------------
section( 'Data layer' );

test( 'sortable columns are a fixed allow list' );
is_same( array( 'title', 'publication_date', 'content_type', 'project_status' ), CEAFSN_PP_DB::sortable_columns(), 'the allow list is fixed' );

test( 'enum allow lists match the specification' );
is_same( array( 'report', 'annual_report', 'policy_brief', 'working_paper', 'strategic_document', 'project' ), CEAFSN_PP_DB::content_types(), 'six content types' );
is_same( array( 'in_progress', 'completed', 'under_review', 'archived' ), CEAFSN_PP_DB::project_statuses(), 'four project statuses' );
is_same( array( 'public', 'members_only' ), CEAFSN_PP_DB::access_levels(), 'two access levels' );

test( 'an injection attempt in the sort column falls back to the default' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'orderby' => 'title; DROP TABLE x' ) );
has_substring( 'ORDER BY publication_date DESC', (string) $wpdb->queries[1], 'the sort column is replaced with the default' );
lacks_substring( 'DROP TABLE', (string) $wpdb->queries[1], 'the injected text never reaches the query' );

test( 'sort direction is a literal, never a parameter' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'order' => 'asc' ) );
has_substring( 'ORDER BY publication_date ASC', (string) $wpdb->queries[1], 'asc is honoured' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'order' => 'sideways' ) );
has_substring( 'ORDER BY publication_date DESC', (string) $wpdb->queries[1], 'anything unrecognised becomes desc' );

test( 'the front end only ever queries published records' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'published_only' => true ) );
has_substring( "status = 'published'", (string) $wpdb->queries[0], 'published_only restricts the count' );
has_substring( "status = 'published'", (string) $wpdb->queries[1], 'published_only restricts the rows' );

test( 'an unknown status filter is ignored' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'status' => 'deleted' ) );
lacks_substring( "status = 'deleted'", (string) $wpdb->queries[0], 'a value outside the allow list adds no clause' );

test( 'members-only records are excluded by default' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array() );
has_substring( "access_level = 'public'", (string) $wpdb->queries[0], 'only public records by default' );

test( 'members-only records are included only when asked' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'include_members_only' => true ) );
lacks_substring( "access_level = 'public'", (string) $wpdb->queries[0], 'the access clause is dropped when members are included' );

test( 'an unknown access level filter is ignored' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'access_level' => 'vip' ) );
lacks_substring( "access_level = 'vip'", (string) $wpdb->queries[0], 'a value outside the allow list adds no clause' );

test( 'a search term is bound as an escaped literal' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'search' => '100%_real' ) );
has_substring( "'%100\\\\%\\\\_real%'", (string) $wpdb->queries[0], 'LIKE wildcards in the search term are escaped, so they match literally' );
has_substring( "executive_summary LIKE", (string) $wpdb->queries[0], 'the summary column is searched' );
has_substring( "doi_citation LIKE", (string) $wpdb->queries[0], 'the citation column is searched' );

test( 'the year filter is bound as an integer' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'year' => '2025' ) );
has_substring( 'YEAR(publication_date) = 2025', (string) $wpdb->queries[0], 'the year is a bound integer' );

test( 'an out-of-range year is ignored' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'year' => 12 ) );
lacks_substring( 'YEAR(publication_date)', (string) $wpdb->queries[0], 'a year below 1900 adds no clause' );

test( 'content type and project status filters are allow listed' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'content_type' => 'report', 'project_status' => 'completed' ) );
has_substring( "content_type = 'report'", (string) $wpdb->queries[0], 'a valid content type filters' );
has_substring( "project_status = 'completed'", (string) $wpdb->queries[0], 'a valid project status filters' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'content_type' => 'nope' ) );
lacks_substring( 'content_type =', (string) $wpdb->queries[0], 'an invalid content type adds no clause' );

test( 'pagination arguments are clamped' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'page' => -5, 'per_page' => -5 ) );
has_substring( 'LIMIT 1 OFFSET 0', (string) $wpdb->queries[1], 'a negative page and size clamp to the first page' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::get_publications( array( 'page' => 3, 'per_page' => 10 ) );
has_substring( 'LIMIT 10 OFFSET 20', (string) $wpdb->queries[1], 'page three starts at offset twenty' );

test( 'the year list only counts published records' );
CEAFSN_PP_Test_State::reset();
$wpdb->results_queue = array( array( (object) array( 'year' => '2025' ), (object) array( 'year' => '2024' ) ) );
is_same( array( 2025, 2024 ), CEAFSN_PP_DB::get_years(), 'years are returned newest first as integers' );
has_substring( "status = 'published'", (string) $wpdb->queries[0], 'draft years are not offered as filters' );

test( 'attachment usage count excludes the record itself' );
CEAFSN_PP_Test_State::reset();
$wpdb->var_result = 3;
is_same( 3, CEAFSN_PP_DB::attachment_usage_count( 11, 4 ), 'the count is returned' );
has_substring( 'publication_id <> 4', (string) $wpdb->queries[0], 'the edited record is excluded' );
has_substring( 'pdf_attachment_id = 11', (string) $wpdb->queries[0], 'the attachment is bound' );

test( 'a zero attachment has no usage' );
CEAFSN_PP_Test_State::reset();
is_same( 0, CEAFSN_PP_DB::attachment_usage_count( 0 ), 'no query is run for a missing attachment' );
is_same( 0, count( CEAFSN_PP_DB::get_attached_others( 0 ) ), 'no shared records are returned for a missing attachment' );

test( 'the export includes members-only records' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::export_all();
lacks_substring( "access_level = 'public'", (string) $wpdb->queries[0], 'an export must not silently drop restricted records' );

test( 'enums are coerced on write' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::insert_publication( array_merge( $complete, array( 'content_type' => 'DROP TABLE', 'status' => 'nope', 'access_level' => 'vip', 'project_status' => 'pending' ) ) );
$written = (string) $wpdb->queries[0];
has_substring( '"content_type":"report"', $written, 'an invalid content type falls back to report' );
has_substring( '"status":"draft"', $written, 'an invalid status falls back to draft' );
has_substring( '"access_level":"public"', $written, 'an invalid access level falls back to public' );
has_substring( '"project_status":"in_progress"', $written, 'an invalid project status falls back to in_progress' );

// -----------------------------------------------------------------------------
// Regression: $wpdb casts values positionally by $format. An ENUM column paired
// with %d destroys the value, so every column must be paired with a specifier
// that matches the PHP type prepare_row() produces for it.
// -----------------------------------------------------------------------------

test( 'every enum column is formatted as a string' );
$pp_formats = CEAFSN_PP_DB::column_formats();
foreach ( array( 'content_type', 'status', 'project_status', 'access_level' ) as $pp_enum ) {
	is_same( '%s', $pp_formats[ $pp_enum ] ?? 'missing', "{$pp_enum} is an ENUM and must be %s, never %d" );
}

test( 'every integer column is formatted as an integer' );
foreach ( array( 'cover_image_id', 'pdf_attachment_id', 'page_count', 'scanned', 'duplicate_ok', 'updated_by' ) as $pp_int ) {
	is_same( '%d', $pp_formats[ $pp_int ] ?? 'missing', "{$pp_int} is an integer and must be %d" );
}

test( 'every text and date column is formatted as a string' );
foreach ( array( 'title', 'executive_summary', 'author_institution', 'publication_date', 'cover_image_alt', 'doi_citation', 'duplicate_note' ) as $pp_text ) {
	is_same( '%s', $pp_formats[ $pp_text ] ?? 'missing', "{$pp_text} is a string column and must be %s" );
}

test( 'the format list covers every column exactly once' );
is_same(
	17,
	count( $pp_formats ),
	'the publications table has seventeen writable columns and each needs one specifier'
);
is_same(
	count( $pp_formats ),
	count( array_unique( array_keys( $pp_formats ) ) ),
	'no column is listed twice in the format map'
);

test( 'the format specifier count matches the values actually written' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::insert_publication( $complete );
is_same(
	count( CEAFSN_PP_DB::column_formats() ),
	count( $wpdb->inserted_rows[0] ),
	'insert writes one value per format specifier, so positional casting is unambiguous'
);

test( 'enum values survive an insert without numeric coercion' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::insert_publication(
	array_merge(
		$complete,
		array(
			'project_status' => 'in_progress',
			'access_level'   => 'members_only',
			'content_type'   => 'working_paper',
			'status'         => 'published',
		)
	)
);
$pp_stored = $wpdb->inserted_rows[0];
is_same( 'in_progress', $pp_stored['project_status'], 'project_status must not be cast to 0' );
is_same( 'members_only', $pp_stored['access_level'], 'access_level must not be cast to 0' );
is_same( 'working_paper', $pp_stored['content_type'], 'content_type must not be cast to 0' );
is_same( 'published', $pp_stored['status'], 'status must not be cast to 0' );
is_same( 'members_only', CEAFSN_PP_DB::access_levels()[1], 'the members_only value is a real enum member' );
ok( 'in_progress' !== '0', 'a %d specifier would have reduced in_progress to 0' );

test( 'enum values survive an update without numeric coercion' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::update_publication(
	7,
	array_merge( $complete, array( 'project_status' => 'under_review', 'access_level' => 'members_only' ) )
);
is_same( 'under_review', $wpdb->updated_rows[0]['project_status'], 'update must not cast project_status' );
is_same( 'members_only', $wpdb->updated_rows[0]['access_level'], 'update must not cast access_level' );

test( 'integer columns stay integers and are not stringified' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_DB::insert_publication(
	array_merge(
		$complete,
		array(
			'cover_image_id'    => 42,
			'pdf_attachment_id' => 11,
			'page_count'        => 17,
			'scanned'           => 1,
			'duplicate_ok'      => 1,
		)
	)
);
$pp_stored = $wpdb->inserted_rows[0];
foreach ( array( 'cover_image_id' => 42, 'pdf_attachment_id' => 11, 'page_count' => 17, 'scanned' => 1, 'duplicate_ok' => 1, 'updated_by' => 1 ) as $pp_col => $pp_expected ) {
	is_same( $pp_expected, $pp_stored[ $pp_col ], "{$pp_col} must be stored as an integer" );
}

test( 'the harness fails loudly when formats and values disagree' );
$pp_threw = false;
try {
	$wpdb->insert( 'wp_x', array( 'a' => 'one', 'b' => 'two' ), array( '%s' ) );
} catch ( RuntimeException $e ) {
	$pp_threw = true;
}
ok( $pp_threw, 'a short $format list raises, so a positional mismatch cannot pass silently again' );
CEAFSN_PP_Test_State::reset();

// -----------------------------------------------------------------------------
section( 'Public front end' );

/**
 * Render the shortcode with a given data set.
 *
 * @param array<int,object> $rows   Publication rows.
 * @param int              $total  Total matching records.
 * @param array            $atts   Shortcode attributes.
 * @param array            $get    Query string to simulate.
 * @return string Rendered HTML.
 */
function render_library( array $rows = array(), int $total = 0, array $atts = array(), array $get = array(), bool $logged_in = false, array $caps = array() ): string {
	global $wpdb;
	// Registered attachments are fixtures, not per-test state, so keep them and
	// guarantee the document and cover the sample records point at.
	$attachments = CEAFSN_PP_Test_State::$attachments;
	CEAFSN_PP_Test_State::reset();
	CEAFSN_PP_Test_State::$attachments = $attachments;
	CEAFSN_PP_Test_State::add_attachment( 11, $GLOBALS['ceafsn_pp_valid_pdf'] );
	CEAFSN_PP_Test_State::add_attachment( 42, $GLOBALS['ceafsn_pp_cover_image'], 'image/png' );

	// The viewer is staged after the reset, because the reset clears the login
	// and capability state the rendering depends on.
	CEAFSN_PP_Test_State::$options['__logged_in'] = $logged_in;
	CEAFSN_PP_Test_State::$options['__caps']      = $caps;

	// The records under test are handed to the plugin the only way a real
	// request would deliver them: as the result set the query returns.
	$wpdb->results_queue = array( array_values( $rows ) );
	$wpdb->var_result    = $total;

	$_GET = $get;

	$public = new CEAFSN_PP_Public();
	$html   = $public->render_shortcode( $atts );

	$_GET = array();
	return $html;
}

test( 'the empty state is honest' );
$html = render_library();
has_substring( 'No publications or projects have been published yet.', $html, 'the empty state is shown' );
lacks_substring( '<table', $html, 'no table when there is nothing to show' );
lacks_substring( 'View Document', $html, 'no document links in the empty state' );

test( 'the empty state invents nothing' );
$html = render_library();
lacks_substring( '0 pages', $html, 'no fabricated page count' );
lacks_substring( 'KB', $html, 'no fabricated file size' );
lacks_substring( 'Lorem', $html, 'no placeholder text' );

test( 'records render as an accessible grid by default' );
$html = render_library( array( publication_row() ), 1 );
has_substring( 'ceafsn-pp--grid', $html, 'the grid view is the default' );
has_substring( '<ul class="ceafsn-pp-grid"', $html, 'the grid is a list' );
has_substring( 'Food Security in the Horn of Africa', $html, 'the title is rendered' );
has_substring( 'CE-AFSN Secretariat', $html, 'the author is rendered' );
has_substring( '4 pages', $html, 'the page count is rendered' );
has_substring( 'Report', $html, 'the content type label is rendered' );
has_substring( 'Completed', $html, 'the project status label is rendered' );

test( 'the list view renders a sortable table' );
$html = render_library( array( publication_row() ), 1, array(), array( 'pp_view' => 'list' ) );
has_substring( 'ceafsn-pp--list', $html, 'the list view is selected' );
has_substring( '<table class="ceafsn-pp-table"', $html, 'the list view is a table' );
has_substring( '<caption', $html, 'the table has a caption' );
has_substring( 'scope="row"', $html, 'row headers are used' );
has_substring( 'aria-sort=', $html, 'sortable columns report their state' );

test( 'an unknown view name falls back to the grid' );
$html = render_library( array( publication_row() ), 1, array(), array( 'pp_view' => 'sidebar' ) );
has_substring( 'ceafsn-pp--grid', $html, 'an unrecognised view is ignored' );

test( 'the shortcode view attribute is the default' );
$html = render_library( array( publication_row() ), 1, array( 'view' => 'list' ) );
has_substring( 'ceafsn-pp--list', $html, 'the attribute selects the layout' );
CEAFSN_PP_Test_State::$filters = array();
$html = render_library( array( publication_row() ), 1, array( 'view' => 'list' ), array( 'pp_view' => 'grid' ) );
has_substring( 'ceafsn-pp--grid', $html, 'the query string overrides the attribute' );

test( 'both views offer a working toggle' );
foreach ( array( '', 'list' ) as $view ) {
	$html = render_library( array( publication_row() ), 1, array(), array( 'pp_view' => $view ) );
	has_substring( 'pp_view=grid', $html, 'the grid link is present' );
	has_substring( 'pp_view=list', $html, 'the list link is present' );
	has_substring( 'aria-current="true"', $html, 'the active view is announced' );
}

test( 'aria-sort reports the current state' );
$html = render_library( array( publication_row() ), 1, array(), array( 'pp_view' => 'list', 'pp_order' => 'asc' ) );
has_substring( 'aria-sort="ascending"', $html, 'ascending is reported when ascending' );
$html = render_library( array( publication_row() ), 1, array(), array( 'pp_view' => 'list', 'pp_order' => 'desc' ) );
has_substring( 'aria-sort="descending"', $html, 'descending is reported when descending' );

test( 'document links open safely in a new tab' );
$html = render_library( array( publication_row() ), 1 );
has_substring( 'target="_blank"', $html, 'the document opens in a new tab' );
has_substring( 'rel="noopener noreferrer"', $html, 'the new tab cannot reach back' );

test( 'document links are labelled with the record' );
$html = render_library( array( publication_row( array( 'title' => 'Annual Report 2024' ) ) ), 1 );
has_substring( 'aria-label="View document: Annual Report 2024', $html, 'the link names the record instead of repeating "View Document"' );

test( 'a record whose document is gone says so instead of linking' );
$html = render_library( array( publication_row( array( 'pdf_attachment_id' => 999 ) ) ), 1 );
has_substring( 'Document currently unavailable.', $html, 'the missing document is reported' );
lacks_substring( 'href="https://example.test/wp-content/uploads/', $html, 'no link is rendered for a missing file' );

test( 'a members-only record is locked for a logged-out visitor' );
$html = render_library( array( publication_row( array( 'access_level' => 'members_only' ) ) ), 1 );
has_substring( 'Members only', $html, 'the access level is disclosed' );
has_substring( 'Sign in to view this document.', $html, 'the visitor is told why there is no link' );
lacks_substring( 'target="_blank"', $html, 'no document link is exposed' );

test( 'a members-only record is still locked for a signed-in non-member' );
$html = render_library(
	array( publication_row( array( 'access_level' => 'members_only' ) ) ),
	1,
	array(),
	array(),
	true,
	array( 'read' => true )
);
has_substring( 'Sign in to view this document.', $html, 'being signed in is not the same as being a member' );
lacks_substring( 'target="_blank"', $html, 'no document link is exposed to a non-member' );

test( 'a members-only record is readable by a member' );
$html = render_library(
	array( publication_row( array( 'access_level' => 'members_only' ) ) ),
	1,
	array(),
	array(),
	true,
	array( 'read' => true, 'manage_options' => true )
);
has_substring( 'target="_blank"', $html, 'a member gets the document link' );
lacks_substring( 'Sign in to view', $html, 'the lock message is gone for a member' );

test( 'the membership check is filterable' );
CEAFSN_PP_Test_State::reset();
add_filter( 'ceafsn_pp_can_view_members_only', static fn( $allowed ) => true );
ok( CEAFSN_PP_Public::can_view_members_only(), 'a site can grant membership through the filter' );

test( 'a shared document is disclosed on the card' );
$html = render_library( array( publication_row( array( 'duplicate_ok' => 1, 'duplicate_note' => 'Shared with the French edition.' ) ) ), 1 );
has_substring( 'shared with another record', strtolower( $html ), 'the sharing flag is visible to visitors' );

test( 'a shared document is disclosed in the list view too' );
$html = render_library(
	array( publication_row( array( 'duplicate_ok' => 1, 'duplicate_note' => 'Shared with the French edition.' ) ) ),
	1,
	array(),
	array( 'pp_view' => 'list' )
);
has_substring( 'Shared document', $html, 'the list view marks a shared document' );
$html = render_library( array( publication_row() ), 1, array(), array( 'pp_view' => 'list' ) );
lacks_substring( 'Shared document', $html, 'an unshared record is not marked' );

test( 'record values are escaped' );
$html = render_library( array( publication_row( array( 'title' => '<script>alert(1)</script>Report' ) ) ), 1 );
lacks_substring( '<script>', $html, 'script tags never reach the page' );
has_substring( '&lt;script&gt;', $html, 'the title is escaped instead' );

test( 'a cover image is rendered with its alt text' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_Test_State::add_attachment( 42, $cover_image, 'image/png' );
$html = render_library( array( publication_row( array( 'cover_image_id' => 42, 'cover_image_alt' => 'Field staff in Kajiado county.' ) ) ), 1 );
has_substring( 'alt="Field staff in Kajiado county."', $html, 'the cover image carries its description' );
has_substring( 'loading="lazy"', $html, 'the cover image is lazy loaded' );

test( 'a record with no cover image still renders' );
$html = render_library( array( publication_row() ), 1 );
lacks_substring( 'ceafsn-pp-card__cover', $html, 'no empty image element is rendered' );
has_substring( 'ceafsn-pp-card__title', $html, 'the card body is still present' );

test( 'the filter controls are labelled' );
$html = render_library( array( publication_row() ), 1 );
foreach ( array( 'ceafsn-pp-search', 'ceafsn-pp-type', 'ceafsn-pp-status', 'ceafsn-pp-year' ) as $id ) {
	has_substring( 'for="' . $id . '"', $html, "the {$id} control has a label" );
	has_substring( 'id="' . $id . '"', $html, "the {$id} control has an id" );
}

test( 'the view toggle is a labelled group' );
$html = render_library( array( publication_row() ), 1 );
has_substring( 'role="group"', $html, 'the toggle is a group' );
has_substring( 'aria-label="Layout"', $html, 'the group is labelled' );

test( 'the result count is pluralised correctly' );
has_substring( '1 record', render_library( array( publication_row() ), 1 ), 'singular for one' );
has_substring( '4 records', render_library( array(), 4 ), 'plural for several' );

test( 'the results region can receive focus' );
$html = render_library( array( publication_row() ), 1 );
has_substring( 'id="ceafsn-pp-results"', $html, 'the results region has an id' );
has_substring( 'tabindex="-1"', $html, 'the results region is focusable' );

test( 'the result count is announced politely' );
has_substring( 'role="status"', render_library( array( publication_row() ), 1 ), 'the count is a live region' );

test( 'the content type attribute pre-filters the default view' );
CEAFSN_PP_Test_State::reset();
render_library( array(), 0, array( 'content_type' => 'policy_brief' ) );
has_substring( "content_type = 'policy_brief'", (string) $wpdb->queries[0], 'the attribute filters the query' );

test( 'the query string type overrides the shortcode attribute' );
CEAFSN_PP_Test_State::reset();
render_library( array(), 0, array( 'content_type' => 'policy_brief' ), array( 'pp_type' => 'report' ) );
has_substring( "content_type = 'report'", (string) $wpdb->queries[0], 'the query string wins' );
lacks_substring( 'policy_brief', (string) $wpdb->queries[0], 'the attribute does not override the query string' );

test( 'per_page is clamped to a safe range' );
is_same( 12, CEAFSN_PP_Public::clamp_per_page( 0 ), 'zero falls back to the default' );
is_same( 12, CEAFSN_PP_Public::clamp_per_page( -9 ), 'a negative value falls back to the default' );
is_same( 48, CEAFSN_PP_Public::clamp_per_page( 5000 ), 'a huge value is capped' );
is_same( 20, CEAFSN_PP_Public::clamp_per_page( 20 ), 'a sensible value is kept' );

test( 'pagination appears only when there is more than one page' );
lacks_substring( 'ceafsn-pp-pagination', render_library( array( publication_row() ), 1 ), 'no pagination for a single page' );
has_substring( 'ceafsn-pp-pagination', render_library( array( publication_row() ), 40, array( 'per_page' => 10 ) ), 'pagination appears for several pages' );
has_substring( 'Page 1 of 4', render_library( array(), 40, array( 'per_page' => 10 ) ), 'the page position is announced' );

test( 'clear filters only appears when a filter is active' );
lacks_substring( 'Clear filters', render_library( array( publication_row() ), 1 ), 'no clear link with no filters' );
has_substring( 'Clear filters', render_library( array( publication_row() ), 1, array(), array( 'pp_search' => 'food' ) ), 'a clear link appears with an active filter' );
has_substring( 'Clear filters', render_library( array( publication_row() ), 1, array(), array( 'pp_year' => '2025' ) ), 'a year filter also shows the clear link' );

test( 'assets are enqueued once' );
CEAFSN_PP_Test_State::reset();
$public = new CEAFSN_PP_Public();
$public->render_shortcode( array() );
$public->render_shortcode( array() );
is_same( 1, count( CEAFSN_PP_Test_State::$styles ), 'the stylesheet is enqueued once' );
is_same( 1, count( CEAFSN_PP_Test_State::$scripts ), 'the script is enqueued once' );
ok( CEAFSN_PP_Test_State::$scripts[0]['in_footer'], 'the public script loads in the footer' );

test( 'the public script needs no jQuery' );
ok( array() === CEAFSN_PP_Test_State::$scripts[0]['deps'], 'the public script has no library dependencies' );

test( 'file sizes are read from disk, not invented' );
CEAFSN_PP_Test_State::reset();
CEAFSN_PP_Test_State::add_attachment( 11, $valid_pdf );
CEAFSN_PP_Test_State::add_attachment( 21, $large_pdf );
$small_label = CEAFSN_PP_Public::file_size_label( 11 );
$large_label = CEAFSN_PP_Public::file_size_label( 21 );
ok( '' !== $small_label, 'a real file reports a size' );
ok( (bool) preg_match( '/^(0 B|[\\d,]+ B)$/', $small_label ), 'a sub-kilobyte file is reported in bytes (' . $small_label . ')' );
has_substring( ' KB', $large_label, 'a larger file is reported in kilobytes' );
is_same( '', CEAFSN_PP_Public::file_size_label( 0 ), 'a missing attachment reports nothing' );
is_same( '', CEAFSN_PP_Public::file_size_label( 999 ), 'a deleted file reports nothing' );
CEAFSN_PP_Test_State::add_attachment( 22, $empty_attached );
is_same( '', CEAFSN_PP_Public::file_size_label( 22 ), 'an empty file reports nothing' );

test( 'a megabyte file is not reported in kilobytes' );
CEAFSN_PP_Test_State::add_attachment( 23, $huge_pdf );
ok( str_ends_with( CEAFSN_PP_Public::file_size_label( 23 ), ' MB' ), 'a large file switches to megabytes' );

test( 'human labels exist for every enum value' );
foreach ( CEAFSN_PP_DB::content_types() as $type ) {
	$label = CEAFSN_PP_Public::type_label( $type );
	ok( '' !== $label && $label !== $type, "content type {$type} has a human label" );
}
foreach ( CEAFSN_PP_DB::project_statuses() as $status_key ) {
	$label = CEAFSN_PP_Public::status_label( $status_key );
	ok( '' !== $label && $label !== $status_key, "project status {$status_key} has a human label" );
}

// -----------------------------------------------------------------------------
section( 'Route migration' );

test( 'the legacy path redirects permanently to the publications page' );
$case = ceafsn_pp_test_run_redirect_case( 'legacy-404' );
is_same( 1, count( $case['redirects'] ), 'a redirect is issued' );
is_same( 301, (int) ( $case['redirects'][0]['status'] ?? 0 ), 'the redirect is a permanent 301' );
is_same( 'https://example.test/publications/', (string) ( $case['redirects'][0]['location'] ?? '' ), 'the target is the publications page' );
ok( $case['exited'], 'the request ends after the redirect, as WordPress requires' );

test( 'a query string on the legacy URL does not block the redirect' );
$case = ceafsn_pp_test_run_redirect_case( 'legacy-query' );
is_same( 1, count( $case['redirects'] ), 'the query string is ignored when matching the path' );
ok( $case['exited'], 'the request still ends' );

test( 'the redirect is not issued for other paths' );
$case = ceafsn_pp_test_run_redirect_case( 'other-path' );
is_same( 0, count( $case['redirects'] ), 'the target page does not redirect to itself' );
ok( ! $case['exited'], 'an unrelated request is left running' );

test( 'a real page at the legacy slug wins over the redirect' );
$case = ceafsn_pp_test_run_redirect_case( 'legacy-page' );
is_same( 0, count( $case['redirects'] ), 'an existing page is left alone' );
ok( ! $case['exited'], 'the real page is served normally' );

test( 'the redirect can be switched off' );
$case = ceafsn_pp_test_run_redirect_case( 'disabled' );
is_same( 0, count( $case['redirects'] ), 'no redirect when the option is off' );
ok( ! $case['exited'], 'the request is not terminated when the redirect is off' );
CEAFSN_PP_Test_State::reset();
update_option( CEAFSN_PP_Activator::REDIRECT_OPTION, false );
ok( ! CEAFSN_PP_Activator::redirect_enabled(), 'the option reports as disabled' );
CEAFSN_PP_Test_State::reset();

test( 'the redirect is enabled by default when the option was never set' );
ok( CEAFSN_PP_Activator::redirect_enabled(), 'a fresh site redirects without any setting' );

test( 'the redirect target is decided separately from sending the header' );
CEAFSN_PP_Test_State::$is_404 = true;
$_SERVER['REQUEST_URI']        = '/privacy-policy-2/';
is_same( 'https://example.test/publications/', CEAFSN_PP_Activator::legacy_redirect_target(), 'the target is the publications page' );
is_same( 0, count( CEAFSN_PP_Test_State::$redirects ), 'asking for the target sends no header' );
$_SERVER['REQUEST_URI'] = '/privacy-policy-2-without-a-trailing-slash/';
is_same( '', CEAFSN_PP_Activator::legacy_redirect_target(), 'a different path has no target' );
CEAFSN_PP_Test_State::reset();

test( 'the redirect path is read from the request, not the query string' );
$_SERVER['REQUEST_URI'] = '/privacy-policy-2/?a=1';
is_same( '/privacy-policy-2/', CEAFSN_PP_Activator::request_path(), 'the query string is not part of the path' );
$_SERVER['REQUEST_URI'] = '/';
is_same( '/', CEAFSN_PP_Activator::request_path(), 'an empty path is the site root' );

test( 'deactivation keeps the redirect preference and deletes no data' );
$activator_source = (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-pp-activator.php' );
has_substring( 'remove_filter( \'upload_mimes\'', $activator_source, 'deactivation only removes the upload filter' );
lacks_substring( 'delete_option', $activator_source, 'deactivation deletes no options' );
lacks_substring( 'DROP TABLE', $activator_source, 'deactivation drops no tables' );

test( 'the redirect is registered on the front end only' );
ceafsn_pp_test_reload( true );
ok( empty( CEAFSN_PP_Test_State::$actions['template_redirect'] ), 'no redirect is registered in the admin' );
ok( ! empty( CEAFSN_PP_Test_State::$actions['admin_post_ceafsn_pp_save_publication'] ), 'the admin handlers are registered instead' );
ceafsn_pp_test_reload();
ok( ! empty( CEAFSN_PP_Test_State::$actions['template_redirect'] ), 'the redirect is registered on the front end' );
ok( empty( CEAFSN_PP_Test_State::$actions['admin_post_ceafsn_pp_save_publication'] ), 'no admin handlers are registered on the front end' );
ok( ! empty( CEAFSN_PP_Test_State::$shortcodes['ceafsn_projects_pubs'] ), 'the shortcode is registered in both contexts' );
ceafsn_pp_test_reload();

// -----------------------------------------------------------------------------
section( 'Activation' );

test( 'uploads are restricted on this plugin screen only' );
CEAFSN_PP_Test_State::reset();
update_option( '__is_admin', 1 );
CEAFSN_PP_Activator::register_upload_filter();
ok( isset( CEAFSN_PP_Test_State::$filters['upload_mimes'] ), 'the filter is registered on an admin request' );

$_GET = array( 'page' => 'ceafsn-pp' );
$on_plugin_screen = apply_filters(
	'upload_mimes',
	array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'zip'      => 'application/zip',
	)
);
is_same( 'application/pdf', (string) ( $on_plugin_screen['pdf'] ?? '' ), 'the document PDF is offered on the plugin screen' );
ok( ! isset( $on_plugin_screen['zip'] ), 'an unrelated upload type is not offered on the plugin screen' );

// A record has a cover image as well as a document, so a picker that hides
// images would make the cover field impossible to use.
foreach ( array( 'jpg|jpeg', 'png', 'gif', 'webp' ) as $image_group ) {
	ok( isset( $on_plugin_screen[ $image_group ] ), "cover images ( {$image_group} ) can still be chosen" );
}

$_GET = array( 'page' => 'some-other-plugin' );
$other = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' );
is_same( $other, apply_filters( 'upload_mimes', $other ), 'another admin screen keeps every upload type' );

$_GET = array();
$front = array( 'jpg|jpeg' => 'image/jpeg' );
is_same( $front, apply_filters( 'upload_mimes', $front ), 'the front end is untouched' );

CEAFSN_PP_Activator::deactivate();
is_same( $front, apply_filters( 'upload_mimes', $front ), 'the restriction is gone after deactivation' );
$_GET = array();

test( 'activation alone does not register the upload filter' );
CEAFSN_PP_Test_State::reset();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_PP_Activator::activate();
ok( empty( CEAFSN_PP_Test_State::$filters['upload_mimes'] ), 'a filter added at activation would not survive the next request' );
delete_option( '__caps' );

test( 'activation seeds the placeholder list' );
CEAFSN_PP_Test_State::reset();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_PP_Activator::activate();
is_same( array( 'ceafsn.pdf' ), get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'ceafsn.pdf is seeded' );
ok( CEAFSN_PP_Activator::redirect_enabled(), 'the legacy redirect is enabled on activation' );

test( 'activation never overwrites an existing placeholder list' );
CEAFSN_PP_Test_State::reset();
update_option( '__caps', array( 'activate_plugins' => true ) );
update_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION, array( 'legacy.pdf', 'old.pdf' ) );
CEAFSN_PP_Activator::activate();
is_same( array( 'legacy.pdf', 'old.pdf' ), get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'an administrator list survives reactivation' );

test( 'activation never turns a disabled redirect back on' );
CEAFSN_PP_Test_State::reset();
update_option( '__caps', array( 'activate_plugins' => true ) );
update_option( CEAFSN_PP_Activator::REDIRECT_OPTION, false );
CEAFSN_PP_Activator::activate();
is_same( false, CEAFSN_PP_Activator::redirect_enabled(), 'an administrator who switched the redirect off keeps it off' );

test( 'activation without the capability does nothing' );
CEAFSN_PP_Test_State::reset();
$GLOBALS['ceafsn_pp_dbdelta'] = array();
CEAFSN_PP_Activator::activate();
is_same( array(), $GLOBALS['ceafsn_pp_dbdelta'], 'no schema is created without the capability' );
ok( false === get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'no options are written without the capability' );

test( 'activation with the capability creates the schema' );
CEAFSN_PP_Test_State::reset();
$GLOBALS['ceafsn_pp_dbdelta'] = array();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_PP_Activator::activate();
$schema = (string) ( $GLOBALS['ceafsn_pp_dbdelta'][0] ?? '' );
has_substring( 'ceafsn_pp_publications', $schema, 'the table is created' );
has_substring( "ENUM('report','annual_report'", $schema, 'the content type enum is in the schema' );
has_substring( "ENUM('in_progress','completed'", $schema, 'the project status enum is in the schema' );
has_substring( "ENUM('public','members_only')", $schema, 'the access level enum is in the schema' );
has_substring( 'KEY pdf_attachment_id', $schema, 'the duplicate-document lookup is indexed' );
is_same( CEAFSN_PP_DB::SCHEMA_VERSION, get_option( 'ceafsn_pp_db_version' ), 'the schema version is recorded' );

// -----------------------------------------------------------------------------
section( 'Uninstall safety' );

test( 'uninstall refuses to run outside the WordPress uninstall context' );
$output = ceafsn_pp_test_run_uninstall_case( 'no-context' );
is_same( array(), $output['queries'], 'no query is run without the uninstall context' );
is_same( 'no-context', $output['case'], 'the case ran' );

test( 'uninstall keeps data when the opt-in flag is off' );
$output = ceafsn_pp_test_run_uninstall_case( 'flag-off' );
is_same( array(), $output['queries'], 'no query is run without the opt-in' );
ok( ! empty( $output['options'] ), 'the options are still set' );

test( 'uninstall drops the table only after explicit opt-in' );
$output = ceafsn_pp_test_run_uninstall_case( 'flag-on' );
is_same( 1, count( $output['queries'] ), 'exactly one statement is run' );
has_substring( 'DROP TABLE IF EXISTS `wp_ceafsn_pp_publications`', $output['queries'][0], 'the publications table is dropped' );
is_same( array(), $output['options'], 'every plugin option is removed' );

test( 'the uninstall context guard is present' );
$uninstall = (string) file_get_contents( $plugin_dir . '/uninstall.php' );
has_substring( 'WP_UNINSTALL_PLUGIN', $uninstall, 'the uninstall context is checked' );
lacks_substring( 'wp_delete_attachment', $uninstall, 'uninstall never deletes media files' );
lacks_substring( 'wp_delete_post', $uninstall, 'uninstall never deletes posts' );

// -----------------------------------------------------------------------------
section( 'Content hygiene' );

// The harness is excluded from content checks: it is deliberately full of
// fixtures, canned values, and WordPress stubs, and it is never served.
$production_php = ceafsn_pp_test_production_php_files();

test( 'no placeholder copy ships in the plugin' );
$offenders = array();
foreach ( $production_php as $file ) {
	if ( preg_match( '/\bLorem ipsum\b|\bdolor sit amet\b|\bTBD\b|\bFIXME\b|\bXXX\b/', (string) file_get_contents( $file ) ) ) {
		$offenders[] = basename( $file );
	}
}
is_same( array(), $offenders, 'no placeholder copy in any shipped PHP file' );

test( 'no superglobal reaches the page without escaping' );
$offenders = array();
foreach ( $production_php as $file ) {
	foreach ( explode( "\n", (string) file_get_contents( $file ) ) as $line ) {
		if ( ! preg_match( '/(echo|print)\s+[^;]*\$_(GET|POST|REQUEST|COOKIE|SERVER)\b/', $line ) ) {
			continue;
		}
		if ( preg_match( '/\b(esc_html|esc_attr|esc_url|esc_textarea|absint|intval|sanitize_text_field|sanitize_textarea_field|wp_unslash)\s*\(/', $line ) ) {
			continue;
		}
		$offenders[] = basename( $file ) . ': ' . trim( $line );
	}
}
is_same( array(), $offenders, 'every echoed superglobal passes through an escaping function' );

test( 'no debug output' );
$offenders = array();
foreach ( $production_php as $file ) {
	$source = (string) file_get_contents( $file );
	if ( preg_match( '/\b(var_dump|print_r|error_log|wp_die\(\s*var_dump)\s*\(/', $source ) ) {
		$offenders[] = basename( $file );
	}
}
is_same( array(), $offenders, 'no debug helper is left behind' );

test( 'every shipped PHP file guards direct access' );
$unguarded = array();
foreach ( $production_php as $file ) {
	if ( str_ends_with( $file, '/uninstall.php' ) ) {
		// uninstall.php is loaded by WordPress itself, where ABSPATH is not
		// guaranteed, and it guards itself with WP_UNINSTALL_PLUGIN instead.
		continue;
	}
	if ( ! str_contains( (string) file_get_contents( $file ), "defined( 'ABSPATH' ) || exit" ) ) {
		$unguarded[] = basename( $file );
	}
}
is_same( array(), $unguarded, 'every shipped PHP file refuses direct access' );

test( 'every admin write handler checks capabilities and nonces' );
$source = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-pp-admin.php' );
preg_match_all( '/public function (handle_[a-z_]+)\(.*?\n\t\}/s', $source, $handler_matches );
$handlers = $handler_matches[1] ?? array();
is_same( 4, count( $handlers ), 'the four write handlers are present (' . implode( ', ', $handlers ) . ')' );
foreach ( $handlers as $handler ) {
	preg_match( '/public function ' . preg_quote( $handler, '/' ) . '\(.*?\n\t\}/s', $source, $body );
	$body = (string) ( $body[0] ?? '' );
	$ceafsn_want = ( preg_match( '/handle_(save_settings|export|uninstall)/', $handler ) ) ? 'require_manage()' : 'require_edit()';
	$ceafsn_have = ( false !== strpos( $body, 'require_manage();' ) ) ? 'require_manage()' : 'require_edit()';
	is_same( $ceafsn_want, $ceafsn_have, "{$handler} is guarded by the capability that matches what it does" );
	has_substring( $ceafsn_want, $body, "{$handler} checks {$ceafsn_want}" );
	has_substring( 'check_admin_referer', $body, "{$handler} verifies a nonce" );
}
has_substring( "'manage_options'", $source, 'manage_options is still accepted as a fallback capability' );
has_substring( 'private const CAP_EDIT', $source, 'the capability names are mirrored locally so a missing shared library cannot fatal' );

test( 'publishing is gated on document validation in the handler' );
has_substring( 'if ( \'published\' === $data[\'status\'] )', $source, 'the published branch is guarded' );
has_substring( 'CEAFSN_PP_Validator::publish_blockers( $data )', $source, 'the handler calls the validator' );
has_substring( "\$data['status'] = 'draft';", $source, 'a failed publish is downgraded to draft' );

test( 'the record being edited is excluded from its own duplicate count' );
has_substring( "\$data['publication_id'] = \$id;", $source, 'the edited id is passed to the validator' );

test( 'the table name uses the site prefix' );
$db = (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-pp-db.php' );
has_substring( '$wpdb->prefix . self::TABLE_SUFFIX', $db, 'the table name is built from the prefix' );
has_substring( "const TABLE_SUFFIX = 'ceafsn_pp_publications'", $db, 'the table suffix is a constant' );

test( 'referenced asset files exist' );
foreach ( array( 'assets/css/ceafsn-pp-admin.css', 'assets/css/ceafsn-pp-public.css', 'assets/js/ceafsn-pp-admin.js', 'assets/js/ceafsn-pp-public.js' ) as $asset ) {
	ok( file_exists( $plugin_dir . '/' . $asset ), "{$asset} exists" );
}

test( 'CSS is scoped to the component' );
$public_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-pp-public.css' );
ok( ! preg_match( '/^\s*(body|html|p|h1|h2|table|th|td|ul|li|img|a)\s*\{/m', $public_css ), 'no bare element selectors outside the component' );
has_substring( '.ceafsn-pp', $public_css, 'styles are scoped to the component' );
has_substring( 'prefers-reduced-motion', $public_css, 'motion is reduced when the visitor asks for it' );

test( 'the layout reflows to one column on a narrow screen' );
has_substring( 'minmax(min(100%, 17rem), 1fr)', $public_css, 'the grid track can shrink to the viewport width' );

test( 'the public stylesheet paints no single side of an element' );
$guard_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-pp-public.css' );
is_same(
	0,
	(int) preg_match( '/border-(top|right|bottom|left)(-\w+)?\s*:/', $guard_css ),
	'no rule paints one side of an element with a border'
);

test( 'the admin stylesheet paints no single side of an element' );
$admin_guard_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-pp-admin.css' );
is_same(
	0,
	(int) preg_match( '/border-(top|right|bottom|left)(-\w+)?\s*:/', $admin_guard_css ),
	'no admin rule paints one side of an element with a border'
);

test( 'the admin stylesheet is scoped to the component' );
$pp_admin_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-pp-admin.css' );
ok(
	! preg_match( '/(^|\})\s*(body|html|p|h1|h2)\s*\{/m', $pp_admin_css ),
	'no bare element selectors in the admin stylesheet'
);
has_substring( '.ceafsn-pp-wrap', $pp_admin_css, 'admin styles are scoped to the wrapper' );
ok( substr_count( $pp_admin_css, '{' ) === substr_count( $pp_admin_css, '}' ), 'admin CSS braces are balanced' );
foreach ( array( 'ceafsn-np-', 'ceafsn-med-', 'ceafsn-od-' ) as $other ) {
	lacks_substring( $other, $pp_admin_css, "no styles leaked in from {$other}" );
}
foreach ( array( 'ceafsn-app__rail', 'ceafsn-hero', 'ceafsn-stepper', 'ceafsn-kpi-row', 'ceafsn-embed', 'ceafsn-alert--ok', 'ceafsn-btn--danger', 'box-shadow' ) as $component ) {
	has_substring( $component, $pp_admin_css, "the admin stylesheet carries {$component}" );
}

test( 'the admin partials use the branded app layout' );
$pp_pub_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/publications.php' );
$pp_set_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
foreach ( array( 'ceafsn-app__main', 'ceafsn-app__rail', 'ceafsn-hero', 'ceafsn-stepper', 'ceafsn-kpi-row', 'ceafsn-ticks', 'ceafsn-cta', 'ceafsn-empty', 'ceafsn-sr' ) as $component ) {
	has_substring( $component, $pp_pub_partial, "the publications view uses {$component}" );
}
foreach ( array( 'ceafsn-hero', 'ceafsn-tabs', 'ceafsn-check', 'ceafsn-danger', 'ceafsn-embed' ) as $component ) {
	has_substring( $component, $pp_set_partial, "the settings view uses {$component}" );
}
lacks_substring( 'wp-list-table', $pp_pub_partial, 'the legacy WordPress list table is gone' );
lacks_substring( 'nav-tab', $pp_set_partial, 'the legacy nav-tab markup is gone' );
ok( ! preg_match( '/\bonclick=/i', $pp_pub_partial ), 'no inline handlers, so the enhanced delete confirm still works' );

test( 'the settings view tells the administrator which shortcode to use' );
has_substring( '[ceafsn_projects_pubs]', $pp_set_partial, 'the exact shortcode is shown' );
foreach ( array( 'per_page', 'content_type', 'view' ) as $attribute ) {
	has_substring( '<code>' . $attribute . '</code>', $pp_set_partial, "the shortcode card documents {$attribute}" );
}

test( 'the admin markup keeps its JavaScript contracts' );
foreach (
	array(
		'ceafsn-pp-cover-id', 'ceafsn-pp-cover-field', 'ceafsn-pp-cover-button', 'ceafsn-pp-cover-clear',
		'ceafsn-pp-pdf-id', 'ceafsn-pp-pdf-field', 'ceafsn-pp-pdf-button', 'ceafsn-pp-pdf-clear',
	) as $element_id
) {
	has_substring( 'id="' . $element_id . '"', $pp_pub_partial, "the media picker keeps #{$element_id}" );
}
foreach ( array( 'data-target="ceafsn-pp-cover-id"', 'data-target="ceafsn-pp-pdf-id"', 'ceafsn-pp-media__name', 'ceafsn-pp-validation', 'ceafsn-pp-shared-list' ) as $contract ) {
	has_substring( $contract, $pp_pub_partial, "the record view keeps {$contract}" );
}
has_substring( 'class="ceafsn-pp-delete-link"', $pp_pub_partial, 'the delete link keeps its class' );
foreach ( array( 'ceafsn_pp_save_publication', 'ceafsn_pp_publication_nonce', 'ceafsn_pp_nonce', 'ceafsn_pp_delete_publication' ) as $contract ) {
	has_substring( $contract, $pp_pub_partial, "the record form keeps {$contract}" );
}
foreach ( array( 'ceafsn_pp_save_settings', 'ceafsn_pp_settings_nonce', 'ceafsn_pp_nonce' ) as $contract ) {
	has_substring( $contract, $pp_set_partial, "the settings form keeps {$contract}" );
}
has_substring( 'ceafsn_pp_export_nonce', $pp_set_partial, 'the export link keeps its nonce action' );
foreach ( array( 'admin-post.php', 'wp_nonce_field' ) as $contract ) {
	has_substring( $contract, $pp_pub_partial, "the record form keeps {$contract}" );
	has_substring( $contract, $pp_set_partial, "the settings form keeps {$contract}" );
}

test( 'the settings URL uses the declared slug constant' );
$pp_admin_class = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-pp-admin.php' );
has_substring( "const SETTINGS_SLUG = 'ceafsn-pp-settings'", $pp_admin_class, 'the settings page slug is a constant' );
foreach ( array( $pp_pub_partial, $pp_set_partial ) as $partial ) {
	lacks_substring( "admin.php?page=ceafsn-pp-settings", $partial, 'a hard-coded settings URL is gone' );
	has_substring( 'CEAFSN_PP_Admin::SETTINGS_SLUG', $partial, 'the settings URL uses the constant' );
}

test( 'the publications page shows the shortcode too' );
$main_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/publications.php' );
has_substring( '[ceafsn_projects_pubs]', $main_partial, 'the dashboard page shows the exact shortcode' );
foreach ( array( 'per_page', 'content_type', 'view' ) as $shortcode_attribute ) {
	has_substring( $shortcode_attribute, $main_partial, "the dashboard card mentions {$shortcode_attribute}" );
}
has_substring( 'ceafsn-embed__code', $main_partial, 'the dashboard card uses the shared code block' );
has_substring( "&tab=display", $main_partial, 'the card links to the Display settings tab' );

test( 'the translation template exists and covers every translatable string' );
$pot_path = $plugin_dir . '/languages/ceafsn-pp.pot';
ok( file_exists( $pot_path ), 'languages/ceafsn-pp.pot exists' );

if ( file_exists( $pot_path ) ) {
	$pot = (string) file_get_contents( $pot_path );
	has_substring( '"X-Domain: ceafsn-pp\\n"', $pot, 'the template declares the text domain' );

	$pot_msgids = array();
	foreach ( array( 'msgid', 'msgid_plural' ) as $keyword ) {
		preg_match_all( '/^' . $keyword . ' ((?:"(?:[^"\\\\]|\\\\.)*"\s*)+)/m', $pot, $found );
		foreach ( $found[1] ?? array() as $literal ) {
			preg_match_all( '/"((?:[^"\\\\]|\\\\.)*)"/', $literal, $parts );
			$pot_msgids[ str_replace( array( '\\"', '\\n', '\\\\' ), array( '"', "\n", '\\' ), implode( '', $parts[1] ) ) ] = true;
		}
	}

	$uncovered = array();
	foreach ( $production_php as $file ) {
		$source = (string) file_get_contents( $file );

		// Every single-string call, then both halves of each _n() pair.
		preg_match_all( '/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $source, $singles );
		preg_match_all( '/\b_n\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $source, $plurals );

		$candidates = array_merge( $singles[1] ?? array(), $plurals[1] ?? array(), $plurals[2] ?? array() );
		foreach ( $candidates as $literal ) {
			if ( ! isset( $pot_msgids[ stripslashes( $literal ) ] ) ) {
				$uncovered[] = basename( $file ) . ': ' . stripslashes( $literal );
			}
		}
	}
	is_same( array(), $uncovered, 'no translatable string is missing from the template' );
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
section( 'Admin: settings scopes' );

global $plugin_dir;
$pp_settings_admin_ref = new ReflectionClass( new CEAFSN_PP_Admin() );

/**
 * Call PP's private settings writer with a given POST body.
 *
 * @param ReflectionClass $class Admin class.
 * @param array           $post  POST body to save.
 * @return void
 */
function save_pp_settings( ReflectionClass $class, array $post ): void {
	$method = $class->getMethod( 'persist_settings' );
	$method->setAccessible( true );
	$method->invoke( $class->newInstanceWithoutConstructor(), $post );
}

test( 'each PP settings tab only touches the options it owns' );
CEAFSN_PP_Test_State::$options = array(
	CEAFSN_PP_Validator::PLACEHOLDER_OPTION => array( 'keep.pdf' ),
	CEAFSN_PP_Activator::REDIRECT_OPTION     => true,
	'ceafsn_pp_uninstall_delete_data'        => true,
);
save_pp_settings( $pp_settings_admin_ref, array( 'ceafsn_pp_settings_scope' => 'placeholders', 'ceafsn_pp_placeholder_files' => 'new.pdf' ) );
is_same( array( 'new.pdf' ), get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'the placeholder list is saved' );
is_same( true, get_option( CEAFSN_PP_Activator::REDIRECT_OPTION ), 'the Routes tab option survives' );
is_same( true, get_option( 'ceafsn_pp_uninstall_delete_data' ), 'the uninstall opt-in survives' );

save_pp_settings( $pp_settings_admin_ref, array( 'ceafsn_pp_settings_scope' => 'routes' ) );
is_same( false, get_option( CEAFSN_PP_Activator::REDIRECT_OPTION ), 'an unticked redirect saves as false' );
is_same( array( 'new.pdf' ), get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'the placeholder list is not reset' );
is_same( true, get_option( 'ceafsn_pp_uninstall_delete_data' ), 'the uninstall opt-in survives the Routes tab' );

save_pp_settings( $pp_settings_admin_ref, array( 'ceafsn_pp_settings_scope' => 'uninstall', 'ceafsn_pp_uninstall_delete_data' => '1' ) );
is_same( true, get_option( 'ceafsn_pp_uninstall_delete_data' ), 'the opt-in saves as true' );
is_same( array( 'new.pdf' ), get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'the placeholder list is not reset' );
is_same( false, get_option( CEAFSN_PP_Activator::REDIRECT_OPTION ), 'the Routes option is not reset' );

test( 'a missing or unknown scope writes nothing' );
CEAFSN_PP_Test_State::$options = array(
	CEAFSN_PP_Validator::PLACEHOLDER_OPTION => array( 'keep.pdf' ),
	CEAFSN_PP_Activator::REDIRECT_OPTION     => true,
	'ceafsn_pp_uninstall_delete_data'        => false,
);
save_pp_settings( $pp_settings_admin_ref, array( 'ceafsn_pp_placeholder_files' => 'new.pdf', 'ceafsn_pp_uninstall_delete_data' => '1' ) );
is_same( array( 'keep.pdf' ), get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ), 'no scope changes nothing' );
is_same( false, get_option( 'ceafsn_pp_uninstall_delete_data' ), 'the opt-in is not silently enabled' );

test( 'the editable settings forms declare the scope they own' );
$pp_settings_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
foreach ( array( 'placeholders', 'routes', 'uninstall' ) as $scope ) {
	has_substring( 'value="' . $scope . '"', $pp_settings_partial, "the {$scope} scope is posted by its own form" );
}
$pp_admin_class_src = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-pp-admin.php' );
has_substring( 'private function persist_settings( array $post ): void', $pp_admin_class_src, 'the writer is a separate method' );
has_substring( '$this->persist_settings( wp_unslash( $_POST ) );', $pp_admin_class_src, 'the handler delegates to the writer' );
has_substring( '$this->require_manage();', $pp_admin_class_src, 'the capability check still guards the handler' );


// -----------------------------------------------------------------------------
section( 'Translation and status labels' );

test( 'the textdomain is loaded on init, not before' );
$pp_boot = (string) file_get_contents( $plugin_dir . '/ceafsn-projects-publications.php' );
has_substring( "add_action( 'init', 'ceafsn_pp_load_textdomain' );", $pp_boot, 'the loader waits for init' );
ok( ! preg_match( "/add_action\\(\\s*'plugins_loaded'[^)]*load_textdomain/", $pp_boot ), 'WP 6.7 deprecated loading a textdomain earlier than init, so nothing registers it on plugins_loaded' );
has_substring( "'ceafsn-pp',", $pp_boot, 'the domain string is ceafsn-pp' );

test( 'every stored enum value has a translated label' );
foreach ( array( 'draft', 'published', 'archived' ) as $pp_key ) {
	$pp_label = CEAFSN_PP_DB::label( CEAFSN_PP_DB::status_labels(), $pp_key );
	ok( '' !== $pp_label, 'status_labels() returns a non-empty label for "' . $pp_key . '"' );
	ok( $pp_label !== $pp_key, 'and "' . $pp_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'in_progress', 'completed', 'under_review', 'archived' ) as $pp_key ) {
	$pp_label = CEAFSN_PP_DB::label( CEAFSN_PP_DB::project_status_labels(), $pp_key );
	ok( '' !== $pp_label, 'project_status_labels() returns a non-empty label for "' . $pp_key . '"' );
	ok( $pp_label !== $pp_key, 'and "' . $pp_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'report', 'annual_report', 'policy_brief', 'working_paper', 'strategic_document', 'project' ) as $pp_key ) {
	$pp_label = CEAFSN_PP_DB::label( CEAFSN_PP_DB::content_type_labels(), $pp_key );
	ok( '' !== $pp_label, 'content_type_labels() returns a non-empty label for "' . $pp_key . '"' );
	ok( $pp_label !== $pp_key, 'and "' . $pp_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'public', 'members_only' ) as $pp_key ) {
	$pp_label = CEAFSN_PP_DB::label( CEAFSN_PP_DB::access_level_labels(), $pp_key );
	ok( '' !== $pp_label, 'access_level_labels() returns a non-empty label for "' . $pp_key . '"' );
	ok( $pp_label !== $pp_key, 'and "' . $pp_key . '" is not shown as a raw machine key' );
}

test( 'each label map covers the schema exactly' );
is_same( array( 'draft', 'published', 'archived' ), array_keys( CEAFSN_PP_DB::status_labels() ), 'status_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'in_progress', 'completed', 'under_review', 'archived' ), array_keys( CEAFSN_PP_DB::project_status_labels() ), 'project_status_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'report', 'annual_report', 'policy_brief', 'working_paper', 'strategic_document', 'project' ), array_keys( CEAFSN_PP_DB::content_type_labels() ), 'content_type_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'public', 'members_only' ), array_keys( CEAFSN_PP_DB::access_level_labels() ), 'access_level_labels() has one label per stored value, no more and no fewer' );

test( 'an unrecognised key falls back to the key itself' );
is_same( 'Draft', CEAFSN_PP_DB::label( CEAFSN_PP_DB::status_labels(), 'draft' ), 'a known key returns its translated label' );
is_same( 'not_a_status', CEAFSN_PP_DB::label( CEAFSN_PP_DB::status_labels(), 'not_a_status' ), 'an unknown key is shown verbatim, so a missing label is obvious instead of silently English' );

test( 'no partial renders a stored enum through ucfirst()' );
$pp_seen = 0;
foreach ( glob( $plugin_dir . '/admin/partials/*.php' ) ?: array() as $pp_partial ) {
	$pp_seen++;
	ok( ! str_contains( (string) file_get_contents( $pp_partial ), 'ucfirst(' ), basename( $pp_partial ) . ' does not title-case a stored value' );
}
foreach ( glob( $plugin_dir . '/public/partials/*.php' ) ?: array() as $pp_partial ) {
	$pp_seen++;
	ok( ! str_contains( (string) file_get_contents( $pp_partial ), 'ucfirst(' ), basename( $pp_partial ) . ' does not title-case a stored value' );
}
ok( $pp_seen > 0, 'the partials were actually scanned' );

// -----------------------------------------------------------------------------
section( 'Schema migration engine' );

test( 'maybe_upgrade is a no-op when the stored version is current' );
CEAFSN_PP_Test_State::reset();
$GLOBALS['ceafsn_pp_dbdelta'] = array();
update_option( 'ceafsn_pp_db_version', CEAFSN_PP_DB::SCHEMA_VERSION );
is_same( false, CEAFSN_PP_DB::maybe_upgrade(), 'a current schema is left alone' );
is_same( array(), (array) $GLOBALS['ceafsn_pp_dbdelta'], 'and no schema work runs, so admin_init stays cheap' );

test( 'maybe_upgrade does nothing on a downgrade' );
CEAFSN_PP_Test_State::reset();
$GLOBALS['ceafsn_pp_dbdelta'] = array();
update_option( 'ceafsn_pp_db_version', '99.0.0' );
is_same( false, CEAFSN_PP_DB::maybe_upgrade(), 'a newer stored schema is never downgraded' );
is_same( '99.0.0', get_option( 'ceafsn_pp_db_version' ), 'and the stored version is untouched' );

test( 'maybe_upgrade applies dbDelta when the stored version is behind' );
CEAFSN_PP_Test_State::reset();
$GLOBALS['ceafsn_pp_dbdelta'] = array();
is_same( false, get_option( 'ceafsn_pp_db_version', false ), 'no version is recorded on a site that never activated the plugin' );
is_same( true, CEAFSN_PP_DB::maybe_upgrade(), 'a missing schema takes the upgrade path' );
ok( ! empty( (array) $GLOBALS['ceafsn_pp_dbdelta'] ), 'the schema is created through dbDelta' );
is_same( CEAFSN_PP_DB::SCHEMA_VERSION, get_option( 'ceafsn_pp_db_version' ), 'and the version option is brought up to date' );

test( 'maybe_upgrade repairs an older installed version' );
CEAFSN_PP_Test_State::reset();
$GLOBALS['ceafsn_pp_dbdelta'] = array();
update_option( 'ceafsn_pp_db_version', '0.9.0' );
is_same( true, CEAFSN_PP_DB::maybe_upgrade(), 'a version bump is applied' );
ok( ! empty( (array) $GLOBALS['ceafsn_pp_dbdelta'] ), 'dbDelta runs so missing columns and indexes are added' );
is_same( CEAFSN_PP_DB::SCHEMA_VERSION, get_option( 'ceafsn_pp_db_version' ), 'and the option ends at the current version' );

test( 'the migration is hooked to admin_init, not activation alone' );
$med_bootstrap = (string) file_get_contents( $plugin_dir . '/ceafsn-projects-publications.php' );
has_substring( "add_action( 'admin_init', array( 'CEAFSN_PP_DB', 'maybe_upgrade' ) );", $med_bootstrap, 'plugin updates reach existing sites because the hook is on admin_init' );

$pass = $GLOBALS['ceafsn_pp_test_pass'];
$fail = $GLOBALS['ceafsn_pp_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "Assertions: {$pass} passed, {$fail} failed\n";
echo str_repeat( '-', 60 ) . "\n";

exit( $fail > 0 ? 1 : 0 );
