<?php
/**
 * Test runner for CE-AFSN Nutrition Policy.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-nutrition-policy/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_NP
 */

declare( strict_types=1 );

// WordPress is not installed here, so point ABSPATH at a temporary directory
// that contains just enough of wp-admin for the activator to load.
$fake_wp_admin = sys_get_temp_dir() . '/ceafsn-np-fake-wp/wp-admin/includes';

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
	. "\t\t\t\$GLOBALS['ceafsn_np_dbdelta'][] = (string) \$query;\n"
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
final class FakeNpWpdb {

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

	/** @var mixed Value returned by the next get_var() call. */
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
	 * @param array  $format Where formats.
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
}

// -----------------------------------------------------------------------------
// Assertions
// -----------------------------------------------------------------------------

$GLOBALS['ceafsn_np_test_pass']    = 0;
$GLOBALS['ceafsn_np_test_fail']    = 0;
$GLOBALS['ceafsn_np_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_np_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ok( bool $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_np_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_np_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_np_test_current']}] {$message}\n";
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
		++$GLOBALS['ceafsn_np_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_np_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_np_test_current']}] {$message}\n"
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
		++$GLOBALS['ceafsn_np_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_np_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_np_test_current']}] {$message}\n       missing: {$needle}\n";
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
		++$GLOBALS['ceafsn_np_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_np_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_np_test_current']}] {$message}\n       found: {$needle}\n";
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
$fixture_dir = sys_get_temp_dir() . '/ceafsn-np-fixtures';

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

$valid_pdf     = fixture( 'real-policy.pdf', make_pdf( 4 ) );
$one_page_pdf  = fixture( 'single-page.pdf', make_pdf( 1 ) );
$truncated_pdf = fixture( 'truncated.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Page >>\nendobj\n" );
$not_a_pdf     = fixture( 'notes.txt', "This is a plain text file pretending to be a document.\n" );
$empty_pdf     = fixture( 'empty.pdf', '' );
$placeholder   = fixture( 'ceafsn.pdf', make_pdf( 1 ) );
$image_pdf     = fixture( 'scan.png', "\x89PNG\r\n\x1a\nnot a pdf at all" );

/**
 * Register a fixture as a Media Library attachment.
 *
 * @param int    $id    Attachment ID.
 * @param string $path  Absolute path.
 * @param string $mime  MIME type.
 */
function attach( int $id, string $path, string $mime = 'application/pdf' ): void {
	CEAFSN_NP_Test_State::add_attachment( $id, $path, $mime );
}

/**
 * Build a policy row.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return object Policy row.
 */
function policy_row( array $overrides = array() ): object {
	return (object) array_merge(
		array(
			'policy_id'             => 1,
			'title'                 => 'National Nutrition Policy',
			'description'           => 'A summary of the national nutrition policy.',
			'publication_date'      => '2025-03-14',
			'topic'                 => 'Nutrition policy',
			'authoring_institution' => 'Ministry of Health',
			'pdf_attachment_id'     => 11,
			'pdf_filename'          => 'real-policy.pdf',
			'source_url'            => '',
			'status'                => 'published',
		),
		$overrides
	);
}

// -----------------------------------------------------------------------------
// Load plugin
// -----------------------------------------------------------------------------

$plugin_source = (string) file_get_contents( $plugin_dir . '/ceafsn-nutrition-policy.php' );
require_once $plugin_dir . '/ceafsn-nutrition-policy.php';

global $wpdb;
$wpdb = new FakeNpWpdb();

section( 'Plugin bootstrap' );

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', $plugin_source, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_NP_VERSION' ), 'CEAFSN_NP_VERSION defined' );
ok( defined( 'CEAFSN_NP_PLUGIN_DIR' ), 'CEAFSN_NP_PLUGIN_DIR defined' );
is_same( '1.0.0', CEAFSN_NP_VERSION, 'version constant matches header' );

test( 'all plugin classes loaded' );
foreach ( array( 'CEAFSN_NP_DB', 'CEAFSN_NP_Validator', 'CEAFSN_NP_Activator', 'CEAFSN_NP_Admin', 'CEAFSN_NP_Public' ) as $class ) {
	ok( class_exists( $class ), "class {$class} exists" );
}

test( 'shortcode is registered on plugins_loaded' );
ok( isset( CEAFSN_NP_Test_State::$actions['plugins_loaded'] ), 'bootstrap registers a plugins_loaded callback' );
ceafsn_np_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_NP_Test_State::$shortcodes['ceafsn_policy_table'] ), '[ceafsn_policy_table] registered' );

// -----------------------------------------------------------------------------
section( 'PDF validation' );

