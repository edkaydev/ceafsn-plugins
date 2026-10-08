<?php
/**
 * Test runner for CE-AFSN AI Assistant.
 *
 * Zero-dependency test suite. Run it with:
 *
 *     php plugins/ceafsn-ai-assistant/tests/run-tests.php
 *
 * Exit code 0 = all passed, 1 = at least one failure.
 *
 * @package CEAFSN_AI
 */

declare( strict_types=1 );

// This file is a CLI test harness: it must never execute over HTTP. Release
// ZIPs exclude tests/, and this guard makes the file inert if it is ever
// deployed by mistake.
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( 1 );
}

// Deliberate failure-path tests make the adapters log to error_log(). Send
// those lines to a scratch file so they do not land in the test output.
ini_set( 'error_log', sys_get_temp_dir() . '/ceafsn-ai-test-error.log' );

// WordPress is not installed here, so point ABSPATH at a temporary directory
// that contains just enough of wp-admin for the activator to load.
$fake_wp_admin = sys_get_temp_dir() . '/ceafsn-ai-fake-wp/wp-admin/includes';

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
	. "\t\t\t\$GLOBALS['ceafsn_ai_dbdelta'][] = (string) \$query;\n"
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
 * replays canned result sets for reads. `var_result` defaults to null rather
 * than 0 because the plugin distinguishes "no rows" from "zero": an absent
 * MAX() is an empty timestamp, not the epoch.
 */
final class FakeAiWpdb {

	/** @var string Table prefix. */
	public string $prefix = 'wp_';

	/** @var string Posts table name. */
	public string $posts = 'wp_posts';

	/**
	 * Character set clause appended to CREATE TABLE statements.
	 *
	 * @return string Charset and collation.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/** @var int Insert id after the last insert. */
	public int $insert_id = 0;

	/** @var array<int,string> Every query the plugin ran. */
	public array $queries = array();

	/** @var array<int,mixed> Result queue for the next get_results() calls. */
	public array $results_queue = array();

	/** @var array<int,mixed> Queue for the next get_var() calls. */
	public array $var_queue = array();

	/** @var mixed Value returned by get_var() when the queue is empty. */
	public mixed $var_result = null;

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
		++$this->insert_id;
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
		$this->insert_id     = 0;
		$this->queries       = array();
		$this->results_queue = array();
		$this->var_queue     = array();
		$this->var_result    = null;
		$this->row_result    = null;
		$this->col_queue     = array();
	}
}

// -----------------------------------------------------------------------------
// Assertions
// -----------------------------------------------------------------------------

$GLOBALS['ceafsn_ai_test_pass']    = 0;
$GLOBALS['ceafsn_ai_test_fail']    = 0;
$GLOBALS['ceafsn_ai_test_current'] = '';

/**
 * Start a named test case.
 *
 * @param string $name Test name.
 */
function test( string $name ): void {
	$GLOBALS['ceafsn_ai_test_current'] = $name;
}

/**
 * Assert a condition is true.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 */
function ok( bool $condition, string $message ): void {
	if ( $condition ) {
		++$GLOBALS['ceafsn_ai_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_ai_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_ai_test_current']}] {$message}\n";
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
		++$GLOBALS['ceafsn_ai_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_ai_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_ai_test_current']}] {$message}\n"
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
		++$GLOBALS['ceafsn_ai_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_ai_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_ai_test_current']}] {$message}\n       missing: {$needle}\n       in: {$haystack}\n";
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
		++$GLOBALS['ceafsn_ai_test_pass'];
		return;
	}
	++$GLOBALS['ceafsn_ai_test_fail'];
	echo "  FAIL [{$GLOBALS['ceafsn_ai_test_current']}] {$message}\n       found: {$needle}\n";
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
// Helpers
// -----------------------------------------------------------------------------

$plugin_dir = dirname( __DIR__ );

/**
 * Re-register the hooks the plugin's main file installed at load time.
 *
 * The main file runs exactly once per process, but CEAFSN_AI_Test_State::reset()
 * clears every recorded hook between test cases, so the three file-level
 * registrations have to be put back. `Plugin bootstrap` asserts that the source
 * still contains each of these lines, so they cannot drift apart silently.
 *
 * @return void
 */
function ceafsn_ai_test_register_file_hooks(): void {
	add_action( 'init', 'ceafsn_ai_load_textdomain' );
	add_action( 'plugins_loaded', 'ceafsn_ai_init' );
	add_action( 'admin_init', array( 'CEAFSN_AI_DB', 'maybe_upgrade' ) );

	if ( class_exists( 'CEAFSN_Audit_Log', false ) ) {
		add_action( 'admin_init', array( 'CEAFSN_Audit_Log', 'maybe_upgrade' ) );
	}
}

/**
 * Clear all recorded state and restore the plugin's file-level hooks.
 *
 * @param bool $as_admin Whether to simulate an admin request.
 * @return void
 */
function ceafsn_ai_test_reload( bool $as_admin = false ): void {
	CEAFSN_AI_Test_State::reset();

	if ( $as_admin ) {
		update_option( '__is_admin', 1 );
	}

	// Partials format timestamps through these; a fresh install sets them.
	update_option( 'date_format', 'j F Y' );
	update_option( 'time_format', 'H:i' );

	ceafsn_ai_test_register_file_hooks();
}

/**
 * Call a private method on the admin class without constructing it.
 *
 * @param ReflectionClass $class  Admin class reflection.
 * @param string          $method Method name.
 * @param mixed           ...$args Arguments.
 * @return mixed
 */
function call_admin( ReflectionClass $class, string $method, ...$args ) {
	$reflection = $class->getMethod( $method );
	$reflection->setAccessible( true );
	$target = $class->newInstanceWithoutConstructor();
	return $reflection->invokeArgs( $target, $args );
}

/**
 * Call a private static method on a class.
 *
 * @param ReflectionClass $class  Class reflection.
 * @param string          $method Method name.
 * @param mixed           ...$args Arguments.
 * @return mixed
 */
