<?php
/**
 * Activation and deactivation for CE-AFSN AI Assistant.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Activator
 */
class CEAFSN_AI_Activator {

	/** @var string Admin page slug. */
	const PAGE_SLUG = 'ceafsn-ai';

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		CEAFSN_AI_DB::create_tables();

		if ( class_exists( 'CEAFSN_Caps' ) ) {
			CEAFSN_Caps::install();
		}

		if ( class_exists( 'CEAFSN_Audit_Log' ) ) {
			CEAFSN_Audit_Log::create_table();
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Plugin deactivation — no data deleted.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
