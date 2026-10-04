<?php
/**
 * Test runner for CE-AFSN Open Datasets.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-open-datasets/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_NP
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
$fake_wp_admin = sys_get_temp_dir() . '/ceafsn-od-fake-wp/wp-admin/includes';

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
	. "\t\t\t\$GLOBALS['ceafsn_od_dbdelta'][] = (string) \$query;\n"
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
final class FakeOdWpdb {

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

$GLOBALS['ceafsn_od_test_pass']    = 0;
$GLOBALS['ceafsn_od_test_fail']    = 0;
$GLOBALS['ceafsn_od_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_od_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param mixed  $condition Condition (any truthy value).
 * @param string $message   Failure message.
 */
function ok( $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_od_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_od_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_od_test_current']}] {$message}\n";
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
		++$GLOBALS['ceafsn_od_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_od_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_od_test_current']}] {$message}\n"
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
		++$GLOBALS['ceafsn_od_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_od_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_od_test_current']}] {$message}\n       missing: {$needle}\n";
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
		++$GLOBALS['ceafsn_od_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_od_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_od_test_current']}] {$message}\n       found: {$needle}\n";
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
$fixture_dir = sys_get_temp_dir() . '/ceafsn-od-fixtures';

if ( ! is_dir( $fixture_dir ) ) {
	mkdir( $fixture_dir, 0777, true );
}

/**
 * Build a minimal but well-formed ZIP container for tests.
 *
 * A real archive is a local file header per entry, then a central directory.
 * Only the magic numbers matter to the validator, so the payload is filler.
 *
 * @param int $entries Number of files in the archive.
 * @return string Raw ZIP bytes.
 */
function make_zip( int $entries = 2 ): string {
	$raw = '';
	for ( $i = 0; $i < $entries; $i++ ) {
		$raw .= "PK\x03\x04" . str_repeat( "\x00", 20 ) . "data{$i}";
	}
	$raw .= "PK\x01\x02" . str_repeat( "\x00", 20 );
	$raw .= "PK\x05\x06" . str_repeat( "\x00", 18 );
	return $raw;
}

/**
 * Build a minimal but well-formed XLSX container for tests.
 *
 * @return string Raw XLSX bytes.
 */
function make_xlsx(): string {
	$raw  = "PK\x03\x04" . str_repeat( "\x00", 20 ) . "[Content_Types].xml";
	$raw .= "PK\x03\x04" . str_repeat( "\x00", 20 ) . "xl/workbook.xml";
	$raw .= "PK\x05\x06" . str_repeat( "\x00", 18 );
	return $raw;
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

$valid_csv      = fixture( 'food-prices.csv', "district,commodity,price\nNimba,cassava,320\nBong,maize,410\n" );
$header_only    = fixture( 'header-only.csv', "district,commodity,price\n" );
$semicolon_csv  = fixture( 'semi.csv', "district;commodity;price\nNimba;cassava;320\n" );
$binary_csv     = fixture( 'fake.csv', "\x00\x01\x02\x03binary payload" );
$no_header_csv  = fixture( 'blank.csv', "\n\n" );
$valid_zip      = fixture( 'survey.zip', make_zip( 3 ) );
$empty_zip      = fixture( 'empty-archive.zip', "PK\x05\x06" . str_repeat( "\x00", 18 ) );
$truncated_zip  = fixture( 'truncated.zip', "PK\x03\x04" . str_repeat( "\x00", 20 ) );
$valid_xlsx     = fixture( 'workbook.xlsx', make_xlsx() );
$not_a_zip      = fixture( 'readme.txt', "Just text, not an archive.\n" );
$empty_file     = fixture( 'zero.csv', '' );

/**
 * Register a fixture as a Media Library attachment.
 *
 * @param int    $id   Attachment ID.
 * @param string $path Absolute path.
 * @param string $mime MIME type.
 */
function attach( int $id, string $path, string $mime = 'text/csv' ): void {
	CEAFSN_OD_Test_State::add_attachment( $id, $path, $mime );
}

/**
 * Build a dataset row.
 *
 * @param array<string,mixed> $overrides Field overrides.
 * @return object Dataset row.
 */
function dataset_row( array $overrides = array() ): object {
	return (object) array_merge(
		array(
			'dataset_id'         => 1,
			'name'               => 'Monthly food prices',
			'description'        => 'Retail prices for staple commodities by district.',
			'category'           => 'Food prices',
			'coverage_area'      => 'All counties',
			'last_updated'       => '2025-06-01',
			'file_attachment_id' => 11,
			'download_url'       => '',
			'file_type'          => 'csv',
			'file_size'          => 2048,
			'data_license'       => 'CC BY 4.0',
			'methodology_url'    => '',
			'contact_owner'      => 'data@example.org',
			'status'             => 'published',
		),
		$overrides
	);
}

/**
 * Render the front-end table with canned query results.
 *
 * @param array<int,object> $datasets   Dataset rows.
 * @param int               $total      Total matching records.
 * @param array<int,string> $categories Distinct categories.
 * @param array<string,string> $atts   Shortcode attributes.
 * @return string HTML output.
 */
function render_table( array $datasets, int $total = 1, array $categories = array(), array $atts = array() ): string {
	global $wpdb;
	// Registered attachments and options are fixtures a test has set up before
	// calling this helper, so they must survive the per-test reset.
	$attachments = CEAFSN_OD_Test_State::$attachments;
	$options     = CEAFSN_OD_Test_State::$options;
	CEAFSN_OD_Test_State::reset();
	CEAFSN_OD_Test_State::$attachments = $attachments;
	CEAFSN_OD_Test_State::$options     = $options;
	$wpdb->queries = array();
	// get_datasets: COUNT then rows; then get_categories, which reads ->category.
	$wpdb->var_result    = $total;
	$wpdb->results_queue = array(
		$datasets,
		array_map(
			static function ( string $category ): object {
				return (object) array( 'category' => $category );
			},
			$categories
		),
	);
	$public = new CEAFSN_OD_Public();
	return $public->render_shortcode( $atts );
}

/**
 * Call the admin class's private validate_dataset() through reflection.
 *
 * @param array<string,mixed> $data Field values.
 * @return array<int,string> Errors.
 */
function ceafsn_od_test_validate( array $data ): array {
	$admin   = new CEAFSN_OD_Admin();
	$method  = new ReflectionMethod( $admin, 'validate_dataset' );
	$method->setAccessible( true );
	return (array) $method->invoke( $admin, $data );
}

/**
 * Call the admin class's private extract_dataset_fields() through reflection.
 *
 * @param CEAFSN_OD_Admin $admin Admin instance.
 * @return array<string,mixed>
 */
function ceafsn_od_test_extract( CEAFSN_OD_Admin $admin ): array {
	$method = new ReflectionMethod( $admin, 'extract_dataset_fields' );
	$method->setAccessible( true );
	return (array) $method->invoke( $admin );
}

/**
 * Call the DB class's private prepare_row() through reflection.
 *
 * @param array<string,mixed> $data Field values.
 * @return array<string,mixed>
 */
function ceafsn_od_test_prepare_row( array $data ): array {
	$method = new ReflectionMethod( 'CEAFSN_OD_DB', 'prepare_row' );
	$method->setAccessible( true );
	return (array) $method->invoke( null, $data );
}

/**
 * Run uninstall.php in a child process and report what it did.
 *
 * @param bool $delete_data Value for the opt-in option.
 * @return array{queries:array<int,string>,options:array<string,mixed>}
 */
function ceafsn_od_test_uninstall( bool $delete_data ): array {
	$cases = dirname( __DIR__ ) . '/tests/uninstall-cases.php';

	$command = sprintf(
		'%s -d error_reporting=0 %s %s %d %s 2>/dev/null',
		escapeshellarg( PHP_BINARY ),
		escapeshellarg( $cases ),
		$delete_data ? 'flag-on' : 'flag-off',
		$delete_data ? '1' : '0',
		escapeshellarg( $delete_data ? 'yes' : 'no' )
	);

	$output = (string) shell_exec( $command );
	$json   = json_decode( $output, true );

	if ( ! is_array( $json ) ) {
		ok( false, 'the uninstall helper returned invalid JSON: ' . $output );
		return array( 'queries' => array(), 'options' => array() );
	}

	return array(
		'queries' => isset( $json['queries'] ) && is_array( $json['queries'] ) ? $json['queries'] : array(),
		'options' => isset( $json['options'] ) && is_array( $json['options'] ) ? $json['options'] : array(),
	);
}

// -----------------------------------------------------------------------------
// Load plugin
// -----------------------------------------------------------------------------

$plugin_source = (string) file_get_contents( $plugin_dir . '/ceafsn-open-datasets.php' );
require_once $plugin_dir . '/ceafsn-open-datasets.php';

global $wpdb;
$wpdb = new FakeOdWpdb();

section( 'Plugin bootstrap' );

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', $plugin_source, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_OD_VERSION' ), 'CEAFSN_OD_VERSION defined' );
ok( defined( 'CEAFSN_OD_PLUGIN_DIR' ), 'CEAFSN_OD_PLUGIN_DIR defined' );
is_same( '1.0.0', CEAFSN_OD_VERSION, 'version constant matches header' );

