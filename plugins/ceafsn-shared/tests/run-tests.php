<?php
/**
 * Test suite for the CE-AFSN shared library.
 *
 *     php plugins/ceafsn-shared/tests/run-tests.php
 *
 * @package CEAFSN_Shared
 */

declare( strict_types=1 );

// WordPress is not installed, so point ABSPATH at a temporary tree holding just
// enough of wp-admin for create_table() to find dbDelta. Rewritten every run so a
// previously failed run cannot poison this one.
$ceafsn_shared_fake_admin = sys_get_temp_dir() . '/ceafsn-shared-fake-wp/wp-admin/includes';

if ( ! is_dir( $ceafsn_shared_fake_admin ) ) {
	mkdir( $ceafsn_shared_fake_admin, 0777, true );
}

file_put_contents(
	$ceafsn_shared_fake_admin . '/upgrade.php',
	"<?php\n"
	. "/**\n"
	. " * Minimal stand-in for wp-admin/includes/upgrade.php. Records the CREATE\n"
	. " * TABLE statements dbDelta() is given so tests can assert on the schema.\n"
	. " */\n"
	. "if ( ! function_exists( 'dbDelta' ) ) {\n"
	. "\tfunction dbDelta( \$queries ) {\n"
	. "\t\tforeach ( (array) \$queries as \$query ) {\n"
	. "\t\t\t\$GLOBALS['ceafsn_shared_dbdelta'][] = (string) \$query;\n"
	. "\t\t}\n"
	. "\t\treturn array();\n"
	. "\t}\n"
	. "}\n"
);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( $ceafsn_shared_fake_admin, 2 ) . '/' );
}

require_once __DIR__ . '/bootstrap.php';

$GLOBALS['ceafsn_shared_dbdelta'] = array();

/** @var int Assertions run. */
$GLOBALS['assertions'] = 0;

/** @var int Assertions failed. */
$GLOBALS['failed'] = 0;

/** @var string Current section title. */
$GLOBALS['section'] = '';

/**
 * Start a new section.
 *
 * @param string $title Section title.
 * @return void
 */
function section( string $title ): void {
	$GLOBALS['section'] = $title;
	echo "\n\033[1m" . $title . "\033[0m\n\n";
}

/**
 * Start a new test.
 *
 * @param string $name Test name.
 * @return void
 */
function test( string $name ): void {
	echo "  - {$name}\n";
}

/**
 * Assert a condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @return void
 */
function ok( bool $condition, string $message ): void {
	++$GLOBALS['assertions'];
	if ( ! $condition ) {
		++$GLOBALS['failed'];
		echo "    \033[31mFAIL\033[0m {$message}\n";
	}
}

/**
 * Assert equality.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Message.
 * @return void
 */
function is_same( $expected, $actual, string $message ): void {
	++$GLOBALS['assertions'];
	if ( $expected !== $actual ) {
		++$GLOBALS['failed'];
		echo "    \033[31mFAIL\033[0m {$message}\n";
		echo '         expected: ' . str_replace( "\n", '', var_export( $expected, true ) ) . "\n";
		echo '         actual:   ' . str_replace( "\n", '', var_export( $actual, true ) ) . "\n";
	}
}

/**
 * Assert a substring is present.
 *
 * @param string $needle   Substring.
 * @param string $haystack Subject.
 * @param string $message  Message.
 * @return void
 */
function has_substring( string $needle, string $haystack, string $message ): void {
	++$GLOBALS['assertions'];
	if ( ! str_contains( $haystack, $needle ) ) {
		++$GLOBALS['failed'];
		echo "    \033[31mFAIL\033[0m {$message}\n";
		echo "         missing: {$needle}\n";
	}
}

/**
 * Assert a substring is absent.
 *
 * @param string $needle   Substring.
 * @param string $haystack Subject.
 * @param string $message  Message.
 * @return void
 */