function call_static( ReflectionClass $class, string $method, ...$args ) {
	$reflection = $class->getMethod( $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( null, $args );
}

/**
 * Run a helper script in its own process and decode its JSON report.
 *
 * @param string $script Script file name inside tests/.
 * @param array  $args   Command line arguments.
 * @return array<string,mixed> Decoded report, or an empty array on failure.
 */
function ceafsn_ai_test_run_case( string $script, array $args ): array {
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
 * @return array{case: string, queries: array<int,string>, options: array<string,mixed>, transients: array<string,mixed>}
 */
function ceafsn_ai_test_run_uninstall_case( string $case ): array {
	$report = ceafsn_ai_test_run_case( 'uninstall-cases.php', array( $case ) );

	if ( ! isset( $report['queries'] ) || ! is_array( $report['queries'] ) ) {
		ok( false, "the uninstall case {$case} returned no usable report" );
		return array(
			'case'       => $case,
			'queries'    => array(),
			'options'    => array(),
			'transients' => array(),
		);
	}

	return array(
		'case'       => isset( $report['case'] ) ? (string) $report['case'] : $case,
		'queries'    => array_map( 'strval', $report['queries'] ),
		'options'    => isset( $report['options'] ) && is_array( $report['options'] ) ? $report['options'] : array(),
		'transients' => isset( $report['transients'] ) && is_array( $report['transients'] ) ? $report['transients'] : array(),
	);
}

/**
 * Render an admin page through the admin class itself.
 *
 * The renderers assemble their own variables and check capability, so going
 * through them proves the screen actually works rather than that its partial
 * parses in isolation.
 *
 * @param callable $callback Renderer to call.
 * @return string Rendered HTML.
 */
function render_admin_page( callable $callback ): string {
	ob_start();

	try {
		$callback();
	} finally {
		$html = (string) ob_get_clean();
	}

	return $html;
}

// -----------------------------------------------------------------------------
// Load plugin
// -----------------------------------------------------------------------------

$plugin_source = (string) file_get_contents( $plugin_dir . '/ceafsn-ai-assistant.php' );
require_once $plugin_dir . '/ceafsn-ai-assistant.php';

global $wpdb;
$wpdb = new FakeAiWpdb();

$admin_ref = new ReflectionClass( 'CEAFSN_AI_Admin' );

// -----------------------------------------------------------------------------
section( 'Plugin bootstrap' );
// -----------------------------------------------------------------------------

test( 'plugin header declares the required fields' );
foreach ( array( 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Text Domain', 'License' ) as $field ) {
	has_substring( $field . ':', $plugin_source, "header has {$field}" );
}

test( 'plugin constants are defined' );
ok( defined( 'CEAFSN_AI_VERSION' ), 'CEAFSN_AI_VERSION defined' );
ok( defined( 'CEAFSN_AI_PLUGIN_DIR' ), 'CEAFSN_AI_PLUGIN_DIR defined' );
ok( defined( 'CEAFSN_AI_PLUGIN_BASE' ), 'CEAFSN_AI_PLUGIN_BASE defined' );
is_same( '1.0.0', CEAFSN_AI_VERSION, 'version constant matches header' );
is_same( 'ceafsn-ai-assistant.php', basename( CEAFSN_AI_PLUGIN_BASE ), 'plugin basename matches the folder slug' );

test( 'all plugin classes loaded' );
foreach (
	array(
		'CEAFSN_AI_DB',
		'CEAFSN_AI_Provider',
		'CEAFSN_AI_Providers',
		'CEAFSN_AI_Provider_OpenAI',
		'CEAFSN_AI_Provider_Gemini',
		'CEAFSN_AI_Provider_Grok',
		'CEAFSN_AI_Provider_Claude',
		'CEAFSN_AI_Indexer',
		'CEAFSN_AI_Query',
		'CEAFSN_AI_Activator',
		'CEAFSN_AI_Admin',
		'CEAFSN_AI_Public',
	) as $ai_class
) {
	ok( class_exists( $ai_class ), "class {$ai_class} exists" );
}

test( 'the shared library is loaded exactly when its folder is present' );
$ai_shared_present = file_exists( dirname( $plugin_dir ) . '/ceafsn-shared/ceafsn-shared-load.php' );
is_same(
	$ai_shared_present,
	class_exists( 'CEAFSN_Caps', false ),
	'the optional CEAFSN shared library is loaded when the folder ships alongside the plugin'
);

test( 'access is decided without reading a class constant that may not exist' );
$ai_admin_source = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-ai-admin.php' );
has_substring( "class_exists( 'CEAFSN_Caps' )", $ai_admin_source, 'the capability check guards against the shared library being absent' );
lacks_substring( 'CEAFSN_Caps::', $ai_admin_source, 'the admin class never reads a static member off an optional class' );

$ai_cap = call_admin( $admin_ref, 'cap' );
is_same( $ai_shared_present ? 'ceafsn_manage' : 'manage_options', $ai_cap, 'the capability follows whichever library is installed' );

test( 'the shortcode is registered on plugins_loaded' );
ceafsn_ai_test_reload();
ceafsn_ai_test_fire_action( 'plugins_loaded' );
ok( isset( CEAFSN_AI_Test_State::$shortcodes['ceafsn_ai_assistant'] ), '[ceafsn_ai_assistant] registered' );

test( 'the REST route is registered on rest_api_init' );
ceafsn_ai_test_fire_action( 'rest_api_init' );
is_same( 1, count( CEAFSN_AI_Test_State::$rest_routes ), 'exactly one route is registered' );
$ai_route = CEAFSN_AI_Test_State::$rest_routes[0];
is_same( 'ceafsn-ai/v1', $ai_route['ns'], 'the namespace is ceafsn-ai/v1' );
is_same( '/ask', $ai_route['route'], 'the route is /ask' );
is_same( 'POST', $ai_route['args']['methods'], 'the endpoint accepts POST only' );
is_same( '__return_true', $ai_route['args']['permission_callback'], 'the endpoint is public, and says so explicitly' );
ok( isset( $ai_route['args']['args']['question']['validate_callback'] ), 'the question is validated before it reaches the model' );

test( 'translations are loaded on init' );
ceafsn_ai_test_fire_action( 'init' );
ok( in_array( 'ceafsn-ai', CEAFSN_AI_Test_State::$textdomains, true ), 'ceafsn-ai text domain loaded' );

test( 'the reload helper mirrors the hooks the main file registers' );
has_substring( "add_action( 'init', 'ceafsn_ai_load_textdomain' );", $plugin_source, 'the textdomain loader is on init' );
has_substring( "add_action( 'plugins_loaded', 'ceafsn_ai_init' );", $plugin_source, 'the bootstrap is on plugins_loaded' );
has_substring( "add_action( 'admin_init', array( 'CEAFSN_AI_DB', 'maybe_upgrade' ) );", $plugin_source, 'schema migrations are on admin_init' );
ok(
	! preg_match( "/add_action\\(\\s*'plugins_loaded'[^)]*load_textdomain/", $plugin_source ),
	'WP 6.7 deprecated loading a textdomain earlier than init, so nothing registers it on plugins_loaded'
);

test( 'the plugin does not touch the database at load time' );
is_same( array(), $wpdb->queries, 'requiring the plugin file runs no SQL' );

// -----------------------------------------------------------------------------
section( 'Schema' );
// -----------------------------------------------------------------------------

test( 'create_tables builds the chunks table through dbDelta' );
ceafsn_ai_test_reload();
$GLOBALS['ceafsn_ai_dbdelta'] = array();
CEAFSN_AI_DB::create_tables();
ok( ! empty( $GLOBALS['ceafsn_ai_dbdelta'] ), 'dbDelta ran' );
$ai_schema = implode( ' ', (array) $GLOBALS['ceafsn_ai_dbdelta'] );
has_substring( 'CREATE TABLE', $ai_schema, 'a create statement was issued' );
has_substring( 'wp_ceafsn_ai_chunks', $ai_schema, 'the table name carries the site prefix' );
foreach (
	array(
		'chunk_id',
		'source_type',
		'source_id',
		'source_label',
		'source_url',
		'lang',
		'chunk_text',
		'embedding',
		'provider',
		'indexed_at',
		'KEY source',
		'KEY lang',
		'KEY provider',
	) as $ai_column
) {
	has_substring( $ai_column, $ai_schema, "the schema declares {$ai_column}" );
}
is_same( CEAFSN_AI_DB::SCHEMA_VERSION, get_option( 'ceafsn_ai_db_version' ), 'the version option is recorded after creating the schema' );

test( 'maybe_upgrade is a no-op when the stored version is current' );
ceafsn_ai_test_reload();
$GLOBALS['ceafsn_ai_dbdelta'] = array();
update_option( 'ceafsn_ai_db_version', CEAFSN_AI_DB::SCHEMA_VERSION );
is_same( false, CEAFSN_AI_DB::maybe_upgrade(), 'a current schema is left alone' );
is_same( array(), (array) $GLOBALS['ceafsn_ai_dbdelta'], 'no schema work runs, so admin_init stays cheap' );

test( 'maybe_upgrade does nothing on a downgrade' );
ceafsn_ai_test_reload();
$GLOBALS['ceafsn_ai_dbdelta'] = array();
update_option( 'ceafsn_ai_db_version', '99.0.0' );
is_same( false, CEAFSN_AI_DB::maybe_upgrade(), 'a newer stored schema is never downgraded' );
is_same( '99.0.0', get_option( 'ceafsn_ai_db_version' ), 'and the stored version is untouched' );

test( 'maybe_upgrade applies dbDelta when the stored version is behind' );
ceafsn_ai_test_reload();
$GLOBALS['ceafsn_ai_dbdelta'] = array();
is_same( false, get_option( 'ceafsn_ai_db_version', false ), 'no version is recorded on a site that never activated the plugin' );
is_same( true, CEAFSN_AI_DB::maybe_upgrade(), 'a missing schema takes the upgrade path' );
ok( ! empty( $GLOBALS['ceafsn_ai_dbdelta'] ), 'the schema is created through dbDelta' );
is_same( CEAFSN_AI_DB::SCHEMA_VERSION, get_option( 'ceafsn_ai_db_version' ), 'and the version option is brought up to date' );

test( 'activation creates the schema and flushes rewrite rules' );
ceafsn_ai_test_reload();
update_option( '__caps', array( 'activate_plugins' => true ) );
$GLOBALS['ceafsn_ai_dbdelta'] = array();
CEAFSN_AI_Activator::activate();
ok( ! empty( $GLOBALS['ceafsn_ai_dbdelta'] ), 'activation runs create_tables()' );
is_same( CEAFSN_AI_DB::SCHEMA_VERSION, get_option( 'ceafsn_ai_db_version' ), 'and records the schema version' );

test( 'activation without the capability changes nothing' );
ceafsn_ai_test_reload();
update_option( '__caps', array() );
$GLOBALS['ceafsn_ai_dbdelta'] = array();
CEAFSN_AI_Activator::activate();
is_same( array(), (array) $GLOBALS['ceafsn_ai_dbdelta'], 'a subscriber cannot install plugin tables' );

// -----------------------------------------------------------------------------
section( 'Chunking' );
// -----------------------------------------------------------------------------

test( 'empty or whitespace-only text produces no chunks' );
is_same( array(), CEAFSN_AI_DB::split_into_chunks( '' ), 'an empty string is not a chunk' );
is_same( array(), CEAFSN_AI_DB::split_into_chunks( "   \n\t  " ), 'whitespace is not a chunk' );

test( 'text shorter than the chunk size stays in one piece' );
is_same( 1, count( CEAFSN_AI_DB::split_into_chunks( 'Short paragraph.' ) ), 'one short chunk' );

test( 'long text splits into chunks no bigger than the limit' );
$ai_long  = str_repeat( 'abcdefghij', 90 ); // 900 characters.
$ai_chunks = CEAFSN_AI_DB::split_into_chunks( $ai_long );
is_same( 2, count( $ai_chunks ), 'a 900-character text needs two chunks' );
foreach ( $ai_chunks as $ai_index => $ai_chunk ) {
	ok( mb_strlen( $ai_chunk ) <= CEAFSN_AI_DB::CHUNK_SIZE, "chunk {$ai_index} respects the size limit" );
}

test( 'consecutive chunks overlap so a boundary sentence is not lost' );
$ai_advance = CEAFSN_AI_DB::CHUNK_SIZE - (int) round( CEAFSN_AI_DB::CHUNK_SIZE * 0.10 );
is_same( substr( $ai_long, $ai_advance ), $ai_chunks[1], 'the second chunk starts where the first one overlapped' );
is_same(
	substr( $ai_chunks[0], $ai_advance ),
	substr( $ai_chunks[1], 0, CEAFSN_AI_DB::CHUNK_SIZE - $ai_advance ),
	'the two chunks share their boundary text'
);

test( 'a very long text keeps producing full-sized middle chunks' );
$ai_huge  = str_repeat( 'x', 5000 );
$ai_parts = CEAFSN_AI_DB::split_into_chunks( $ai_huge );
ok( count( $ai_parts ) >= 6, '5 000 characters produce at least six chunks' );
foreach ( array_slice( $ai_parts, 1, 3 ) as $ai_part ) {
	is_same( CEAFSN_AI_DB::CHUNK_SIZE, mb_strlen( $ai_part ), 'interior chunks are full width' );
}

// -----------------------------------------------------------------------------
section( 'Data layer' );
// -----------------------------------------------------------------------------

test( 'insert_chunk sanitises every field and stores the vector as JSON' );
ceafsn_ai_test_reload();
$ai_id = CEAFSN_AI_DB::insert_chunk(
	array(
		'source_type'  => 'WP Page!',
		'source_id'    => 9,
		'source_label' => '  About <b>CE-AFSN</b>  ',
		'source_url'   => 'https://example.test/about/',
		'lang'         => 'EN',
		'chunk_text'   => "Nutrition policy\nresearch <script>alert(1)</script>",
		'embedding'    => array( 0.1, 0.2, 0.3 ),
		'provider'     => 'OpenAI',
	)
);
is_same( 1, $ai_id, 'the new chunk id is returned' );
$ai_insert = (string) end( $wpdb->queries );
has_substring( '"source_type":"wppage"', $ai_insert, 'the source type is lowercased and stripped' );
has_substring( '"source_label":"About CE-AFSN"', $ai_insert, 'tags are removed from the label' );
has_substring( '"lang":"en"', $ai_insert, 'the language code is normalised' );
has_substring( '"embedding":"[0.1,0.2,0.3]"', $ai_insert, 'the vector round-trips as JSON' );
has_substring( '"provider":"openai"', $ai_insert, 'the provider key is normalised' );
lacks_substring( '<script>', $ai_insert, 'markup in the chunk text never reaches the database' );

test( 'a chunk with no vector stores an empty string, not a meaningless array' );
CEAFSN_AI_DB::insert_chunk(
	array(
		'source_type' => 'wp_page',
		'chunk_text'  => 'text',
		'embedding'   => array(),
	)
);
$ai_insert = (string) end( $wpdb->queries );
has_substring( '"embedding":""', $ai_insert, 'an empty vector is stored as an empty string' );

test( 'counts report what is actually stored' );
ceafsn_ai_test_reload();
$wpdb->var_queue = array( 12 );
is_same( 12, CEAFSN_AI_DB::count_chunks(), 'total chunk count' );
$wpdb->var_queue = array( 10 );
is_same( 10, CEAFSN_AI_DB::count_embedded_chunks(), 'embedded chunk count' );
$wpdb->var_queue = array( '2026-05-01 10:00:00' );
is_same( '2026-05-01 10:00:00', CEAFSN_AI_DB::last_indexed_at(), 'the most recent index time' );
$wpdb->var_queue = array( null );
is_same( '', CEAFSN_AI_DB::last_indexed_at(), 'an index that has never run reports an empty timestamp, not the epoch' );

test( 'provider_breakdown groups rows by the provider that built them' );
$wpdb->results_queue = array(
	array(
		(object) array( 'provider' => 'openai', 'n' => 7 ),
		(object) array( 'provider' => 'gemini', 'n' => 3 ),
	),
);
is_same( array( 'openai' => 7, 'gemini' => 3 ), CEAFSN_AI_DB::provider_breakdown(), 'each provider is counted separately' );

test( 'table existence is checked with an escaped LIKE so an underscore is not a wildcard' );
ceafsn_ai_test_reload();
$wpdb->queries = array();
call_static(
	new ReflectionClass( 'CEAFSN_AI_Indexer' ),
	'collect_plugin_table',
	'grants',
	'ceafsn_gf_items',
	'id',
	array( 'title' => 'Title' )
);
$ai_show_table = (string) end( $wpdb->queries );
has_substring( 'SHOW TABLES LIKE', $ai_show_table, 'the indexer probes the table before selecting from it' );
lacks_substring( "'wp_ceafsn_gf_items'", $ai_show_table, 'the table name is never passed to LIKE unescaped' );

test( 'the write helpers target the chunks table only' );
ceafsn_ai_test_reload();
CEAFSN_AI_DB::delete_all_chunks();
has_substring( 'DELETE FROM `wp_ceafsn_ai_chunks`', (string) end( $wpdb->queries ), 'a full wipe has no WHERE clause' );
CEAFSN_AI_DB::delete_chunks( 'wp_page', 5 );
has_substring( 'wp_ceafsn_ai_chunks {"source_type":"wp_page","source_id":5}', (string) end( $wpdb->queries ), 'a single source is targeted by type and id' );
CEAFSN_AI_DB::delete_chunks_by_type( 'ceafsn_gf' );
has_substring( 'wp_ceafsn_ai_chunks {"source_type":"ceafsn_gf"}', (string) end( $wpdb->queries ), 'a whole source type can be dropped' );

test( 'similarity search only ever asks for rows that carry a vector' );
ceafsn_ai_test_reload();
CEAFSN_AI_DB::get_chunks_with_embeddings( 'openai' );
$ai_select = (string) end( $wpdb->queries );
has_substring( "provider = 'openai'", $ai_select, 'the search is scoped to one embeddings provider' );
has_substring( "embedding != ''", $ai_select, 'rows without a vector are excluded in SQL' );
CEAFSN_AI_DB::get_chunks_with_embeddings();
has_substring( "embedding != ''", (string) end( $wpdb->queries ), 'the unfiltered search still excludes empty vectors' );
lacks_substring( 'provider =', (string) end( $wpdb->queries ), 'and it does not invent a provider filter' );

// -----------------------------------------------------------------------------
section( 'Providers: registry and models' );
// -----------------------------------------------------------------------------

test( 'the registry lists four providers with provider-only labels' );
ceafsn_ai_test_reload();
$ai_all = CEAFSN_AI_Providers::all();
is_same( array( 'openai', 'gemini', 'grok', 'claude' ), array_keys( $ai_all ), 'all four providers are offered' );
is_same(
	array( 'OpenAI', 'Google Gemini', 'xAI Grok', 'Anthropic Claude' ),
	array_values( $ai_all ),
	'labels name the vendor, never the model, because the model is editable'
);
foreach ( $ai_all as $ai_label ) {
	lacks_substring( 'gpt-', strtolower( $ai_label ), "label {$ai_label} does not pin a model name" );
	lacks_substring( 'gemini-', strtolower( $ai_label ), "label {$ai_label} does not pin a model name" );
}

test( 'every key option shares one prefix' );
foreach ( array( 'OPENAI', 'GEMINI', 'GROK', 'CLAUDE' ) as $ai_suffix ) {
	is_same(
		CEAFSN_AI_Providers::OPTION_KEY_PREFIX . strtolower( $ai_suffix ),
		constant( 'CEAFSN_AI_Providers::OPTION_KEY_' . $ai_suffix ),
		"OPTION_KEY_{$ai_suffix} is built from the shared prefix"
	);
}

test( 'active_key falls back to openai when the stored value is nonsense' );
ceafsn_ai_test_reload();
is_same( 'openai', CEAFSN_AI_Providers::active_key(), 'an unset option means OpenAI' );
update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, 'claude' );
is_same( 'claude', CEAFSN_AI_Providers::active_key(), 'a valid choice is honoured' );
update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, 'not-a-provider' );
is_same( 'openai', CEAFSN_AI_Providers::active_key(), 'a tampered value falls back instead of fataling' );

test( 'no provider object exists until an API key is stored' );
ceafsn_ai_test_reload();
is_same( null, CEAFSN_AI_Providers::make( 'openai' ), 'no key, no provider' );
is_same( null, CEAFSN_AI_Providers::active(), 'the active provider is null when unconfigured' );
is_same( null, CEAFSN_AI_Providers::embeddings(), 'no embeddings provider either' );
is_same( '', CEAFSN_AI_Providers::get_api_key( 'openai' ), 'a missing key reads as empty' );
is_same( '', CEAFSN_AI_Providers::get_api_key( 'nonsense' ), 'an unknown provider key is safe to ask about' );
is_same( null, CEAFSN_AI_Providers::make( 'nonsense' ), 'an unknown provider key builds nothing' );

test( 'a stored key produces the right provider object' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$ai_provider = CEAFSN_AI_Providers::make( 'openai' );
ok( $ai_provider instanceof CEAFSN_AI_Provider_OpenAI, 'make() returns the OpenAI adapter' );
is_same( 'OpenAI', $ai_provider->name(), 'with the shared label' );
is_same( 'openai', $ai_provider->key(), 'and the machine key' );
ok( CEAFSN_AI_Providers::active() instanceof CEAFSN_AI_Provider, 'active() resolves the same way' );

test( 'a provider with no embeddings API falls back to OpenAI, then Gemini' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, 'claude' );
update_option( CEAFSN_AI_Providers::OPTION_KEY_CLAUDE, 'sk-ant-live-1' );
is_same( null, CEAFSN_AI_Providers::embeddings(), 'Claude alone cannot build vectors' );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-2' );
is_same( 'openai', CEAFSN_AI_Providers::embeddings()?->key(), 'OpenAI is tried first' );
delete_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI );
update_option( CEAFSN_AI_Providers::OPTION_KEY_GEMINI, 'AIza-test-2' );
is_same( 'gemini', CEAFSN_AI_Providers::embeddings()?->key(), 'Gemini is the second choice' );
delete_option( CEAFSN_AI_Providers::OPTION_KEY_GEMINI );
is_same( null, CEAFSN_AI_Providers::embeddings(), 'and there is no third fallback' );

test( 'only the vendors that expose an embeddings API say they do' );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-3' );
$ai_openai_probe = CEAFSN_AI_Providers::make( 'openai' );
ok( $ai_openai_probe instanceof CEAFSN_AI_Provider, 'make() can build the OpenAI adapter' );
is_same( true, $ai_openai_probe?->supports_embeddings(), 'OpenAI embeds' );
is_same( false, ( new CEAFSN_AI_Provider_Grok( 'x' ) )->supports_embeddings(), 'Grok does not' );
is_same( false, ( new CEAFSN_AI_Provider_Claude( 'x' ) )->supports_embeddings(), 'Claude does not' );
is_same( null, ( new CEAFSN_AI_Provider_Claude( 'x' ) )->embed( 'anything' ), 'Claude returns null rather than pretending' );

test( 'every provider ships a default chat model' );
is_same( array_keys( $ai_all ), array_keys( CEAFSN_AI_Providers::DEFAULT_MODELS ), 'defaults exist for exactly the providers the registry offers' );
foreach ( CEAFSN_AI_Providers::DEFAULT_MODELS as $ai_key => $ai_models ) {
	ok( '' !== $ai_models['chat'], "{$ai_key} has a default chat model" );
	ok( isset( $ai_models['embed'] ), "{$ai_key} declares an embeddings slot" );
}
ok( '' !== CEAFSN_AI_Providers::DEFAULT_MODELS['openai']['embed'], 'OpenAI has a default embeddings model' );
ok( '' !== CEAFSN_AI_Providers::DEFAULT_MODELS['gemini']['embed'], 'Gemini has a default embeddings model' );
is_same( '', CEAFSN_AI_Providers::DEFAULT_MODELS['grok']['embed'], 'Grok has no embeddings model to default to' );
is_same( '', CEAFSN_AI_Providers::DEFAULT_MODELS['claude']['embed'], 'Claude has no embeddings model to default to' );

test( 'a saved model override is used, and a malformed one is discarded' );
ceafsn_ai_test_reload();
is_same( 'gpt-4o-mini', CEAFSN_AI_Providers::chat_model( 'openai' ), 'the default is used when nothing is saved' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai', 'gpt-4.1-mini' );
is_same( 'gpt-4.1-mini', CEAFSN_AI_Providers::chat_model( 'openai' ), 'a saved override wins' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai', 'not a model' );
is_same( 'gpt-4o-mini', CEAFSN_AI_Providers::chat_model( 'openai' ), 'a value with spaces is rejected' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai', '"gpt-4o-mini"; DROP TABLE' );
is_same( 'gpt-4o-mini', CEAFSN_AI_Providers::chat_model( 'openai' ), 'a value with quotes and punctuation is rejected' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai', '../gpt-4o-mini' );
is_same( 'gpt-4o-mini', CEAFSN_AI_Providers::chat_model( 'openai' ), 'a value that does not start alphanumeric is rejected' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai', 'org/path.model-v2' );
is_same( 'org/path.model-v2', CEAFSN_AI_Providers::chat_model( 'openai' ), 'slashes and dots are legitimate in a model id' );

test( 'embeddings overrides resolve the same way' );
ceafsn_ai_test_reload();
is_same( 'text-embedding-3-small', CEAFSN_AI_Providers::embed_model( 'openai' ), 'the default embeddings model' );
update_option( CEAFSN_AI_Providers::OPTION_EMBED_MODEL_PREFIX . 'openai', 'text-embedding-3-large' );
is_same( 'text-embedding-3-large', CEAFSN_AI_Providers::embed_model( 'openai' ), 'an override is honoured' );
is_same( '', CEAFSN_AI_Providers::embed_model( 'grok' ), 'a provider with no embeddings API resolves to an empty model' );

// -----------------------------------------------------------------------------
section( 'Providers: HTTP adapters' );
// -----------------------------------------------------------------------------

/**
 * Read the JSON body of the most recent outbound request.
 *
 * @return array<string,mixed>
 */
function ai_last_request_body(): array {
	$call = end( CEAFSN_AI_Test_State::$http_calls );
	return json_decode( (string) ( $call['args']['body'] ?? '' ), true ) ?: array();
}

/**
 * Read the URL of the most recent outbound request.
 *
 * @return string
 */
function ai_last_request_url(): string {
	$call = end( CEAFSN_AI_Test_State::$http_calls );
	return (string) ( $call['url'] ?? '' );
}

test( 'OpenAI embeddings go to the embeddings endpoint with the default model' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1234' );
$ai_openai = CEAFSN_AI_Providers::make( 'openai' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.1, 0.2, 0.3 ) ) ) ) ),
	)
);
is_same( array( 0.1, 0.2, 0.3 ), $ai_openai->embed( 'hello world' ), 'the vector comes back as floats' );
has_substring( 'https://api.openai.com/v1/embeddings', ai_last_request_url(), 'the embeddings endpoint is used' );
is_same( 'text-embedding-3-small', ai_last_request_body()['model'] ?? '', 'the default embeddings model is sent' );
$ai_call = end( CEAFSN_AI_Test_State::$http_calls );
is_same( 'Bearer sk-live-1234', $ai_call['args']['headers']['Authorization'] ?? '', 'the key travels in the Authorization header' );

test( 'OpenAI chat goes to the chat endpoint with both messages' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'The answer.' ) ) ) ) ),
	)
);
is_same( 'The answer.', $ai_openai->complete( 'You are CE-AFSN.', 'What grants exist?' ), 'the completion text is returned' );
has_substring( 'https://api.openai.com/v1/chat/completions', ai_last_request_url(), 'the chat endpoint is used' );
$ai_body = ai_last_request_body();
is_same( 'gpt-4o-mini', $ai_body['model'] ?? '', 'the default chat model is sent' );
is_same( 'system', $ai_body['messages'][0]['role'] ?? '', 'the system prompt is first' );
is_same( 'You are CE-AFSN.', $ai_body['messages'][0]['content'] ?? '', 'and carries the system prompt verbatim' );
is_same( 'user', $ai_body['messages'][1]['role'] ?? '', 'the visitor question is second' );
is_same( 'What grants exist?', $ai_body['messages'][1]['content'] ?? '', 'and carries the question verbatim' );

