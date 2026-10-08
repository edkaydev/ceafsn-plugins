<?php
/**
 * Test runner for CE-AFSN M&E Dashboard.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-me-dashboard/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_MED
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
// holding just enough of wp-admin for the schema code to load. The fixture is
// written on every run rather than only when the directory is missing, because
// a previous run removes it again and a half-created fixture fails far from the
// cause. ABSPATH is defined before bootstrap.php loads so the bootstrap's own
// "define only if undefined" guard keeps this value.
$med_fake_wp_admin = sys_get_temp_dir() . '/ceafsn-med-fake-wp/wp-admin/includes';

if ( ! is_dir( $med_fake_wp_admin ) ) {
	mkdir( $med_fake_wp_admin, 0777, true );
}

file_put_contents(
	$med_fake_wp_admin . '/upgrade.php',
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
	. "\t\t\t\$GLOBALS['ceafsn_med_dbdelta'][] = (string) \$query;\n"
	. "\t\t}\n"
	. "\t\treturn array();\n"
	. "\t}\n"
	. "}\n"
);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( $med_fake_wp_admin, 2 ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';

/**
 * Minimal in-memory $wpdb replacement.
 *
 * Records every query so tests can assert on the SQL that the plugin
 * generates, and replays canned result sets for reads.
 */
final class FakeWpdb {

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
	 * Escape a LIKE wildcard.
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
	 * @return int Rows affected.
	 */
	public function insert( string $table, array $data, array $format = array() ): int {
		unset( $format );
		$this->queries[] = 'INSERT INTO ' . $table . ' ' . wp_json_encode_stub( $data );
		return 1;
	}

	/**
	 * Record an update.
	 *
	 * @param string $table         Table.
	 * @param array  $data          Column data.
	 * @param array  $where         Where clause.
	 * @param array  $format        Formats.
	 * @param array  $where_format  Where formats.
	 * @return int Rows affected.
	 */
	public function update( string $table, array $data, array $where, array $format = array(), array $where_format = array() ): int {
		unset( $format, $where_format );
		$this->queries[] = 'UPDATE ' . $table . ' ' . wp_json_encode_stub( $data ) . ' ' . wp_json_encode_stub( $where );
		return 1;
	}

	/**
	 * Record a delete.
	 *
	 * @param string $table  Table.
	 * @param array  $where  Where clause.
	 * @param array  $format Where formats.
	 * @return int Rows affected.
	 */
	public function delete( string $table, array $where, array $format = array() ): int {
		unset( $format );
		$this->queries[] = 'DELETE FROM ' . $table . ' ' . wp_json_encode_stub( $where );
		return 1;
	}

	/**
	 * Record a query run through query().
	 *
	 * @param string $sql SQL.
	 * @return int Rows affected.
	 */
	public function query( string $sql ): int {
		$this->queries[] = $sql;
		return 1;
	}
}

/**
 * Tiny JSON encoder for test query strings.
 *
 * @param array $data Data.
 * @return string JSON.
 */
function wp_json_encode_stub( array $data ): string {
	return (string) json_encode( $data );
}

// -----------------------------------------------------------------------------
// Assertions
// -----------------------------------------------------------------------------

$GLOBALS['ceafsn_test_pass']    = 0;
$GLOBALS['ceafsn_test_fail']    = 0;
$GLOBALS['ceafsn_test_name']    = '';
$GLOBALS['ceafsn_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_test_name']    = $name;
	$GLOBALS['ceafsn_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ok( bool $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_test_current']}] {$message}\n";
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
		++$GLOBALS['ceafsn_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_test_current']}] {$message}\n"
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
		++$GLOBALS['ceafsn_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_test_current']}] {$message}\n       missing: {$needle}\n";
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
		++$GLOBALS['ceafsn_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_test_current']}] {$message}\n       found: {$needle}\n";
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

$plugin_dir = dirname( __DIR__ );

/**
 * Build a public metric row.
 *
 * @param string $label   Label.
 * @param string $value   Value.
 * @param string $source  Source.
 * @param string $period  Reporting period.
 * @param string $unit    Unit.
 * @return object Metric row.
 */
function metric_row( string $label, string $value, string $source = 'Annual report', string $period = '2025', string $unit = '' ): object {
	return (object) array(
		'metric_id'        => 1,
		'label'            => $label,
		'value'            => $value,
		'unit'             => $unit,
		'definition'       => 'Definition supplied by the institution.',
		'source'           => $source,
		'reporting_period' => $period,
		'visibility'       => 'public',
	);
}

/**
 * Build a demographic row.
 *
 * @param string $label Label.
 * @param float  $value Value.
 * @param string $type  Value type.
 * @return object Demographic row.
 */
function demographic_row( string $label, float $value, string $type = 'percentage' ): object {
	return (object) array(
		'group_id'         => 1,
		'label'            => $label,
		'value'            => $value,
		'value_type'       => $type,
		'reporting_period' => '2025',
		'source'           => 'Household survey',
		'visibility'       => 'public',
	);
}

