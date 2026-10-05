<?php
/**
 * xAI Grok provider adapter.
 *
 * Grok's API is OpenAI-compatible, so the endpoints follow the same shape.
 * Uses grok-beta for completions. For embeddings Grok does not yet have a
 * public embeddings endpoint, so when Grok is selected as the active provider
 * the plugin automatically falls back to OpenAI embeddings if an OpenAI key
 * is also configured, or uses a simple TF-IDF fallback otherwise.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Provider_Grok
 */
class CEAFSN_AI_Provider_Grok extends CEAFSN_AI_Provider {

	/** @var string Chat completions endpoint. */
	const CHAT_ENDPOINT = 'https://api.x.ai/v1/chat/completions';

	/** @var string Completion model. */
	const CHAT_MODEL = 'grok-beta';

	/** {@inheritdoc} */
	public function name(): string {
		return 'xAI Grok (grok-beta)';
	}

	/** {@inheritdoc} */
	public function key(): string {
		return 'grok';
	}

	/**
	 * Grok does not currently offer a public embeddings API.
	 *
	 * {@inheritdoc}
	 */
	public function supports_embeddings(): bool {
		return false;
	}

	/**
	 * Grok has no embeddings endpoint; always returns null.
	 *
	 * The query engine checks supports_embeddings() first and routes to the
	 * fallback embeddings provider when this returns false.
	 *
	 * {@inheritdoc}
	 */
	public function embed( string $text ): ?array {
		return null;
	}

	/** {@inheritdoc} */
	public function complete( string $system_prompt, string $user_message ): ?string {
		$response = $this->http_post(
			self::CHAT_ENDPOINT,
			array(
				'model'       => self::CHAT_MODEL,
				'messages'    => array(
					array( 'role' => 'system', 'content' => $system_prompt ),
					array( 'role' => 'user',   'content' => $user_message ),
				),
				'max_tokens'  => 600,
				'temperature' => 0.2,
			),
			array( 'Authorization' => 'Bearer ' . $this->api_key )
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[CEAFSN AI] Grok complete error: ' . $response->get_error_message() );
			return null;
		}

		return $response['choices'][0]['message']['content'] ?? null;
	}
}