test( 'a saved chat model override replaces the default in the request body' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai', 'gpt-4.1-mini' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'ok' ) ) ) ) ),
	)
);
$ai_openai->complete( 'sys', 'q' );
is_same( 'gpt-4.1-mini', ai_last_request_body()['model'] ?? '', 'the override reaches the API' );
delete_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai' );

test( 'a provider failure is a null, never a half-parsed answer' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 500,
		'body' => (string) wp_json_encode( array( 'error' => array( 'message' => 'quota exceeded' ) ) ),
	)
);
is_same( null, $ai_openai->embed( 'hello' ), 'a 500 on embeddings is null' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 500,
		'body' => (string) wp_json_encode( array( 'error' => array( 'message' => 'model overloaded' ) ) ),
	)
);
is_same( null, $ai_openai->complete( 'sys', 'q' ), 'a 500 on chat is null' );
CEAFSN_AI_Test_State::queue_http( array( 'error' => new WP_Error( 'http_request_failed', 'connection refused' ) ) );
is_same( null, $ai_openai->embed( 'hello' ), 'a transport error is null' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => 'not a vector' ) ) ) ),
	)
);
is_same( null, $ai_openai->embed( 'hello' ), 'a malformed body is null rather than a cast crash' );

test( 'Gemini embeds through the embedContent method' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_GEMINI, 'AIza-live-1234' );
$ai_gemini = CEAFSN_AI_Providers::make( 'gemini' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'embedding' => array( 'values' => array( 1.0, 2.0 ) ) ) ),
	)
);
is_same( array( 1.0, 2.0 ), $ai_gemini->embed( 'question' ), 'the values array is unwrapped' );
has_substring( 'models/gemini-embedding-001:embedContent', ai_last_request_url(), 'the embedding model is in the path' );
lacks_substring( 'AIza-live-1234', ai_last_request_url(), 'the key is never placed in the request URL, where logs would keep it' );
is_same(
	'AIza-live-1234',
	end( CEAFSN_AI_Test_State::$http_calls )['args']['headers']['x-goog-api-key'] ?? '',
	'the key travels as the x-goog-api-key header instead'
);
is_same( 'models/gemini-embedding-001', ai_last_request_body()['model'] ?? '', 'and the body names the same model' );

test( 'Gemini chat reads the first candidate text' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode(
			array(
				'candidates' => array(
					array( 'content' => array( 'parts' => array( array( 'text' => 'Resposta.' ) ) ) ),
				),
			)
		),
	)
);
is_same( 'Resposta.', $ai_gemini->complete( 'sys', 'q' ), 'the candidate text is returned' );
has_substring( 'models/gemini-3.5-flash:generateContent', ai_last_request_url(), 'the chat model is in the path' );

test( 'Grok answers over the Chat Completions endpoint' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_GROK, 'xai-live-1234' );
$ai_grok = CEAFSN_AI_Providers::make( 'grok' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'Grok answer.' ) ) ) ) ),
	)
);
is_same( 'Grok answer.', $ai_grok->complete( 'sys', 'q' ), 'the completion text is returned' );
has_substring( 'https://api.x.ai/v1/chat/completions', ai_last_request_url(), 'xAI is called on the Chat Completions path' );
is_same( 'grok-4.5', ai_last_request_body()['model'] ?? '', 'the current Grok model is sent by default' );
update_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'grok', 'grok-4.1-fast' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'x' ) ) ) ) ),
	)
);
$ai_grok->complete( 'sys', 'q' );
is_same( 'grok-4.1-fast', ai_last_request_body()['model'] ?? '', 'and the operator can move to a newer one without a code change' );

test( 'Claude answers over the Messages endpoint with a separate system field' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_CLAUDE, 'sk-ant-live-1234' );
$ai_claude = CEAFSN_AI_Providers::make( 'claude' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => 'Claude answer.' ) ) ) ),
	)
);
is_same( 'Claude answer.', $ai_claude->complete( 'sys', 'q' ), 'the content block is unwrapped' );
has_substring( 'https://api.anthropic.com/v1/messages', ai_last_request_url(), 'Anthropic is called on the Messages path' );
$ai_body = ai_last_request_body();
is_same( 'claude-haiku-4-5', $ai_body['model'] ?? '', 'the current Claude model is sent by default' );
is_same( 'sys', $ai_body['system'] ?? '', 'the system prompt uses its own field, not a message role' );
$ai_call = end( CEAFSN_AI_Test_State::$http_calls );
is_same( 'sk-ant-live-1234', $ai_call['args']['headers']['x-api-key'] ?? '', 'the key is sent as x-api-key' );
is_same( '2023-06-01', $ai_call['args']['headers']['anthropic-version'] ?? '', 'and the API version is pinned' );

test( 'no request ever carries a key the plugin did not configure' );
ceafsn_ai_test_reload();
foreach ( CEAFSN_AI_Test_State::$http_calls as $ai_call ) {
	lacks_substring( 'sk-live-1234', (string) wp_json_encode( $ai_call ), 'a key from a previous case was not reused' );
}

// -----------------------------------------------------------------------------
section( 'Query engine: result codes' );
// -----------------------------------------------------------------------------

/**
 * Build the four-key result envelope every code shares.
 *
 * @param string $code Expected result code.
 * @param array  $got  Actual result.
 * @return void
 */
function assert_ai_envelope( string $code, array $got ): void {
	is_same( $code, $got['code'] ?? null, "the result code is {$code}" );
	ok( array_key_exists( 'answer', $got ), 'an answer key is always present' );
	ok( array_key_exists( 'sources', $got ), 'a sources key is always present' );
	ok( array_key_exists( 'error', $got ), 'an error key is always present' );
	is_same( 4, count( $got ), 'exactly four keys are returned' );
}

test( 'an empty question is refused before any provider is contacted' );
ceafsn_ai_test_reload();
$ai_result = CEAFSN_AI_Query::ask( '' );
assert_ai_envelope( 'empty_question', $ai_result );
has_substring( 'Please enter a question', $ai_result['error'], 'the refusal explains itself' );
is_same( array(), CEAFSN_AI_Test_State::$http_calls, 'no API call was spent on an empty question' );
$ai_result = CEAFSN_AI_Query::ask( "   \n  " );
assert_ai_envelope( 'empty_question', $ai_result );

test( 'a question with no chat provider is a configuration error, not a bad request' );
ceafsn_ai_test_reload();
$ai_result = CEAFSN_AI_Query::ask( 'What fellowships are open?' );
assert_ai_envelope( 'no_chat_provider', $ai_result );
has_substring( 'not configured yet', $ai_result['error'], 'the message says what to do' );
is_same( 503, call_static( new ReflectionClass( 'CEAFSN_AI_Public' ), 'status_for', $ai_result['code'] ), '503: the site is not set up' );