/**
 * Build a project row.
 *
 * @param string $title Project title.
 * @return object Project row.
 */
function project_row( string $title = 'Regional food security study' ): object {
	return (object) array(
		'project_id'           => 1,
		'title'                => $title,
		'principal_investigator' => 'Dr. A. Investigator',
		'target_region'        => 'East Africa',
		'status'               => 'active',
		'verification_status'  => 'verified',
		'last_updated'         => '2026-01-15',
		'source_url'           => '',
	);
}

// -----------------------------------------------------------------------------
// Load plugin
// -----------------------------------------------------------------------------

$source_header = file_get_contents( $plugin_dir . '/ceafsn-me-dashboard.php' );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( $plugin_dir, 2 ) . '/' );
}

require_once $plugin_dir . '/ceafsn-me-dashboard.php';

global $wpdb;
$wpdb = new FakeWpdb();

section( 'Plugin bootstrap' );

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', (string) $source_header, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_MED_VERSION' ), 'CEAFSN_MED_VERSION defined' );
ok( defined( 'CEAFSN_MED_PLUGIN_DIR' ), 'CEAFSN_MED_PLUGIN_DIR defined' );
ok( defined( 'CEAFSN_MED_PLUGIN_URL' ), 'CEAFSN_MED_PLUGIN_URL defined' );
is_same( '1.0.0', CEAFSN_MED_VERSION, 'version constant matches header' );

test( 'all plugin classes loaded' );
foreach ( array( 'CEAFSN_MED_DB', 'CEAFSN_MED_Activator', 'CEAFSN_MED_Admin', 'CEAFSN_MED_Public' ) as $class ) {
	ok( class_exists( $class ), "class {$class} exists" );
}

test( 'shortcode is registered on plugins_loaded' );
ok(
	isset( CEAFSN_Test_State::$actions['plugins_loaded'] ),
	'bootstrap registers a plugins_loaded callback'
);
ceafsn_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_Test_State::$shortcodes['ceafsn_me_dashboard'] ), '[ceafsn_me_dashboard] registered' );

// -----------------------------------------------------------------------------
section( 'Input validation' );

$admin        = new CEAFSN_MED_Admin();
$admin_ref    = new ReflectionClass( $admin );

/**
 * Invoke a private validator through reflection.
 *
 * @param ReflectionClass $class    Class with the method.
 * @param string          $method   Method name.
 * @param array           $data     Input data.
 * @return array<int,string>
 */
function call_validator( ReflectionClass $class, string $method, array $data ): array {
	$reflection = $class->getMethod( $method );
	$reflection->setAccessible( true );
	return (array) $reflection->invoke( $class->newInstanceWithoutConstructor(), $data );
}

test( 'metric validation rejects missing required fields' );
$errors = call_validator(
	$admin_ref,
	'validate_metric',
	array(
		'label'            => '',
		'value'            => '',
		'source'           => '',
		'reporting_period' => '',
		'visibility'       => 'public',
	)
);
is_same( 4, count( $errors ), 'four required-field errors reported' );

test( 'metric validation accepts a complete record' );
$errors = call_validator(
	$admin_ref,
	'validate_metric',
	array(
		'label'            => 'Households surveyed',
		'value'            => '1,240',
		'source'           => '2025 household survey',
		'reporting_period' => '2025',
		'visibility'       => 'public',
	)
);
is_same( array(), $errors, 'no errors for a valid metric' );

test( 'metric validation rejects an unknown visibility value' );
$errors = call_validator(
	$admin_ref,
	'validate_metric',
	array(
		'label'            => 'Label',
		'value'            => '10',
		'source'           => 'Source',
		'reporting_period' => '2025',
		'visibility'       => 'secret',
	)
);
is_same( 1, count( $errors ), 'invalid visibility rejected' );

test( 'demographic validation rejects a non-numeric value' );
$errors = call_validator(
	$admin_ref,
	'validate_demographic',
	array(
		'label'            => 'Group',
		'value'            => 'many',
		'value_type'       => 'count',
		'reporting_period' => '2025',
		'source'           => 'Survey',
	)
);
is_same( 1, count( $errors ), 'non-numeric value rejected' );

test( 'demographic validation rejects an unknown value type' );
$errors = call_validator(
	$admin_ref,
	'validate_demographic',
	array(
		'label'            => 'Group',
		'value'            => 42,
		'value_type'       => 'ratio',
		'reporting_period' => '2025',
		'source'           => 'Survey',
	)
);
is_same( 1, count( $errors ), 'unknown value_type rejected' );

test( 'project validation rejects an unknown status' );
$errors = call_validator(
	$admin_ref,
	'validate_project',
	array(
		'title'                  => 'Study',
		'principal_investigator' => 'Dr. A',
		'status'                 => 'ongoing',
		'verification_status'    => 'verified',
		'last_updated'           => '2026-01-01',
		'source_url'             => '',
	)
);
is_same( 1, count( $errors ), 'invalid project status rejected' );

