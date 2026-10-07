<?php
/**
 * Provider registry — resolves the active provider from saved options.
 *
 * One place to ask "which provider object should I use right now?" so the
 * indexer, query engine, and admin page all stay in sync.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Providers
 */
final class CEAFSN_AI_Providers {

	/** @var string Option key for the active provider. */
	const OPTION_ACTIVE = 'ceafsn_ai_provider';

	/** @var string Option key for OpenAI API key. */
	const OPTION_KEY_OPENAI = 'ceafsn_ai_key_openai';

	/** @var string Option key for Gemini API key. */
	const OPTION_KEY_GEMINI = 'ceafsn_ai_key_gemini';

	/** @var string Option key for Grok API key. */
	const OPTION_KEY_GROK = 'ceafsn_ai_key_grok';

	/** @var string Option key for the Claude API key. */
	const OPTION_KEY_CLAUDE = 'ceafsn_ai_key_claude';

	/** @var string Option key prefix shared by all four API key options. */
	const OPTION_KEY_PREFIX = 'ceafsn_ai_key_';

	/** @var string Option key prefix for a per-provider chat model override. */
	const OPTION_MODEL_PREFIX = 'ceafsn_ai_model_';

	/** @var string Option key prefix for a per-provider embeddings model override. */
	const OPTION_EMBED_MODEL_PREFIX = 'ceafsn_ai_embed_model_';

	/**
	 * Default chat and embeddings model for each provider.
	 *
	 * Model identifiers are retired on their vendors' schedules, not ours —
	 * `grok-beta`, `gemini-1.5-flash`, `claude-3-5-haiku`, and
	 * `text-embedding-004` had all been withdrawn by the time this plugin was
	 * finished. The defaults below were checked against first-party
	 * documentation on 2026-10-06, and they will age again, so every one of
	 * them can be overridden from the settings screen without a code change.
	 *
	 * @var array<string,array{chat:string,embed:string}>
	 */
	const DEFAULT_MODELS = array(
		'openai' => array(
			'chat'  => 'gpt-4o-mini',
			'embed' => 'text-embedding-3-small',
		),
		'gemini' => array(
			'chat'  => 'gemini-3.5-flash',
			'embed' => 'gemini-embedding-001',
		),
		'grok'   => array(
			'chat'  => 'grok-4.5',
			'embed' => '',
		),
		'claude' => array(
			'chat'  => 'claude-haiku-4-5',
			'embed' => '',
		),
	);

	/**
	 * All supported provider keys in display order.
	 *
	 * Labels stay provider-only. The model behind each provider is editable,
	 * so naming it in the label would make the select wrong the moment an
	 * operator overrides it.
	 *
	 * @return array<string,string> key => human label
	 */
	public static function all(): array {
		return array(
			'openai' => __( 'OpenAI', 'ceafsn-ai' ),
			'gemini' => __( 'Google Gemini', 'ceafsn-ai' ),
			'grok'   => __( 'xAI Grok', 'ceafsn-ai' ),
			'claude' => __( 'Anthropic Claude', 'ceafsn-ai' ),
		);
	}

	/**
	 * Resolve the chat model for a provider, honouring any saved override.
	 *
	 * A value that is not a plausible model identifier — spaces, quotes,
	 * control characters — is discarded and the default used instead, so a
	 * mistyped field can never be carried into an API request as-is.
	 *
	 * @param string $provider_key Provider key.
	 * @return string Model identifier.
	 */
	public static function chat_model( string $provider_key ): string {
		return self::resolve_model(
			self::OPTION_MODEL_PREFIX . $provider_key,
			self::DEFAULT_MODELS[ $provider_key ]['chat'] ?? ''
		);
	}