test( 'a chat key with no embeddings key cannot look anything up' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, 'claude' );
update_option( CEAFSN_AI_Providers::OPTION_KEY_CLAUDE, 'sk-ant-live-1' );
$ai_result = CEAFSN_AI_Query::ask( 'What fellowships are open?' );
assert_ai_envelope( 'no_embeddings_provider', $ai_result );
has_substring( 'No embeddings provider', $ai_result['error'], 'the message names the missing half' );
is_same( array(), CEAFSN_AI_Test_State::$http_calls, 'nothing was sent anywhere' );

test( 'a failed question embedding is reported as an upstream failure' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 500,
		'body' => (string) wp_json_encode( array( 'error' => array( 'message' => 'rate limited' ) ) ),
	)
);
$ai_result = CEAFSN_AI_Query::ask( 'What grants exist?' );
assert_ai_envelope( 'embed_failed', $ai_result );
is_same( 502, call_static( new ReflectionClass( 'CEAFSN_AI_Public' ), 'status_for', $ai_result['code'] ), '502: the upstream provider failed' );

test( 'an index that was never built is a setup step, not a wrong provider' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array( array() );
$wpdb->var_queue     = array( 0 );
$ai_result = CEAFSN_AI_Query::ask( 'What grants exist?' );
assert_ai_envelope( 'no_index', $ai_result );
has_substring( 'knowledge base is empty', $ai_result['error'], 'the message points at the index' );
is_same( 503, call_static( new ReflectionClass( 'CEAFSN_AI_Public' ), 'status_for', $ai_result['code'] ), '503: the site is not set up' );

test( 'an index built with another provider is distinguished from an empty one' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array( array() );
$wpdb->var_queue     = array( 900 );
$ai_result = CEAFSN_AI_Query::ask( 'What grants exist?' );
assert_ai_envelope( 'index_provider_mismatch', $ai_result );
has_substring( 'different embeddings provider', $ai_result['error'], 'the message names the real problem' );
has_substring( 'full rebuild', $ai_result['error'], 'and says how to fix it' );

test( 'a question with no close match is refused rather than guessed at' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array(
	array(
		(object) array(
			'chunk_id'     => 1,
			'source_label' => 'Nutrition policy brief',
			'source_url'   => 'https://example.test/?p=1',
			'lang'         => 'en',
			'chunk_text'   => 'A completely unrelated paragraph about rainfall.',
			'embedding'    => (string) wp_json_encode( array( 0.0, 1.0 ) ),
		),
	),
);
$ai_result = CEAFSN_AI_Query::ask( 'What grants exist?' );
assert_ai_envelope( 'no_match', $ai_result );
has_substring( 'No relevant content found', $ai_result['error'], 'the refusal is explicit' );
is_same( 404, call_static( new ReflectionClass( 'CEAFSN_AI_Public' ), 'status_for', $ai_result['code'] ), '404: nothing matched' );

test( 'a matching chunk is answered, with its source attached' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array(
	array(
		(object) array(
			'chunk_id'     => 4,
			'source_label' => 'Early-Career Nutrition Research Grant',
			'source_url'   => 'https://example.test/?p=4',
			'lang'         => 'en',
			'chunk_text'   => 'Applications close on 1 June 2026.',
			'embedding'    => (string) wp_json_encode( array( 1.0, 0.0 ) ),
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'Applications close on <strong>1 June 2026</strong>.' ) ) ) ) ),
	)
);
$ai_result = CEAFSN_AI_Query::ask( 'When does the grant close?' );
assert_ai_envelope( 'ok', $ai_result );
is_same( '', $ai_result['error'], 'a successful answer carries no error' );
has_substring( '<strong>1 June 2026</strong>', $ai_result['answer'], 'safe markup from the model is preserved' );
is_same( 1, count( $ai_result['sources'] ), 'one source is attributed' );
is_same( 'Early-Career Nutrition Research Grant', $ai_result['sources'][0]['label'], 'by the record label' );
is_same( 'https://example.test/?p=4', $ai_result['sources'][0]['url'], 'and a link back to it' );
$ai_body = ai_last_request_body();
has_substring( 'Applications close on 1 June 2026.', $ai_body['messages'][0]['content'] ?? '', 'the retrieved chunk is what grounds the prompt' );
has_substring( 'When does the grant close?', $ai_body['messages'][1]['content'] ?? '', 'the visitor question reaches the model unchanged' );
has_substring( 'Answer ONLY using the context provided below.', $ai_body['messages'][0]['content'] ?? '', 'the model is told to stay inside the index' );

test( 'a failed model call is reported as an upstream failure' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array(
	array(
		(object) array(
			'chunk_id'     => 4,
			'source_label' => 'Grant',
			'source_url'   => 'https://example.test/?p=4',
			'lang'         => 'en',
			'chunk_text'   => 'Applications close on 1 June 2026.',
			'embedding'    => (string) wp_json_encode( array( 1.0, 0.0 ) ),
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 503,
		'body' => '{"error":"upstream unavailable"}',
	)
);
$ai_result = CEAFSN_AI_Query::ask( 'When does the grant close?' );
assert_ai_envelope( 'model_failed', $ai_result );
has_substring( 'did not respond', $ai_result['error'], 'the message blames the model, not the site' );
is_same( 502, call_static( new ReflectionClass( 'CEAFSN_AI_Public' ), 'status_for', $ai_result['code'] ), '502: the upstream provider failed' );

test( 'the prompt is built in the language of the question, never invented facts' );
ceafsn_ai_test_reload();
$ai_prompt = (string) call_static( new ReflectionClass( 'CEAFSN_AI_Query' ), 'build_system_prompt', 'context here' );
has_substring( 'context here', $ai_prompt, 'the retrieved context is embedded in the prompt' );
has_substring( 'Detect the language of the user\'s question', $ai_prompt, 'the model is told to mirror the question language' );
has_substring( 'I don\'t have that information in the CE-AFSN knowledge base.', $ai_prompt, 'the not-in-the-index reply is scripted in both directions' );
has_substring( 'CE-AFSN', $ai_prompt, 'the assistant identifies itself as CE-AFSN' );

// -----------------------------------------------------------------------------
section( 'Query engine: retrieval and maths' );
// -----------------------------------------------------------------------------

test( 'cosine similarity is 1 for identical directions and 0 for orthogonal ones' );
is_same( 1.0, CEAFSN_AI_Query::cosine_similarity( array( 1.0, 0.0 ), array( 1.0, 0.0 ) ), 'identical vectors score 1' );
is_same( 0.0, CEAFSN_AI_Query::cosine_similarity( array( 1.0, 0.0 ), array( 0.0, 1.0 ) ), 'orthogonal vectors score 0' );
ok(
	abs( CEAFSN_AI_Query::cosine_similarity( array( 1.0, 2.0, 3.0 ), array( 4.0, 5.0, 6.0 ) ) - 0.974631826 ) < 1e-6,
	'the textbook [1,2,3] · [4,5,6] example is 0.9746'
);

test( 'degenerate vectors score 0 instead of dividing by zero' );
is_same( 0.0, CEAFSN_AI_Query::cosine_similarity( array(), array( 1.0 ) ), 'an empty vector is unrelated' );
is_same( 0.0, CEAFSN_AI_Query::cosine_similarity( array( 0.0, 0.0 ), array( 1.0, 1.0 ) ), 'a zero vector has no direction' );

test( 'mismatched dimensions are unrelated rather than a fatal error' );
is_same( 0.0, CEAFSN_AI_Query::cosine_similarity( array( 1.0, 0.0, 0.0 ), array( 1.0, 0.0 ) ), 'different lengths never score' );

test( 'the similarity threshold and top-K are deliberately bounded' );
ok( CEAFSN_AI_Query::MIN_SIMILARITY > 0.0 && CEAFSN_AI_Query::MIN_SIMILARITY < 1.0, 'the threshold is inside the score range' );
is_same( 5, CEAFSN_AI_Query::TOP_K, 'at most five chunks go into a prompt' );

test( 'malformed stored vectors are skipped, not cast blindly' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array(
	array(
		(object) array(
			'chunk_id'     => 1,
			'source_label' => 'Broken',
			'source_url'   => 'https://example.test/?p=1',
			'lang'         => 'en',
			'chunk_text'   => 'not json at all',
			'embedding'    => 'not json at all',
		),
		(object) array(
			'chunk_id'     => 2,
			'source_label' => 'Valid',
			'source_url'   => 'https://example.test/?p=2',
			'lang'         => 'en',
			'chunk_text'   => 'Applications close on 1 June 2026.',
			'embedding'    => (string) wp_json_encode( array( 1.0, 0.0 ) ),
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'ok' ) ) ) ) ),
	)
);
$ai_result = CEAFSN_AI_Query::ask( 'When do applications close?' );
assert_ai_envelope( 'ok', $ai_result );
is_same( 1, count( $ai_result['sources'] ), 'only the well-formed chunk is used' );
is_same( 'Valid', $ai_result['sources'][0]['label'], 'and it is the valid one' );

// -----------------------------------------------------------------------------
section( 'Indexer' );
// -----------------------------------------------------------------------------

test( 'indexing refuses to run with nothing to build vectors with' );
ceafsn_ai_test_reload();
$ai_summary = CEAFSN_AI_Indexer::run();
is_same( 0, $ai_summary['indexed'], 'nothing is indexed' );
is_same( 1, $ai_summary['errors'], 'the failure is counted' );
has_substring( 'No embeddings provider configured', implode( ' ', $ai_summary['sources'] ), 'the summary says what is missing' );
is_same( false, $ai_summary['partial'], 'an aborted run is not a partial run' );
is_same( array(), $wpdb->queries, 'no SQL is issued when there is no provider' );

test( 'a full rebuild wipes the index before rebuilding it' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->results_queue = array(
	array(
		(object) array(
			'ID'           => 7,
			'post_title'   => 'Research Fellowships',
			'post_content' => 'CE-AFSN awards twelve fellowships a year.',
			'post_type'    => 'page',
			'post_name'    => 'research-fellowships',
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.1, 0.2 ) ) ) ) ),
	)
);
$ai_summary = CEAFSN_AI_Indexer::run( true );
has_substring( 'DELETE FROM `wp_ceafsn_ai_chunks`', implode( "\n", $wpdb->queries ), 'the old index is wiped first' );
is_same( 1, $ai_summary['indexed'], 'one chunk written' );
is_same( 0, $ai_summary['errors'], 'and no errors' );
is_same( false, $ai_summary['partial'], 'a run with no budget is never partial' );
is_same( array( 'wp_page' ), $ai_summary['sources'], 'the source type is reported' );
$ai_insert = (string) end( $wpdb->queries );
has_substring( '"source_type":"wp_page"', $ai_insert, 'the page row was indexed' );
has_substring( '"source_url":"https:\/\/example.test\/?p=7"', $ai_insert, 'and carries a link back to the page' );
has_substring( '"provider":"openai"', $ai_insert, 'and records which vendor built the vector' );
is_same( 1, count( CEAFSN_AI_Test_State::$http_calls ), 'exactly one embeddings call for one chunk' );

test( 'an incremental run only clears the source being re-indexed' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->results_queue = array(
	array(
		(object) array(
			'ID'           => 8,
			'post_title'   => 'Open Datasets',
			'post_content' => 'Fourteen public datasets.',
			'post_type'    => 'post',
			'post_name'    => 'open-datasets',
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.3, 0.4 ) ) ) ) ),
	)
);
CEAFSN_AI_Indexer::run( false );
has_substring( 'wp_ceafsn_ai_chunks {"source_type":"wp_post","source_id":8}', implode( "\n", $wpdb->queries ), 'only this post\'s chunks are deleted' );
lacks_substring( 'DELETE FROM `wp_ceafsn_ai_chunks`', implode( "\n", $wpdb->queries ), 'and the whole index survives' );

test( 'a source with no text is skipped, not counted as indexed' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->results_queue = array(
	array(
		(object) array(
			'ID'           => 9,
			'post_title'   => '',
			'post_content' => '',
			'post_type'    => 'page',
			'post_name'    => 'empty',
		),
	),
);
$ai_summary = CEAFSN_AI_Indexer::run();
is_same( 0, $ai_summary['indexed'], 'nothing to index' );
is_same( 1, $ai_summary['skipped'], 'the empty source is reported as skipped' );
is_same( array(), CEAFSN_AI_Test_State::$http_calls, 'and no API money was spent on it' );

test( 'published pages and posts are collected, and other statuses are not' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->results_queue = array(
	array(
		(object) array(
			'ID'           => 3,
			'post_title'   => 'About',
			'post_content' => 'CE-AFSN is hosted at Eduardo Mondlane University.',
			'post_type'    => 'page',
			'post_name'    => 'about',
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.5, 0.6 ) ) ) ) ),
	)
);
CEAFSN_AI_Indexer::run();
$ai_select = '';
foreach ( $wpdb->queries as $ai_query ) {
	if ( str_contains( (string) $ai_query, 'post_status' ) ) {
		$ai_select = (string) $ai_query;
	}
}
has_substring( "post_status = 'publish'", $ai_select, 'only published content is indexed' );
has_substring( "post_type IN ('post','page')", $ai_select, 'and only pages and posts' );