test( 'project validation rejects a malformed date' );
$errors = call_validator(
	$admin_ref,
	'validate_project',
	array(
		'title'                  => 'Study',
		'principal_investigator' => 'Dr. A',
		'status'                 => 'active',
		'verification_status'    => 'verified',
		'last_updated'           => '15/01/2026',
		'source_url'             => '',
	)
);
is_same( 1, count( $errors ), 'non YYYY-MM-DD date rejected' );

test( 'project validation rejects a malformed source URL' );
$errors = call_validator(
	$admin_ref,
	'validate_project',
	array(
		'title'                  => 'Study',
		'principal_investigator' => 'Dr. A',
		'status'                 => 'active',
		'verification_status'    => 'verified',
		'last_updated'           => '2026-01-01',
		'source_url'             => 'not a url',
	)
);
is_same( 1, count( $errors ), 'invalid source_url rejected' );

test( 'project validation accepts a complete record' );
$errors = call_validator(
	$admin_ref,
	'validate_project',
	array(
		'title'                  => 'Study',
		'principal_investigator' => 'Dr. A',
		'status'                 => 'active',
		'verification_status'    => 'verified',
		'last_updated'           => '2026-01-01',
		'source_url'             => 'https://example.org/study',
	)
);
is_same( array(), $errors, 'no errors for a valid project' );

test( 'field extraction strips tags and slashes' );
$_POST = array(
	'label'            => '  <b>Households</b> surveyed\\',
	'value'            => '1,240',
	'unit'             => 'HH',
	'definition'       => '<script>alert(1)</script>Definition',
	'source'           => 'Survey',
	'reporting_period' => '2025',
	'visibility'       => 'PUBLIC',
);
$extract = $admin_ref->getMethod( 'extract_metric_fields' );
$extract->setAccessible( true );
$fields = (array) $extract->invoke( $admin, array() );
is_same( 'Households surveyed', $fields['label'], 'tags stripped and slashes removed from label' );
is_same( 'alert(1)Definition', $fields['definition'], 'script tags removed from textarea field' );
is_same( 'public', $fields['visibility'], 'visibility lowercased by sanitize_key' );
$_POST = array();

// -----------------------------------------------------------------------------
section( 'Public front end' );

/**
 * Render the shortcode with a given data set.
 *
 * @param array $metrics      Metric rows.
 * @param array $demographics Demographic rows.
 * @param array $projects     Project rows.
 * @param int   $total        Project total.
 * @return string Rendered HTML.
 */
function render_dashboard( array $metrics, array $demographics, array $projects, int $total = 1, array $options = array() ): string {
	global $wpdb;
	CEAFSN_Test_State::reset();
	foreach ( $options as $name => $value ) {
		update_option( $name, $value );
	}
	$wpdb->queries       = array();
	$wpdb->results_queue = array( $metrics, $demographics, $projects );
	$wpdb->var_result    = $total;
	$public             = new CEAFSN_MED_Public();
	return $public->render_shortcode( array() );
}

test( 'empty dashboard states that nothing is published' );
$html = render_dashboard( array(), array(), array(), 0 );
has_substring( 'No public metrics have been published yet.', $html, 'metric empty state shown' );
has_substring( 'No public demographic data has been published yet.', $html, 'demographic empty state shown' );
has_substring( 'No projects match the current filters.', $html, 'project empty state shown' );

test( 'empty dashboard invents no numbers' );
lacks_substring( 'Lorem', $html, 'no lorem ipsum' );
lacks_substring( 'TBD', $html, 'no TBD text' );
lacks_substring( 'coming soon', $html, 'no coming soon text' );
lacks_substring( 'sample data', $html, 'no sample data text' );
lacks_substring( 'demo data', $html, 'no demo data text' );
lacks_substring( 'N/A', $html, 'no N/A filler' );
is_same( 0, preg_match( '/>\s*\d[\d,.]*\s*</', $html ), 'no bare numeric filler in the output' );

test( 'dashboard exposes the ARIA tabs pattern' );
has_substring( 'role="tablist"', $html, 'tablist present' );
is_same( 3, substr_count( $html, 'role="tab"' ), 'three tabs rendered' );
is_same( 3, substr_count( $html, 'role="tabpanel"' ), 'three tabpanels rendered' );
has_substring( 'aria-controls="ceafsn-panel-overview"', $html, 'tab controls its panel' );
has_substring( 'aria-labelledby="ceafsn-tab-overview"', $html, 'panel is labelled by its tab' );
is_same( 1, substr_count( $html, 'aria-selected="true"' ), 'exactly one tab selected' );
has_substring( 'tabindex="-1"', $html, 'unselected tabs are removed from tab order' );