test( 'all plugin classes loaded' );
foreach ( array( 'CEAFSN_OD_DB', 'CEAFSN_OD_Validator', 'CEAFSN_OD_Activator', 'CEAFSN_OD_Admin', 'CEAFSN_OD_Public' ) as $class ) {
	ok( class_exists( $class ), "class {$class} exists" );
}

test( 'shortcode is registered on plugins_loaded' );
ok( isset( CEAFSN_OD_Test_State::$actions['plugins_loaded'] ), 'bootstrap registers a plugins_loaded callback' );
ceafsn_od_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_OD_Test_State::$shortcodes['ceafsn_open_datasets'] ), '[ceafsn_open_datasets] registered' );

// -----------------------------------------------------------------------------
section( 'CSV and ZIP validation' );

test( 'a well-formed CSV passes' );
$result = CEAFSN_OD_Validator::inspect_bytes( "district,commodity,price\nNimba,cassava,320\n", 'csv' );
ok( $result['valid'], 'valid CSV reported as valid' );
is_same( array(), $result['errors'], 'no errors for a valid CSV' );
is_same( 1, $result['entries'], 'data rows counted, header excluded' );

test( 'a header row with no data rows is still readable' );
$result = CEAFSN_OD_Validator::inspect_bytes( "district,commodity,price\n", 'csv' );
ok( $result['valid'], 'a header-only CSV is structurally valid' );
is_same( 0, $result['entries'], 'no data rows' );

test( 'a semicolon-delimited CSV is accepted' );
$result = CEAFSN_OD_Validator::inspect_bytes( "district;commodity;price\nNimba;cassava;320\n", 'csv' );
ok( $result['valid'], 'semicolon delimiter detected' );

test( 'a byte order mark does not break the header' );
is_same(
	array( 'district', 'commodity' ),
	CEAFSN_OD_Validator::read_header_row( "\xEF\xBB\xBFdistrict,commodity\nNimba,cassava\n" ),
	'BOM stripped before parsing'
);