test( 'plugin tables are skipped silently when those plugins are not installed' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->results_queue = array( array() );
$ai_summary = CEAFSN_AI_Indexer::run();
foreach ( array( 'ceafsn_gf', 'ceafsn_rf', 'ceafsn_pp', 'ceafsn_od', 'ceafsn_np', 'ceafsn_med' ) as $ai_absent ) {
	lacks_substring( $ai_absent, implode( ' ', $ai_summary['sources'] ), "{$ai_absent} is not reported as missing" );
}
is_same( 0, $ai_summary['errors'], 'a plugin that is not installed is not an error' );

test( 'a run that runs out of budget stops cleanly and reports itself as partial' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$ai_rows = array();
for ( $ai_i = 1; $ai_i <= 24; $ai_i++ ) {
	$ai_rows[] = (object) array(
		'ID'           => $ai_i,
		'post_title'   => 'Page ' . $ai_i,
		'post_content' => 'Content for page ' . $ai_i . '.',
		'post_type'    => 'page',
		'post_name'    => 'page-' . $ai_i,
	);
}
$wpdb->results_queue = array( $ai_rows );
for ( $ai_i = 0; $ai_i < 24; $ai_i++ ) {
	CEAFSN_AI_Test_State::queue_http(
		array(
			'code' => 200,
			'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.1, 0.1 ) ) ) ) ),
		)
	);
}
$ai_started = microtime( true );
$ai_summary = CEAFSN_AI_Indexer::run( true, 1 );
$ai_elapsed = microtime( true ) - $ai_started;
is_same( true, $ai_summary['partial'], 'the run reports itself as partial' );
ok( $ai_summary['indexed'] > 0 && $ai_summary['indexed'] < 24, 'some, but not all, pages were indexed' );
is_same( array( 'wp_page' ), $ai_summary['sources'], 'and it names what it did finish' );
ok( $ai_elapsed < 5.0, 'the deadline actually cut the run short' );
$ai_notice = call_static( $admin_ref, 'index_notice', $ai_summary );
is_same( 'warning', $ai_notice['type'], 'a partial run is a warning, never a success' );
has_substring( 'paused after reaching its time budget', $ai_notice['message'], 'and the notice says why' );

test( 'a completed run is reported as a success with its counts' );
$ai_notice = call_static(
	$admin_ref,
	'index_notice',
	array( 'indexed' => 42, 'skipped' => 3, 'errors' => 1, 'sources' => array( 'wp_page' ), 'partial' => false )
);
is_same( 'success', $ai_notice['type'], 'a finished run is a success' );
has_substring( 'Indexing complete.', $ai_notice['message'], 'the notice opens with the outcome' );
has_substring( '42', $ai_notice['message'], 'it reports the chunk count' );
has_substring( '3', $ai_notice['message'], 'it reports the skipped count' );
has_substring( '1', $ai_notice['message'], 'and it reports the error count' );

// -----------------------------------------------------------------------------
section( 'Indexer: visibility and invalidation' );
// -----------------------------------------------------------------------------

test( 'a password-protected page is not collected' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->results_queue = array( array() );
CEAFSN_AI_Indexer::run();
$ai_wp_select = '';
foreach ( $wpdb->queries as $ai_query ) {
	if ( str_contains( (string) $ai_query, 'post_status' ) ) {
		$ai_wp_select = (string) $ai_query;
	}
}
has_substring( "post_password = ''", $ai_wp_select, 'the query only takes pages a visitor can read without a password' );

test( 'a members-only publication is left out of the index' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->var_queue     = array( null, null, 'wp_ceafsn_pp_publications', null, null, null );
$wpdb->results_queue = array( array(), array() );
CEAFSN_AI_Indexer::run();
$ai_pp_select = '';
foreach ( $wpdb->queries as $ai_query ) {
	if ( str_contains( (string) $ai_query, 'FROM `wp_ceafsn_pp_publications`' ) ) {
		$ai_pp_select = (string) $ai_query;
	}
}
has_substring( "access_level = 'public'", $ai_pp_select, 'only publicly readable publications are collected' );
has_substring( "status = 'published'", $ai_pp_select, 'and only published ones' );

test( 'M&E projects are collected whatever their status says' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->var_queue     = array( null, null, null, null, null, 'wp_ceafsn_med_projects' );
$wpdb->results_queue = array( array(), array() );
CEAFSN_AI_Indexer::run();
$ai_med_select = '';
foreach ( $wpdb->queries as $ai_query ) {
	if ( str_contains( (string) $ai_query, 'FROM `wp_ceafsn_med_projects`' ) ) {
		$ai_med_select = (string) $ai_query;
	}
}
has_substring( 'WHERE (1 = 1)', $ai_med_select, 'the registry carries every row' );
lacks_substring( "status = 'published'", $ai_med_select, 'the blanket predicate would have matched no rows at all' );

test( 'saving a draft takes the page out of the index' );
ceafsn_ai_test_reload();
CEAFSN_AI_Indexer::on_post_saved( 9, (object) array( 'post_type' => 'page', 'post_status' => 'draft' ) );
has_substring(
	'wp_ceafsn_ai_chunks {"source_type":"wp_page","source_id":9}',
	(string) end( $wpdb->queries ),
	'the page\'s chunks are dropped'
);

test( 'a post type this plugin never indexes is left alone' );
ceafsn_ai_test_reload();
$wpdb->queries = array();
CEAFSN_AI_Indexer::on_post_saved( 4, (object) array( 'post_type' => 'ceafsn_gf', 'post_status' => 'publish' ) );
is_same( array(), $wpdb->queries, 'no query runs for another plugin\'s record' );

test( 'trashing a post without a post object purges both indexed types' );
ceafsn_ai_test_reload();
CEAFSN_AI_Indexer::on_post_removed( 12 );
$ai_purge = implode( "\n", $wpdb->queries );
has_substring( '"source_type":"wp_page","source_id":12', $ai_purge, 'the page is purged' );
has_substring( '"source_type":"wp_post","source_id":12', $ai_purge, 'and so is the post, since only an id was given' );

test( 'a record saved from another plugin is re-indexed once that request has written' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->var_queue     = array( 'wp_ceafsn_gf_grants' );
$wpdb->results_queue = array(
	array(
		(object) array(
			'grant_id'   => 5,
			'title'      => 'Seed funding call',
			'eligibility' => 'Open to UEM staff.',
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.2, 0.3 ) ) ) ) ),
	)
);
$_POST['grant_id'] = '5';
CEAFSN_AI_Indexer::queue_record_event( 'ceafsn_gf', 'grant_id', 'save' );
CEAFSN_AI_Indexer::flush_pending_events();
unset( $_POST['grant_id'] );
$ai_refresh = implode( "\n", $wpdb->queries );
has_substring( '"source_type":"ceafsn_gf","source_id":5', $ai_refresh, 'the old chunks for that grant are dropped' );
has_substring( '"source_type":"ceafsn_gf"', (string) end( $wpdb->queries ), 'and the new ones are written' );
is_same( 0, CEAFSN_AI_Indexer::dirty_state()['count'], 'the index kept up, so nothing is flagged' );

test( 'the same save with no embeddings provider flags the index as stale instead' );
ceafsn_ai_test_reload();
$wpdb->var_queue     = array( 'wp_ceafsn_gf_grants' );
$wpdb->results_queue = array(
	array(
		(object) array( 'grant_id' => 5, 'title' => 'Seed funding call', 'eligibility' => 'Open to UEM staff.' ),
	),
);
$_POST['grant_id'] = '5';
CEAFSN_AI_Indexer::queue_record_event( 'ceafsn_gf', 'grant_id', 'save' );
CEAFSN_AI_Indexer::flush_pending_events();
unset( $_POST['grant_id'] );
$ai_stale = CEAFSN_AI_Indexer::dirty_state();
is_same( 1, $ai_stale['count'], 'one unindexed change is counted' );
ok( $ai_stale['first'] > 0 && $ai_stale['last'] >= $ai_stale['first'], 'and the first and last change are both recorded' );
has_substring(
	'wp_ceafsn_ai_chunks {"source_type":"ceafsn_gf","source_id":5}',
	implode( "\n", $wpdb->queries ),
	'the stale text was dropped rather than left to be retrieved'
);

test( 'deleting a record from another plugin drops only that record' );
ceafsn_ai_test_reload();
$_POST['grant_id'] = '5';
CEAFSN_AI_Indexer::queue_record_event( 'ceafsn_gf', 'grant_id', 'delete' );
CEAFSN_AI_Indexer::flush_pending_events();
unset( $_POST['grant_id'] );
has_substring(
	'wp_ceafsn_ai_chunks {"source_type":"ceafsn_gf","source_id":5}',
	(string) end( $wpdb->queries ),
	'the deleted grant\'s chunks are gone'
 );
is_same( 0, CEAFSN_AI_Indexer::dirty_state()['count'], 'nothing else needs rebuilding' );

test( 'a complete run clears the stale flag, a partial one does not' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Indexer::mark_dirty();
CEAFSN_AI_Indexer::mark_dirty();
is_same( 2, CEAFSN_AI_Indexer::dirty_state()['count'], 'the flag counts every change' );
$wpdb->results_queue = array( array() );
CEAFSN_AI_Indexer::run();
is_same( 0, CEAFSN_AI_Indexer::dirty_state()['count'], 'a clean run catches up and resets it' );

ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Indexer::mark_dirty();
$ai_rows = array();
for ( $ai_i = 1; $ai_i <= 30; $ai_i++ ) {
	$ai_rows[] = (object) array(
		'ID'           => $ai_i,
		'post_title'   => 'Page ' . $ai_i,
		'post_content' => 'Content for page ' . $ai_i . '.',
		'post_type'    => 'page',
		'post_name'    => 'page-' . $ai_i,
	);
}
$wpdb->results_queue = array( $ai_rows );
for ( $ai_i = 0; $ai_i < 30; $ai_i++ ) {
	CEAFSN_AI_Test_State::queue_http(
		array(
			'code' => 200,
			'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 0.1, 0.1 ) ) ) ) ),
		)
	);
}
$ai_partial_summary = CEAFSN_AI_Indexer::run( true, 1 );
is_same( true, $ai_partial_summary['partial'], 'the run stopped at its deadline' );
is_same( 1, CEAFSN_AI_Indexer::dirty_state()['count'], 'a run that stopped early is still behind, so the flag survives' );

// -----------------------------------------------------------------------------
section( 'Public: REST and shortcode' );
// -----------------------------------------------------------------------------

test( 'every result code maps to an HTTP status a client can act on' );
ceafsn_ai_test_reload();
$ai_status = new ReflectionClass( 'CEAFSN_AI_Public' );
$ai_expectations = array(
	'ok'                     => 200,
	'empty_question'         => 400,
	'no_match'               => 404,
	'rate_limited'           => 429,
	'embed_failed'           => 502,
	'model_failed'           => 502,
	'no_chat_provider'       => 503,
	'no_embeddings_provider' => 503,
	'no_index'               => 503,
	'index_provider_mismatch' => 503,
);
foreach ( $ai_expectations as $ai_code => $ai_status_code ) {
	is_same( $ai_status_code, call_static( $ai_status, 'status_for', $ai_code ), "{$ai_code} responds {$ai_status_code}" );
}
is_same( 500, call_static( $ai_status, 'status_for', 'something_unexpected' ), 'an unknown code is a server error, never a 200' );

test( 'the endpoint answers with the query result and its status' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'data' => array( array( 'embedding' => array( 1.0, 0.0 ) ) ) ) ),
	)
);
$wpdb->results_queue = array(
	array(
		(object) array(
			'chunk_id'     => 4,
			'source_label' => 'Grant call',
			'source_url'   => 'https://example.test/?p=4',
			'lang'         => 'en',
			'chunk_text'   => 'Applications close on 1 June 2026.',
			'embedding'    => (string) wp_json_encode( array( 1.0, 0.0 ) ),
		),
	),
);
CEAFSN_AI_Test_State::queue_http(
	array(
		'code' => 200,
		'body' => (string) wp_json_encode( array( 'choices' => array( array( 'message' => array( 'content' => '1 June 2026.' ) ) ) ) ),
	)
);
$ai_public = new CEAFSN_AI_Public();
$ai_response = $ai_public->rest_ask( new WP_REST_Request( array( 'question' => 'When do applications close?' ) ) );
is_same( 200, $ai_response->get_status(), 'a grounded answer is 200' );
is_same( 'ok', $ai_response->get_data()['code'], 'and carries the ok code' );

test( 'a visitor gets 429 after twenty questions in a minute' );
ceafsn_ai_test_reload();
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$ai_limit_key = 'ceafsn_ai_rl_' . md5( '203.0.113.9' );
set_transient( $ai_limit_key, 19, 60 );
$ai_response = $ai_public->rest_ask( new WP_REST_Request( array( 'question' => 'What is CE-AFSN?' ) ) );
is_same( 20, get_transient( $ai_limit_key ), 'the twentieth question is still allowed and counted' );
ok( 429 !== $ai_response->get_status(), 'the twentieth question is not refused' );
$ai_response = $ai_public->rest_ask( new WP_REST_Request( array( 'question' => 'What is CE-AFSN?' ) ) );
is_same( 429, $ai_response->get_status(), 'the twenty-first is refused' );
is_same( 'rate_limited', $ai_response->get_data()['code'], 'with a machine-readable code' );
has_substring( 'Too many requests', $ai_response->get_data()['error'], 'and a message a person can read' );
is_same( 60, CEAFSN_AI_Test_State::$transients[ $ai_limit_key ]['expiry'], 'the counter expires after a minute' );
unset( $_SERVER['REMOTE_ADDR'] );

test( 'the rate-limit window is fixed, so a hit cannot push it forward' );
ceafsn_ai_test_reload();
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$ai_count_key           = 'ceafsn_ai_rl_' . md5( '203.0.113.7' );
$ai_window_key          = 'ceafsn_ai_rl_win_' . md5( '203.0.113.7' );
set_transient( $ai_window_key, time() - 50, 60 );
set_transient( $ai_count_key, 5, 60 );
$ai_public = new CEAFSN_AI_Public();
$ai_public->rest_ask( new WP_REST_Request( array( 'question' => 'What is CE-AFSN?' ) ) );
is_same( 6, get_transient( $ai_count_key ), 'the question was counted' );
$ai_remaining = (int) CEAFSN_AI_Test_State::$transients[ $ai_count_key ]['expiry'];
ok( $ai_remaining > 0 && $ai_remaining <= 10, "the counter expires with its window ({$ai_remaining}s left), not a fresh minute later" );
is_same( 60, CEAFSN_AI_Test_State::$transients[ $ai_window_key ]['expiry'], 'and the window itself was not restarted' );
unset( $_SERVER['REMOTE_ADDR'] );