function lacks_substring( string $needle, string $haystack, string $message ): void {
	++$GLOBALS['assertions'];
	if ( str_contains( $haystack, $needle ) ) {
		++$GLOBALS['failed'];
		echo "    \033[31mFAIL\033[0m {$message}\n";
		echo "         unexpectedly present: {$needle}\n";
	}
}

// -----------------------------------------------------------------------------
section( 'Capabilities' );

test( 'the three capabilities are the documented ones' );
is_same(
	array( 'ceafsn_edit', 'ceafsn_approve', 'ceafsn_manage' ),
	CEAFSN_Caps::all(),
	'the registry exposes edit, approve, and manage in increasing privilege'
);
is_same( 'ceafsn_research_editor', CEAFSN_Caps::EDITOR_ROLE, 'the editor role has a stable slug' );

test( 'install grants the administrator every capability' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Caps::install();
$admin = get_role( 'administrator' );
ok( null !== $admin, 'the administrator role still exists' );
foreach ( CEAFSN_Caps::all() as $cap ) {
	ok( $admin->has_cap( $cap ), "the administrator holds {$cap}" );
}

test( 'install creates the research editor role with edit and approve, but not manage' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Caps::install();
$editor = get_role( CEAFSN_Caps::EDITOR_ROLE );
ok( null !== $editor, 'the research editor role was created' );
ok( $editor->has_cap( 'ceafsn_edit' ), 'an editor may work with records' );
ok( $editor->has_cap( 'ceafsn_approve' ), 'an editor may publish' );
ok( ! $editor->has_cap( 'ceafsn_manage' ), 'an editor may NOT change site settings' );
ok( $editor->has_cap( 'read' ), 'and can still sign in' );

test( 'install is idempotent, so activating several plugins at once is safe' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Caps::install();
CEAFSN_Caps::install();
CEAFSN_Caps::install();
is_same( true, get_role( CEAFSN_Caps::EDITOR_ROLE )?->has_cap( 'ceafsn_approve' ), 'repeat installs do not drop or duplicate the capabilities' );

test( 'uninstall removes every capability and the custom role' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Caps::install();
CEAFSN_Caps::uninstall();
$admin = get_role( 'administrator' );
foreach ( CEAFSN_Caps::all() as $cap ) {
	ok( ! $admin->has_cap( $cap ), "the administrator no longer holds {$cap}" );
}
ok( $admin->has_cap( 'manage_options' ), 'and the site-administration capability itself is left alone' );
is_same( null, get_role( CEAFSN_Caps::EDITOR_ROLE ), 'the research editor role is gone' );

test( 'uninstall tolerates a role that was already removed by hand' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$roles = array( 'editor' => array( 'read' => true ) );
CEAFSN_Caps::uninstall();
ok( true, 'a missing administrator role is skipped rather than fatal' );

test( 'manage_options is accepted as a fallback so nobody is locked out' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$user_caps = array( 'manage_options' => true );
ok( CEAFSN_Caps::can_edit(), 'an administrator who predates the capabilities can still edit' );
ok( CEAFSN_Caps::can_approve(), 'and still approve' );
ok( CEAFSN_Caps::can_manage(), 'and still manage settings' );

test( 'a real capability grants access without manage_options' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$user_caps = array( 'ceafsn_edit' => true );
ok( CEAFSN_Caps::can_edit(), 'the edit capability is honoured on its own' );
ok( ! CEAFSN_Caps::can_manage(), 'and it does not imply the manage capability' );

test( 'an unrelated capability grants nothing' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$user_caps = array( 'edit_posts' => true );
ok( ! CEAFSN_Caps::can_edit(), 'the WordPress edit_posts capability is not a substitute' );
ok( ! CEAFSN_Caps::can_manage(), 'nor is it enough for settings' );

test( 'an unknown capability name is decided by the fallback alone' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$user_caps = array( 'manage_options' => true );
ok( CEAFSN_Caps::can( 'ceafsn_not_a_thing' ), 'an unrecognised name still resolves through the fallback' );

test( 'the menu capability is the edit one, not the restrictive manage one' );
is_same( CEAFSN_Caps::EDIT, CEAFSN_Caps::menu_capability(), 'admin screens stay reachable for an editor' );

// -----------------------------------------------------------------------------
section( 'Audit log: schema and upgrade path' );

test( 'create_table builds the agreed columns' );
CEAFSN_Shared_Test_State::reset();
$GLOBALS['ceafsn_shared_dbdelta'] = array();
CEAFSN_Audit_Log::create_table();
$sql = implode( "\n", (array) $GLOBALS['ceafsn_shared_dbdelta'] );
foreach ( array( 'id', 'user_id', 'action', 'entity_type', 'entity_id', 'payload_before', 'payload_after', 'created_at' ) as $column ) {
	has_substring( $column, $sql, "the table has a {$column} column" );
}
has_substring( 'wp_ceafsn_audit_log', $sql, 'the table name uses the site prefix' );
has_substring( 'PRIMARY KEY  (id)', $sql, 'the primary key is declared so dbDelta can reconcile it' );
is_same( CEAFSN_Audit_Log::SCHEMA_VERSION, get_option( CEAFSN_Audit_Log::VERSION_OPTION ), 'activation records the schema version' );

test( 'maybe_upgrade is a no-op at the current version' );
CEAFSN_Shared_Test_State::reset();
update_option( CEAFSN_Audit_Log::VERSION_OPTION, CEAFSN_Audit_Log::SCHEMA_VERSION );
$GLOBALS['ceafsn_shared_dbdelta'] = array();
ok( ! CEAFSN_Audit_Log::maybe_upgrade(), 'an up-to-date table is left alone' );
is_same( array(), (array) $GLOBALS['ceafsn_shared_dbdelta'], 'and no schema work runs on every admin page load' );

test( 'maybe_upgrade creates the table when no version is recorded' );
CEAFSN_Shared_Test_State::reset();
$GLOBALS['ceafsn_shared_dbdelta'] = array();
ok( ! get_option( CEAFSN_Audit_Log::VERSION_OPTION, false ), 'no version is recorded yet' );
ok( CEAFSN_Audit_Log::maybe_upgrade(), 'a missing table takes the upgrade path' );
ok( ! empty( (array) $GLOBALS['ceafsn_shared_dbdelta'] ), 'dbDelta runs' );
is_same( CEAFSN_Audit_Log::SCHEMA_VERSION, get_option( CEAFSN_Audit_Log::VERSION_OPTION ), 'and the version is recorded' );

test( 'maybe_upgrade repairs an older table' );
CEAFSN_Shared_Test_State::reset();
update_option( CEAFSN_Audit_Log::VERSION_OPTION, '0.5.0' );
$GLOBALS['ceafsn_shared_dbdelta'] = array();
ok( CEAFSN_Audit_Log::maybe_upgrade(), 'a version bump is applied' );
ok( ! empty( (array) $GLOBALS['ceafsn_shared_dbdelta'] ), 'dbDelta runs so new columns are added to existing sites' );
is_same( CEAFSN_Audit_Log::SCHEMA_VERSION, get_option( CEAFSN_Audit_Log::VERSION_OPTION ), 'and the option ends current' );

test( 'maybe_upgrade never downgrades a newer table' );
CEAFSN_Shared_Test_State::reset();
update_option( CEAFSN_Audit_Log::VERSION_OPTION, '99.0.0' );
$GLOBALS['ceafsn_shared_dbdelta'] = array();
ok( ! CEAFSN_Audit_Log::maybe_upgrade(), 'a newer build owns the table' );
is_same( array(), (array) $GLOBALS['ceafsn_shared_dbdelta'], 'so nothing is written' );
is_same( '99.0.0', get_option( CEAFSN_Audit_Log::VERSION_OPTION ), 'and the stored version is untouched' );

// -----------------------------------------------------------------------------
section( 'Audit log: diffing' );

test( 'an unchanged row produces no diff' );
$diff = CEAFSN_Audit_Log::diff(
	array( 'title' => 'A', 'status' => 'draft' ),
	array( 'title' => 'A', 'status' => 'draft' )
);
ok( ! $diff['has_changes'], 'nothing changed, so nothing is logged' );
is_same( array(), $diff['before'], 'and no before payload' );

test( 'only the changed columns are kept' );
$diff = CEAFSN_Audit_Log::diff(
	array( 'title' => 'A', 'status' => 'draft', 'owner' => 'team' ),
	array( 'title' => 'B', 'status' => 'draft', 'owner' => 'team' )
);
ok( $diff['has_changes'], 'a changed title is a change' );
is_same( array( 'title' => 'A' ), $diff['before'], 'only the old title is stored' );
is_same( array( 'title' => 'B' ), $diff['after'], 'only the new title is stored' );

test( 'a create keeps the whole new row and has no before side' );
$diff = CEAFSN_Audit_Log::diff( null, array( 'title' => 'New', 'status' => 'draft' ) );
ok( $diff['has_changes'], 'a create is worth logging' );
ok( $diff['has_after'], 'the new row is present' );
ok( ! $diff['has_before'], 'and there was nothing before it' );

test( 'a delete keeps the whole old row and has no after side' );
$diff = CEAFSN_Audit_Log::diff( array( 'title' => 'Gone' ), null );
ok( $diff['has_changes'], 'a delete is worth logging' );
ok( $diff['has_before'], 'the old row is present' );
ok( ! $diff['has_after'], 'and there is nothing after it' );
is_same( array( 'title' => 'Gone' ), $diff['before'], 'the deleted row is preserved verbatim' );

test( 'a cleared column counts as a change' );
$diff = CEAFSN_Audit_Log::diff(
	array( 'title' => 'A', 'notes' => 'text' ),
	array( 'title' => 'A' )
);
ok( $diff['has_changes'], 'removing a value is an edit worth recording' );
is_same( 'text', $diff['before']['notes'] ?? null, 'the old value is kept' );
ok( array_key_exists( 'notes', $diff['after'] ), 'and the after side records it as cleared' );
ok( array_key_exists( 'notes', $diff['after'] ), 'the after side still names the column' );
is_same( null, $diff['after']['notes'], 'with a null rather than omitting it' );

test( 'a numeric string that did not actually change is not logged' );
$diff = CEAFSN_Audit_Log::diff(
	array( 'sort_order' => '5' ),
	array( 'sort_order' => 5 )
);
ok( ! $diff['has_changes'], 'MySQL would store both as 5, so there is no edit' );

test( 'a real numeric change is logged' );
$diff = CEAFSN_Audit_Log::diff( array( 'sort_order' => '5' ), array( 'sort_order' => '6' ) );
ok( $diff['has_changes'], '5 to 6 is a genuine edit' );

test( 'objects are accepted, because that is what $wpdb hands back' );
$diff = CEAFSN_Audit_Log::diff(
	(object) array( 'title' => 'A', 'status' => 'draft' ),
	(object) array( 'title' => 'A', 'status' => 'published' )
);
ok( $diff['has_changes'], 'stdClass rows are diffed like arrays' );
is_same( 'published', $diff['after']['status'] ?? null, 'and the changed value is captured' );

test( 'non-scalar values are described, never dumped' );
$diff = CEAFSN_Audit_Log::diff( null, array( 'handler' => new stdClass() ) );
is_same( '[object]', $diff['after']['handler'] ?? null, 'an object is recorded as its type' );

test( 'a closure cannot be serialized into the log' );
$diff = CEAFSN_Audit_Log::diff( null, array( 'callback' => static fn() => 1 ) );
is_same( '[object]', $diff['after']['callback'] ?? null, 'a Closure is recorded as its type' );

test( 'caller-supplied ignore columns are dropped' );
$diff = CEAFSN_Audit_Log::diff(
	array( 'title' => 'A', 'internal_note' => 'x' ),
	array( 'title' => 'B', 'internal_note' => 'y' ),
	array( 'internal_note' )
);
ok( ! array_key_exists( 'internal_note', $diff['after'] ), 'the ignored column is absent from the after side' );
is_same( array( 'title' => 'A' ), $diff['before'], 'and only the remaining change is kept' );

// -----------------------------------------------------------------------------
section( 'Audit log: redaction' );

test( 'sensitive column names are recognised' );
foreach ( array( 'password', 'user_pass', 'api_key', 'apikey', 'client_secret', 'access_token', 'email', 'user_email', 'session_token', 'nonce' ) as $column ) {
	ok( CEAFSN_Audit_Log::is_redacted_column( $column ), "{$column} is treated as sensitive" );
}
foreach ( array( 'title', 'status', 'year', 'doi', 'summary' ) as $column ) {
	ok( ! CEAFSN_Audit_Log::is_redacted_column( $column ), "{$column} is not treated as sensitive" );
}

test( 'a change to only a redacted column does not reach the log' );
// Both sides collapse to the same placeholder, so the address is neither
// disclosed nor mistaken for an edit worth recording.
$diff = CEAFSN_Audit_Log::diff(
	array( 'title' => 'A', 'contact_email' => 'before@example.org' ),
	array( 'title' => 'A', 'contact_email' => 'after@example.org' )
);
ok( ! $diff['has_changes'], 'redacting an address alone does not create an audit row' );

test( 'a redacted column is still redacted when something else changes' );
$diff = CEAFSN_Audit_Log::diff(
	array( 'title' => 'A', 'contact_email' => 'before@example.org' ),
	array( 'title' => 'B', 'contact_email' => 'after@example.org' )
);
ok( $diff['has_changes'], 'the title change is recorded' );
lacks_substring( 'example.org', wp_json_encode( $diff ), 'but the address never reaches the log' );
lacks_substring( 'contact_email', implode( ',', array_keys( $diff['after'] ) ), 'and the untouched redacted column is not carried into the payload at all' );

// -----------------------------------------------------------------------------
section( 'Audit log: recording' );

test( 'record writes the acting user and the change' );
CEAFSN_Shared_Test_State::reset();
$GLOBALS['wpdb']->inserted_rows = array();
CEAFSN_Shared_Test_State::$user_id = 42;
CEAFSN_Audit_Log::record( 'update', 'policy', 7, array( 'title' => 'A' ), array( 'title' => 'B' ) );
is_same( 1, count( $GLOBALS['wpdb']->inserted_rows ), 'one row was written' );
$row = $GLOBALS['wpdb']->inserted_rows[0]['data'];
is_same( 'wp_ceafsn_audit_log', $GLOBALS['wpdb']->inserted_rows[0]['table'], 'into the shared table' );
is_same( 42, $row['user_id'], 'attributed to the acting user' );
is_same( 'update', $row['action'], 'with the action name' );
is_same( 'policy', $row['entity_type'], 'and the entity type' );
is_same( 7, $row['entity_id'], 'and the entity id' );
is_same( '{"title":"A"}', $row['payload_before'], 'the old value is stored as JSON' );
is_same( '{"title":"B"}', $row['payload_after'], 'and the new one' );

test( 'an action name is sanitized and bounded before storage' );
CEAFSN_Shared_Test_State::reset();
$GLOBALS['wpdb']->inserted_rows = array();
CEAFSN_Audit_Log::record( 'Update <script>!', 'policy', 1, null, array( 'a' => 1 ) );
$action = $GLOBALS['wpdb']->inserted_rows[0]['data']['action'];
lacks_substring( '<', $action, 'markup cannot be stored verbatim' );
lacks_substring( '>', $action, 'markup cannot be stored verbatim' );
ok( strlen( $action ) <= 40, 'a long action name is truncated to the column width' );

test( 'a no-op update writes nothing at all' );
CEAFSN_Shared_Test_State::reset();
$GLOBALS['wpdb']->inserted_rows = array();
$id = CEAFSN_Audit_Log::record( 'update', 'policy', 7, array( 'title' => 'A' ), array( 'title' => 'A' ) );
is_same( 0, count( $GLOBALS['wpdb']->inserted_rows ), 'an unchanged record produces no row' );
is_same( 0, $id, 'and reports no id' );

test( 'a failed write reports zero rather than throwing' );
CEAFSN_Shared_Test_State::reset();
$GLOBALS['wpdb']->inserted_rows = array();
add_filter_stub_insert_failure();
is_same( 0, CEAFSN_Audit_Log::record( 'create', 'policy', 1, null, array( 'a' => 1 ) ), 'the caller sees a clean failure' );
remove_filter_stub_insert_failure();

/**
 * Make the next insert() call fail.
 *
 * @return void
 */
function add_filter_stub_insert_failure(): void {
	$GLOBALS['ceafsn_shared_fail_insert'] = true;
}

/**
 * Undo the insert failure.
 *
 * @return void
 */
function remove_filter_stub_insert_failure(): void {
	$GLOBALS['ceafsn_shared_fail_insert'] = false;
}

// -----------------------------------------------------------------------------
section( 'Audit log: reading' );

test( 'entries filters by entity and orders newest first' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$queries = array();
CEAFSN_Audit_Log::entries( array( 'entity_type' => 'policy', 'entity_id' => 7, 'per_page' => 10 ) );
$sql = (string) ( CEAFSN_Shared_Test_State::$queries[0] ?? '' );
has_substring( 'wp_ceafsn_audit_log', $sql, 'the shared table is queried' );
has_substring( "entity_type = 'policy'", $sql, 'the entity type filter is applied' );
has_substring( 'entity_id = 7', $sql, 'the entity id filter is applied' );
has_substring( 'ORDER BY id DESC', $sql, 'newest entries come first' );
has_substring( 'LIMIT 10', $sql, 'the page size is applied' );

test( 'entries clamps a nonsense page size instead of passing it through' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$queries = array();
CEAFSN_Audit_Log::entries( array( 'per_page' => 0 ) );
has_substring( 'LIMIT 1', (string) CEAFSN_Shared_Test_State::$queries[0], 'a zero page size becomes one' );

test( 'entries with no filters does not build a WHERE clause' );
CEAFSN_Shared_Test_State::reset();
CEAFSN_Shared_Test_State::$queries = array();
CEAFSN_Audit_Log::entries();
$sql = (string) CEAFSN_Shared_Test_State::$queries[0];
has_substring( 'WHERE 1=1', $sql, 'the query stays valid with no filters' );
lacks_substring( "entity_type = '", $sql, 'and no stray filter is added' );

// -----------------------------------------------------------------------------
section( 'Loader' );

test( 'the loader defines the classes and has no side effects' );
$loader = (string) file_get_contents( dirname( __DIR__ ) . '/ceafsn-shared-load.php' );
has_substring( 'class_exists( \'CEAFSN_Caps\', false )', $loader, 'the caps class is guarded against double loading' );
has_substring( 'class_exists( \'CEAFSN_Audit_Log\', false )', $loader, 'the audit class is guarded against double loading' );
lacks_substring( 'add_action(', $loader, 'loading it registers no hooks' );
lacks_substring( 'add_option(', $loader, 'loading it writes no options' );
lacks_substring( 'dbDelta(', $loader, 'loading it creates no tables' );

// Remove the fake wp-admin tree.
if ( file_exists( $ceafsn_shared_fake_admin . '/upgrade.php' ) ) {
	unlink( $ceafsn_shared_fake_admin . '/upgrade.php' );
	rmdir( $ceafsn_shared_fake_admin );
	rmdir( dirname( $ceafsn_shared_fake_admin ) );
	rmdir( dirname( $ceafsn_shared_fake_admin, 2 ) );
}

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo "Assertions: {$GLOBALS['assertions']} passed, {$GLOBALS['failed']} failed\n";
echo str_repeat( '-', 60 ) . "\n";

exit( $GLOBALS['failed'] > 0 ? 1 : 0 );