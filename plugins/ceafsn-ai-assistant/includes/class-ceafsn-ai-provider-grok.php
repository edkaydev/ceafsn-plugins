<?php
/**
 * xAI Grok provider adapter.
 *
 * Grok's API is OpenAI-compatible, so the endpoint and response shape follow
 * the same pattern. xAI has no public embeddings endpoint, so when Grok is the
 * active provider the registry resolves embeddings from OpenAI first and
 * Gemini second — see CEAFSN_AI_Providers::embeddings(). Nothing else in the
 * plugin changes.
 *
 * The Chat Completions endpoint is marked deprecated by xAI in favour of the
 * Responses API but is still served and still documented, so it is used here
 * because it is the one shape shared with the OpenAI adapter. The model
 * identifier itself is overridable from the settings screen.
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

	/** @var string Fallback completion model. See CEAFSN_AI_Providers::DEFAULT_MODELS. */
	const CHAT_MODEL = 'grok-4.5';

	/**
	 * Chat model actually used, after any saved override.
	 *
	 * @return string
	 */
	private function chat_model(): string {
		return CEAFSN_AI_Providers::chat_model( $this->key() );
	}

	/** {@inheritdoc} */
	public function name(): string {
		return 'xAI Grok';
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
				'model'       => $this->chat_model(),
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