test( 'a visitor at the cap is refused without being counted again' );
ceafsn_ai_test_reload();
$_SERVER['REMOTE_ADDR'] = '203.0.113.8';
$ai_count_key           = 'ceafsn_ai_rl_' . md5( '203.0.113.8' );
$ai_window_key          = 'ceafsn_ai_rl_win_' . md5( '203.0.113.8' );
set_transient( $ai_window_key, time() - 30, 60 );
set_transient( $ai_count_key, 20, 60 );
$ai_public = new CEAFSN_AI_Public();
$ai_response = $ai_public->rest_ask( new WP_REST_Request( array( 'question' => 'What is CE-AFSN?' ) ) );
is_same( 429, $ai_response->get_status(), 'the request is refused' );
is_same( 20, get_transient( $ai_count_key ), 'the counter was left where it was' );
is_same( 60, CEAFSN_AI_Test_State::$transients[ $ai_count_key ]['expiry'], 'and nothing was rewritten to buy the visitor more time' );
unset( $_SERVER['REMOTE_ADDR'] );

test( 'an over-long question is refused before any provider is called' );
ceafsn_ai_test_reload();
$ai_status_ref = new ReflectionClass( 'CEAFSN_AI_Public' );
$ai_result     = CEAFSN_AI_Query::ask( str_repeat( 'a', CEAFSN_AI_Query::MAX_QUESTION_LENGTH + 1 ) );
is_same( 'question_too_long', $ai_result['code'], 'the code names the problem' );
has_substring( '500', $ai_result['error'], 'the message says how long the limit is' );
is_same( 400, call_static( $ai_status_ref, 'status_for', $ai_result['code'] ), '400: the request was malformed, not the server' );
is_same( array(), CEAFSN_AI_Test_State::$http_calls, 'and no API money was spent on it' );
$ai_short = CEAFSN_AI_Query::ask( 'What is CE-AFSN?' );
lacks_substring( 'question_too_long', $ai_short['code'], 'a normal question is not caught by it' );

test( 'the route validates the length before the handler runs' );
ceafsn_ai_test_reload();
ceafsn_ai_test_fire_action( 'plugins_loaded' );
ceafsn_ai_test_fire_action( 'rest_api_init' );
$ai_validate = CEAFSN_AI_Test_State::$rest_routes[0]['args']['args']['question']['validate_callback'] ?? null;
ok( is_callable( $ai_validate ), 'the question carries a validate_callback' );
is_same( false, call_user_func( $ai_validate, str_repeat( 'a', 501 ) ), 'a 501-character question is rejected' );
is_same( true, call_user_func( $ai_validate, str_repeat( 'a', 500 ) ), 'a 500-character question is accepted' );
is_same( false, call_user_func( $ai_validate, 'ab' ), 'and a two-character question still is not' );

test( 'the shortcode renders the assistant UI with its REST wiring' );
ceafsn_ai_test_reload();
ceafsn_ai_test_fire_action( 'plugins_loaded' );
$ai_public = new CEAFSN_AI_Public();
$html = $ai_public->render_shortcode( array() );
has_substring( 'class="ceafsn-ai-assistant"', $html, 'the assistant wrapper is rendered' );
has_substring( 'data-rest-url="https://example.test/wp-json/ceafsn-ai/v1/ask"', $html, 'the script is pointed at the REST route' );
has_substring( 'data-nonce="testnonce"', $html, 'a REST nonce is handed to the script' );
has_substring( 'Ask a question about CE-AFSN research', $html, 'the English placeholder has a default' );
has_substring( 'Faça uma pergunta sobre pesquisa', $html, 'the Portuguese placeholder has a default' );
has_substring( 'aria-live="polite"', $html, 'answers are announced to assistive technology' );
has_substring( 'role="alert"', $html, 'errors are announced assertively' );
ok( wp_style_is( 'ceafsn-ai-public' ), 'the public stylesheet is enqueued' );
ok( wp_script_is( 'ceafsn-ai-public' ), 'the public script is enqueued' );

test( 'shortcode attributes override both placeholders' );
$html = $ai_public->render_shortcode(
	array(
		'placeholder_en' => 'Ask about fellowships',
		'placeholder_pt' => 'Pergunte sobre bolsas',
	)
);
has_substring( 'placeholder="Ask about fellowships"', $html, 'the English placeholder is overridden' );
has_substring( 'data-placeholder-pt="Pergunte sobre bolsas"', $html, 'the Portuguese placeholder is overridden' );
lacks_substring( 'Ask a question about CE-AFSN research', $html, 'and the default is gone' );

test( 'the public script handles a WordPress error body without a blank screen' );
$ai_js = (string) file_get_contents( $plugin_dir . '/assets/js/ceafsn-ai-public.js' );
has_substring( 'fallbackMessage', $ai_js, 'there is a localised fallback message' );
has_substring( '.catch(', $ai_js, 'a JSON parse failure is caught' );
has_substring( 'response.ok', $ai_js, 'non-2xx responses are treated as failures' );
has_substring( 'rate_limited', $ai_js, 'the script knows what a rate limit looks like' );
has_substring( 'escAttr( rawUrl )', $ai_js, 'the source link is escaped for its attribute, not just as text' );
lacks_substring( 'href="${url}"', $ai_js, 'the plain escaper is no longer trusted inside an attribute' );
has_substring( '/^https?:\\/\\//i', $ai_js, 'only a real web address is turned into a link' );

// -----------------------------------------------------------------------------
section( 'Admin: menu and assets' );
// -----------------------------------------------------------------------------

test( 'one menu entry and two pages are registered under a single slug' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
$ai_admin = new CEAFSN_AI_Admin();
$ai_admin->init();
ceafsn_ai_test_fire_action( 'admin_menu' );

is_same( 1, count( CEAFSN_AI_Test_State::$menus ), 'exactly one top-level menu' );
is_same( 2, count( CEAFSN_AI_Test_State::$submenus ), 'and two submenus: Overview, then Settings' );
is_same( 'ceafsn-ai', CEAFSN_AI_Test_State::$menus[0]['menu_slug'], 'the menu slug' );
is_same( 'ceafsn_manage', CEAFSN_AI_Test_State::$menus[0]['capability'], 'the capability follows the shared library' );
is_same( 'dashicons-superhero-alt', CEAFSN_AI_Test_State::$menus[0]['icon'], 'the menu has a dashicon' );
is_same( array( $ai_admin, 'render_overview_page' ), CEAFSN_AI_Test_State::$menus[0]['callback'], 'the top-level page renders the overview' );
is_same( 'ceafsn-ai', CEAFSN_AI_Test_State::$submenus[0]['menu_slug'], 'the first submenu is the overview' );
is_same( 'ceafsn-ai-settings', CEAFSN_AI_Test_State::$submenus[1]['menu_slug'], 'the second is settings' );
is_same( array( $ai_admin, 'render_settings_page' ), CEAFSN_AI_Test_State::$submenus[1]['callback'], 'rendering the settings screen' );
is_same(
	CEAFSN_AI_Test_State::$submenus[0]['hook'],
	CEAFSN_AI_Test_State::$menus[0]['hook'],
	'the overview reuses the parent hook so it lands first in the flyout'
);

test( 'the admin stylesheet loads on this plugin\'s screens only' );
$ai_settings_hook = CEAFSN_AI_Test_State::$submenus[1]['hook'];
CEAFSN_AI_Test_State::$styles = array();
$ai_admin->enqueue_assets( CEAFSN_AI_Test_State::$menus[0]['hook'] );
is_same( 1, count( CEAFSN_AI_Test_State::$styles ), 'the overview screen enqueues the stylesheet' );
is_same( 'ceafsn-ai-admin', CEAFSN_AI_Test_State::$styles[0]['handle'], 'with the expected handle' );
has_substring( 'ceafsn-ai-admin.css', CEAFSN_AI_Test_State::$styles[0]['src'], 'pointing at the plugin stylesheet' );

CEAFSN_AI_Test_State::$styles = array();
$ai_admin->enqueue_assets( $ai_settings_hook );
is_same( 1, count( CEAFSN_AI_Test_State::$styles ), 'the settings screen enqueues it too' );

CEAFSN_AI_Test_State::$styles = array();
$ai_admin->enqueue_assets( 'toplevel_page_some-other-plugin' );
is_same( array(), CEAFSN_AI_Test_State::$styles, 'a colliding slug on another plugin enqueues nothing' );
CEAFSN_AI_Test_State::$styles = array();
$ai_admin->enqueue_assets( '' );
is_same( array(), CEAFSN_AI_Test_State::$styles, 'an empty suffix enqueues nothing' );

test( 'a user without the capability is refused with a permission message' );
update_option( '__caps', array() );
CEAFSN_AI_Test_State::$died = false;
$ai_refused = false;
try {
	render_admin_page( array( $ai_admin, 'render_overview_page' ) );
} catch ( RuntimeException $e ) {
	$ai_refused = true;
}
ok( $ai_refused, 'rendering aborts instead of leaking the screen' );
ok( CEAFSN_AI_Test_State::$died, 'wp_die was called' );
has_substring( 'permission', CEAFSN_AI_Test_State::$die_message, 'and the message says why' );

test( 'the settings save handlers are wired to admin-post' );
$ai_source = (string) file_get_contents( $plugin_dir . '/admin/class-ceafsn-ai-admin.php' );
foreach ( array( 'ceafsn_ai_save_settings', 'ceafsn_ai_run_index', 'ceafsn_ai_clear_index' ) as $ai_action ) {
	has_substring( "admin_post_{$ai_action}", $ai_source, "a handler is registered for {$ai_action}" );
}

test( 'both forms post to admin-post.php with a nonce' );
$ai_overview = (string) file_get_contents( $plugin_dir . '/admin/partials/overview.php' );
has_substring( 'admin-post.php', $ai_overview, 'the overview forms post to admin-post' );
has_substring( 'wp_nonce_field( \'ceafsn_ai_index\' )', $ai_overview, 'the index form is nonced' );
has_substring( 'wp_nonce_field( \'ceafsn_ai_clear\' )', $ai_overview, 'the clear form is nonced' );
has_substring( 'return confirm(', $ai_overview, 'clearing the index asks first' );

// -----------------------------------------------------------------------------
section( 'Admin: settings scopes and tabs' );
// -----------------------------------------------------------------------------

/**
 * Resolve a POST body to its tab and persist it, the way the handler does.
 *
 * @param array<string,mixed> $post POST body.
 * @return void
 */
function save_ai_settings( array $post ): void {
	global $admin_ref;
	call_admin( $admin_ref, 'persist_settings', $post, call_admin( $admin_ref, 'posted_tab', $post ) );
}

test( 'the tab scope is read from the posted field, never assumed' );
is_same( 'providers', call_admin( $admin_ref, 'posted_tab', array( 'ceafsn_ai_settings_scope' => 'providers' ) ), 'the providers scope resolves' );
is_same( 'uninstall', call_admin( $admin_ref, 'posted_tab', array( 'ceafsn_ai_settings_scope' => 'uninstall' ) ), 'the uninstall scope resolves' );
is_same( 'display', call_admin( $admin_ref, 'posted_tab', array( 'ceafsn_ai_settings_scope' => 'display' ) ), 'the display scope resolves' );
is_same( '', call_admin( $admin_ref, 'posted_tab', array() ), 'a missing scope claims no tab' );
is_same( '', call_admin( $admin_ref, 'posted_tab', array( 'ceafsn_ai_settings_scope' => 'bogus' ) ), 'an unknown scope claims no tab' );

test( 'saving one tab leaves the options belonging to another alone' );
ceafsn_ai_test_reload();
update_option( 'ceafsn_ai_uninstall_delete_data', true );
update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, 'openai' );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-original' );
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'uninstall',
		'ceafsn_ai_uninstall_delete_data' => '1',
		'ceafsn_ai_provider' => 'gemini',
		'ceafsn_ai_key_openai' => 'sk-live-replaced',
	)
);
is_same( true, get_option( 'ceafsn_ai_uninstall_delete_data' ), 'the uninstall opt-in saves' );
is_same( 'openai', get_option( CEAFSN_AI_Providers::OPTION_ACTIVE ), 'the provider choice on another tab survives' );
is_same( 'sk-live-original', get_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI ), 'the stored key on another tab survives' );

test( 'saving the Providers tab writes keys but not the uninstall opt-in' );
ceafsn_ai_test_reload();
update_option( 'ceafsn_ai_uninstall_delete_data', false );
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'providers',
		'ceafsn_ai_provider'       => 'gemini',
		'ceafsn_ai_key_openai'     => 'sk-live-new-key-9876',
		'ceafsn_ai_model_openai'   => 'gpt-4.1-mini',
	)
);
is_same( 'gemini', get_option( CEAFSN_AI_Providers::OPTION_ACTIVE ), 'the active provider saves' );
is_same( 'sk-live-new-key-9876', get_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI ), 'a new key saves' );
is_same( 'gpt-4.1-mini', get_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai' ), 'the model override saves' );
is_same( false, get_option( 'ceafsn_ai_uninstall_delete_data' ), 'the uninstall opt-in on another tab is untouched' );

test( 'a submission with no scope writes nothing at all' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, 'openai' );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-keep-me' );
update_option( 'ceafsn_ai_uninstall_delete_data', false );
save_ai_settings( array( 'ceafsn_ai_key_openai' => 'sk-live-wiped' ) );
is_same( 'sk-live-keep-me', get_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI ), 'a bare POST cannot wipe the stored keys' );
is_same( false, get_option( 'ceafsn_ai_uninstall_delete_data' ), 'and it cannot switch the opt-in on' );
save_ai_settings( array( 'ceafsn_ai_settings_scope' => 'nonsense', 'ceafsn_ai_key_openai' => 'sk-live-wiped' ) );
is_same( 'sk-live-keep-me', get_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI ), 'an unknown scope changes nothing either' );