test( 'a well-formed PDF passes' );
$result = CEAFSN_NP_Validator::inspect_bytes( make_pdf( 4 ) );
ok( $result['valid'], 'valid PDF reported as valid' );
is_same( array(), $result['errors'], 'no errors for a valid PDF' );
is_same( 4, $result['pages'], 'page count read correctly' );

test( 'a single page PDF passes and reports one page' );
$result = CEAFSN_NP_Validator::inspect_bytes( make_pdf( 1 ) );
ok( $result['valid'], 'one page PDF is valid' );
is_same( 1, $result['pages'], 'page count is 1' );

test( 'an empty file is rejected' );
$result = CEAFSN_NP_Validator::inspect_bytes( '' );
ok( ! $result['valid'], 'empty file is not valid' );
is_same( 0, $result['pages'], 'empty file has no pages' );

test( 'a non-PDF file is rejected' );
$result = CEAFSN_NP_Validator::inspect_bytes( 'just some text, no PDF here' );
ok( ! $result['valid'], 'plain text is not a valid PDF' );

test( 'a truncated PDF is rejected' );
$result = CEAFSN_NP_Validator::inspect_bytes( (string) file_get_contents( $truncated_pdf ) );
ok( ! $result['valid'], 'truncated PDF is not valid' );
has_substring( '%%EOF', implode( ' ', $result['errors'] ) . '%%EOF', 'errors mention the truncation' );

test( 'a PDF with no readable page count is rejected' );
$result = CEAFSN_NP_Validator::inspect_bytes( "%PDF-1.4\n%%EOF\n" );
ok( ! $result['valid'], 'PDF with no page objects is not valid' );
is_same( 0, $result['pages'], 'unreadable page count reports zero' );

test( 'page count falls back to the page tree' );
is_same( 12, CEAFSN_NP_Validator::count_pages( '%PDF-1.4 /Type /Pages /Count 12 %%EOF' ), '/Count used when no page objects are visible' );

test( 'the page tree node is not counted as a page' );
is_same( 2, CEAFSN_NP_Validator::count_pages( "%PDF-1.4\n/Type /Page\n/Type /Page\n/Type /Pages\n%%EOF" ), '/Type /Pages is not counted' );

test( 'placeholder filenames are recognised' );
CEAFSN_NP_Test_State::reset();
ok( CEAFSN_NP_Validator::is_placeholder_filename( $placeholder ), 'ceafsn.pdf is a known placeholder' );
ok( CEAFSN_NP_Validator::is_placeholder_filename( '/uploads/2026/01/CEAFSN.PDF' ), 'placeholder match is case insensitive' );
ok( ! CEAFSN_NP_Validator::is_placeholder_filename( $valid_pdf ), 'a real policy PDF is not a placeholder' );

test( 'extra placeholder names can be configured' );
CEAFSN_NP_Test_State::reset();
update_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION, array( 'draft-only.pdf' ) );
ok( CEAFSN_NP_Validator::is_placeholder_filename( 'draft-only.pdf' ), 'configured placeholder is recognised' );
ok( CEAFSN_NP_Validator::is_placeholder_filename( 'ceafsn.pdf' ), 'ceafsn.pdf stays blocked even after reconfiguration' );
CEAFSN_NP_Test_State::reset();

test( 'attachment validation reads the real file' );
CEAFSN_NP_Test_State::reset();
attach( 11, $valid_pdf );
$result = CEAFSN_NP_Validator::validate_attachment( 11 );
ok( $result['valid'], 'a real attachment validates' );
is_same( 4, $result['pages'], 'page count read from disk' );

test( 'a missing attachment ID is rejected' );
CEAFSN_NP_Test_State::reset();
$result = CEAFSN_NP_Validator::validate_attachment( 0 );
ok( ! $result['valid'], 'zero attachment ID is invalid' );
has_substring( 'No PDF is attached', implode( ' ', $result['errors'] ), 'error names the missing attachment' );

test( 'a deleted attachment is rejected' );
CEAFSN_NP_Test_State::reset();
attach( 12, $fixture_dir . '/does-not-exist.pdf' );
$result = CEAFSN_NP_Validator::validate_attachment( 12 );
ok( ! $result['valid'], 'attachment with no file on disk is invalid' );

test( 'a non-PDF attachment is rejected' );
CEAFSN_NP_Test_State::reset();
attach( 13, $not_a_pdf, 'text/plain' );
$result = CEAFSN_NP_Validator::validate_attachment( 13 );
ok( ! $result['valid'], 'a text file is not accepted' );
has_substring( 'not a PDF', implode( ' ', $result['errors'] ), 'error names the type problem' );

