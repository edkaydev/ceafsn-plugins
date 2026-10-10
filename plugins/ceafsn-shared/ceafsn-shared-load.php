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
 * Loading this file defines classes and attaches the ceafsn_staff role hooks
 * (login redirect and admin-menu cleanup). The hooks are guarded by a constant
 * so they are only attached once, even when multiple plugins load this file in
 * the same request. Requiring it from an activation hook or a test bootstrap is
 * safe because WordPress's add_action / add_filter are no-ops until the
 * corresponding hook fires.
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

/**
 * Attach hooks that enforce the ceafsn_staff role experience (login redirect
 * and admin menu cleanup). These hooks must run on every admin request, not
 * just during activation, so they live outside the class files.
 *
 * The guard prevents re-attaching hooks if multiple plugins load this file
 * during the same request.
 */
if ( ! defined( 'CEAFSN_STAFF_HOOKS_LOADED' ) ) {
	define( 'CEAFSN_STAFF_HOOKS_LOADED', true );
	require_once __DIR__ . '/includes/class-ceafsn-staff-hooks.php';
}