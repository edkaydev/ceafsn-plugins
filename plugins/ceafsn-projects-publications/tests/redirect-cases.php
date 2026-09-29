<?php
/**
 * Legacy redirect test cases, run as separate processes.
 *
 * maybe_redirect_legacy_route() ends the request with exit once it has sent the
 * 301, so the parent suite in run-tests.php runs each case here. The report is
 * written from a shutdown function, which runs whether the handler exits or
 * returns, so a case that should not redirect is reported just as clearly as
 * one that is.
 *
 * Usage: php tests/redirect-cases.php <case>
 *
 * @package CEAFSN_PP
 */

declare( strict_types=1 );

define( 'CEAFSN_PP_VERSION', '1.0.0' );
define( 'CEAFSN_PP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'CEAFSN_PP_PLUGIN_URL', 'https://example.test/wp-content/plugins/ceafsn-projects-publications/' );

require_once __DIR__ . '/bootstrap.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'includes/class-ceafsn-pp-db.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'includes/class-ceafsn-pp-validator.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'includes/class-ceafsn-pp-activator.php';

$case = $argv[1] ?? '';

switch ( $case ) {
	case 'legacy-404':
		$_SERVER['REQUEST_URI']            = '/privacy-policy-2/';
		CEAFSN_PP_Test_State::$is_404     = true;
		break;

	case 'legacy-query':
		$_SERVER['REQUEST_URI']            = '/privacy-policy-2/?utm_source=newsletter';
		CEAFSN_PP_Test_State::$is_404     = true;
		break;

	case 'legacy-page':
		// A real page now exists at the legacy slug, so it wins.
		$_SERVER['REQUEST_URI']            = '/privacy-policy-2/';
		CEAFSN_PP_Test_State::$is_404     = false;
		break;

	case 'other-path':
		$_SERVER['REQUEST_URI']            = '/publications/';
		CEAFSN_PP_Test_State::$is_404     = true;
		break;

	case 'disabled':
		$_SERVER['REQUEST_URI']            = '/privacy-policy-2/';
		CEAFSN_PP_Test_State::$is_404     = true;
		update_option( CEAFSN_PP_Activator::REDIRECT_OPTION, false );
		break;

	default:
		echo "unknown case\n";
		exit( 2 );
}

// Assumed until proven otherwise: the handler is expected to stop the request.
$exited = true;

register_shutdown_function(
	static function () use ( &$exited, $case ): void {
		echo (string) json_encode(
			array(
				'case'      => $case,
				'redirects' => CEAFSN_PP_Test_State::$redirects,
				'exited'    => $exited,
			)
		);
	}
);

CEAFSN_PP_Activator::maybe_redirect_legacy_route();

// Reaching this line means the handler returned without ending the request.
$exited = false;