test( 'an image renamed to .pdf is rejected' );
CEAFSN_NP_Test_State::reset();
attach( 14, $image_pdf, 'application/pdf' );
$result = CEAFSN_NP_Validator::validate_attachment( 14 );
ok( ! $result['valid'], 'a PNG with a .pdf name is rejected on its signature' );

test( 'the known placeholder is rejected' );
CEAFSN_NP_Test_State::reset();
attach( 15, $placeholder );
$result = CEAFSN_NP_Validator::validate_attachment( 15 );
ok( ! $result['valid'], 'ceafsn.pdf is rejected' );
has_substring( 'placeholder', implode( ' ', $result['errors'] ), 'error explains the placeholder problem' );

test( 'a zero byte attachment is rejected' );
CEAFSN_NP_Test_State::reset();
attach( 16, $empty_pdf );
$result = CEAFSN_NP_Validator::validate_attachment( 16 );
ok( ! $result['valid'], 'a zero byte file is rejected' );

test( 'publish_blockers mirrors validate_attachment' );
CEAFSN_NP_Test_State::reset();
attach( 11, $valid_pdf );
is_same( array(), CEAFSN_NP_Validator::publish_blockers( 11 ), 'no blockers for a valid PDF' );
attach( 15, $placeholder );
is_same( 1, count( CEAFSN_NP_Validator::publish_blockers( 15 ) ), 'one blocker for the placeholder' );

// -----------------------------------------------------------------------------
section( 'Input validation' );

$admin     = new CEAFSN_NP_Admin();
$admin_ref = new ReflectionClass( $admin );

/**
 * Invoke a private method through reflection.
 *
 * @param ReflectionClass $class  Class with the method.
 * @param string          $method Method name.
 * @param array           $data   Input data.
 * @return array<string,mixed>
 */
function call_admin( ReflectionClass $class, string $method, array $data = array() ): array {
	$reflection = $class->getMethod( $method );
	$reflection->setAccessible( true );
	return (array) $reflection->invoke( $class->newInstanceWithoutConstructor(), $data );
}

$complete = array(
	'title'                 => 'National Nutrition Policy',
	'description'           => 'A summary.',
	'publication_date'      => '2025-03-14',
	'topic'                 => 'Nutrition policy',
	'authoring_institution' => 'Ministry of Health',
	'pdf_attachment_id'     => 11,
	'source_url'            => '',
	'status'                => 'draft',
);

test( 'a complete record validates' );
is_same( array(), call_admin( $admin_ref, 'validate_policy', $complete ), 'no errors for a valid record' );

test( 'missing required fields are each reported' );
$errors = call_admin( $admin_ref, 'validate_policy', array( 'status' => 'draft' ) );
is_same( 6, count( $errors ), 'six required-field errors reported' );

test( 'a malformed publication date is rejected' );
$errors = call_admin( $admin_ref, 'validate_policy', array_merge( $complete, array( 'publication_date' => '14/03/2025' ) ) );
is_same( 1, count( $errors ), 'non YYYY-MM-DD date rejected' );

test( 'an unknown status is rejected' );
$errors = call_admin( $admin_ref, 'validate_policy', array_merge( $complete, array( 'status' => 'live' ) ) );
is_same( 1, count( $errors ), 'invalid status rejected' );

test( 'a malformed source URL is rejected' );
$errors = call_admin( $admin_ref, 'validate_policy', array_merge( $complete, array( 'source_url' => 'not a url' ) ) );
is_same( 1, count( $errors ), 'invalid source URL rejected' );

test( 'a missing attachment is rejected at save time' );
$errors = call_admin( $admin_ref, 'validate_policy', array_merge( $complete, array( 'pdf_attachment_id' => 0 ) ) );
is_same( 1, count( $errors ), 'zero attachment ID rejected' );

test( 'field extraction strips tags and coerces the attachment ID' );
$_POST = array(
	'title'                 => '  <b>National</b> Nutrition Policy\\',
	'description'           => '<script>alert(1)</script>Summary',
	'publication_date'      => '2025-03-14',
	'topic'                 => 'Nutrition policy',
	'authoring_institution' => 'Ministry of Health',
	'pdf_attachment_id'     => '11abc',
	'source_url'            => 'https://example.org/policy',
	'status'                => 'PUBLISHED',
);
CEAFSN_NP_Test_State::reset();
attach( 11, $valid_pdf );
$fields = call_admin( $admin_ref, 'extract_policy_fields' );
is_same( 'National Nutrition Policy', $fields['title'], 'tags stripped and slashes removed' );
is_same( 'alert(1)Summary', $fields['description'], 'script tags removed from description' );
is_same( 11, $fields['pdf_attachment_id'], 'attachment ID coerced to an integer' );
is_same( 'published', $fields['status'], 'status lowercased by sanitize_key' );
is_same( 'real-policy.pdf', $fields['pdf_filename'], 'filename is resolved from the attachment, not trusted from POST' );
$_POST = array();