test( 'an unticked uninstall box saves as false, not as whatever it was' );
ceafsn_ai_test_reload();
update_option( 'ceafsn_ai_uninstall_delete_data', true );
save_ai_settings( array( 'ceafsn_ai_settings_scope' => 'uninstall' ) );
is_same( false, get_option( 'ceafsn_ai_uninstall_delete_data' ), 'an absent checkbox is an explicit off' );

test( 'a masked placeholder in the key field is never mistaken for a new key' );
ceafsn_ai_test_reload();
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-real-key-1234' );
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'providers',
		'ceafsn_ai_key_openai'     => str_repeat( '•', 18 ) . '1234',
	)
);
is_same( 'sk-live-real-key-1234', get_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI ), 'the stored key survives a redisplay' );
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'providers',
		'ceafsn_ai_key_openai'     => '',
	)
);
ok( false === get_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI ), 'an explicitly empty field deletes the key' );

test( 'a malformed model override is dropped at save time, not stored' );
ceafsn_ai_test_reload();
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'providers',
		'ceafsn_ai_model_openai'   => 'gpt 4o mini',
	)
);
is_same( false, get_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai' ), 'a model id with spaces is not stored' );
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'providers',
		'ceafsn_ai_model_openai'   => 'org/path.model-v2',
	)
);
is_same( 'org/path.model-v2', get_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai' ), 'a legitimate id is stored' );
save_ai_settings(
	array(
		'ceafsn_ai_settings_scope' => 'providers',
		'ceafsn_ai_model_openai'   => '',
	)
);
is_same( false, get_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . 'openai' ), 'clearing the field returns to the built-in default' );

test( 'each settings form declares the scope it owns' );
$ai_set_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
has_substring( 'name="ceafsn_ai_settings_scope"', $ai_set_partial, 'the scope field is posted' );
has_substring( 'value="providers"', $ai_set_partial, 'the providers form declares providers' );
has_substring( 'value="uninstall"', $ai_set_partial, 'the uninstall form declares uninstall' );
is_same( 2, substr_count( $ai_set_partial, 'name="ceafsn_ai_settings_scope"' ), 'exactly two forms carry a scope' );
is_same( 2, substr_count( $ai_set_partial, '<form' ), 'there are exactly two forms' );

test( 'the settings tabs are declared in one place the screens share' );
is_same(
	array( 'display', 'providers', 'uninstall' ),
	array_keys( CEAFSN_AI_Admin::SETTINGS_TABS ),
	'the tab list is the class constant, not scattered through the partial' );
has_substring( 'const SETTINGS_TABS', $ai_source, 'and it is a constant' );

// -----------------------------------------------------------------------------
section( 'Admin: branded layout' );
// -----------------------------------------------------------------------------

$ai_overview_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/overview.php' );
$ai_settings_partial = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
$ai_admin_css        = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-ai-admin.css' );

test( 'every admin partial is scoped to the plugin wrapper' );
has_substring( 'class="wrap ceafsn-ai-wrap"', $ai_overview_partial, 'the overview screen uses the scoped wrapper' );
has_substring( 'class="wrap ceafsn-ai-wrap"', $ai_settings_partial, 'the settings screen uses the scoped wrapper' );

test( 'the shared design system is scoped so it cannot leak into other plugins' );
ok(
	! preg_match( '/(^|\})\s*(body|html|p|h1|h2|h3|table|ul|ol|label|input)\s*\{/m', $ai_admin_css ),
	'no bare element selectors in the admin stylesheet'
);
has_substring( '.ceafsn-ai-wrap', $ai_admin_css, 'admin styles are scoped to the wrapper' );
is_same(
	substr_count( $ai_admin_css, '{' ),
	substr_count( $ai_admin_css, '}' ),
	'the admin stylesheet braces are balanced'
);
foreach ( array( 'ceafsn-gf', 'ceafsn-od', 'ceafsn-pp', 'ceafsn-rf', 'ceafsn-np', 'ceafsn-med' ) as $ai_foreign ) {
	lacks_substring( $ai_foreign, $ai_admin_css, "the stylesheet carries no {$ai_foreign} selectors" );
}

test( 'the overview uses the shared summary and layout components' );
foreach (
	array(
		'ceafsn-hero',
		'ceafsn-app',
		'ceafsn-app__main',
		'ceafsn-app__rail',
		'ceafsn-kpi',
		'ceafsn-kpi__value',
		'ceafsn-stepper',
		'ceafsn-card',
		'ceafsn-card__head',
		'ceafsn-card__body',
		'ceafsn-embed',
		'ceafsn-cta',
		'ceafsn-ticks',
		'ceafsn-alerts',
		'ceafsn-alert__title',
		'ceafsn-check',
		'ceafsn-form__actions',
		'ceafsn-btn',
		'ceafsn-sr',
		'ceafsn-help',
	) as $ai_component
) {
	has_substring( $ai_component, $ai_overview_partial, "the overview uses {$ai_component}" );
	has_substring( $ai_component, $ai_admin_css, "the stylesheet defines {$ai_component}" );
}

test( 'the settings screen uses the shared form and tab components' );
foreach (
	array(
		'ceafsn-hero',
		'ceafsn-tabs',
		'ceafsn-tabs__tab--active',
		'ceafsn-card',
		'ceafsn-field',
		'ceafsn-field__hint',
		'ceafsn-field__mono',
		'ceafsn-embed',
		'ceafsn-embed__table',
		'ceafsn-embed__caption',
		'ceafsn-check',
		'ceafsn-form__actions',
		'ceafsn-alert',
		'ceafsn-btn',
	) as $ai_component
) {
	has_substring( $ai_component, $ai_settings_partial, "settings uses {$ai_component}" );
	has_substring( $ai_component, $ai_admin_css, "the stylesheet defines {$ai_component}" );
}

test( 'the AI vocabulary has its own status modifiers' );
foreach (
	array(
		'ceafsn-badge--ready',
		'ceafsn-badge--empty',
		'ceafsn-badge--warn',
		'ceafsn-badge--key-set',
		'ceafsn-badge--key-missing',
		'ceafsn-dot--green',
		'ceafsn-dot--gold',
		'ceafsn-dot--grey',
		'ceafsn-dot--danger',
		'ceafsn-kpi--accent',
		'ceafsn-kpi--alert',
		'ceafsn-pill--green',
		'ceafsn-alert--warn',
		'ceafsn-alert--danger',
		'ceafsn-alert--ok',
		'ceafsn-ai-flag',
	) as $ai_modifier
) {
	has_substring( $ai_modifier, $ai_admin_css, "the stylesheet styles {$ai_modifier}" );
}
has_substring( 'ceafsn-badge--<?php echo', $ai_overview_partial, 'the index badge is chosen from the real counts' );
has_substring( "'warning' === \$ai_notice['type'] ? 'warn'", $ai_overview_partial, 'a warning notice maps to the warn modifier' );

test( 'a password field is styled like every other field' );
has_substring( 'input[type="password"]', $ai_admin_css, 'the API key input is covered by the field rules' );

test( 'nothing on the overview invents a figure' );
has_substring( 'number_format_i18n( $chunk_count )', $ai_overview_partial, 'chunk counts are locale-formatted' );
has_substring( 'number_format_i18n( $shortcode_pages )', $ai_overview_partial, 'site counts are locale-formatted' );
has_substring( 'count_shortcode_pages()', $ai_source, 'the site count is computed from published content' );
has_substring( 'has_shortcode(', $ai_source, 'by looking for the shortcode itself' );
lacks_substring( 'shortcode_exists(', $ai_source, 'and not from a stored guess' );

test( 'the help badge jumps to a section that actually exists' );
has_substring( 'id="ceafsn-ai-help"', $ai_overview_partial, 'the help section carries the anchor' );
has_substring( 'href="#ceafsn-ai-help"', $ai_overview_partial, 'and the badge points at it' );

test( 'the public script and stylesheet are branded consistently' );
$ai_public_css = (string) file_get_contents( $plugin_dir . '/assets/css/ceafsn-ai-public.css' );
has_substring( '.ceafsn-ai-assistant', $ai_public_css, 'the public stylesheet scopes to the assistant' );
has_substring( '.ceafsn-ai-wrap', $ai_overview_partial, 'the admin screen uses the same ceafsn-ai namespace' );
has_substring( 'ceafsn-ai-assistant', (string) file_get_contents( $plugin_dir . '/assets/js/ceafsn-ai-public.js' ), 'the script addresses the same root class' );

// -----------------------------------------------------------------------------
section( 'Admin: partials render' );
// -----------------------------------------------------------------------------