test( 'charts are paired with data tables' );
$html = render_dashboard( array( metric_row( 'Households surveyed', '1,240' ) ), array( demographic_row( 'Rural', 62.5 ) ), array( project_row() ) );
has_substring( 'aria-hidden="true"', $html, 'decorative chart hidden from assistive tech' );
has_substring( '<caption', $html, 'data table has a caption' );
is_same( 3, substr_count( $html, '<table' ), 'one table per panel' );
has_substring( 'scope="col"', $html, 'table headers use scope' );
has_substring( 'View as table', $html, 'toggle for the equivalent table exists' );

test( 'every table sits in a keyboard-scrollable wrapper' );
is_same( 3, substr_count( $html, 'class="ceafsn-med-table-wrap"' ), 'one wrapper per table' );
is_same( 3, substr_count( $html, 'role="region"' ), 'each wrapper is announced as a region' );
is_same( 3, substr_count( $html, 'class="ceafsn-med-table-wrap" tabindex="0"' ), 'each wrapper can be reached and scrolled by keyboard' );
has_substring( 'aria-labelledby="ceafsn-projects-caption"', $html, 'the project region is named by its caption' );

test( 'table cells carry the label the card layout reads on a phone' );
$html = render_dashboard( array( metric_row( 'Households surveyed', '1,240' ) ), array( demographic_row( 'Rural', 62.5 ) ), array( project_row() ) );
has_substring( 'data-label="Principal Investigator"', $html, 'the project row names its columns for a narrow screen' );
has_substring( 'data-label="Verification"', $html, 'including the badge columns' );
has_substring( 'data-label="Reporting Period"', $html, 'the metrics and demographics tables do too' );
$med_public_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-med-public.css' );
has_substring( 'attr(data-label)', $med_public_css, 'the stylesheet reads that label back' );
has_substring( 'min-height: 2.75rem', $med_public_css, 'the filter controls are sized for a thumb' );

test( 'record values are rendered and escaped' );
$html = render_dashboard(
	array( metric_row( '<script>alert(1)</script>Households', '1,240 & rising' ) ),
	array(),
	array()
);
lacks_substring( '<script>alert(1)</script>', $html, 'injected script tag is escaped' );
has_substring( '&lt;script&gt;', $html, 'injected tag rendered as text' );
has_substring( '1,240 &amp; rising', $html, 'ampersand in value escaped once' );
lacks_substring( '&amp;amp;', $html, 'value is not double escaped' );

test( 'preview banner only appears in preview mode' );
$html = render_dashboard( array(), array(), array(), 0 );
lacks_substring( 'ceafsn-med-preview-banner', $html, 'no preview banner by default' );

$html = render_dashboard( array(), array(), array(), 0, array( 'ceafsn_med_preview_mode' => '1' ) );
has_substring( 'ceafsn-med-preview-banner', $html, 'preview banner shown when enabled' );
has_substring( 'not live institutional data', $html, 'preview banner is explicit about not being live data' );

test( 'public queries are restricted to public records' );
render_dashboard( array(), array(), array(), 0 );
$metric_query = '';
foreach ( $wpdb->queries as $query ) {
	if ( str_contains( $query, 'ceafsn_med_metrics' ) ) {
		$metric_query = $query;
	}
}
has_substring( "visibility = 'public'", $metric_query, 'metric query filters to public visibility' );
ok( '' !== $metric_query, 'a metric query was actually run' );

test( 'query string filters are parameterised' );
$_GET = array(
	'med_status' => 'active',
	'med_search' => "O'Brien",
	'med_page'   => '2',
);
render_dashboard( array(), array(), array(), 0 );
$project_query = '';
foreach ( $wpdb->queries as $query ) {
	if ( str_contains( $query, 'ceafsn_med_projects' ) && str_contains( $query, 'LIMIT' ) ) {
		$project_query = $query;
	}
}
has_substring( "status = 'active'", $project_query, 'status filter applied' );
has_substring( "LIKE '%O\\'Brien%'", $project_query, 'search term is bound as an escaped literal' );
has_substring( 'principal_investigator LIKE', $project_query, 'search also covers the investigator field' );
has_substring( 'LIMIT 20 OFFSET 20', $project_query, 'page 2 offsets by per_page' );
$_GET = array();

test( 'unknown status values are ignored rather than injected' );
$_GET = array( 'med_status' => "active' OR 1=1 --" );
render_dashboard( array(), array(), array(), 0 );
foreach ( $wpdb->queries as $query ) {
	if ( str_contains( $query, 'ceafsn_med_projects' ) ) {
		lacks_substring( 'OR 1=1', $query, 'SQL injection attempt neutralised' );
	}
}
$_GET = array();

test( 'assets are enqueued once and only on render' );
CEAFSN_Test_State::reset();
$public = new CEAFSN_MED_Public();
$public->render_shortcode( array() );
$public->render_shortcode( array() );
is_same( 1, count( CEAFSN_Test_State::$styles ), 'stylesheet enqueued exactly once' );
is_same( 1, count( CEAFSN_Test_State::$scripts ), 'script enqueued exactly once' );
ok(
	isset( CEAFSN_Test_State::$styles[0] ) && CEAFSN_Test_State::$styles[0]['src'] !== '',
	'stylesheet has a source URL'
);
ok(
	isset( CEAFSN_Test_State::$scripts[0] ) && str_ends_with( CEAFSN_Test_State::$scripts[0]['src'], '.js' ),
	'script has a source URL'
);

