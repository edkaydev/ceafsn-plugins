<?php
/**
 * Admin interface for CE-AFSN AI Assistant.
 *
 * Two pages under one menu: an Overview that reports what the assistant can
 * currently do, and a Settings screen that decides what that is. Indexing runs
 * through admin-post so a slow build never occupies a page load.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Admin
 */
class CEAFSN_AI_Admin {

	/** @var string Admin menu parent and overview slug. */
	const MENU_SLUG = 'ceafsn-ai';

	/** @var string Settings page slug. */
	const PAGE_SETTINGS = 'ceafsn-ai-settings';

	/**
	 * Settings tabs. `display` is documentation only and posts nothing.
	 *
	 * @var array<string,string>
	 */
	const SETTINGS_TABS = array(
		'display'   => 'Display',
		'providers' => 'Providers',
		'uninstall' => 'Uninstall',
	);

	/**
	 * Hook suffixes returned when the menu was registered.
	 *
	 * Empty before admin_menu has fired, which is the safe state: nothing
	 * matches an empty list, so assets stay unloaded if the ordering changes.
	 *
	 * @var array<int,string>
	 */
	private array $page_hooks = array();

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu',            array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ceafsn_ai_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ceafsn_ai_run_index',     array( $this, 'handle_run_index' ) );
		add_action( 'admin_post_ceafsn_ai_clear_index',   array( $this, 'handle_clear_index' ) );
	}

	// ---------------------------------------------------------------------------
	// Menu registration
	// ---------------------------------------------------------------------------

	/**
	 * Register the admin menu and its two pages.
	 *
	 * The submenu whose slug equals the parent slug is the deliberate trick
	 * that puts Overview first in the flyout instead of leaving the parent
	 * item pointing at Settings.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		$cap = $this->cap();

		$this->page_hooks[] = add_menu_page(
			__( 'CE-AFSN AI Assistant', 'ceafsn-ai' ),
			__( 'AI Assistant', 'ceafsn-ai' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_overview_page' ),
			'dashicons-superhero-alt',
			30
		);

		$this->page_hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Overview', 'ceafsn-ai' ),
			__( 'Overview', 'ceafsn-ai' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_overview_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ceafsn-ai' ),
			__( 'Settings', 'ceafsn-ai' ),
			$cap,
			self::PAGE_SETTINGS,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue the admin stylesheet on this plugin's pages only.
	 *
	 * The suffixes compared are the ones core returned at registration, so a
	 * slug that happens to collide with another plugin cannot pull this
	 * stylesheet onto the wrong screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ceafsn-ai-admin',
			CEAFSN_AI_PLUGIN_URL . 'assets/css/ceafsn-ai-admin.css',
			array(),
			CEAFSN_AI_VERSION
		);
	}

	// ---------------------------------------------------------------------------
	// Page renderers
	// ---------------------------------------------------------------------------

	/**
	 * Overview page: what the assistant can answer right now.
	 *
	 * @return void
	 */
	public function render_overview_page(): void {
		$this->require_access();

		$ai_provider    = CEAFSN_AI_Providers::active();
		$embed_provider = CEAFSN_AI_Providers::embeddings();
		$chunk_count    = CEAFSN_AI_DB::count_chunks();
		$embedded_count = CEAFSN_AI_DB::count_embedded_chunks();
		$last_indexed   = CEAFSN_AI_DB::last_indexed_at();
		$breakdown      = CEAFSN_AI_DB::provider_breakdown();
		$notices        = $this->get_notices();

		// A chunk row without a vector cannot be found by a similarity search,
		// so it is counted separately rather than folded into the total.
		$unembedded = max( 0, $chunk_count - $embedded_count );

		// Chunks stored against a provider other than the one that will embed
		// the next question. Those rows will never be returned, so the index
		// is effectively stale even though it looks populated.
		$foreign = 0;
		$mine    = 0;
		if ( null !== $embed_provider ) {
			$mine    = (int) ( $breakdown[ $embed_provider->key() ] ?? 0 );
			$foreign = max( 0, $chunk_count - $mine );
		}

		// Whether the assistant is reachable by a visitor at all. Counted from
		// published content rather than assumed from activation, because a
		// plugin can be perfectly configured and still be showing to nobody.
		$shortcode_pages = $this->count_shortcode_pages();

		require CEAFSN_AI_PLUGIN_DIR . 'admin/partials/overview.php';
	}

	/**
	 * Count published pages and posts that carry the assistant shortcode.
	 *
	 * Limited to the most recent 200 of each so a very large archive cannot
	 * turn this page into a full content scan. The figure is used only as
	 * "zero or more", and the detail screen is where an operator goes to fix it.
	 *
	 * @return int
	 */
	private function count_shortcode_pages(): int {
		$count = 0;

		$ids = get_posts(
			array(
				'post_type'      => array( 'page', 'post' ),
				'post_status'    => 'publish',
				'numberposts'    => 200,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		foreach ( $ids as $ceafsn_post_id ) {
			$ceafsn_content = (string) get_post_field( 'post_content', $ceafsn_post_id );
			if ( has_shortcode( $ceafsn_content, 'ceafsn_ai_assistant' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Settings page: display, providers, and uninstall.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		$this->require_access();

		$active_key     = CEAFSN_AI_Providers::active_key();
		$providers      = CEAFSN_AI_Providers::all();
		$embed_provider = CEAFSN_AI_Providers::embeddings();
		$notices        = $this->get_notices();

		// Keys are shown masked — display only the last four characters so an
		// administrator can confirm a key is set without the full secret ever
		// reaching the DOM.
		$keys_masked = array();
		foreach ( array_keys( $providers ) as $pk ) {
			$k               = CEAFSN_AI_Providers::get_api_key( $pk );
			$keys_masked[ $pk ] = '' !== $k
				? str_repeat( '•', max( 0, strlen( $k ) - 4 ) ) . substr( $k, -4 )
				: '';
		}

		require CEAFSN_AI_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	// ---------------------------------------------------------------------------
	// Form handlers
	// ---------------------------------------------------------------------------

	/**
	 * Handle the settings form POST.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$this->require_access();
		check_admin_referer( 'ceafsn_ai_settings' );

		$tab    = $this->posted_tab( wp_unslash( $_POST ) );
		$before = self::settings_snapshot();

		$this->persist_settings( wp_unslash( $_POST ), $tab );

		$this->audit( 'update', 'ai_settings', 0, $before, self::settings_snapshot() );

		$this->redirect( array( 'page' => self::PAGE_SETTINGS, 'tab' => $tab, 'saved' => '1' ) );
	}

	/**
	 * Record a write in the shared audit log.
	 *
	 * A no-op when the shared library is absent, so this plugin stays usable on
	 * its own.
	 *
	 * @param string                $action      create, update, or delete.
	 * @param string                $entity_type Record type.
	 * @param int                   $entity_id   Record id.
	 * @param array<string,mixed>|object|null $before Previous row.
	 * @param array<string,mixed>|object|null $after  New row.
	 * @return void
	 */
	private function audit( string $action, string $entity_type, int $entity_id, array|object|null $before, array|object|null $after ): void {
		if ( ! class_exists( 'CEAFSN_Audit_Log' ) ) {
			return;
		}

		CEAFSN_Audit_Log::record( $action, $entity_type, $entity_id, $before, $after );
	}

	/**
	 * The settings this plugin holds, shaped for the audit log.
	 *
	 * Keys are reduced to their last four characters, which is all the
	 * settings screen itself ever shows. That makes a rotation visible —
	 * 1234 giving way to 5678 is a change the log can report — without
	 * writing a working credential into a table anybody can read.
	 *
	 * @return array<string,mixed>
	 */
	private static function settings_snapshot(): array {
		$snapshot = array(
			'provider'  => (string) get_option( CEAFSN_AI_Providers::OPTION_ACTIVE, '' ),
			'uninstall' => (bool) get_option( 'ceafsn_ai_uninstall_delete_data', false ),
		);

		foreach ( array_keys( CEAFSN_AI_Providers::all() ) as $provider_key ) {
			$saved_key = (string) get_option( CEAFSN_AI_Providers::OPTION_KEY_PREFIX . $provider_key, '' );

			$snapshot[ $provider_key . '_key_last4' ]  = '' === $saved_key ? '' : substr( $saved_key, -4 );
			$snapshot[ $provider_key . '_chat_model' ]  = (string) get_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . $provider_key, '' );
			$snapshot[ $provider_key . '_embed_model' ] = (string) get_option( CEAFSN_AI_Providers::OPTION_EMBED_MODEL_PREFIX . $provider_key, '' );
		}

		return $snapshot;
	}

	/**
	 * Persist the fields belonging to one settings tab only.
	 *
	 * Each tab is its own form, so a tab only submits its own fields. The scope
	 * says which tab is being saved: writing a checkbox tab unconditionally
	 * would clear it whenever a different tab was on screen, because an unticked
	 * checkbox is simply absent from the POST.
	 *
	 * @param array<string,mixed> $post Unslashed $_POST.
	 * @param string              $tab  Active tab key.
	 * @return void
	 */
	private function persist_settings( array $post, string $tab ): void {
		if ( 'providers' === $tab ) {
			$this->persist_providers( $post );
		}

		if ( 'uninstall' === $tab ) {
			// Explicit opt-in: an unticked box is stored as false rather than
			// left at whatever it was before.
			update_option( 'ceafsn_ai_uninstall_delete_data', isset( $post['ceafsn_ai_uninstall_delete_data'] ) );
		}
	}

	/**
	 * Save the active provider, the API keys, and any model overrides.
	 *
	 * @param array<string,mixed> $post Unslashed $_POST.
	 * @return void
	 */
	private function persist_providers( array $post ): void {
		$all_keys = array_keys( CEAFSN_AI_Providers::all() );

		$provider = sanitize_key( (string) ( $post['ceafsn_ai_provider'] ?? '' ) );
		if ( in_array( $provider, $all_keys, true ) ) {
			update_option( CEAFSN_AI_Providers::OPTION_ACTIVE, $provider );
		}

		// A value consisting entirely of bullet characters is the masked
		// placeholder rendered in the field — ignore it so a redisplay of the
		// form can never overwrite a real key with dots.
		foreach ( $all_keys as $pk ) {
			$submitted = trim( sanitize_text_field( (string) ( $post[ 'ceafsn_ai_key_' . $pk ] ?? '' ) ) );

			if ( '' !== $submitted && ! preg_match( '/^[•]+[0-9a-zA-Z]{0,4}$/', $submitted ) ) {
				update_option( CEAFSN_AI_Providers::OPTION_KEY_PREFIX . $pk, $submitted );
			} elseif ( '' === $submitted ) {
				delete_option( CEAFSN_AI_Providers::OPTION_KEY_PREFIX . $pk );
			}
		}

		// Model overrides. Empty means "use the plugin default", which is what
		// an operator wants after a vendor retires a model they had pinned.
		foreach ( $all_keys as $pk ) {
			$this->save_model_option( $post, CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . $pk, 'ceafsn_ai_model_' . $pk );
			$this->save_model_option( $post, CEAFSN_AI_Providers::OPTION_EMBED_MODEL_PREFIX . $pk, 'ceafsn_ai_embed_model_' . $pk );
		}
	}

	/**
	 * Read one model field and store or clear its option.
	 *
	 * @param array<string,mixed> $post    Unslashed $_POST.
	 * @param string              $option  Option name to write.
	 * @param string              $field   Submitted field name.
	 * @return void
	 */
	private function save_model_option( array $post, string $option, string $field ): void {
		$value = trim( sanitize_text_field( (string) ( $post[ $field ] ?? '' ) ) );

		if ( '' === $value ) {
			delete_option( $option );
			return;
		}

		// Mirror the check in CEAFSN_AI_Providers::resolve_model(): anything
		// that is not shaped like a model identifier is dropped here rather
		// than stored and silently ignored later.
		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._\-\/]{0,99}$/', $value ) ) {
			return;
		}

		update_option( $option, $value );
	}

	/**
	 * Handle the run-index form POST.
	 *
	 * Indexing calls out to an embeddings API once per content item, so the
	 * request is given a long but finite budget: time-limit relief plus an
	 * abort guard, then the indexer's own deadline. When the budget expires the
	 * partial result is reported as partial — never as a completed run.
	 *
	 * @return void
	 */
	public function handle_run_index(): void {
		$this->require_access();
		check_admin_referer( 'ceafsn_ai_index' );

		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$full   = ! empty( $_POST['ceafsn_ai_full_rebuild'] );
		$result = CEAFSN_AI_Indexer::run( $full, 90 );
		$notice = self::index_notice( $result );

		$this->audit(
			'update',
			'ai_index',
			0,
			null,
			array(
				'full_rebuild' => (bool) $full,
				'indexed'      => (int) $result['indexed'],
				'skipped'      => (int) $result['skipped'],
				'errors'       => (int) $result['errors'],
				'partial'      => (bool) $result['partial'],
			)
		);

		$this->add_notice( 'index_done', $notice['message'], $notice['type'] );

		$this->redirect( array( 'page' => self::MENU_SLUG, 'indexed' => '1' ) );
	}

	/**
	 * Turn an indexer summary into the notice shown afterwards.
	 *
	 * A run that ran out of time budget is reported as a warning that invites
	 * another press of the button, never as a completed run — the whole point
	 * of the deadline is that the operator can tell the difference.
	 *
	 * @param array{indexed:int,skipped:int,errors:int,sources:string[],partial:bool} $result Indexer summary.
	 * @return array{message:string,type:string}
	 */
	private static function index_notice( array $result ): array {
		if ( ! empty( $result['partial'] ) ) {
			return array(
				'message' => sprintf(
					/* translators: 1: indexed chunks, 2: skipped, 3: errors. */
					__( 'Indexing paused after reaching its time budget — indexed %1$d chunks, skipped %2$d, errors %3$d. Run it again to continue from where it stopped.', 'ceafsn-ai' ),
					(int) $result['indexed'],
					(int) $result['skipped'],
					(int) $result['errors']
				),
				'type'    => 'warning',
			);
		}

		return array(
			'message' => sprintf(
				/* translators: 1: indexed chunks, 2: skipped, 3: errors. */
				__( 'Indexing complete. Indexed: %1$d chunks, skipped %2$d, errors %3$d.', 'ceafsn-ai' ),
				(int) $result['indexed'],
				(int) $result['skipped'],
				(int) $result['errors']
			),
			'type'    => 'success',
		);
	}

	/**
	 * Handle the clear-index form POST.
	 *
	 * @return void
	 */
	public function handle_clear_index(): void {
		$this->require_access();
		check_admin_referer( 'ceafsn_ai_clear' );

		$chunks = CEAFSN_AI_DB::count_chunks();

		CEAFSN_AI_DB::delete_all_chunks();

		$this->audit( 'delete', 'ai_index', 0, array( 'chunks' => (int) $chunks ), null );

		$this->add_notice( 'index_cleared', __( 'Index cleared. Run indexing again to rebuild.', 'ceafsn-ai' ) );

		$this->redirect( array( 'page' => self::MENU_SLUG ) );
	}

	// ---------------------------------------------------------------------------
	// Notices
	// ---------------------------------------------------------------------------

	/**
	 * Store a notice for display on the next page load.
	 *
	 * A transient rather than a query argument, because the messages contain
	 * counts and interpolated sentences that would have to survive being
	 * encoded, read back, and sanitised without changing meaning.
	 *
	 * @param string $key     Unique key.
	 * @param string $message Message text.
	 * @param string $type    notice type: success, warning, or error.
	 * @return void
	 */
	private function add_notice( string $key, string $message, string $type = 'success' ): void {
		$notices         = (array) get_transient( 'ceafsn_ai_admin_notices' );
		$notices[ $key ] = array(
			'message' => $message,
			'type'    => in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success',
		);
		set_transient( 'ceafsn_ai_admin_notices', $notices, 60 );
	}

	/**
	 * Retrieve and clear pending notices.
	 *
	 * Older builds stored plain strings; those are upgraded to the array shape
	 * here so an update never renders an array to the screen.
	 *
	 * @return array<string,array{message:string,type:string}>
	 */
	private function get_notices(): array {
		$stored = (array) get_transient( 'ceafsn_ai_admin_notices' );
		delete_transient( 'ceafsn_ai_admin_notices' );

		$notices = array();
		foreach ( $stored as $key => $value ) {
			if ( is_array( $value ) ) {
				$notices[ $key ] = array(
					'message' => (string) ( $value['message'] ?? '' ),
					'type'    => (string) ( $value['type'] ?? 'success' ),
				);
			} else {
				$notices[ $key ] = array(
					'message' => (string) $value,
					'type'    => 'success',
				);
			}
		}

		return $notices;
	}

	// ---------------------------------------------------------------------------
	// Utility
	// ---------------------------------------------------------------------------

	/**
	 * Capability needed to see and change this plugin.
	 *
	 * The name is a literal here rather than a reference to CEAFSN_Caps: the
	 * shared library is optional, and reading a constant off a class that may
	 * not exist would fatal the very fallback that keeps this plugin usable on
	 * its own.
	 *
	 * @return string
	 */
	private function cap(): string {
		return class_exists( 'CEAFSN_Caps' ) ? 'ceafsn_manage' : 'manage_options';
	}

	/**
	 * Abort with a 403 unless the user may manage this plugin.
	 *
	 * @return void
	 */
	private function require_access(): void {
		if ( ! current_user_can( $this->cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ceafsn-ai' ), 403 );
		}
	}

	/**
	 * Which settings tab a submission belongs to.
	 *
	 * Returns an empty string when the scope is missing or unrecognised, and
	 * persist_settings() then writes nothing. Defaulting to a real tab would
	 * mean a bare POST to admin-post.php — no scope field at all — went
	 * straight through persist_providers() and deleted every stored API key,
	 * because an absent key field reads as an empty one.
	 *
	 * @param array<string,mixed> $post Unslashed $_POST.
	 * @return string Tab key, or '' when the submission claims no tab.
	 */
	private function posted_tab( array $post ): string {
		$tab = isset( $post['ceafsn_ai_settings_scope'] ) && is_string( $post['ceafsn_ai_settings_scope'] )
			? sanitize_key( $post['ceafsn_ai_settings_scope'] )
			: '';

		return array_key_exists( $tab, self::SETTINGS_TABS ) ? $tab : '';
	}

	/**
	 * Redirect to an admin page built from query arguments.
	 *
	 * @param array<string,string> $args Query arguments.
	 * @return void
	 */
	private function redirect( array $args ): void {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