test( 'the overview renders an unconfigured site without fataling' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
$ai_admin = new CEAFSN_AI_Admin();
$html = render_admin_page( array( $ai_admin, 'render_overview_page' ) );
ok( strlen( $html ) > 1500, 'the page produced real markup' );
has_substring( 'class="wrap ceafsn-ai-wrap"', $html, 'inside the scoped wrapper' );
has_substring( 'No chat provider is configured', $html, 'the missing chat provider blocks loudly' );
has_substring( 'No embeddings provider is configured', $html, 'and so does the missing embeddings key' );
has_substring( 'The knowledge base is empty', $html, 'an empty index blocks too' );
has_substring( 'ceafsn-kpi--alert', $html, 'the blocking-issues tile is in its alert state' );
has_substring( 'ceafsn-alert--danger', $html, 'and the issues are rendered as danger alerts' );
has_substring( 'The assistant answers only when all three steps are done', $html, 'the screen explains the gate' );
has_substring( 'Add an API key', $html, 'the rail offers the next action' );
has_substring( '[ceafsn_ai_assistant]', $html, 'the shortcode is shown' );
has_substring( 'id="ceafsn-ai-index"', $html, 'the knowledge base card is anchored' );
has_substring( 'ceafsn-badge--empty', $html, 'zero chunks is an empty badge, not a zero of a healthy total' );
has_substring( 'Not configured', $html, 'an unset provider is named as such' );
is_same( 1, substr_count( $html, 'ceafsn-step is-current' ), 'exactly one setup step is current' );
is_same( 0, substr_count( $html, 'ceafsn-step is-done' ), 'and no step is marked done without a count behind it' );
has_substring( 'disabled="disabled"', $html, 'indexing cannot be started without an embeddings provider' );

test( 'the overview reports a configured site as ready' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->var_queue     = array( 40, 38, '2026-05-01 10:00:00' );
$wpdb->results_queue = array(
	array( (object) array( 'provider' => 'openai', 'n' => 40 ) ),
);
CEAFSN_AI_Test_State::add_post( 7, 'Ask the assistant: [ceafsn_ai_assistant]' );
$ai_admin = new CEAFSN_AI_Admin();
$html = render_admin_page( array( $ai_admin, 'render_overview_page' ) );

lacks_substring( 'ceafsn-alert--danger', $html, 'a working site raises no danger alert' );
has_substring( 'ceafsn-pill--green', $html, 'the knowledge base pill is green' );
has_substring( '>Ready<', $html, 'and it says Ready' );
has_substring( 'The assistant can answer a question now', $html, 'the blocking tile reports a healthy state' );
has_substring( 'ceafsn-dot--green', $html, 'the chunks tile carries a green dot' );
has_substring( 'Provider configured', $html, 'the first setup step is named' );
has_substring( '1 May 2026', $html, 'the last index time is rendered in the site date format' );
has_substring( 'Live on 1 published item', $html, 'the shortcode placement is counted from real content' );
has_substring( 'ceafsn-step is-done', $html, 'a proved step is marked done' );
is_same( 2, substr_count( $html, 'ceafsn-step is-done' ), 'two steps are proved' );
has_substring( '38 with vectors', $html, 'the stepper reports the vector shortfall instead of hiding it' );
has_substring( 'cannot be found until they are embedded', $html, 'and the shortfall is raised as a warning' );
lacks_substring( 'disabled="disabled"', $html, 'indexing can be started' );

test( 'an index built with another provider is told to rebuild' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-1' );
$wpdb->var_queue     = array( 40, 40, '2026-05-01 10:00:00' );
$wpdb->results_queue = array(
	array( (object) array( 'provider' => 'gemini', 'n' => 40 ) ),
);
$ai_admin = new CEAFSN_AI_Admin();
$html = render_admin_page( array( $ai_admin, 'render_overview_page' ) );
has_substring( 'ceafsn-alert--danger', $html, 'the mismatch is a blocking danger alert' );
has_substring( 'different embeddings provider', $html, 'which names the real problem' );
has_substring( 'ceafsn-ai-flag', $html, 'the row carries the mismatch flag' );
has_substring( 'belong to a different provider', $html, 'and quantifies it' );

test( 'the settings screen renders every tab' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
update_option( CEAFSN_AI_Providers::OPTION_KEY_OPENAI, 'sk-live-example-key-9876' );
$ai_admin = new CEAFSN_AI_Admin();

foreach ( array( 'display', 'providers', 'uninstall' ) as $ai_tab ) {
	$_GET          = array( 'tab' => $ai_tab );
	$html          = render_admin_page( array( $ai_admin, 'render_settings_page' ) );
	ok( strlen( $html ) > 800, "the {$ai_tab} tab renders" );
	has_substring( 'class="wrap ceafsn-ai-wrap"', $html, "the {$ai_tab} tab is scoped" );
	has_substring( 'ceafsn-tabs__tab--active', $html, "the {$ai_tab} tab marks itself active" );
	has_substring( 'aria-current="page"', $html, "and announces itself to assistive technology" );
}

test( 'an unknown settings tab falls back to Display' );
$_GET = array( 'tab' => 'bogus' );
$html = render_admin_page( array( $ai_admin, 'render_settings_page' ) );
has_substring( '[ceafsn_ai_assistant]', $html, 'the fallback tab is the shortcode card' );

test( 'the Display tab documents the exact shortcode and its attributes' );
$_GET = array( 'tab' => 'display' );
$html = render_admin_page( array( $ai_admin, 'render_settings_page' ) );
has_substring( '[ceafsn_ai_assistant]', $html, 'the exact shortcode is shown' );
has_substring( '<code>placeholder_en</code>', $html, 'the English placeholder attribute is documented' );
has_substring( '<code>placeholder_pt</code>', $html, 'the Portuguese placeholder attribute is documented' );
has_substring( 'ceafsn-embed__code', $html, 'the shortcode uses the shared embed code style' );
has_substring( 'ceafsn-embed__table', $html, 'the attribute table uses the shared embed table' );
has_substring( 'ceafsn-embed__caption', $html, 'and its caption' );
lacks_substring( 'name="ceafsn_ai_settings_scope"', $html, 'the Display tab posts nothing' );
lacks_substring( '<form', $html, 'because it has no form at all' );

test( 'the Providers tab posts its own scope and masks the stored key' );
$_GET = array( 'tab' => 'providers' );
$html = render_admin_page( array( $ai_admin, 'render_settings_page' ) );
has_substring( 'value="providers"', $html, 'the form declares the providers scope' );
has_substring( 'name="ceafsn_ai_key_openai"', $html, 'the OpenAI key field is present' );
has_substring( 'name="ceafsn_ai_key_claude"', $html, 'the Claude key field is present' );
has_substring( 'type="password"', $html, 'keys are not shown in clear text' );
has_substring( '9876', $html, 'the mask keeps the last four characters so a key can be recognised' );
lacks_substring( 'sk-live-example-key-9876', $html, 'the full key never reaches the DOM' );
has_substring( 'ceafsn-badge--key-set', $html, 'a stored key is badged as set' );
has_substring( 'name="ceafsn_ai_model_openai"', $html, 'the chat model override is offered' );
has_substring( 'placeholder="gpt-4o-mini"', $html, 'and the built-in default is shown as the placeholder' );
has_substring( 'name="ceafsn_ai_embed_model_openai"', $html, 'OpenAI offers an embeddings model override' );
has_substring( 'name="ceafsn_ai_embed_model_gemini"', $html, 'Gemini offers one too' );
lacks_substring( 'name="ceafsn_ai_embed_model_grok"', $html, 'Grok does not, because it has no embeddings API' );
lacks_substring( 'name="ceafsn_ai_embed_model_claude"', $html, 'and neither does Claude' );
has_substring( 'name="ceafsn_ai_model_claude"', $html, 'but Claude still gets a chat model override' );

test( 'the Uninstall tab posts its own scope and the opt-in' );
$_GET          = array( 'tab' => 'uninstall' );
$html          = render_admin_page( array( $ai_admin, 'render_settings_page' ) );
has_substring( 'value="uninstall"', $html, 'the form declares the uninstall scope' );
has_substring( 'name="ceafsn_ai_uninstall_delete_data"', $html, 'the opt-in checkbox is posted' );
has_substring( 'Off by default', $html, 'the hint explains the default' );
has_substring( 'your own pages and posts are never touched', $html, 'and it rules out the scary reading' );
lacks_substring( 'name="ceafsn_ai_provider"', $html, 'provider settings are not carried in this form' );
$_GET = array();

test( 'a saved notice and a stored notice are both rendered' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
$ai_admin = new CEAFSN_AI_Admin();
$_GET     = array( 'tab' => 'display', 'saved' => '1' );
$html     = render_admin_page( array( $ai_admin, 'render_settings_page' ) );
has_substring( 'Settings saved.', $html, 'the redirect flag renders a confirmation' );
$_GET = array();

ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
set_transient(
	'ceafsn_ai_admin_notices',
	array(
		'index_done' => array(
			'message' => 'Indexing complete. Indexed: 42 chunks, skipped: 3, errors: 1.',
			'type'    => 'success',
		),
	),
	60
);
$ai_admin = new CEAFSN_AI_Admin();
$html     = render_admin_page( array( $ai_admin, 'render_overview_page' ) );
has_substring( 'Indexing complete. Indexed: 42 chunks', $html, 'the stored notice is rendered' );
has_substring( 'ceafsn-alert--ok', $html, 'a success notice uses the ok modifier' );
is_same( false, get_transient( 'ceafsn_ai_admin_notices' ), 'notices are consumed, so they appear once' );

test( 'a legacy plain-string notice is upgraded rather than rendered as an array' );
ceafsn_ai_test_reload( true );
update_option( '__caps', array( 'ceafsn_manage' => true ) );
set_transient( 'ceafsn_ai_admin_notices', 'Something happened.', 60 );
$ai_admin = new CEAFSN_AI_Admin();
$html     = render_admin_page( array( $ai_admin, 'render_overview_page' ) );
has_substring( 'Something happened.', $html, 'the old string shape still renders' );
lacks_substring( 'Array', $html, 'and it is not printed as a bare array' );

test( 'a warning notice maps to the warn modifier, an error to danger' );
$ai_html = '<div class="ceafsn-alert ceafsn-alert--' . 'warning' === 'warning' ? 'warn' : 'ok' . '"></div>';
has_substring(
	"'warning' === \$ai_notice['type'] ? 'warn' : ( 'error' === \$ai_notice['type'] ? 'danger' : 'ok' )",
	$ai_overview_partial,
	'the overview maps notice types onto the shared modifiers'
);

// -----------------------------------------------------------------------------
section( 'Uninstall' );
// -----------------------------------------------------------------------------

test( 'uninstall.php returns immediately with no uninstall context' );
$ai_report = ceafsn_ai_test_run_uninstall_case( 'no-context' );
is_same( array(), $ai_report['queries'], 'nothing is touched outside the WordPress uninstall context' );
ok( array_key_exists( 'ceafsn_ai_db_version', $ai_report['options'] ), 'and the site data is left exactly as it was' );

test( 'uninstall.php keeps the index and the keys when the opt-in is off' );
$ai_report = ceafsn_ai_test_run_uninstall_case( 'flag-off' );
is_same( array(), $ai_report['queries'], 'no DROP TABLE runs without the opt-in' );
ok( ! array_key_exists( 'ceafsn_ai_db_version', $ai_report['options'] ), 'the schema version is cleared' );
ok( ! array_key_exists( 'ceafsn_ai_provider', $ai_report['options'] ), 'the provider choice is cleared' );
ok( ! array_key_exists( 'ceafsn_ai_uninstall_delete_data', $ai_report['options'] ), 'the opt-in itself is cleared' );
ok( ! isset( $ai_report['transients']['ceafsn_ai_admin_notices'] ), 'stale admin notices are cleared' );
is_same( 'sk-live-example-key-1234', $ai_report['options']['ceafsn_ai_key_openai'] ?? null, 'the API key survives' );
is_same( 'gpt-4.1-mini', $ai_report['options']['ceafsn_ai_model_openai'] ?? null, 'and so does the model override' );

test( 'uninstall.php drops the index and the credentials only when the opt-in is on' );
$ai_report = ceafsn_ai_test_run_uninstall_case( 'flag-on' );
ok( ! empty( $ai_report['queries'] ), 'a query ran' );
has_substring( 'DROP TABLE IF EXISTS `wp_ceafsn_ai_chunks`', implode( ' ', $ai_report['queries'] ), 'the knowledge base table is dropped' );
foreach (
	array(
		'ceafsn_ai_key_openai',
		'ceafsn_ai_key_gemini',
		'ceafsn_ai_key_grok',
		'ceafsn_ai_key_claude',
		'ceafsn_ai_model_openai',
		'ceafsn_ai_model_gemini',
		'ceafsn_ai_model_grok',
		'ceafsn_ai_model_claude',
		'ceafsn_ai_embed_model_openai',
		'ceafsn_ai_embed_model_gemini',
	) as $ai_option
) {
	ok( ! array_key_exists( $ai_option, $ai_report['options'] ), "{$ai_option} is removed" );
}

// -----------------------------------------------------------------------------
section( 'Translation and docs' );
// -----------------------------------------------------------------------------

test( 'the textdomain is loaded on init, not before' );
has_substring( "add_action( 'init', 'ceafsn_ai_load_textdomain' );", $plugin_source, 'the loader waits for init' );
has_substring( "'ceafsn-ai',", $plugin_source, 'the domain string is ceafsn-ai' );
is_same( 'ceafsn-ai', (string) ( preg_match( "/Text Domain:\\s*(\\S+)/", $plugin_source, $ai_match ) ? $ai_match[1] : '' ), 'the header declares the same domain' );

test( 'every translated string uses the plugin textdomain' );
$ai_domain_hits = 0;
foreach ( glob( $plugin_dir . '/*.php' ) ?: array() as $ai_file ) {
	$ai_sources = array( (string) file_get_contents( $ai_file ) );
	foreach ( array( 'admin', 'includes', 'public' ) as $ai_dir ) {
		foreach ( glob( $plugin_dir . '/' . $ai_dir . '/**/*.php' ) ?: array() as $ai_nested ) {
			$ai_sources[] = (string) file_get_contents( $ai_nested );
		}
	}
	foreach ( $ai_sources as $ai_contents ) {
		if ( preg_match_all( "/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e|_n)\\s*\\([^;]*?'(ceafsn-[a-z-]+)'\\s*\\)/s", $ai_contents, $ai_out ) ) {
			foreach ( $ai_out[1] as $ai_domain ) {
				++$ai_domain_hits;
				is_same( 'ceafsn-ai', $ai_domain, 'a translated string uses ceafsn-ai' );
			}
		}
	}
}
ok( $ai_domain_hits > 100, "the scan actually found translated strings ({$ai_domain_hits})" );

test( 'no partial title-cases a stored value' );
$ai_seen = 0;
foreach ( glob( $plugin_dir . '/admin/partials/*.php' ) ?: array() as $ai_partial ) {
	++$ai_seen;
	ok( ! str_contains( (string) file_get_contents( $ai_partial ), 'ucfirst(' ), basename( $ai_partial ) . ' does not title-case a value' );
}
foreach ( glob( $plugin_dir . '/public/partials/*.php' ) ?: array() as $ai_partial ) {
	++$ai_seen;
	ok( ! str_contains( (string) file_get_contents( $ai_partial ), 'ucfirst(' ), basename( $ai_partial ) . ' does not title-case a value' );
}
ok( $ai_seen > 0, 'the partials were actually scanned' );

test( 'every i18n call carries a translators comment where it interpolates' );
$ai_settings_source = (string) file_get_contents( $plugin_dir . '/admin/partials/settings.php' );
$ai_interpolated    = 0;
if ( preg_match_all( "/printf\\(\\s*\\n?\\s*\\/\\* translators:/", $ai_settings_source, $ai_hits ) ) {
	$ai_interpolated = count( $ai_hits[0] );
}
ok( $ai_interpolated >= 2, "the interpolating messages carry translators comments ({$ai_interpolated})" );

test( 'the plugin ships the files a release needs' );
foreach (
	array(
		'README.md',
		'readme.txt',
		'uninstall.php',
		'languages/ceafsn-ai.pot',
		'assets/css/ceafsn-ai-admin.css',
		'assets/css/ceafsn-ai-public.css',
		'assets/js/ceafsn-ai-public.js',
	) as $ai_required
) {
	ok( file_exists( $plugin_dir . '/' . $ai_required ), "{$ai_required} exists" );
}

test( 'the POT file is a real catalogue for this textdomain' );
$ai_pot = (string) file_get_contents( $plugin_dir . '/languages/ceafsn-ai.pot' );
has_substring( 'X-Domain: ceafsn-ai', $ai_pot, 'the catalogue declares the domain' );
has_substring( 'msgid "', $ai_pot, 'and it contains message entries' );
has_substring( 'CE-AFSN AI Assistant', $ai_pot, 'and pulls real strings from the plugin' );
ok( substr_count( $ai_pot, 'msgid "' ) > 30, 'more than a handful of strings are extracted' );

test( 'the readme files describe the shortcode and the providers' );
$ai_readme = (string) file_get_contents( $plugin_dir . '/readme.txt' );
has_substring( 'CE-AFSN AI Assistant', $ai_readme, 'readme.txt names the plugin' );
has_substring( '[ceafsn_ai_assistant]', $ai_readme, 'and documents the shortcode' );
$ai_md = (string) file_get_contents( $plugin_dir . '/README.md' );
has_substring( '[ceafsn_ai_assistant]', $ai_md, 'README.md documents the shortcode' );
has_substring( 'ceafsn-ai', $ai_md, 'and the textdomain' );

test( 'release ZIPs are built without the test harness' );
$ai_ignore = (string) file_get_contents( $plugin_dir . '/.distignore' );
has_substring( 'tests', $ai_ignore, 'tests/ is excluded from release packages' );

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------

$pass = $GLOBALS['ceafsn_ai_test_pass'];
$fail = $GLOBALS['ceafsn_ai_test_fail'];

echo "\n" . str_repeat( '-', 60 ) . "\n";
printf( "%d assertions, %d passed, %d failed\n", $pass + $fail, $pass, $fail );

exit( $fail > 0 ? 1 : 0 );