test( 'search and filter controls are labelled' );
$html = render_dashboard( array(), array(), array(), 0 );
has_substring( 'for="ceafsn-med-search"', $html, 'search input has a label' );
has_substring( 'for="ceafsn-med-status"', $html, 'status select has a label' );
has_substring( 'class="screen-reader-text"', $html, 'visually hidden labels use the WP class' );

// -----------------------------------------------------------------------------
section( 'Data layer' );

test( 'pagination arguments are clamped' );
$wpdb->queries = array();
CEAFSN_MED_DB::get_projects(
	array(
		'per_page' => 0,
		'page'     => -5,
	)
);
$last = end( $wpdb->queries );
has_substring( 'LIMIT 1 OFFSET 0', (string) $last, 'per_page and page clamped to safe minimums' );

test( 'export returns all three collections' );
CEAFSN_Test_State::reset();
$wpdb->results_queue = array( array(), array(), array() );
$export = CEAFSN_MED_DB::export_all();
ok( isset( $export['metrics'], $export['demographics'], $export['projects'] ), 'export contains metrics, demographics, and projects' );
is_same( 3, count( $export ), 'export contains exactly three collections' );

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
	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/uninstall-cases.php' ) . ' ' . escapeshellarg( $case );
	$output  = (string) shell_exec( $command . ' 2>/dev/null' );
	$decoded = json_decode( $output, true );
	return is_array( $decoded ) ? $decoded : array( 'case' => $case, 'queries' => array() );
}

test( 'uninstall refuses to run outside the WordPress uninstall context' );
$result = run_uninstall_case( 'no-context' );
is_same( 0, count( $result['queries'] ), 'no queries run when WP_UNINSTALL_PLUGIN is undefined' );

test( 'uninstall keeps data when the opt-in flag is off' );
$result = run_uninstall_case( 'flag-off' );
is_same( 0, count( $result['queries'] ), 'no queries run when the delete flag is not set' );

test( 'uninstall drops tables only after explicit opt-in' );
$result = run_uninstall_case( 'flag-on' );
is_same( 3, count( $result['queries'] ), 'three tables dropped when opted in' );
has_substring( 'DROP TABLE IF EXISTS `wp_ceafsn_med_metrics`', (string) ( $result['queries'][0] ?? '' ), 'metrics table dropped' );
has_substring( 'DROP TABLE IF EXISTS `wp_ceafsn_med_demographics`', (string) ( $result['queries'][1] ?? '' ), 'demographics table dropped' );
has_substring( 'DROP TABLE IF EXISTS `wp_ceafsn_med_projects`', (string) ( $result['queries'][2] ?? '' ), 'projects table dropped' );
ok( ! isset( $result['options']['ceafsn_med_uninstall_delete_data'] ), 'the opt-in flag itself is cleared' );

test( 'the uninstall context guard is present' );
$uninstall_source = (string) file_get_contents( $plugin_dir . '/uninstall.php' );
has_substring( "if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )", $uninstall_source, 'guard against direct invocation' );
has_substring( "get_option( 'ceafsn_med_uninstall_delete_data'", $uninstall_source, 'deletion gated behind the opt-in option' );

// -----------------------------------------------------------------------------
section( 'Content hygiene' );

$php_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS ) );
$php_list  = array();
foreach ( $php_files as $file ) {
	if ( 'php' === strtolower( $file->getExtension() ) ) {
		$php_list[] = (string) $file->getPathname();
	}
}

test( 'no placeholder copy ships in the plugin' );
foreach ( $php_list as $file ) {
	if ( str_contains( $file, '/tests/' ) ) {
		continue;
	}
	$contents = (string) file_get_contents( $file );
	$haystack = strtolower( $contents );
	lacks_substring( 'lorem ipsum', $haystack, 'no lorem ipsum in ' . basename( $file ) );
	lacks_substring( 'dolor sit amet', $haystack, 'no lorem dolor in ' . basename( $file ) );
	lacks_substring( 'todo: ', $haystack, 'no TODO markers in ' . basename( $file ) );
	lacks_substring( 'example.com', $haystack, 'no example.com placeholders in ' . basename( $file ) );
}

test( 'no direct superglobal output without escaping' );
foreach ( $php_list as $file ) {
	$contents = (string) file_get_contents( $file );
	ok(
		! preg_match( '/echo\s+\$_(GET|POST|REQUEST|FILES)\b/', $contents ),
		'no raw echo of superglobals in ' . basename( $file )
	);
}

test( 'every PHP file guards direct access' );
foreach ( $php_list as $file ) {
	if ( str_contains( $file, '/tests/' ) || str_ends_with( $file, '/uninstall.php' ) ) {
		// uninstall.php runs in a context where ABSPATH is not guaranteed.
		continue;
	}
	$contents = (string) file_get_contents( $file );
	has_substring( "defined( 'ABSPATH' ) || exit", $contents, 'ABSPATH guard in ' . basename( $file ) );
}

