<?php
/**
 * Google Gemini provider adapter.
 *
 * One API key from Google AI Studio covers both endpoints. The model
 * identifiers come from CEAFSN_AI_Providers::chat_model()/embed_model() so an
 * operator can move to a newer Gemini when Google retires one, without a
 * plugin update.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Provider_Gemini
 */
class CEAFSN_AI_Provider_Gemini extends CEAFSN_AI_Provider {

	/** @var string Base URL for Gemini API v1beta. */
	const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/** @var string Fallback completion model. See CEAFSN_AI_Providers::DEFAULT_MODELS. */
	const CHAT_MODEL = 'gemini-3.5-flash';

	/** @var string Fallback embedding model. See CEAFSN_AI_Providers::DEFAULT_MODELS. */
	const EMBED_MODEL = 'gemini-embedding-001';

	/**
	 * Chat model actually used, after any saved override.
	 *
	 * @return string
	 */
	private function chat_model(): string {
		return CEAFSN_AI_Providers::chat_model( $this->key() );
	}

	/**
	 * Embeddings model actually used, after any saved override.
	 *
	 * @return string
	 */
	private function embed_model(): string {
		return CEAFSN_AI_Providers::embed_model( $this->key() );
	}

	/** {@inheritdoc} */
	public function name(): string {
		return 'Google Gemini';
	}

	/** {@inheritdoc} */
	public function key(): string {
		return 'gemini';
	}

	/** {@inheritdoc} */
	public function supports_embeddings(): bool {
		return true;
	}

	/**
	 * Send the key as a header rather than as a query parameter.
	 *
	 * A key in the URL is copied into proxy logs, CDN logs, browser history,
	 * and any Referer a redirected page chooses to send. Google accepts the
	 * same credential as `x-goog-api-key`, so there is no reason for it to be
	 * in the request line.
	 *
	 * @return array<string,string>
	 */
	private function auth_headers(): array {
		return array( 'x-goog-api-key' => $this->api_key );
	}

	/** {@inheritdoc} */
	public function embed( string $text ): ?array {
		$url = self::BASE_URL . $this->embed_model() . ':embedContent';

		$response = $this->http_post(
			$url,
			array(
				'model'   => 'models/' . $this->embed_model(),
				'content' => array(
					'parts' => array(
						array( 'text' => $text ),
					),
				),
			),
			$this->auth_headers()
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[CEAFSN AI] Gemini embed error: ' . $response->get_error_message() );
			return null;
		}

		$vector = $response['embedding']['values'] ?? null;
		return is_array( $vector ) ? array_map( 'floatval', $vector ) : null;
	}

	/** {@inheritdoc} */
	public function complete( string $system_prompt, string $user_message ): ?string {
		$url = self::BASE_URL . $this->chat_model() . ':generateContent';

		// Gemini uses a system_instruction field separate from the conversation.
		$response = $this->http_post(
			$url,
			array(
				'system_instruction' => array(
					'parts' => array( array( 'text' => $system_prompt ) ),
				),
				'contents' => array(
					array(
						'role'  => 'user',
						'parts' => array( array( 'text' => $user_message ) ),
					),
				),
				'generationConfig' => array(
					'maxOutputTokens' => 600,
					'temperature'     => 0.2,
				),
			),
			$this->auth_headers()
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[CEAFSN AI] Gemini complete error: ' . $response->get_error_message() );
			return null;
		}

		return $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
	}
}