	/**
	 * Resolve the embeddings model for a provider, honouring any override.
	 *
	 * Returns an empty string for providers with no embeddings API (Grok and
	 * Claude); the registry falls back to another provider before this value
	 * is ever read.
	 *
	 * @param string $provider_key Provider key.
	 * @return string Model identifier, or ''.
	 */
	public static function embed_model( string $provider_key ): string {
		return self::resolve_model(
			self::OPTION_EMBED_MODEL_PREFIX . $provider_key,
			self::DEFAULT_MODELS[ $provider_key ]['embed'] ?? ''
		);
	}

	/**
	 * Read a model option and reject values that are not model identifiers.
	 *
	 * @param string $option  Option name.
	 * @param string $default Default model identifier.
	 * @return string
	 */
	private static function resolve_model( string $option, string $default ): string {
		$saved = trim( (string) get_option( $option, '' ) );

		if ( '' === $saved ) {
			return $default;
		}

		if ( 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._\-\/]{0,99}$/', $saved ) ) {
			return $saved;
		}

		return $default;
	}

	/**
	 * Return the active provider key saved in options.
	 *
	 * @return string Provider key, defaults to 'openai'.
	 */
	public static function active_key(): string {
		$key = sanitize_key( (string) get_option( self::OPTION_ACTIVE, 'openai' ) );
		return array_key_exists( $key, self::all() ) ? $key : 'openai';
	}

	/**
	 * Retrieve the stored API key for a given provider.
	 *
	 * Keys live in the options table like any other option, so they are only
	 * as protected as the database and the file system are. Nothing on the
	 * front end ever reads them, and the settings screen shows a masked value
	 * (last four characters) rather than the key itself, so a screen share or
	 * a saved HTML page does not leak the secret.
	 *
	 * @param string $provider_key Provider key.
	 * @return string API key or empty string.
	 */
	public static function get_api_key( string $provider_key ): string {
		$option_map = array(
			'openai' => self::OPTION_KEY_OPENAI,
			'gemini' => self::OPTION_KEY_GEMINI,
			'grok'   => self::OPTION_KEY_GROK,
			'claude' => self::OPTION_KEY_CLAUDE,
		);

		$option = $option_map[ $provider_key ] ?? '';
		return '' !== $option ? (string) get_option( $option, '' ) : '';
	}

	/**
	 * Build a provider object for the given key.
	 *
	 * Returns null when no API key is configured for that provider.
	 *
	 * @param string $key Provider key.
	 * @return CEAFSN_AI_Provider|null
	 */
	public static function make( string $key ): ?CEAFSN_AI_Provider {
		$api_key = self::get_api_key( $key );
		if ( '' === $api_key ) {
			return null;
		}

		switch ( $key ) {
			case 'openai':
				return new CEAFSN_AI_Provider_OpenAI( $api_key );
			case 'gemini':
				return new CEAFSN_AI_Provider_Gemini( $api_key );
			case 'grok':
				return new CEAFSN_AI_Provider_Grok( $api_key );
			case 'claude':
				return new CEAFSN_AI_Provider_Claude( $api_key );
		}

		return null;
	}

	/**
	 * Return the active provider object, or null when not configured.
	 *
	 * @return CEAFSN_AI_Provider|null
	 */
	public static function active(): ?CEAFSN_AI_Provider {
		return self::make( self::active_key() );
	}

	/**
	 * Return the embeddings provider.
	 *
	 * When the active provider supports embeddings it is returned directly.
	 * When it does not (Grok, Claude), we fall back to OpenAI → Gemini in that
	 * order, using whichever has a key stored. Returns null when no embeddings
	 * provider can be resolved.
	 *
	 * @return CEAFSN_AI_Provider|null
	 */
	public static function embeddings(): ?CEAFSN_AI_Provider {
		$active = self::active();

		if ( $active && $active->supports_embeddings() ) {
			return $active;
		}

		// Fallback order for providers that lack embeddings.
		foreach ( array( 'openai', 'gemini' ) as $fallback ) {
			$provider = self::make( $fallback );
			if ( $provider && $provider->supports_embeddings() ) {
				return $provider;
			}
		}

		return null;
	}
}