test( 'every admin write handler checks capabilities and nonces' );
$admin_source = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-med-admin.php' );
preg_match_all( '/public function (handle_[a-z_]+)\(.*?\n\t\}/s', $admin_source, $handler_matches );
$handlers = $handler_matches[1] ?? array();
ok( count( $handlers ) >= 8, 'all expected write handlers are present (' . count( $handlers ) . ')' );
foreach ( $handlers as $handler ) {
	preg_match( '/public function ' . preg_quote( $handler, '/' ) . '\(.*?\n\t\}/s', $admin_source, $body );
	$body = (string) ( $body[0] ?? '' );
	$ceafsn_want = ( preg_match( '/handle_(save_settings|export|uninstall)/', $handler ) ) ? 'require_manage()' : 'require_edit()';
	$ceafsn_have = ( false !== strpos( $body, 'require_manage();' ) ) ? 'require_manage()' : 'require_edit()';
	is_same( $ceafsn_want, $ceafsn_have, "{$handler} is guarded by the capability that matches what it does" );
	has_substring( $ceafsn_want, $body, "{$handler} checks {$ceafsn_want}" );
	has_substring( 'check_admin_referer', $body, "{$handler} verifies a nonce" );
}
has_substring( "'manage_options'", $admin_source, 'manage_options is still accepted as a fallback capability' );
has_substring( 'private const CAP_EDIT', $admin_source, 'the capability names are mirrored locally so a missing shared library cannot fatal' );

test( 'referenced asset files exist' );
foreach ( array( 'assets/css/ceafsn-med-public.css', 'assets/css/ceafsn-med-admin.css', 'assets/js/ceafsn-med-public.js', 'assets/js/ceafsn-med-admin.js' ) as $asset ) {
	ok( file_exists( $plugin_dir . '/' . $asset ), "asset exists: {$asset}" );
}

test( 'the public stylesheet paints no single side of an element' );
$guard_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-med-public.css' );
is_same(
	0,
	(int) preg_match( '/border-(top|right|bottom|left)(-\w+)?\s*:/', $guard_css ),
	'no rule paints one side of an element with a border'
);

test( 'table names use the site prefix' );
$db_source = (string) file_get_contents( $plugin_dir . '/includes/class-ceafsn-med-db.php' );
has_substring( '{$wpdb->prefix}ceafsn_med_metrics', $db_source, 'metrics table uses $wpdb->prefix' );
has_substring( '{$wpdb->prefix}ceafsn_med_demographics', $db_source, 'demographics table uses $wpdb->prefix' );
has_substring( '{$wpdb->prefix}ceafsn_med_projects', $db_source, 'projects table uses $wpdb->prefix' );

test( 'the settings screen shows the administrator which shortcode to use' );
$med_settings_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
has_substring( '[ceafsn_me_dashboard]', $med_settings_partial, 'the exact shortcode is shown' );
has_substring( 'ceafsn-embed__code', $med_settings_partial, 'the shortcode is shown in the shared code block' );
has_substring( 'ceafsn-embed__label', $med_settings_partial, 'the code block carries a label' );
lacks_substring( '<code>per_page</code>', $med_settings_partial, 'no attribute is claimed for a shortcode that takes none' );
has_substring( 'no attributes', $med_settings_partial, 'the card says the shortcode takes no attributes' );
ok( str_contains( $med_settings_partial, "'display' === \$active_tab" ), 'the shortcode has its own read-only tab' );
$med_admin_css_source = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-med-admin.css' );
has_substring( 'ceafsn-embed', $med_admin_css_source, 'the stylesheet carries the shared embed component' );
ok(
	substr_count( $med_admin_css_source, '{' ) === substr_count( $med_admin_css_source, '}' ),
	'the admin stylesheet braces are still balanced'
);

test( 'the overview page shows the shortcode too' );
$main_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/overview.php' );
has_substring( '[ceafsn_me_dashboard]', $main_partial, 'the dashboard page shows the exact shortcode' );
has_substring( 'ceafsn-embed__code', $main_partial, 'the dashboard card uses the shared code block' );
has_substring( "no attributes", $main_partial, 'the card says the shortcode takes no attributes' );
lacks_substring( '<code>per_page</code>', $main_partial, 'no attribute is claimed for a shortcode that takes none' );

// -----------------------------------------------------------------------------
// -----------------------------------------------------------------------------
section( 'Admin: settings scopes' );

global $plugin_dir;
$med_admin_ref = new ReflectionClass( new CEAFSN_MED_Admin() );

/**
 * Call MED's private settings writer with a given POST body.
 *
 * @param ReflectionClass $class Admin class.
 * @param array           $post  POST body to save.
 * @return void
 */