test( 'a submitted filename is ignored when the attachment is unknown' );
$_POST = array(
	'title'                 => 'Policy',
	'description'           => 'Summary',
	'publication_date'      => '2025-03-14',
	'topic'                 => 'Topic',
	'authoring_institution' => 'Institution',
	'pdf_attachment_id'     => 999,
	'pdf_filename'          => '../../evil.pdf',
	'status'                => 'draft',
);
CEAFSN_NP_Test_State::reset();
$fields = call_admin( $admin_ref, 'extract_policy_fields' );
is_same( '', $fields['pdf_filename'], 'no filename is stored for an unknown attachment' );
$_POST = array();

test( 'date normalisation falls back to today' );
is_same( '2025-03-14', CEAFSN_NP_DB::normalize_date( '2025-03-14' ), 'valid date passes through' );
is_same( gmdate( 'Y-m-d' ), CEAFSN_NP_DB::normalize_date( 'nonsense' ), 'invalid date falls back to today' );

// -----------------------------------------------------------------------------
section( 'Data layer' );

test( 'sortable columns are a fixed allow list' );
is_same(
	array( 'title', 'publication_date', 'topic', 'authoring_institution' ),
	CEAFSN_NP_DB::sortable_columns(),
	'allow list contains only known columns'
);

test( 'an injection attempt in the sort column falls back to the default' );
CEAFSN_NP_Test_State::reset();
$wpdb->results_queue = array();
CEAFSN_NP_DB::get_policies( array( 'orderby' => 'title; DROP TABLE x' ) );
$query = (string) end( $wpdb->queries );
lacks_substring( 'DROP TABLE', $query, 'no injected SQL in the ORDER BY clause' );
has_substring( 'ORDER BY publication_date DESC', $query, 'falls back to the default column' );

test( 'the front end only ever queries published records' );
CEAFSN_NP_Test_State::reset();
$wpdb->results_queue = array( array(), array() );
CEAFSN_NP_DB::get_policies( array( 'published_only' => true ) );
$query = (string) end( $wpdb->queries );
has_substring( "status = 'published'", $query, 'published_only restricts to published' );

