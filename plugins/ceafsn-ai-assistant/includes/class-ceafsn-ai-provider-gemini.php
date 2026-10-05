<?php
/**
 * Google Gemini provider adapter.
 *
 * Uses Gemini 1.5 Flash for completions and text-embedding-004 for embeddings.
 * One API key from Google AI Studio covers both.
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

	/** @var string Completion model. */
	const CHAT_MODEL = 'gemini-1.5-flash';

	/** @var string Embedding model. */
	const EMBED_MODEL = 'text-embedding-004';

	/** {@inheritdoc} */
	public function name(): string {
		return 'Google Gemini 1.5 Flash';
	}

	/** {@inheritdoc} */
	public function key(): string {
		return 'gemini';
	}

	/** {@inheritdoc} */
	public function supports_embeddings(): bool {
		return true;
	}

	/** {@inheritdoc} */
	public function embed( string $text ): ?array {
		$url = self::BASE_URL . self::EMBED_MODEL . ':embedContent?key=' . rawurlencode( $this->api_key );

		$response = $this->http_post(
			$url,
			array(
				'model'   => 'models/' . self::EMBED_MODEL,
				'content' => array(
					'parts' => array(
						array( 'text' => $text ),
					),
				),
			)
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
		$url = self::BASE_URL . self::CHAT_MODEL . ':generateContent?key=' . rawurlencode( $this->api_key );

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
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[CEAFSN AI] Gemini complete error: ' . $response->get_error_message() );
			return null;
		}

		return $response['candidates'][0]['content']['parts'][0]['text'] ?? null;
	}
}