function save_med_settings( ReflectionClass $class, array $post ): void {
	$method = $class->getMethod( 'persist_settings' );
	$method->setAccessible( true );
	$method->invoke( $class->newInstanceWithoutConstructor(), $post );
}

test( 'saving the preview tab leaves the uninstall opt-in untouched' );
CEAFSN_Test_State::$options = array(
	'ceafsn_med_preview_mode'          => '0',
	'ceafsn_med_uninstall_delete_data' => true,
);
save_med_settings( $med_admin_ref, array( 'ceafsn_med_settings_scope' => 'settings', 'ceafsn_med_preview_mode' => '1' ) );
is_same( '1', get_option( 'ceafsn_med_preview_mode' ), 'preview mode is saved' );
is_same( true, get_option( 'ceafsn_med_uninstall_delete_data' ), 'the uninstall opt-in on another tab survives' );

test( 'saving the Uninstall tab leaves preview mode untouched' );
CEAFSN_Test_State::$options = array(
	'ceafsn_med_preview_mode'          => '1',
	'ceafsn_med_uninstall_delete_data' => false,
);
save_med_settings( $med_admin_ref, array( 'ceafsn_med_settings_scope' => 'uninstall', 'ceafsn_med_uninstall_delete_data' => '1' ) );
is_same( true, get_option( 'ceafsn_med_uninstall_delete_data' ), 'the opt-in saves as true' );
is_same( '1', get_option( 'ceafsn_med_preview_mode' ), 'preview mode is not reset' );

test( 'a missing or unknown scope writes nothing' );
CEAFSN_Test_State::$options = array(
	'ceafsn_med_preview_mode'          => '0',
	'ceafsn_med_uninstall_delete_data' => false,
);
save_med_settings( $med_admin_ref, array( 'ceafsn_med_preview_mode' => '1', 'ceafsn_med_uninstall_delete_data' => '1' ) );
is_same( '0', get_option( 'ceafsn_med_preview_mode' ), 'no scope changes nothing' );
is_same( false, get_option( 'ceafsn_med_uninstall_delete_data' ), 'the opt-in is not silently enabled' );

test( 'the editable settings forms declare the scope they own' );
$med_set_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
foreach ( array( 'settings', 'uninstall' ) as $scope ) {
	has_substring( 'value="' . $scope . '"', $med_set_partial, "the {$scope} scope is posted by its own form" );
}


// -----------------------------------------------------------------------------
section( 'Translation and status labels' );

test( 'the textdomain is loaded on init, not before' );
$med_boot = (string) file_get_contents( $plugin_dir . '/ceafsn-me-dashboard.php' );
has_substring( "add_action( 'init', 'ceafsn_med_load_textdomain' );", $med_boot, 'the loader waits for init' );
ok( ! preg_match( "/add_action\\(\\s*'plugins_loaded'[^)]*load_textdomain/", $med_boot ), 'WP 6.7 deprecated loading a textdomain earlier than init, so nothing registers it on plugins_loaded' );
has_substring( "'ceafsn-med',", $med_boot, 'the domain string is ceafsn-med' );

test( 'every stored enum value has a translated label' );
foreach ( array( 'active', 'completed', 'suspended' ) as $med_key ) {
	$med_label = CEAFSN_MED_DB::label( CEAFSN_MED_DB::project_status_labels(), $med_key );
	ok( '' !== $med_label, 'project_status_labels() returns a non-empty label for "' . $med_key . '"' );
	ok( $med_label !== $med_key, 'and "' . $med_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'verified', 'pending', 'unverified' ) as $med_key ) {
	$med_label = CEAFSN_MED_DB::label( CEAFSN_MED_DB::verification_labels(), $med_key );
	ok( '' !== $med_label, 'verification_labels() returns a non-empty label for "' . $med_key . '"' );
	ok( $med_label !== $med_key, 'and "' . $med_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'count', 'percentage' ) as $med_key ) {
	$med_label = CEAFSN_MED_DB::label( CEAFSN_MED_DB::value_type_labels(), $med_key );
	ok( '' !== $med_label, 'value_type_labels() returns a non-empty label for "' . $med_key . '"' );
	ok( $med_label !== $med_key, 'and "' . $med_key . '" is not shown as a raw machine key' );
}
foreach ( array( 'public', 'private' ) as $med_key ) {
	$med_label = CEAFSN_MED_DB::label( CEAFSN_MED_DB::visibility_labels(), $med_key );
	ok( '' !== $med_label, 'visibility_labels() returns a non-empty label for "' . $med_key . '"' );
	ok( $med_label !== $med_key, 'and "' . $med_key . '" is not shown as a raw machine key' );
}

test( 'each label map covers the schema exactly' );
is_same( array( 'active', 'completed', 'suspended' ), array_keys( CEAFSN_MED_DB::project_status_labels() ), 'project_status_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'verified', 'pending', 'unverified' ), array_keys( CEAFSN_MED_DB::verification_labels() ), 'verification_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'count', 'percentage' ), array_keys( CEAFSN_MED_DB::value_type_labels() ), 'value_type_labels() has one label per stored value, no more and no fewer' );
is_same( array( 'public', 'private' ), array_keys( CEAFSN_MED_DB::visibility_labels() ), 'visibility_labels() has one label per stored value, no more and no fewer' );

