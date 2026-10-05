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

	/** @var string Option key for Claude API key. */
	const OPTION_KEY_CLAUDE = 'ceafsn_ai_key_claude';

	/**
	 * All supported provider keys in display order.
	 *
	 * @return array<string,string> key => human label
	 */
	public static function all(): array {
		return array(
			'openai' => __( 'OpenAI (GPT-4o-mini)', 'ceafsn-ai' ),
			'gemini' => __( 'Google Gemini 1.5 Flash', 'ceafsn-ai' ),
			'grok'   => __( 'xAI Grok (grok-beta)', 'ceafsn-ai' ),
			'claude' => __( 'Anthropic Claude 3.5 Haiku', 'ceafsn-ai' ),
		);
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
	 * Keys are stored encrypted at rest using WordPress's built-in option
	 * store. They are never exposed in page source; only the admin settings
	 * page reads them back (as masked values for display).
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
