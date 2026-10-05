<?php
/**
 * Loader for the shared CE-AFSN library.
 *
 * The six plugins are independently installable, so none of them may assume this
 * folder exists. Each one does a single guarded require:
 *
 *     if ( file_exists( CEAFSN_MED_PLUGIN_DIR . '../ceafsn-shared/ceafsn-shared-load.php' ) ) {
 *         require_once CEAFSN_MED_PLUGIN_DIR . '../ceafsn-shared/ceafsn-shared-load.php';
 *     }
 *
 * After that, `CEAFSN_Caps` and `CEAFSN_Audit_Log` exist when the folder is
 * present. When it is not, callers fall back to the behaviour they had before
 * these classes were introduced: `manage_options` decides access, and audit
 * writes are skipped. A plugin on its own therefore stays fully functional, it
 * just does not contribute to the shared log.
 *
 * Loading this file has no side effects. It defines classes and nothing else, so
 * requiring it from an activation hook, an admin_init hook, or a test bootstrap
 * is equally safe.
 *
 * @package CEAFSN_Shared
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CEAFSN_Caps', false ) ) {
	require_once __DIR__ . '/includes/class-ceafsn-caps.php';
}

if ( ! class_exists( 'CEAFSN_Audit_Log', false ) ) {
	require_once __DIR__ . '/includes/class-ceafsn-audit-log.php';
}

if ( ! class_exists( 'CEAFSN_Search', false ) ) {
	require_once __DIR__ . '/includes/class-ceafsn-search.php';
}