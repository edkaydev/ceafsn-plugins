<?php
/**
 * Admin interface for CE-AFSN AI Assistant.
 *
 * Settings: provider selector, API keys, index controls, index status.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Admin
 */
class CEAFSN_AI_Admin {

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_post_ceafsn_ai_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_ai_run_index',     array( $this, 'handle_run_index' ) );
		add_action( 'admin_post_ceafsn_ai_clear_index',   array( $this, 'handle_clear_index' ) );
	}

	/**
	 * Register the admin menu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$cap = class_exists( 'CEAFSN_Caps' ) ? 'ceafsn_manage' : 'manage_options';

		add_menu_page(
			__( 'CE-AFSN AI Assistant', 'ceafsn-ai' ),
			__( 'AI Assistant', 'ceafsn-ai' ),
			$cap,
			CEAFSN_AI_Activator::PAGE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-superhero-alt',
			30
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$cap = class_exists( 'CEAFSN_Caps' ) ? 'ceafsn_manage' : 'manage_options';
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ceafsn-ai' ) );
		}

		$active_key      = CEAFSN_AI_Providers::active_key();
		$providers       = CEAFSN_AI_Providers::all();
		$chunk_count     = CEAFSN_AI_DB::count_chunks();
		$embedded_count  = CEAFSN_AI_DB::count_embedded_chunks();
		$last_indexed    = CEAFSN_AI_DB::last_indexed_at();
		$embed_provider  = CEAFSN_AI_Providers::embeddings();
		$notices         = $this->get_notices();

		// Keys are shown masked — display only last 4 chars so admins can
		// confirm a key is set without exposing the full secret in the DOM.
		$keys_masked = array();
		foreach ( array_keys( $providers ) as $pk ) {
			$k = CEAFSN_AI_Providers::get_api_key( $pk );
			$keys_masked[ $pk ] = '' !== $k ? str_repeat( '•', max( 0, strlen( $k ) - 4 ) ) . substr( $k, -4 ) : '';
		}

		require CEAFSN_AI_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	/**
	 * Handle the save-settings form POST.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$cap = class_exists( 'CEAFSN_Caps' ) ? 'ceafsn_manage' : 'manage_options';
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ceafsn-ai' ) );
		}

		check_admin_referer( 'ceafsn_ai_settings' );

		// Active provider.
		$all_keys = array_keys( CEAFSN_AI_Providers::all() );
		$provider = sanitize_key( wp_unslash( $_POST['ceafsn_ai_provider'] ?? '' ) );
		if ( in_array( $provider, $all_keys, true ) ) {
			update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, $provider );
		}

		// API keys — only update when the submitted value is not the masked placeholder.
		$key_options = array(
			'openai' => CEAFSN_AI_Providers::OPTION_KEY_OPENAI,
			'gemini' => CEAFSN_AI_Providers::OPTION_KEY_GEMINI,
			'grok'   => CEAFSN_AI_Providers::OPTION_KEY_GROK,
			'claude' => CEAFSN_AI_Providers::OPTION_KEY_CLAUDE,
		);

		foreach ( $key_options as $pk => $option ) {
			$submitted = trim( sanitize_text_field( wp_unslash( $_POST[ 'ceafsn_ai_key_' . $pk ] ?? '' ) ) );
			// A value consisting entirely of bullet characters is the masked
			// placeholder — ignore it so we never overwrite a real key with dots.
			if ( '' !== $submitted && ! preg_match( '/^[•]+[0-9a-zA-Z]{0,4}$/', $submitted ) ) {
				update_option( $option, $submitted );
			} elseif ( '' === $submitted ) {
				delete_option( $option );
			}
		}

		$this->add_notice( 'settings_saved', __( 'Settings saved.', 'ceafsn-ai' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . CEAFSN_AI_Activator::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Handle the run-index form POST.
	 *
	 * @return void
	 */
	public function handle_run_index(): void {
		$cap = class_exists( 'CEAFSN_Caps' ) ? 'ceafsn_manage' : 'manage_options';
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ceafsn-ai' ) );
		}

		check_admin_referer( 'ceafsn_ai_index' );

		$full = ! empty( $_POST['ceafsn_ai_full_rebuild'] );
		$result = CEAFSN_AI_Indexer::run( $full );

		$msg = sprintf(
			/* translators: 1: indexed chunks, 2: skipped, 3: errors. */
			__( 'Indexing complete. Indexed: %1$d chunks, skipped: %2$d, errors: %3$d.', 'ceafsn-ai' ),
			$result['indexed'],
			$result['skipped'],
			$result['errors']
		);

		$this->add_notice( 'index_done', $msg );

		wp_safe_redirect( admin_url( 'admin.php?page=' . CEAFSN_AI_Activator::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Handle the clear-index form POST.
	 *
	 * @return void
	 */
	public function handle_clear_index(): void {
		$cap = class_exists( 'CEAFSN_Caps' ) ? 'ceafsn_manage' : 'manage_options';
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ceafsn-ai' ) );
		}

		check_admin_referer( 'ceafsn_ai_clear' );

		CEAFSN_AI_DB::delete_all_chunks();

		$this->add_notice( 'index_cleared', __( 'Index cleared. Run indexing again to rebuild.', 'ceafsn-ai' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . CEAFSN_AI_Activator::PAGE_SLUG ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Transient-based notices
	// -------------------------------------------------------------------------

	/**
	 * Store a notice for display on the next page load.
	 *
	 * @param string $key     Unique key.
	 * @param string $message Message text.
	 * @return void
	 */
	private function add_notice( string $key, string $message ): void {
		$notices          = (array) get_transient( 'ceafsn_ai_admin_notices' );
		$notices[ $key ]  = $message;
		set_transient( 'ceafsn_ai_admin_notices', $notices, 60 );
	}

	/**
	 * Retrieve and clear pending notices.
	 *
	 * @return array<string,string>
	 */
	private function get_notices(): array {
		$notices = (array) get_transient( 'ceafsn_ai_admin_notices' );
		delete_transient( 'ceafsn_ai_admin_notices' );
		return $notices;
	}
}