test( 'a CSV whose header is all empty cells is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( ",,,\n1,2,3\n", 'csv' );
ok( ! $result['valid'], 'all-empty header rejected' );
is_same( 1, count( $result['errors'] ), 'one error reported' );

test( 'a CSV with no header at all is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( "\n\n", 'csv' );
ok( ! $result['valid'], 'blank file rejected as CSV' );
has_substring( 'header row', implode( ' ', $result['errors'] ), 'the error names the header row' );

test( 'a binary file renamed to .csv is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( "\x00\x01\x02binary", 'csv' );
ok( ! $result['valid'], 'binary payload rejected as CSV' );

test( 'an empty file is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( '', 'csv' );
ok( ! $result['valid'], 'empty file rejected' );
is_same( 0, $result['entries'], 'no entries in an empty file' );

test( 'a well-formed ZIP passes' );
$result = CEAFSN_OD_Validator::inspect_bytes( make_zip( 3 ), 'zip' );
ok( $result['valid'], 'valid ZIP reported as valid' );
is_same( 3, $result['entries'], 'archive entries counted' );

test( 'a truncated ZIP is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( "PK\x03\x04" . str_repeat( "\x00", 20 ), 'zip' );
ok( ! $result['valid'], 'ZIP without a central directory rejected' );
has_substring( 'truncated', implode( ' ', $result['errors'] ), 'the error mentions truncation' );

test( 'an empty ZIP archive is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( "PK\x05\x06" . str_repeat( "\x00", 18 ), 'zip' );
ok( ! $result['valid'], 'ZIP with no entries rejected' );
has_substring( 'no files', implode( ' ', $result['errors'] ), 'the error says the archive is empty' );

test( 'a text file renamed to .zip is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( "Just text, not an archive.\n", 'zip' );
ok( ! $result['valid'], 'non-ZIP rejected' );
has_substring( 'not a ZIP', implode( ' ', $result['errors'] ), 'the error names the wrong type' );

test( 'a well-formed XLSX passes' );
$result = CEAFSN_OD_Validator::inspect_bytes( make_xlsx(), 'xlsx' );
ok( $result['valid'], 'valid XLSX reported as valid' );
is_same( 2, $result['entries'], 'workbook parts counted' );

test( 'a ZIP without the XLSX content types part is rejected' );
$result = CEAFSN_OD_Validator::inspect_bytes( make_zip( 2 ), 'xlsx' );
ok( ! $result['valid'], 'a plain ZIP is not a valid XLSX' );
has_substring( '[Content_Types].xml', implode( ' ', $result['errors'] ), 'the error names the missing part' );

test( 'the other file type only needs a non-zero body' );
$result = CEAFSN_OD_Validator::inspect_bytes( 'any bytes at all', 'other' );
ok( $result['valid'], 'other file type accepted without structure checks' );
ok( ! CEAFSN_OD_Validator::inspect_bytes( '', 'other' )['valid'], 'but still not when empty' );

test( 'an unknown file type falls back to other' );
ok( CEAFSN_OD_Validator::inspect_bytes( 'bytes', 'exe' )['valid'], 'unknown type treated as other' );

test( 'MIME types are checked per declared file type' );
ok( CEAFSN_OD_Validator::is_allowed_mime( 'text/csv', 'csv' ), 'text/csv allowed for CSV' );
ok( CEAFSN_OD_Validator::is_allowed_mime( 'text/plain', 'csv' ), 'text/plain allowed for CSV' );
ok( ! CEAFSN_OD_Validator::is_allowed_mime( 'image/png', 'csv' ), 'image/png rejected for CSV' );
ok( CEAFSN_OD_Validator::is_allowed_mime( 'application/zip', 'zip' ), 'application/zip allowed for ZIP' );
ok( ! CEAFSN_OD_Validator::is_allowed_mime( 'text/csv', 'zip' ), 'CSV rejected for ZIP' );
ok(
	CEAFSN_OD_Validator::is_allowed_mime( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx' ),
	'OOXML mime allowed for XLSX'
);
ok( CEAFSN_OD_Validator::is_allowed_mime( 'image/png', 'other' ), 'other accepts anything' );

test( 'delimiter detection' );
is_same( ',', CEAFSN_OD_Validator::detect_delimiter( 'a,b,c' ), 'comma' );
is_same( ';', CEAFSN_OD_Validator::detect_delimiter( 'a;b;c' ), 'semicolon' );
is_same( "\t", CEAFSN_OD_Validator::detect_delimiter( "a\tb\tc" ), 'tab' );
is_same( '|', CEAFSN_OD_Validator::detect_delimiter( 'a|b|c' ), 'pipe' );

test( 'byte sizes are formatted for humans' );
is_same( '512 B', CEAFSN_OD_Validator::format_size( 512 ), 'bytes' );
is_same( '1.0 KB', CEAFSN_OD_Validator::format_size( 1024 ), 'kilobytes' );
is_same( '1.5 MB', CEAFSN_OD_Validator::format_size( 1572864 ), 'megabytes' );
is_same( 'Not available', CEAFSN_OD_Validator::format_size( 0 ), 'unknown size never shows a fake number' );

// -----------------------------------------------------------------------------
section( 'Attachment validation' );

test( 'a valid CSV attachment passes' );
attach( 1, $valid_csv, 'text/csv' );
$result = CEAFSN_OD_Validator::validate_attachment( 1, 'csv' );
ok( $result['valid'], 'valid CSV attachment accepted' );
is_same( 2, $result['entries'], 'data rows counted from disk, header excluded' );

test( 'a valid ZIP attachment passes' );
attach( 2, $valid_zip, 'application/zip' );
ok( CEAFSN_OD_Validator::validate_attachment( 2, 'zip' )['valid'], 'valid ZIP attachment accepted' );

test( 'a valid XLSX attachment passes' );
attach( 3, $valid_xlsx, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
ok( CEAFSN_OD_Validator::validate_attachment( 3, 'xlsx' )['valid'], 'valid XLSX attachment accepted' );

test( 'a missing attachment ID is rejected' );
$result = CEAFSN_OD_Validator::validate_attachment( 0, 'csv' );
ok( ! $result['valid'], 'zero attachment ID rejected' );
has_substring( 'No dataset file', implode( ' ', $result['errors'] ), 'the error explains what is missing' );

test( 'an attachment deleted from the media library is rejected' );
$result = CEAFSN_OD_Validator::validate_attachment( 999, 'csv' );
ok( ! $result['valid'], 'unknown attachment ID rejected' );
has_substring( 'missing from the media library', implode( ' ', $result['errors'] ), 'the error says the file is gone' );

test( 'a MIME type that contradicts the declared file type is rejected' );
attach( 4, $valid_zip, 'application/zip' );
$result = CEAFSN_OD_Validator::validate_attachment( 4, 'csv' );
ok( ! $result['valid'], 'ZIP declared as CSV rejected' );
has_substring( 'unexpected type', implode( ' ', $result['errors'] ), 'the error names the MIME mismatch' );

test( 'a zero-byte attachment is rejected' );
attach( 5, $empty_file, 'text/csv' );
$result = CEAFSN_OD_Validator::validate_attachment( 5, 'csv' );
ok( ! $result['valid'], 'zero-byte file rejected' );
has_substring( 'zero bytes', implode( ' ', $result['errors'] ), 'the error says the file is empty' );

test( 'a header-only CSV attachment is accepted' );
attach( 6, $header_only, 'text/csv' );
ok( CEAFSN_OD_Validator::validate_attachment( 6, 'csv' )['valid'], 'a header-only CSV is a real file' );

test( 'a CSV with no readable header is rejected on disk' );
attach( 7, $no_header_csv, 'text/csv' );
ok( ! CEAFSN_OD_Validator::validate_attachment( 7, 'csv' )['valid'], 'blank file rejected on disk' );

test( 'a binary file renamed to .csv is rejected on disk' );
attach( 8, $binary_csv, 'text/csv' );
ok( ! CEAFSN_OD_Validator::validate_attachment( 8, 'csv' )['valid'], 'binary payload rejected on disk' );

test( 'a text file renamed to .zip is rejected on disk' );
attach( 9, $not_a_zip, 'application/zip' );
ok( ! CEAFSN_OD_Validator::validate_attachment( 9, 'zip' )['valid'], 'text file rejected as ZIP' );

// -----------------------------------------------------------------------------
section( 'External download URLs' );

test( 'a URL that returns 200 with a size is accepted' );
update_option(
	'__http',
	array( 'https://data.example.org/prices.csv' => array( 200, array( 'content-length' => '40960', 'content-type' => 'text/csv' ) ) )
);
$result = CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/prices.csv', 'csv' );
ok( $result['valid'], 'HTTP 200 with a size is valid' );
is_same( 40960, $result['size'], 'reported size is read from the header' );
is_same( array( 'https://data.example.org/prices.csv' ), CEAFSN_OD_Test_State::$http_requests, 'a HEAD request was used' );

test( 'a URL that does not return 200 is rejected' );
update_option( '__http', array( 'https://data.example.org/missing.csv' => array( 404, array() ) ) );
$result = CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/missing.csv' );
ok( ! $result['valid'], 'HTTP 404 rejected' );
has_substring( 'HTTP 404', implode( ' ', $result['errors'] ), 'the error reports the status code' );

test( 'a redirect that does not land on 200 is rejected' );
update_option( '__http', array( 'https://data.example.org/moved' => array( 301, array() ) ) );
ok( ! CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/moved' )['valid'], 'HTTP 301 rejected' );

test( 'a 200 response with no content-length is rejected' );
update_option( '__http', array( 'https://data.example.org/stream' => array( 200, array() ) ) );
$result = CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/stream' );
ok( ! $result['valid'], 'unknown size rejected' );
has_substring( 'did not report a file size', implode( ' ', $result['errors'] ), 'the error explains the missing size' );

test( 'a zero content-length is rejected' );
update_option( '__http', array( 'https://data.example.org/zero' => array( 200, array( 'content-length' => '0' ) ) ) );
ok( ! CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/zero' )['valid'], 'zero-byte download rejected' );

test( 'a non-HTTP scheme is rejected before any request is made' );
CEAFSN_OD_Test_State::reset();
update_option( '__http', array() );
$result = CEAFSN_OD_Validator::validate_external_url( 'ftp://data.example.org/prices.csv' );
ok( ! $result['valid'], 'an ftp:// URL is rejected' );
has_substring( 'http:// or https://', implode( ' ', $result['errors'] ), 'the error names the accepted schemes' );
is_same( array(), CEAFSN_OD_Test_State::$http_requests, 'no request is sent for a scheme that cannot be probed' );

test( 'the served content type has to match the declared file type' );
update_option(
	'__http',
	array( 'https://data.example.org/prices.csv' => array( 200, array( 'content-length' => '10', 'content-type' => 'application/vnd.ms-excel' ) ) )
);
$result = CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/prices.csv', 'zip' );
ok( ! $result['valid'], 'a spreadsheet served as a ZIP is rejected' );
has_substring( 'does not match the declared file type', implode( ' ', $result['errors'] ), 'the error explains the mismatch' );

test( 'content type parameters are ignored' );
update_option(
	'__http',
	array( 'https://data.example.org/prices.csv' => array( 200, array( 'content-length' => '10', 'content-type' => 'text/csv; charset=UTF-8' ) ) )
);
ok( CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/prices.csv', 'csv' )['valid'], 'charset parameters do not cause a false rejection' );
is_same( 'text/csv', CEAFSN_OD_Validator::strip_mime_parameters( 'text/CSV; charset=UTF-8' ), 'parameters are stripped and the type is lower-cased' );

test( 'a server that sends no content type is not rejected on that basis alone' );
update_option( '__http', array( 'https://data.example.org/prices.csv' => array( 200, array( 'content-length' => '10' ) ) ) );
ok( CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/prices.csv', 'csv' )['valid'], 'an absent content type does not fail the check' );

test( 'the "other" file type skips the content type check' );
update_option(
	'__http',
	array( 'https://data.example.org/notes.pdf' => array( 200, array( 'content-length' => '10', 'content-type' => 'application/pdf' ) ) )
);
ok( CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/notes.pdf', 'other' )['valid'], 'any content type is accepted for "other"' );

test( 'verify_download_target reports the real size' );
CEAFSN_OD_Test_State::reset();
attach( 21, $valid_csv, 'text/csv' );
$target = CEAFSN_OD_Validator::verify_download_target( 21, '', 'csv' );
ok( $target['valid'], 'a valid attachment passes' );
is_same( filesize( $valid_csv ), $target['size'], 'the size is measured on disk' );
$target = CEAFSN_OD_Validator::verify_download_target( 0, '', 'csv' );
ok( ! $target['valid'], 'a record with no route fails' );
is_same( 0, $target['size'], 'no size is invented' );

test( 'a transport failure is an error, not a pass' );
update_option( '__http', array( 'https://data.example.org/down' => new WP_Error( 'http_request_failed', 'Connection timed out' ) ) );
$result = CEAFSN_OD_Validator::validate_external_url( 'https://data.example.org/down' );
ok( ! $result['valid'], 'WP_Error rejected' );
has_substring( 'Connection timed out', implode( ' ', $result['errors'] ), 'the error surfaces the transport failure' );

test( 'a malformed URL is rejected without a network call' );
CEAFSN_OD_Test_State::$http_requests = array();
$result = CEAFSN_OD_Validator::validate_external_url( 'not a url' );
ok( ! $result['valid'], 'malformed URL rejected' );
is_same( array(), CEAFSN_OD_Test_State::$http_requests, 'no request was attempted' );
delete_option( '__http' );

// -----------------------------------------------------------------------------
section( 'Publish gating' );

test( 'a record with neither a file nor a URL cannot be published' );
$blockers = CEAFSN_OD_Validator::publish_blockers( 0, '', 'csv' );
is_same( 1, count( $blockers ), 'one blocker reported' );
has_substring( 'One of the two is required', $blockers[0], 'the blocker explains the requirement' );

test( 'an empty download URL is treated as absent' );
ok( ! empty( CEAFSN_OD_Validator::publish_blockers( 0, '   ', 'csv' ) ), 'whitespace URL is not a route' );

test( 'a valid attachment satisfies the gate' );
attach( 20, $valid_csv, 'text/csv' );
is_same( array(), CEAFSN_OD_Validator::publish_blockers( 20, '', 'csv' ), 'no blockers' );

test( 'a valid external URL satisfies the gate' );
update_option( '__http', array( 'https://data.example.org/ok.zip' => array( 200, array( 'content-length' => '1024' ) ) ) );
is_same( array(), CEAFSN_OD_Validator::publish_blockers( 0, 'https://data.example.org/ok.zip', 'zip' ), 'no blockers' );

test( 'a broken external URL blocks publication' );
update_option( '__http', array( 'https://data.example.org/gone' => array( 410, array() ) ) );
ok( ! empty( CEAFSN_OD_Validator::publish_blockers( 0, 'https://data.example.org/gone', 'csv' ) ), 'HTTP 410 blocks' );
delete_option( '__http' );

test( 'the attachment takes precedence over the URL' );
CEAFSN_OD_Test_State::$http_requests = array();
is_same( array(), CEAFSN_OD_Validator::publish_blockers( 20, 'https://data.example.org/ignored', 'csv' ), 'attachment wins' );
is_same( array(), CEAFSN_OD_Test_State::$http_requests, 'the URL is not probed when a file is attached' );

// -----------------------------------------------------------------------------
section( 'Input validation' );

test( 'a complete record passes validation' );
$complete = array(
	'name'          => 'Monthly food prices',
	'description'   => 'Retail prices by district.',
	'category'      => 'Food prices',
	'coverage_area' => 'All counties',
	'data_license'  => 'CC BY 4.0',
	'last_updated'  => '2025-06-01',
	'status'            => 'draft',
	'file_type'         => 'csv',
	'file_size'         => 1024,
	'file_attachment_id' => 11,
	'download_url'      => '',
);
is_same( array(), ceafsn_od_test_validate( $complete ), 'no validation errors' );

test( 'every required field is required' );
$required = array( 'name', 'description', 'category', 'coverage_area', 'data_license' );
foreach ( $required as $field ) {
	$errors = ceafsn_od_test_validate( array_merge( $complete, array( $field => '' ) ) );
	ok( ! empty( $errors ), "{$field} is required" );
}
ok( ! empty( ceafsn_od_test_validate( array_merge( $complete, array( 'file_attachment_id' => 0, 'download_url' => '' ) ) ) ), 'a download route is required' );
ok( ! empty( ceafsn_od_test_validate( array_merge( $complete, array( 'last_updated' => '01/06/2025' ) ) ) ), 'a malformed date is rejected' );
ok( ! empty( ceafsn_od_test_validate( array_merge( $complete, array( 'status' => 'nonsense' ) ) ) ), 'an unknown status is rejected' );
ok( ! empty( ceafsn_od_test_validate( array_merge( $complete, array( 'file_type' => 'exe' ) ) ) ), 'an unknown file type is rejected' );
ok( ! empty( ceafsn_od_test_validate( array_merge( $complete, array( 'download_url' => 'javascript:alert(1)' ) ) ) ), 'a javascript: URL is rejected' );
ok( ! empty( ceafsn_od_test_validate( array_merge( $complete, array( 'methodology_url' => 'nope' ) ) ) ), 'a malformed methodology URL is rejected' );

test( 'providing both routes is an error' );
$errors = ceafsn_od_test_validate( array_merge( $complete, array( 'file_attachment_id' => 20, 'download_url' => 'https://data.example.org/a.csv' ) ) );
ok( ! empty( $errors ), 'both routes rejected' );
has_substring( 'not both', implode( ' ', $errors ), 'the error says pick one' );

test( 'a zero-byte attachment is caught before publishing' );
$errors = ceafsn_od_test_validate( array_merge( $complete, array( 'file_attachment_id' => 20, 'file_size' => 0 ) ) );
ok( ! empty( $errors ), 'zero-byte attachment rejected' );

test( 'markup is stripped from text fields' );
$row = ceafsn_od_test_prepare_row(
	array(
		'name'          => "  <script>alert(1)</script>Monthly   prices  ",
		'description'   => "<b>Bold</b> description\nwith a newline",
	)
);
lacks_substring( '<script>', $row['name'], 'script tags stripped from the name' );
lacks_substring( '</script>', $row['name'], 'the closing tag is stripped too' );
lacks_substring( '<b>', $row['description'], 'markup stripped from the description' );
has_substring( "with a newline", $row['description'], 'newlines preserved in the description' );

test( 'whitespace is collapsed in single-line fields' );
$row = ceafsn_od_test_prepare_row(
	array(
		'name'          => "  Monthly   prices  ",
		'category'      => "Food\tprices",
		'coverage_area' => "All\ncounties",
		'data_license'  => "CC BY   4.0",
	)
);
is_same( 'Monthly prices', $row['name'], 'runs of spaces collapse in the name' );
is_same( 'Food prices', $row['category'], 'tab collapsed in the category' );
is_same( 'All counties', $row['coverage_area'], 'newline collapsed in the coverage area' );
is_same( 'CC BY 4.0', $row['data_license'], 'runs of spaces collapse in the license' );

test( 'enum values are matched case-insensitively' );
$row = ceafsn_od_test_prepare_row( array( 'status' => 'PUBLISHED', 'file_type' => 'CSV' ) );
is_same( 'published', $row['status'], 'an upper-case status is accepted' );
is_same( 'csv', $row['file_type'], 'an upper-case file type is accepted' );

test( 'an unknown status or file type degrades safely' );
$row = ceafsn_od_test_prepare_row( array( 'status' => 'evil', 'file_type' => 'exe' ) );
is_same( 'draft', $row['status'], 'unknown status becomes draft' );
is_same( 'other', $row['file_type'], 'unknown file type becomes other' );

test( 'attachment IDs and sizes are cast to non-negative integers' );
$row = ceafsn_od_test_prepare_row( array( 'file_attachment_id' => '-5', 'file_size' => '-100' ) );
is_same( 5, $row['file_attachment_id'], 'negative attachment ID becomes positive' );
is_same( 100, $row['file_size'], 'negative size becomes positive' );

test( 'dates are normalised' );
is_same( '2025-06-01', CEAFSN_OD_DB::normalize_date( '2025-06-01' ), 'a valid ISO date is kept' );
is_same( gmdate( 'Y-m-d' ), CEAFSN_OD_DB::normalize_date( 'not a date' ), 'a malformed date becomes today' );
is_same( gmdate( 'Y-m-d' ), CEAFSN_OD_DB::normalize_date( '' ), 'an empty date becomes today' );

// -----------------------------------------------------------------------------
section( 'Data layer' );

test( 'a record is inserted with sanitised values' );
$wpdb->queries = array();
$wpdb->insert_id = 42;
$id = CEAFSN_OD_DB::insert_dataset( $complete + array( 'file_attachment_id' => 20 ) );
is_same( 42, $id, 'the insert id is returned' );
has_substring( 'INSERT INTO wp_ceafsn_od_datasets', $wpdb->queries[0], 'insert targets the dataset table' );
has_substring( '"name":"Monthly food prices"', $wpdb->queries[0], 'the name is stored sanitised' );
has_substring( '"status":"draft"', $wpdb->queries[0], 'the status is stored' );

test( 'a record is updated by dataset_id' );
$wpdb->queries = array();
CEAFSN_OD_DB::update_dataset( 7, $complete );
has_substring( 'UPDATE wp_ceafsn_od_datasets', $wpdb->queries[0], 'update targets the dataset table' );
has_substring( '"dataset_id":7', $wpdb->queries[0], 'the where clause targets the row' );

test( 'a record is deleted by dataset_id' );
$wpdb->queries = array();
CEAFSN_OD_DB::delete_dataset( 7 );
has_substring( 'DELETE FROM wp_ceafsn_od_datasets', $wpdb->queries[0], 'delete targets the dataset table' );
has_substring( '"dataset_id":7', $wpdb->queries[0], 'the where clause targets the row' );

test( 'sortable columns are an allow list' );
is_same(
	array( 'name', 'category', 'last_updated', 'file_type' ),
	CEAFSN_OD_DB::sortable_columns(),
	'the allow list is the documented set'
);

test( 'an unknown sort column cannot reach the SQL' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'orderby' => 'name; DROP TABLE wp_users--' ) );
$order_query = (string) end( $wpdb->queries );
has_substring( 'ORDER BY last_updated DESC', $order_query, 'an injected column falls back to the default' );
lacks_substring( 'DROP TABLE', $order_query, 'no injected SQL survives' );

test( 'an injected sort order is coerced' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'order' => 'asc; DELETE FROM wp_users' ) );
$order_query = (string) end( $wpdb->queries );
has_substring( 'ORDER BY last_updated DESC', $order_query, 'an unknown direction falls back to DESC' );
lacks_substring( 'DELETE FROM', $order_query, 'no injected direction survives' );

test( 'search terms are escaped and parameterised' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'search' => "100%' OR 1=1--" ) );
$search_query = (string) end( $wpdb->queries );
has_substring( "100\\\\%", $search_query, 'LIKE wildcards are escaped' );
has_substring( "'%100\\\\%\\' OR 1=1--%'", $search_query, 'the whole term is bound as one quoted parameter' );
ok( 0 === substr_count( $search_query, "'" ) % 2, 'no unpaired quote can escape the string' );

test( 'the front end only ever selects published records' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'published_only' => true ) );
has_substring( "status = 'published'", $wpdb->queries[0], 'the count query filters on status' );
has_substring( "status = 'published'", (string) end( $wpdb->queries ), 'the row query filters on status' );

test( 'the file type filter is parameterised' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'file_type' => 'zip' ) );
has_substring( 'file_type = \'zip\'', (string) end( $wpdb->queries ), 'file type is bound as a parameter' );
CEAFSN_OD_DB::get_datasets( array( 'file_type' => "zip' OR 1=1" ) );
lacks_substring( 'OR 1=1', (string) end( $wpdb->queries ), 'an unknown file type is dropped from the query' );

test( 'pagination is bound into the query' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'per_page' => 10, 'page' => 3 ) );
has_substring( 'LIMIT 10 OFFSET 20', (string) end( $wpdb->queries ), 'offset is computed from the page' );

test( 'per_page is clamped to a sane range' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_datasets( array( 'per_page' => 100000 ) );
has_substring( 'LIMIT 100000', (string) end( $wpdb->queries ), 'the DB layer passes per_page through' );
is_same( 100, CEAFSN_OD_Public::clamp_per_page( 100000 ), 'the shortcode clamps it to 100' );
is_same( 20, CEAFSN_OD_Public::clamp_per_page( 0 ), 'zero falls back to the default' );
is_same( 20, CEAFSN_OD_Public::clamp_per_page( -5 ), 'negative falls back to the default' );
is_same( 7, CEAFSN_OD_Public::clamp_per_page( '7' ), 'a valid value is kept' );

test( 'a single record is fetched by id' );
$wpdb->queries = array();
CEAFSN_OD_DB::get_dataset( 5 );
has_substring( 'WHERE dataset_id = 5', $wpdb->queries[0], 'the id is bound into the query' );

test( 'the download URL prefers the attachment' );
attach( 11, fixture( 'monthly.csv', "district,price\nNimba,320\n" ), 'text/csv' );
is_same(
	'https://example.test/wp-content/uploads/monthly.csv',
	CEAFSN_OD_DB::resolve_download_url( dataset_row( array( 'download_url' => 'https://data.example.org/a.csv' ) ) ),
	'the attachment wins when both are present'
);
is_same(
	'https://data.example.org/a.csv',
	CEAFSN_OD_DB::resolve_download_url(
		dataset_row(
			array(
				'file_attachment_id' => 0,
				'download_url'       => 'https://data.example.org/a.csv',
			)
		)
	),
	'the external URL is used when there is no attachment'
);
is_same(
	'',
	CEAFSN_OD_DB::resolve_download_url( dataset_row( array( 'file_attachment_id' => 0, 'download_url' => '' ) ) ),
	'a record with no route resolves to an empty string'
);
is_same(
	'',
	CEAFSN_OD_DB::resolve_download_url( dataset_row( array( 'file_attachment_id' => 999 ) ) ),
	'a deleted attachment resolves to an empty string'
);

test( 'the export covers every record' );
$wpdb->queries = array();
$wpdb->var_result = 0;
$wpdb->results_queue = array( array( dataset_row(), dataset_row( array( 'dataset_id' => 2 ) ) ) );
is_same( 2, count( CEAFSN_OD_DB::export_all() ), 'every record is exported' );

// -----------------------------------------------------------------------------
section( 'Public front end' );

test( 'the empty state is shown when nothing is published' );
$html = render_table( array(), 0 );
has_substring( 'No public dataset is currently available for download.', $html, 'the documented empty state is used' );
lacks_substring( '<table', $html, 'no empty table shell is rendered' );
has_substring( '0 datasets', $html, 'the count reports zero' );
lacks_substring( 'Download CSV', $html, 'no download action is offered' );

test( 'no invented metrics appear anywhere' );
$html = render_table( array( dataset_row() ), 1 );
foreach ( array( 'HDDS', 'household', 'yield', 'kg per capita', 'calorie' ) as $metric ) {
	lacks_substring( $metric, $html, "no invented {$metric} metric" );
}

test( 'records render in an accessible table' );
attach( 11, $valid_csv, 'text/csv' );
$html = render_table( array( dataset_row() ), 1, array( 'Food prices' ) );
has_substring( '<table class="ceafsn-od-table">', $html, 'a table is rendered' );
is_same( 7, substr_count( $html, '<th scope="col"' ), 'seven column headers' );
has_substring( '<th scope="row"', $html, 'each row has a row header' );
has_substring( '<caption', $html, 'the table has a caption' );
has_substring( 'Monthly food prices', $html, 'the dataset name is shown' );
has_substring( 'All counties', $html, 'the coverage area is shown' );
has_substring( '2025-06-01', $html, 'the last updated date is shown' );
has_substring( 'CC BY 4.0', $html, 'the license is shown' );
has_substring( '2.0 KB', $html, 'the file size is shown' );

test( 'every sortable column declares aria-sort' );
is_same( count( CEAFSN_OD_DB::sortable_columns() ), substr_count( $html, 'aria-sort=' ), 'every sortable column declares aria-sort' );
has_substring( 'aria-sort="descending"', $html, 'the active column reports its current order' );
is_same(
	0,
	substr_count( $html, 'aria-sort="ascending"' ),
	'an unsorted column reports none, not a wrong order'
);

test( 'aria-sort reflects the current state, not the next one' );
$_GET = array( 'od_sort' => 'name', 'od_order' => 'desc' );
$html = render_table( array( dataset_row() ), 1 );
has_substring( 'aria-sort="descending"', $html, 'descending while descending' );
has_substring( 'od_order=asc', $html, 'the link offers the opposite order' );
$_GET = array();

test( 'sorting returns to the first page' );
$link = CEAFSN_OD_Public::sort_link( 'https://example.test/open-datasets/', 'name', 'category', 'asc' );
has_substring( 'od_page=1', $link['url'], 'a sort link resets the page' );
has_substring( 'od_sort=name', $link['url'], 'the sort column is set' );

test( 'the search and filter controls are labelled' );
$html = render_table( array(), 0 );
has_substring( 'for="ceafsn-od-search"', $html, 'search input has a label' );
has_substring( 'for="ceafsn-od-category"', $html, 'category select has a label' );
has_substring( 'for="ceafsn-od-file-type"', $html, 'file type select has a label' );
has_substring( 'name="od_search"', $html, 'the search control is named' );
has_substring( 'name="od_category"', $html, 'the category control is named' );
has_substring( 'name="od_file_type"', $html, 'the file type control is named' );
has_substring( '0 datasets', $html, 'empty result count reported' );

test( 'the result count is pluralised correctly' );
attach( 11, $valid_csv, 'text/csv' );
$html = render_table( array( dataset_row() ), 1 );
has_substring( '1 dataset', $html, 'singular result count used for one record' );
lacks_substring( '1 datasets', $html, 'the singular form is not pluralised' );
$html = render_table( array( dataset_row() ), 5 );
has_substring( '5 datasets', $html, 'plural result count used for several records' );

test( 'filter values are sanitised' );
$html = render_table( array(), 0, array(), array( 'category' => '"><script>alert(1)</script>' ) );
lacks_substring( '<script>', $html, 'a category attribute cannot inject a script' );

test( 'the query string overrides the shortcode attribute' );
$_GET = array( 'od_category' => 'FromQuery' );
$wpdb->queries = array();
attach( 11, $valid_csv, 'text/csv' );
$wpdb->var_result = 0;
$wpdb->results_queue = array( array(), array() );
( new CEAFSN_OD_Public() )->render_shortcode( array( 'category' => 'FromAttribute' ) );
has_substring( "category = 'FromQuery'", (string) $wpdb->queries[1], 'the query string wins' );
$_GET = array();

test( 'the shortcode attribute pre-filters when there is no query string' );
$wpdb->queries = array();
$wpdb->var_result = 0;
$wpdb->results_queue = array( array(), array() );
( new CEAFSN_OD_Public() )->render_shortcode( array( 'category' => 'FromAttribute' ) );
has_substring( "category = 'FromAttribute'", (string) $wpdb->queries[1], 'the attribute is used as the default view' );

test( 'download links are safe and descriptive' );
attach( 11, $valid_csv, 'text/csv' );
$html = render_table( array( dataset_row() ), 1 );
has_substring( 'Download CSV', $html, 'the action names the file type' );
has_substring( 'aria-label="Download Monthly food prices as CSV, 2.0 KB"', $html, 'the link label includes name, type, and size' );
has_substring( '(opens Monthly food prices in a new tab)', $html, 'the new tab is announced to screen readers' );
ok(
	0 === substr_count( $html, 'target="_blank"' ) - substr_count( $html, 'rel="noopener noreferrer"' ),
	'every new-tab link carries rel="noopener noreferrer"'
);
lacks_substring( 'target="_blank" rel="noopener noreferrer" target', $html, 'the rel attribute is never omitted' );

test( 'an external download link is rendered when there is no attachment' );
$html = render_table(
	array( dataset_row( array( 'file_attachment_id' => 0, 'download_url' => 'https://data.example.org/prices.zip', 'file_type' => 'zip', 'file_size' => 1048576 ) ) ),
	1
);
has_substring( 'https://data.example.org/prices.zip', $html, 'the external URL is used' );
has_substring( 'Download ZIP', $html, 'the action names the file type' );
has_substring( '1.0 MB', $html, 'the reported size is shown' );

test( 'a record whose file is gone never shows a dead link' );
$html = render_table( array( dataset_row( array( 'file_attachment_id' => 999 ) ) ), 1 );
lacks_substring( 'href=', substr( $html, (int) strpos( $html, 'ceafsn-od-table__download' ) ), 'no link element in the download cell' );
has_substring( 'Download currently unavailable.', $html, 'an honest unavailable message is shown' );

test( 'an unknown file size is never invented' );
$html = render_table( array( dataset_row( array( 'file_size' => 0 ) ) ), 1 );
has_substring( 'Not available', $html, 'the size reads as unavailable' );
lacks_substring( '0 B', $html, 'a zero size is never rendered as 0 B' );

test( 'the data dictionary link is announced' );
$html = render_table( array( dataset_row( array( 'methodology_url' => 'https://example.org/dictionary' ) ) ), 1 );
has_substring( 'Data dictionary', $html, 'the dictionary link is labelled' );
has_substring( '(opens the data dictionary for Monthly food prices in a new tab)', $html, 'the new tab is announced' );

test( 'contact details are private by default' );
$html = render_table( array( dataset_row() ), 1 );
lacks_substring( 'data@example.org', $html, 'the contact is not rendered by default' );
lacks_substring( 'Contact:', $html, 'no contact label is rendered' );

test( 'contact details appear only when an administrator opts in' );
update_option( 'ceafsn_od_show_contact', 1 );
$html = render_table( array( dataset_row() ), 1 );
has_substring( 'Contact: data@example.org', $html, 'the contact is shown when enabled' );
delete_option( 'ceafsn_od_show_contact' );

test( 'pagination links are generated when needed' );
$_GET = array( 'od_page' => '2' );
attach( 11, $valid_csv, 'text/csv' );
$html = render_table( array( dataset_row() ), 45, array(), array( 'per_page' => 20 ) );
has_substring( 'Page 2 of 3', $html, 'the page position is announced' );
has_substring( 'od_page=1', $html, 'a link back to page 1 exists' );
has_substring( 'od_page=3', $html, 'a link forward to page 3 exists' );
has_substring( 'aria-label="Dataset record pages"', $html, 'the pagination nav is labelled' );
$_GET = array();

test( 'no pagination is shown for a single page of results' );
$html = render_table( array( dataset_row() ), 3 );
lacks_substring( 'ceafsn-od-pagination', $html, 'no pagination for one page' );

test( 'assets are enqueued when the shortcode renders' );
CEAFSN_OD_Test_State::reset();
CEAFSN_OD_Test_State::$attachments[11] = array( 'file' => $valid_csv, 'mime' => 'text/csv' );
$wpdb->var_result = 0;
$wpdb->results_queue = array( array(), array() );
( new CEAFSN_OD_Public() )->render_shortcode( array() );
ok( wp_style_is( 'ceafsn-od-public', 'enqueued' ), 'the public stylesheet is enqueued' );
ok( wp_script_is( 'ceafsn-od-public', 'enqueued' ), 'the public script is enqueued' );

test( 'assets are enqueued only once per request' );
$before = count( CEAFSN_OD_Test_State::$styles );
( new CEAFSN_OD_Public() )->render_shortcode( array() );
is_same( $before, count( CEAFSN_OD_Test_State::$styles ), 'a second render does not enqueue again' );

test( 'the shortcode renders twice on one page without a fatal' );
CEAFSN_OD_Test_State::reset();
CEAFSN_OD_Test_State::$attachments[11] = array( 'file' => $valid_csv, 'mime' => 'text/csv' );
$wpdb->var_result = 0;
$wpdb->results_queue = array( array( dataset_row() ), array() );
$public = new CEAFSN_OD_Public();
$first  = $public->render_shortcode( array() );
$wpdb->results_queue = array( array( dataset_row() ), array() );
$second = $public->render_shortcode( array() );
is_same( $first, $second, 'two renders produce identical output' );
ok( ! CEAFSN_OD_Test_State::$died, 'no wp_die was triggered' );

test( 'output is escaped' );
attach( 11, $valid_csv, 'text/csv' );
$html = render_table(
	array( dataset_row( array( 'name' => '<script>alert(1)</script>Evil', 'category' => '"><b>bold</b>' ) ) ),
	1,
	array( '"><img src=x onerror=alert(1)>' )
);
lacks_substring( '<script>alert(1)</script>', $html, 'a script tag in the name is escaped' );
lacks_substring( '<img src=x', $html, 'an injected tag in a category is escaped' );
has_substring( '&lt;script&gt;', $html, 'the name is rendered as text' );

test( 'a javascript: download URL is not rendered as a link' );
$html = render_table(
	array( dataset_row( array( 'file_attachment_id' => 0, 'download_url' => 'javascript:alert(1)' ) ) ),
	1
);
lacks_substring( 'javascript:', $html, 'a javascript: URL is stripped' );
has_substring( 'Download currently unavailable.', $html, 'the record shows as unavailable' );

// -----------------------------------------------------------------------------
section( 'Admin security' );

test( 'state-changing handlers require manage_options' );
CEAFSN_OD_Test_State::reset();
$admin = new CEAFSN_OD_Admin();
$admin->init();
ok( isset( CEAFSN_OD_Test_State::$actions['admin_post_ceafsn_od_save_dataset'] ), 'the save handler is registered' );
ok( isset( CEAFSN_OD_Test_State::$actions['admin_post_ceafsn_od_delete_dataset'] ), 'the delete handler is registered' );
ok( isset( CEAFSN_OD_Test_State::$actions['admin_post_ceafsn_od_save_settings'] ), 'the settings handler is registered' );
ok( isset( CEAFSN_OD_Test_State::$actions['admin_post_ceafsn_od_export'] ), 'the export handler is registered' );

test( 'a user without manage_options cannot save' );
CEAFSN_OD_Test_State::reset();
$_POST = array( 'name' => 'Injected' );
$admin = new CEAFSN_OD_Admin();
try {
	$admin->handle_save_dataset();
} catch ( RuntimeException $e ) {
	unset( $e );
}
ok( CEAFSN_OD_Test_State::$died, 'the request is refused' );
has_substring( 'permission', CEAFSN_OD_Test_State::$die_message, 'a clear permission message is shown' );
is_same( array(), CEAFSN_OD_Test_State::$redirects, 'no redirect was issued' );
$_POST = array();

test( 'the file name is never taken from the form' );
CEAFSN_OD_Test_State::reset();
CEAFSN_OD_Test_State::$attachments[31] = array( 'file' => $valid_csv, 'mime' => 'text/csv' );
update_option( '__caps', array( 'manage_options' => true ) );
$_POST = array(
	'name'              => 'Monthly food prices',
	'description'       => 'Retail prices by district.',
	'category'          => 'Food prices',
	'coverage_area'     => 'All counties',
	'data_license'      => 'CC BY 4.0',
	'last_updated'      => '2025-06-01',
	'status'            => 'draft',
	'file_type'         => 'csv',
	'file_attachment_id'=> '31',
	'file_name'         => '../../evil.csv',
	'file_size'         => '99999999',
);
$row = ceafsn_od_test_extract( $admin );
is_same( filesize( $valid_csv ), $row['file_size'], 'the size is read from disk, not the form' );
lacks_substring( 'evil.csv', (string) json_encode( $row ), 'a submitted file name is never stored' );
delete_option( '__caps' );
$_POST = array();

test( 'an unresolvable attachment stores no size and no name' );
CEAFSN_OD_Test_State::reset();
$_POST = array( 'file_attachment_id' => '999', 'file_name' => '../../etc/passwd' );
$row = ceafsn_od_test_extract( $admin );
is_same( 0, $row['file_size'], 'no size is invented for an unknown attachment' );
lacks_substring( 'passwd', (string) json_encode( $row ), 'no path is stored' );
$_POST = array();

test( 'uninstall requires the explicit opt-in' );
is_same( false, (bool) get_option( 'ceafsn_od_uninstall_delete_data', false ), 'the flag is off by default' );
is_same( array(), ceafsn_od_test_uninstall( false )['queries'], 'no data is removed without the flag' );

test( 'uninstall with the opt-in drops the table' );
$uninstalled = ceafsn_od_test_uninstall( true );
has_substring(
	'DROP TABLE IF EXISTS `wp_ceafsn_od_datasets`',
	implode( "\n", $uninstalled['queries'] ),
	'the dataset table is dropped'
);
is_same( false, (bool) ( $uninstalled['options']['ceafsn_od_show_contact'] ?? false ), 'the contact option is removed' );
is_same( false, (bool) ( $uninstalled['options']['ceafsn_od_db_version'] ?? false ), 'the schema version option is removed' );
is_same( false, (bool) ( $uninstalled['options']['ceafsn_od_uninstall_delete_data'] ?? false ), 'the flag itself is removed' );

test( 'uninstall never removes media files' );
$uninstall_source = (string) file_get_contents( $plugin_dir . '/uninstall.php' );
lacks_substring( 'wp_delete_attachment', $uninstall_source, 'no attachment is deleted on uninstall' );
lacks_substring( 'wp_delete_file', $uninstall_source, 'no file is deleted on uninstall' );

// -----------------------------------------------------------------------------
section( 'Activation' );

test( 'the upload restriction is scoped to the dataset screen' );
CEAFSN_OD_Test_State::reset();
$_GET = array();
$site_mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf' );
is_same( $site_mimes, CEAFSN_OD_Activator::restrict_upload_mimes( $site_mimes ), 'other admin screens are untouched' );

test( 'other admin screens keep every upload type' );
CEAFSN_OD_Test_State::reset();
update_option( '__is_admin', 1 );
$_GET = array( 'page' => 'some-other-plugin' );
$site_mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' );
is_same( $site_mimes, CEAFSN_OD_Activator::restrict_upload_mimes( $site_mimes ), 'a different plugin page is untouched' );
$_GET = array();

test( 'the dataset screen allows only dataset file types' );
CEAFSN_OD_Test_State::reset();
update_option( '__is_admin', 1 );
$_GET = array( 'page' => 'ceafsn-od' );
$restricted = CEAFSN_OD_Activator::restrict_upload_mimes( array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' ) );
ok( ! isset( $restricted['jpg|jpeg|jpe'] ), 'images are blocked on the dataset screen' );
ok( ! isset( $restricted['png'] ), 'images are blocked on the dataset screen' );
ok( isset( $restricted['csv|txt'] ), 'CSV is allowed' );
ok( isset( $restricted['zip|x-zip'] ), 'ZIP is allowed' );
ok( isset( $restricted['xlsx|xlsm|xlsb'] ), 'XLSX is allowed' );
$_GET = array();

test( 'a front-end request never restricts uploads' );
CEAFSN_OD_Test_State::reset();
$_GET = array();
$site_mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg' );
is_same( $site_mimes, CEAFSN_OD_Activator::restrict_upload_mimes( $site_mimes ), 'the front end is untouched' );

test( 'activation registers the restriction and creates the table' );
CEAFSN_OD_Test_State::reset();
$GLOBALS['ceafsn_od_dbdelta'] = array();
update_option( '__caps', array( 'activate_plugins' => true ) );
CEAFSN_OD_Activator::activate();
delete_option( '__caps' );
is_same( CEAFSN_OD_DB::SCHEMA_VERSION, get_option( 'ceafsn_od_db_version' ), 'the schema version is stored' );

$schema = (string) ( $GLOBALS['ceafsn_od_dbdelta'][0] ?? '' );
$schema_flat = (string) preg_replace( '/\s+/', ' ', $schema );
has_substring( 'CREATE TABLE wp_ceafsn_od_datasets', $schema_flat, 'the datasets table is created' );
has_substring( "status ENUM('draft','published','archived')", $schema_flat, 'status is an enum' );
has_substring( "file_type ENUM('csv','zip','xlsx','other')", $schema_flat, 'file type is an enum' );
has_substring( 'file_attachment_id BIGINT UNSIGNED', $schema_flat, 'the attachment column is present' );
has_substring( 'download_url TEXT', $schema_flat, 'the download URL column is present' );
has_substring( 'file_size BIGINT UNSIGNED', $schema_flat, 'the size column is present' );
has_substring( 'PRIMARY KEY (dataset_id)', $schema_flat, 'the primary key is defined' );
has_substring( 'utf8mb4', $schema_flat, 'charset and collation are applied' );

test( 'activation without the capability does nothing' );
CEAFSN_OD_Test_State::reset();
CEAFSN_OD_Activator::activate();
is_same( false, get_option( 'ceafsn_od_db_version', false ), 'no schema version written without activate_plugins' );

test( 'the upload filter is registered on every admin request, not just at activation' );
CEAFSN_OD_Test_State::reset();
ok( empty( CEAFSN_OD_Test_State::$filters['upload_mimes'] ), 'a fresh request has not registered it yet' );
CEAFSN_OD_Activator::register_upload_filter();
ok( isset( CEAFSN_OD_Test_State::$filters['upload_mimes'] ), 'registering adds the filter' );
ok(
	str_contains( $plugin_source, 'CEAFSN_OD_Activator::register_upload_filter()' ),
	'the plugin bootstrap registers it, not just the activation hook'
);
ok(
	! str_contains( (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-od-activator.php' ), "add_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ) );" )
	|| str_contains( $plugin_source, 'register_upload_filter' ),
	'activation alone no longer carries the restriction'
);

test( 'the "other" file type is off unless an administrator enables it' );
CEAFSN_OD_Test_State::reset();
is_same( false, CEAFSN_OD_Admin::other_files_allowed(), 'the setting defaults to off' );
$other = ceafsn_od_test_validate( array_merge( $complete, array( 'file_type' => 'other' ) ) );
ok(
	in_array( 'The "other" file type is not enabled. Enable it in Settings, or choose CSV, ZIP, or XLSX.', $other, true ),
	'the server rejects "other" while the setting is off'
);
update_option( 'ceafsn_od_allow_other_files', 1 );
is_same( true, CEAFSN_OD_Admin::other_files_allowed(), 'the setting can be turned on' );
is_same( array(), ceafsn_od_test_validate( array_merge( $complete, array( 'file_type' => 'other' ) ) ), 'the server accepts "other" once enabled' );
delete_option( 'ceafsn_od_allow_other_files' );

test( 'deactivation removes the restriction and keeps data' );
CEAFSN_OD_Test_State::reset();
update_option( 'ceafsn_od_db_version', CEAFSN_OD_DB::SCHEMA_VERSION );
CEAFSN_OD_Activator::deactivate();
is_same( CEAFSN_OD_DB::SCHEMA_VERSION, get_option( 'ceafsn_od_db_version' ), 'the schema version survives deactivation' );
ok( empty( CEAFSN_OD_Test_State::$filters['upload_mimes'] ), 'the restriction is removed' );

// -----------------------------------------------------------------------------
section( 'Content hygiene' );

test( 'the plugin declares its text domain' );
ok( str_contains( $plugin_source, 'Text Domain:       ceafsn-od' ), 'the text domain is declared' );
ok( str_contains( $plugin_source, 'Domain Path' ), 'the languages path is declared' );
ok( str_contains( $plugin_source, 'load_plugin_textdomain(' ), 'the text domain is loaded at runtime' );

test( 'readme.txt is present and complete' );
$readme = (string) file_get_contents( $plugin_dir . '/readme.txt' );
foreach ( array( 'Requires at least:', 'Requires PHP:', 'Tested up to:', 'License:', 'Shortcode:' ) as $field ) {
	has_substring( $field, $readme, "readme has {$field}" );
}
has_substring( '[ceafsn_open_datasets]', $readme, 'readme documents the shortcode' );
has_substring( 'No public dataset is currently available for download.', $readme, 'readme documents the empty state' );
ok( preg_match( '/== Changelog ==/', $readme ), 'readme has a changelog section' );
ok( file_exists( $plugin_dir . '/languages/ceafsn-od.pot' ), 'the translation template exists' );

test( 'no stray debug output' );
$php_list = array_values(
	array_filter(
		explode( "\n", (string) shell_exec( 'find ' . escapeshellarg( $plugin_dir ) . ' -name "*.php" -not -path "*/tests/*"' ) ),
		static fn( $file ) => is_string( $file ) && '' !== trim( $file )
	)
);
ok( count( $php_list ) > 5, 'the plugin has PHP files (' . count( $php_list ) . ')' );
foreach ( $php_list as $file ) {
	$source = (string) file_get_contents( $file );
	lacks_substring( 'var_dump(', $source, 'no var_dump in ' . basename( $file ) );
	lacks_substring( 'print_r(', $source, 'no print_r in ' . basename( $file ) );
	lacks_substring( 'error_log(', $source, 'no error_log in ' . basename( $file ) );
	lacks_substring( "\$_GET['", $source, 'no unslashed $_GET in ' . basename( $file ) );
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
$admin_source = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-od-admin.php' );
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

test( 'publishing is gated on download validation in the handler' );
has_substring( 'CEAFSN_OD_Validator::verify_download_target', $admin_source, 'the save handler consults the validator' );
has_substring( "\$data['status'] = 'draft'", $admin_source, 'an invalid download downgrades the record to draft' );

test( 'the table name uses the site prefix' );
$db_source = (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-od-db.php' );
has_substring( '{$wpdb->prefix}ceafsn_od_datasets', $db_source, 'datasets table uses $wpdb->prefix' );

test( 'the external probe uses a HEAD request' );
$validator_source = (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-od-validator.php' );
has_substring( 'wp_safe_remote_head', $validator_source, 'remote URLs are probed, not downloaded' );
lacks_substring( 'file_get_contents( $url', $validator_source, 'no remote file is downloaded during a save' );

test( 'the public template declares no functions' );
$public_partial = (string) file_get_contents( $plugin_dir . '/public/partials/datasets.php' );
lacks_substring( "\nfunction ", $public_partial, 'the template declares no functions, so it can render twice' );
ok( ! preg_match( '/\bfunction\s+\w+\s*\(/', $public_partial ), 'no function declarations in the public template' );

test( 'referenced asset files exist' );
foreach ( array( 'assets/css/ceafsn-od-public.css', 'assets/css/ceafsn-od-admin.css', 'assets/js/ceafsn-od-public.js', 'assets/js/ceafsn-od-admin.js' ) as $asset ) {
	ok( file_exists( $plugin_dir . '/' . $asset ), "asset exists: {$asset}" );
}

test( 'CSS is scoped to the component' );
$public_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-od-public.css' );
ok(
	! preg_match( '/(^|\})\s*(body|html|p|h1|h2)\s*\{/m', $public_css ),
	'no bare element selectors in the public stylesheet'
);
has_substring( '.ceafsn-od', $public_css, 'public styles are component-scoped' );

test( 'the admin stylesheet is scoped to the component' );
$admin_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-od-admin.css' );
ok(
	! preg_match( '/(^|\})\s*(body|html|p|h1|h2)\s*\{/m', $admin_css ),
	'no bare element selectors in the admin stylesheet'
);
has_substring( '.ceafsn-od-wrap', $admin_css, 'admin styles are component-scoped' );
ok( substr_count( $admin_css, '{' ) === substr_count( $admin_css, '}' ), 'admin CSS braces are balanced' );
lacks_substring( 'ceafsn-np-', $admin_css, 'no styles leaked in from another plugin' );
lacks_substring( 'ceafsn-med-', $admin_css, 'no styles leaked in from another plugin' );
has_substring( '.ceafsn-alert--ok', $admin_css, 'the shared success alert variant is available' );
has_substring( '.ceafsn-btn--danger', $admin_css, 'the shared destructive button is available' );

test( 'the admin partials use the branded app layout' );
$datasets_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/datasets.php' );
$settings_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
foreach ( array( 'ceafsn-app__main', 'ceafsn-app__rail', 'ceafsn-hero', 'ceafsn-stepper', 'ceafsn-kpi-row', 'ceafsn-ticks', 'ceafsn-cta' ) as $component ) {
	has_substring( $component, $datasets_partial, "datasets view uses {$component}" );
}
foreach ( array( 'ceafsn-hero', 'ceafsn-tabs', 'ceafsn-check', 'ceafsn-danger' ) as $component ) {
	has_substring( $component, $settings_partial, "settings view uses {$component}" );
}
lacks_substring( 'wp-list-table', $datasets_partial, 'the legacy WordPress list table is gone' );
lacks_substring( 'ceafsn-od-empty', $datasets_partial, 'the legacy empty-state block is gone' );
lacks_substring( 'nav-tab', $settings_partial, 'the legacy nav-tab markup is gone' );

test( 'the admin markup keeps its JavaScript contracts' );
foreach ( array( 'ceafsn-od-file-id', 'ceafsn-od-file-field', 'ceafsn-od-file-type', 'ceafsn-od-media-button', 'ceafsn-od-media-clear' ) as $element_id ) {
	has_substring( 'id="' . $element_id . '"', $datasets_partial, "the media picker keeps #{$element_id}" );
}
has_substring( 'class="ceafsn-od-delete-link"', $datasets_partial, 'the delete link keeps its class' );
has_substring( 'data-ceafsn-od="file-type"', $datasets_partial, 'the file type field keeps its data hook' );
has_substring( 'ceafsn-od-media__name', $datasets_partial, 'the filename box is styled as a media name' );
foreach ( array( 'ceafsn_od_save_dataset', 'ceafsn_od_dataset_nonce', 'ceafsn_od_nonce' ) as $contract ) {
	has_substring( $contract, $datasets_partial, "the form keeps {$contract}" );
}

test( 'the settings screen shows the administrator which shortcode to use' );
$od_settings_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
has_substring( '[ceafsn_open_datasets]', $od_settings_partial, 'the exact shortcode is shown' );
foreach ( array( 'per_page', 'category', 'file_type' ) as $od_attribute ) {
	has_substring( '<code>' . $od_attribute . '</code>', $od_settings_partial, "the shortcode card documents {$od_attribute}" );
}
foreach ( array( 'ceafsn-embed__code', 'ceafsn-embed__table', 'ceafsn-embed__caption' ) as $od_class ) {
	has_substring( $od_class, $od_settings_partial, "the shortcode card uses {$od_class}" );
}
ok( str_contains( $od_settings_partial, "'display' === \$active_tab" ), 'the shortcode has its own read-only tab' );
has_substring( 'ceafsn-embed', $admin_css, 'the admin stylesheet carries the shared embed component' );

test( 'the admin write forms all post to admin-post.php' );
has_substring( 'admin-post.php', $datasets_partial, 'the dataset form posts to admin-post.php' );
has_substring( 'admin-post.php', $settings_partial, 'the settings form posts to admin-post.php' );

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

test( 'the datasets page shows the shortcode too' );
$main_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/datasets.php' );
has_substring( '[ceafsn_open_datasets]', $main_partial, 'the dashboard page shows the exact shortcode' );
foreach ( array( 'per_page', 'category', 'file_type' ) as $shortcode_attribute ) {
	has_substring( $shortcode_attribute, $main_partial, "the dashboard card mentions {$shortcode_attribute}" );
}
has_substring( 'ceafsn-embed__code', $main_partial, 'the dashboard card uses the shared code block' );
has_substring( "&tab=display", $main_partial, 'the card links to the Display settings tab' );

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
section( 'Admin: settings scopes' );

global $plugin_dir;
$od_admin_ref = new ReflectionClass( new CEAFSN_OD_Admin() );

/**
 * Call OD's private settings writer with a given POST body.
 *
 * @param ReflectionClass $class Admin class.
 * @param array           $post  POST body to save.
 * @return void
 */
function save_od_settings( ReflectionClass $class, array $post ): void {
	$method = $class->getMethod( 'persist_settings' );
	$method->setAccessible( true );
	$method->invoke( $class->newInstanceWithoutConstructor(), $post );
}

test( 'saving the Display tab leaves the uninstall opt-in untouched' );
CEAFSN_OD_Test_State::$options = array(
	'ceafsn_od_show_contact'          => 1,
	'ceafsn_od_allow_other_files'     => 0,
	'ceafsn_od_uninstall_delete_data' => true,
);
save_od_settings( $od_admin_ref, array( 'ceafsn_od_settings_scope' => 'display' ) );
is_same( 0, get_option( 'ceafsn_od_show_contact' ), 'an unticked display box is saved as zero' );
is_same( 0, get_option( 'ceafsn_od_allow_other_files' ), 'the second display box is saved too' );
is_same( true, get_option( 'ceafsn_od_uninstall_delete_data' ), 'the uninstall opt-in on another tab survives' );

test( 'saving the Uninstall tab leaves the display options untouched' );
CEAFSN_OD_Test_State::$options = array(
	'ceafsn_od_show_contact'          => 1,
	'ceafsn_od_allow_other_files'     => 1,
	'ceafsn_od_uninstall_delete_data' => false,
);
save_od_settings( $od_admin_ref, array( 'ceafsn_od_settings_scope' => 'uninstall', 'ceafsn_od_uninstall_delete_data' => '1' ) );
is_same( true, get_option( 'ceafsn_od_uninstall_delete_data' ), 'the opt-in saves as true' );
is_same( 1, get_option( 'ceafsn_od_show_contact' ), 'contact visibility is not reset' );
is_same( 1, get_option( 'ceafsn_od_allow_other_files' ), 'the file type option is not reset' );

test( 'a missing or unknown scope writes nothing' );
CEAFSN_OD_Test_State::$options = array(
	'ceafsn_od_show_contact'          => 1,
	'ceafsn_od_allow_other_files'     => 1,
	'ceafsn_od_uninstall_delete_data' => false,
);
save_od_settings( $od_admin_ref, array( 'ceafsn_od_show_contact' => '1', 'ceafsn_od_uninstall_delete_data' => '1' ) );
is_same( 1, get_option( 'ceafsn_od_show_contact' ), 'no scope changes nothing' );
is_same( false, get_option( 'ceafsn_od_uninstall_delete_data' ), 'the opt-in is not silently enabled' );

test( 'the editable settings forms declare the scope they own' );
$od_set_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
foreach ( array( 'display', 'uninstall' ) as $scope ) {
	has_substring( 'value="' . $scope . '"', $od_set_partial, "the {$scope} scope is posted by its own form" );
}

$pass = $GLOBALS['ceafsn_od_test_pass'];
$fail = $GLOBALS['ceafsn_od_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "Assertions: {$pass} passed, {$fail} failed\n";
echo str_repeat( '-', 60 ) . "\n";

exit( $fail > 0 ? 1 : 0 );