test( 'a search term is bound as an escaped literal' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_DB::get_policies( array( 'search' => "O'Brien" ) );
$query = (string) end( $wpdb->queries );
has_substring( "LIKE '%O\\'Brien%'", $query, 'apostrophe is escaped, not concatenated raw' );
lacks_substring( "O'Brien%", $query, 'the raw apostrophe never reaches the SQL' );

test( 'an unknown status filter is ignored' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_DB::get_policies( array( 'status' => "published' OR 1=1 --" ) );
foreach ( $wpdb->queries as $query ) {
	lacks_substring( 'OR 1=1', (string) $query, 'SQL injection attempt neutralised' );
}

test( 'pagination arguments are clamped' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_DB::get_policies( array( 'per_page' => 0, 'page' => -5 ) );
$query = (string) end( $wpdb->queries );
has_substring( 'LIMIT 1 OFFSET 0', $query, 'per_page and page clamped to safe minimums' );

test( 'sort direction is a literal, never a parameter' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_DB::get_policies( array( 'order' => 'asc; DELETE FROM wp_posts' ) );
$query = (string) end( $wpdb->queries );
lacks_substring( 'DELETE FROM', $query, 'sort direction cannot inject SQL' );
has_substring( 'ORDER BY publication_date DESC', $query, 'invalid direction falls back to DESC' );

test( 'an empty status does not add a status filter' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_DB::get_policies( array( 'status' => '' ) );
$query = (string) end( $wpdb->queries );
lacks_substring( 'status =', (string) $query, 'admin listing can see every status' );

test( 'topic list only returns published, non-empty topics' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_DB::get_topics();
$query = (string) end( $wpdb->queries );
has_substring( "status = 'published'", $query, 'topic list is restricted to published records' );
has_substring( "topic <> ''", $query, 'empty topics are excluded' );

test( 'attachment usage count excludes the record itself' );
CEAFSN_NP_Test_State::reset();
$wpdb->var_result = 2;
is_same( 2, CEAFSN_NP_DB::attachment_usage_count( 11, 4 ), 'usage count is returned' );
$query = (string) end( $wpdb->queries );
has_substring( 'pdf_attachment_id = 11', $query, 'counts by attachment ID' );
has_substring( 'policy_id <> 4', $query, 'excludes the record being edited' );

test( 'export returns every record' );
CEAFSN_NP_Test_State::reset();
$wpdb->results_queue = array( array() );
$export = CEAFSN_NP_DB::export_all();
ok( is_array( $export ), 'export is an array of records' );

// -----------------------------------------------------------------------------
section( 'Public front end' );

/**
 * Render the shortcode with a given data set.
 *
 * @param array<int,object> $policies Policy rows.
 * @param int              $total    Total matching records.
 * @param array<int,object> $topics   Topic rows returned by get_topics().
 * @param array            $atts     Shortcode attributes.
 * @return string Rendered HTML.
 */
function render_table( array $policies, int $total = 1, array $topics = array(), array $atts = array() ): string {
	global $wpdb;
	// Registered attachments are fixtures, not per-test state, so keep them.
	$attachments = CEAFSN_NP_Test_State::$attachments;
	CEAFSN_NP_Test_State::reset();
	CEAFSN_NP_Test_State::$attachments = $attachments;
	$wpdb->queries = array();
	// get_policies: COUNT then rows; then get_topics, which reads ->topic.
	$wpdb->var_result    = $total;
	$wpdb->results_queue = array(
		$policies,
		array_map(
			static function ( string $topic ): object {
				return (object) array( 'topic' => $topic );
			},
			$topics
		),
	);
	$public = new CEAFSN_NP_Public();
	return $public->render_shortcode( $atts );
}

test( 'the topic attribute pre-filters the default view' );
$wpdb->queries        = array();
$wpdb->var_result     = 0;
$wpdb->results_queue  = array( array(), array() );
$_GET = array();
render_table( array(), 0, array(), array( 'topic' => 'FromAttribute' ) );
has_substring( "topic = 'FromAttribute'", (string) $wpdb->queries[1], 'the attribute is used when the query string is empty' );

test( 'the query string topic overrides the shortcode attribute' );
$wpdb->queries       = array();
$wpdb->var_result    = 0;
$wpdb->results_queue = array( array(), array() );
$_GET = array( 'np_topic' => 'FromQuery' );
render_table( array(), 0, array(), array( 'topic' => 'FromAttribute' ) );
has_substring( "topic = 'FromQuery'", (string) $wpdb->queries[1], 'a shared link with a filter wins over the attribute' );
lacks_substring( 'FromAttribute', (string) $wpdb->queries[1], 'the attribute does not override the query string' );
$_GET = array();

test( 'the empty state is honest' );
$html = render_table( array(), 0 );
has_substring( 'No policies have been published yet.', $html, 'empty state shown' );
lacks_substring( '<table', $html, 'no table rendered when there is nothing to show' );
lacks_substring( 'Read Policy', $html, 'no download links in the empty state' );

test( 'the empty state invents nothing' );
lacks_substring( 'Lorem', $html, 'no lorem ipsum' );
lacks_substring( 'sample', $html, 'no sample data' );
lacks_substring( 'demo', $html, 'no demo data' );
lacks_substring( 'coming soon', $html, 'no coming soon text' );
is_same( 0, preg_match( '/>\s*\d[\d,.]*\s*</', $html ), 'no bare numeric filler' );

test( 'records render in an accessible table' );
attach( 11, $valid_pdf );
$html = render_table( array( policy_row() ), 1 );
has_substring( '<table class="ceafsn-np-table">', $html, 'table rendered' );
has_substring( 'role="region"', $html, 'scroll region is announced' );
has_substring( '<caption', $html, 'table has a caption' );
has_substring( 'scope="col"', $html, 'column headers use scope' );
has_substring( 'scope="row"', $html, 'row header uses scope' );
is_same( count( CEAFSN_NP_DB::sortable_columns() ), substr_count( $html, 'aria-sort=' ), 'every sortable column declares aria-sort' );
has_substring( 'role="status"', $html, 'result count is announced politely' );

test( 'aria-sort reports the current state' );
attach( 11, $valid_pdf );
$_GET = array(
	'np_sort'  => 'title',
	'np_order' => 'asc',
);
$html = render_table( array( policy_row() ), 1 );
has_substring( 'aria-sort="ascending"', $html, 'active ascending column reports ascending' );
has_substring( 'aria-sort="none"', $html, 'inactive columns report none' );
has_substring( 'np_order=desc', $html, 'the active column offers the reverse direction' );
$_GET = array();

test( 'document links open safely in a new tab' );
attach( 11, $valid_pdf );
$html = render_table( array( policy_row() ), 1 );
has_substring( 'target="_blank"', $html, 'document opens in a new tab' );
has_substring( 'rel="noopener noreferrer"', $html, 'new tab is protected with noopener and noreferrer' );
has_substring( 'Read Policy', $html, 'read action is labelled' );
has_substring( '(opens National Nutrition Policy in a new tab)', $html, 'new tab is announced to screen readers' );

test( 'a record whose PDF is gone says so instead of linking' );
$html = render_table( array( policy_row( array( 'pdf_attachment_id' => 0 ) ) ), 1 );
has_substring( 'Document currently unavailable', $html, 'missing document is stated plainly' );
lacks_substring( 'Read Policy', $html, 'no broken download link' );

test( 'record values are escaped' );
attach( 11, $valid_pdf );
$html = render_table(
	array(
		policy_row(
			array(
				'title'       => '<script>alert(1)</script>Policy',
				'description' => 'Ampersand & "quotes"',
			)
		),
	),
	1
);
lacks_substring( '<script>alert(1)</script>', $html, 'injected script tag is escaped' );
has_substring( '&lt;script&gt;', $html, 'injected tag rendered as text' );
has_substring( 'Ampersand &amp;', $html, 'ampersand escaped' );
lacks_substring( '&amp;amp;', $html, 'no double escaping' );

test( 'the search and topic controls are labelled' );
$html = render_table( array(), 0 );
has_substring( 'for="ceafsn-np-search"', $html, 'search input has a label' );
has_substring( 'for="ceafsn-np-topic"', $html, 'topic select has a label' );
has_substring( '0 policy records', $html, 'empty result count reported' );

test( 'the result count is pluralised correctly' );
attach( 11, $valid_pdf );
$html = render_table( array( policy_row() ), 1 );
has_substring( '1 policy record', $html, 'singular result count used for one record' );
$html = render_table( array( policy_row() ), 3 );
has_substring( '3 policy records', $html, 'plural result count used for several records' );

test( 'the results region can receive focus' );
$html = render_table( array(), 0 );
has_substring( 'id="ceafsn-np-results"', $html, 'results region is targetable' );
has_substring( 'tabindex="-1"', $html, 'results region is programmatically focusable' );

test( 'clear filters only appears when a filter is active' );
$html = render_table( array(), 0 );
lacks_substring( 'Clear filters', $html, 'no clear link without an active filter' );
$_GET = array( 'np_search' => 'nutrition' );
$html = render_table( array(), 0 );
has_substring( 'Clear filters', $html, 'clear link appears when filtering' );
has_substring( 'value="nutrition"', $html, 'the active search term is preserved in the field' );
$_GET = array();

test( 'pagination appears only when there is more than one page' );
attach( 11, $valid_pdf );
$html = render_table( array( policy_row() ), 1 );
lacks_substring( 'ceafsn-np-pagination', $html, 'no pagination for a single page' );
$html = render_table( array( policy_row() ), 45 );
has_substring( 'ceafsn-np-pagination', $html, 'pagination shown for multiple pages' );
has_substring( 'Page 1 of 3', $html, 'page status reported' );
has_substring( 'rel="next"', $html, 'next link present' );
lacks_substring( 'rel="prev"', $html, 'no previous link on the first page' );

test( 'per_page is clamped to a safe range' );
is_same( 20, CEAFSN_NP_Public::clamp_per_page( 0 ), 'zero falls back to the default' );
is_same( 20, CEAFSN_NP_Public::clamp_per_page( -10 ), 'negative falls back to the default' );
is_same( 100, CEAFSN_NP_Public::clamp_per_page( 5000 ), 'large value clamped to 100' );
is_same( 25, CEAFSN_NP_Public::clamp_per_page( '25' ), 'numeric string accepted' );

test( 'assets are enqueued once' );
CEAFSN_NP_Test_State::reset();
$wpdb->var_result    = 0;
$wpdb->results_queue = array( array(), array() );
$public = new CEAFSN_NP_Public();
$public->render_shortcode( array() );
$public->render_shortcode( array() );
is_same( 1, count( CEAFSN_NP_Test_State::$styles ), 'stylesheet enqueued exactly once' );
is_same( 1, count( CEAFSN_NP_Test_State::$scripts ), 'script enqueued exactly once' );
has_substring( 'ceafsn-np-public.css', (string) ( CEAFSN_NP_Test_State::$styles[0]['src'] ?? '' ), 'stylesheet URL is correct' );
has_substring( 'ceafsn-np-public.js', (string) ( CEAFSN_NP_Test_State::$scripts[0]['src'] ?? '' ), 'script URL is correct' );

// -----------------------------------------------------------------------------
section( 'Activation' );

test( 'uploads are restricted to PDF on the policy screen only' );
CEAFSN_NP_Test_State::reset();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_NP_Activator::activate();
ok(
	empty( CEAFSN_NP_Test_State::$filters['upload_mimes'] ),
	'activation alone does not carry the restriction past that one request'
);
delete_option( '__caps' );

update_option( '__is_admin', 1 );
CEAFSN_NP_Activator::register_upload_filter();
ok( isset( CEAFSN_NP_Test_State::$filters['upload_mimes'] ), 'the filter is registered on an admin request' );

$_GET = array( 'page' => 'ceafsn-np' );
$mimes = apply_filters( 'upload_mimes', array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ) );
is_same( array( 'pdf' => 'application/pdf' ), $mimes, 'only application/pdf is allowed on the policy screen' );

$_GET = array( 'page' => 'some-other-plugin' );
$other = array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' );
is_same( $other, apply_filters( 'upload_mimes', $other ), 'another admin screen keeps every upload type' );

$_GET = array();
$front = array( 'jpg|jpeg' => 'image/jpeg' );
is_same( $front, apply_filters( 'upload_mimes', $front ), 'the front end is untouched' );

CEAFSN_NP_Activator::deactivate();
is_same( $front, apply_filters( 'upload_mimes', $front ), 'the restriction is gone after deactivation' );

test( 'activation seeds the placeholder list' );
CEAFSN_NP_Test_State::reset();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_NP_Activator::activate();
is_same( array( 'ceafsn.pdf' ), get_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION ), 'ceafsn.pdf seeded on first activation' );
update_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION, array( 'custom.pdf' ) );
CEAFSN_NP_Activator::activate();
is_same( array( 'custom.pdf' ), get_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION ), 'an existing list is never overwritten' );
delete_option( '__caps' );

test( 'activation without the capability does nothing' );
CEAFSN_NP_Test_State::reset();
CEAFSN_NP_Activator::activate();
is_same( false, get_option( 'ceafsn_np_db_version', false ), 'no schema version written without activate_plugins' );

test( 'activation with the capability creates the schema' );
CEAFSN_NP_Test_State::reset();
$GLOBALS['ceafsn_np_dbdelta'] = array();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_NP_Activator::activate();
delete_option( '__caps' );
is_same( CEAFSN_NP_DB::SCHEMA_VERSION, get_option( 'ceafsn_np_db_version' ), 'schema version stored' );

$schema = (string) ( $GLOBALS['ceafsn_np_dbdelta'][0] ?? '' );
// Collapse the column padding so the assertions below are readable.
$schema_flat = (string) preg_replace( '/\s+/', ' ', $schema );
has_substring( 'CREATE TABLE wp_ceafsn_np_policies', $schema_flat, 'the policies table is created' );
has_substring( "status ENUM('draft','published','archived')", $schema_flat, 'status is an enum' );
has_substring( 'pdf_attachment_id BIGINT UNSIGNED', $schema_flat, 'the attachment column is present' );
has_substring( 'PRIMARY KEY (policy_id)', $schema_flat, 'the primary key is defined' );
has_substring( 'utf8mb4', $schema_flat, 'charset and collation are applied' );

// -----------------------------------------------------------------------------
section( 'Uninstall safety' );

/**
 * Run one uninstall case in its own process.
 *
 * uninstall.php exits immediately when loaded outside the WordPress uninstall
 * context, so each scenario must run in a separate process.
 *
 * @param string $case Case name.
 * @return array<string,mixed> Decoded result.
 */
function run_uninstall_case( string $case ): array {
	$command  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/uninstall-cases.php' ) . ' ' . escapeshellarg( $case );
	$output   = (string) shell_exec( $command . ' 2>/dev/null' );
	$decoded  = json_decode( $output, true );
	return is_array( $decoded ) ? $decoded : array( 'case' => $case, 'queries' => array() );
}

test( 'uninstall refuses to run outside the WordPress uninstall context' );
$result = run_uninstall_case( 'no-context' );
is_same( 0, count( $result['queries'] ), 'no queries run when WP_UNINSTALL_PLUGIN is undefined' );

test( 'uninstall keeps data when the opt-in flag is off' );
$result = run_uninstall_case( 'flag-off' );
is_same( 0, count( $result['queries'] ), 'no queries run when the delete flag is not set' );

test( 'uninstall drops the table only after explicit opt-in' );
$result = run_uninstall_case( 'flag-on' );
is_same( 1, count( $result['queries'] ), 'one table dropped when opted in' );
has_substring( 'DROP TABLE IF EXISTS `wp_ceafsn_np_policies`', (string) ( $result['queries'][0] ?? '' ), 'the policies table is dropped' );
ok( ! isset( $result['options']['ceafsn_np_uninstall_delete_data'] ), 'the opt-in flag itself is cleared' );
ok( ! isset( $result['options']['ceafsn_np_db_version'] ), 'the schema version option is cleared' );

test( 'the uninstall context guard is present' );
$uninstall_source = (string) file_get_contents( $plugin_dir . '/uninstall.php' );
has_substring( "if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )", $uninstall_source, 'guard against direct invocation' );
has_substring( "get_option( 'ceafsn_np_uninstall_delete_data'", $uninstall_source, 'deletion gated behind the opt-in option' );

// -----------------------------------------------------------------------------
section( 'Content hygiene' );

$php_list = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( 'php' === strtolower( $file->getExtension() ) && ! str_contains( (string) $file->getPathname(), '/tests/' ) ) {
		$php_list[] = (string) $file->getPathname();
	}
}

test( 'no placeholder copy ships in the plugin' );
foreach ( $php_list as $file ) {
	$contents = (string) file_get_contents( $file );
	$haystack = strtolower( $contents );
	lacks_substring( 'lorem ipsum', $haystack, 'no lorem ipsum in ' . basename( $file ) );
	lacks_substring( 'dolor sit amet', $haystack, 'no lorem dolor in ' . basename( $file ) );
	lacks_substring( 'todo: ', $haystack, 'no TODO markers in ' . basename( $file ) );
	lacks_substring( 'example.com', $haystack, 'no example.com placeholders in ' . basename( $file ) );
}

test( 'no raw superglobal output' );
foreach ( $php_list as $file ) {
	$contents = (string) file_get_contents( $file );
	ok(
		! preg_match( '/echo\s+\$_(GET|POST|REQUEST|FILES)\b/', $contents ),
		'no raw echo of superglobals in ' . basename( $file )
	);
}

test( 'every PHP file guards direct access' );
foreach ( $php_list as $file ) {
	if ( str_ends_with( $file, '/uninstall.php' ) ) {
		// uninstall.php runs where ABSPATH is not guaranteed.
		continue;
	}
	has_substring( "defined( 'ABSPATH' ) || exit", (string) file_get_contents( $file ), 'ABSPATH guard in ' . basename( $file ) );
}

test( 'every admin write handler checks capabilities and nonces' );
$admin_source = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-np-admin.php' );
preg_match_all( '/public function (handle_[a-z_]+)\(.*?\n\t\}/s', $admin_source, $handler_matches );
$handlers = $handler_matches[1] ?? array();
ok( count( $handlers ) >= 4, 'all expected write handlers are present (' . count( $handlers ) . ')' );
foreach ( $handlers as $handler ) {
	preg_match( '/public function ' . preg_quote( $handler, '/' ) . '\(.*?\n\t\}/s', $admin_source, $body );
	$body = (string) ( $body[0] ?? '' );
	has_substring( 'require_manage_options()', $body, "{$handler} checks manage_options" );
	has_substring( 'check_admin_referer', $body, "{$handler} verifies a nonce" );
}
has_substring( "'manage_options'", $admin_source, 'manage_options is the required capability' );

test( 'publishing is gated on PDF validation in the handler' );
has_substring( 'CEAFSN_NP_Validator::publish_blockers', $admin_source, 'the save handler consults the validator' );
has_substring( "\$data['status'] = 'draft'", $admin_source, 'an invalid document downgrades the record to draft' );

test( 'the table name uses the site prefix' );
$db_source = (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-np-db.php' );
has_substring( '{$wpdb->prefix}ceafsn_np_policies', $db_source, 'policies table uses $wpdb->prefix' );

test( 'referenced asset files exist' );
foreach ( array( 'assets/css/ceafsn-np-public.css', 'assets/css/ceafsn-np-admin.css', 'assets/js/ceafsn-np-public.js', 'assets/js/ceafsn-np-admin.js' ) as $asset ) {
	ok( file_exists( $plugin_dir . '/' . $asset ), "asset exists: {$asset}" );
}

test( 'CSS is scoped to the component' );
$public_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-np-public.css' );
ok(
	! preg_match( '/(^|\})\s*(body|html|p|h1|h2|p)\s*\{/m', $public_css ),
	'no bare element selectors in the public stylesheet'
);
has_substring( '.ceafsn-np', $public_css, 'public styles are component-scoped' );

// Clean up fixtures.
foreach ( (array) glob( $fixture_dir . '/*' ) as $fixture_file ) {
	if ( is_string( $fixture_file ) ) {
		unlink( $fixture_file );
	}
}
rmdir( $fixture_dir );
unlink( $fake_wp_admin . '/upgrade.php' );
rmdir( $fake_wp_admin );
rmdir( dirname( $fake_wp_admin ) );
rmdir( dirname( $fake_wp_admin, 2 ) );

// -----------------------------------------------------------------------------
$pass = $GLOBALS['ceafsn_np_test_pass'];
$fail = $GLOBALS['ceafsn_np_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "Assertions: {$pass} passed, {$fail} failed\n";
echo str_repeat( '-', 60 ) . "\n";

exit( $fail > 0 ? 1 : 0 );