test( 'an unrecognised key falls back to the key itself' );
is_same( 'Active', CEAFSN_MED_DB::label( CEAFSN_MED_DB::project_status_labels(), 'active' ), 'a known key returns its translated label' );
is_same( 'not_a_status', CEAFSN_MED_DB::label( CEAFSN_MED_DB::project_status_labels(), 'not_a_status' ), 'an unknown key is shown verbatim, so a missing label is obvious instead of silently English' );

test( 'no partial renders a stored enum through ucfirst()' );
$med_seen = 0;
foreach ( glob( $plugin_dir . '/admin/partials/*.php' ) ?: array() as $med_partial ) {
	$med_seen++;
	ok( ! str_contains( (string) file_get_contents( $med_partial ), 'ucfirst(' ), basename( $med_partial ) . ' does not title-case a stored value' );
}
foreach ( glob( $plugin_dir . '/public/partials/*.php' ) ?: array() as $med_partial ) {
	$med_seen++;
	ok( ! str_contains( (string) file_get_contents( $med_partial ), 'ucfirst(' ), basename( $med_partial ) . ' does not title-case a stored value' );
}
ok( $med_seen > 0, 'the partials were actually scanned' );

// -----------------------------------------------------------------------------
section( 'Schema migration engine' );

test( 'maybe_upgrade is a no-op when the stored version is current' );
CEAFSN_Test_State::reset();
$GLOBALS['ceafsn_med_dbdelta'] = array();
update_option( 'ceafsn_med_db_version', CEAFSN_MED_DB::SCHEMA_VERSION );
is_same( false, CEAFSN_MED_DB::maybe_upgrade(), 'a current schema is left alone' );
is_same( array(), (array) $GLOBALS['ceafsn_med_dbdelta'], 'and no schema work runs, so admin_init stays cheap' );

test( 'maybe_upgrade does nothing on a downgrade' );
CEAFSN_Test_State::reset();
$GLOBALS['ceafsn_med_dbdelta'] = array();
update_option( 'ceafsn_med_db_version', '99.0.0' );
is_same( false, CEAFSN_MED_DB::maybe_upgrade(), 'a newer stored schema is never downgraded' );
is_same( '99.0.0', get_option( 'ceafsn_med_db_version' ), 'and the stored version is untouched' );

test( 'maybe_upgrade applies dbDelta when the stored version is behind' );
CEAFSN_Test_State::reset();
$GLOBALS['ceafsn_med_dbdelta'] = array();
is_same( false, get_option( 'ceafsn_med_db_version', false ), 'no version is recorded on a site that never activated the plugin' );
is_same( true, CEAFSN_MED_DB::maybe_upgrade(), 'a missing schema takes the upgrade path' );
ok( ! empty( (array) $GLOBALS['ceafsn_med_dbdelta'] ), 'the schema is created through dbDelta' );
is_same( CEAFSN_MED_DB::SCHEMA_VERSION, get_option( 'ceafsn_med_db_version' ), 'and the version option is brought up to date' );

test( 'maybe_upgrade repairs an older installed version' );
CEAFSN_Test_State::reset();
$GLOBALS['ceafsn_med_dbdelta'] = array();
update_option( 'ceafsn_med_db_version', '0.9.0' );
is_same( true, CEAFSN_MED_DB::maybe_upgrade(), 'a version bump is applied' );
ok( ! empty( (array) $GLOBALS['ceafsn_med_dbdelta'] ), 'dbDelta runs so missing columns and indexes are added' );
is_same( CEAFSN_MED_DB::SCHEMA_VERSION, get_option( 'ceafsn_med_db_version' ), 'and the option ends at the current version' );

test( 'the migration is hooked to admin_init, not activation alone' );
$med_bootstrap = (string) file_get_contents( $plugin_dir . '/ceafsn-me-dashboard.php' );
has_substring( "add_action( 'admin_init', array( 'CEAFSN_MED_DB', 'maybe_upgrade' ) );", $med_bootstrap, 'plugin updates reach existing sites because the hook is on admin_init' );

// Remove the fake wp-admin tree. The fixture is rewritten on every run, so a
// failed run cannot poison the next one.
if ( file_exists( $med_fake_wp_admin . '/upgrade.php' ) ) {
	unlink( $med_fake_wp_admin . '/upgrade.php' );
	rmdir( $med_fake_wp_admin );
	rmdir( dirname( $med_fake_wp_admin ) );
	rmdir( dirname( $med_fake_wp_admin, 2 ) );
}

$pass = $GLOBALS['ceafsn_test_pass'];
$fail = $GLOBALS['ceafsn_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "Assertions: {$pass} passed, {$fail} failed\n";
echo str_repeat( '-', 60 ) . "\n";

exit( $fail > 0 ? 1 : 0 );
