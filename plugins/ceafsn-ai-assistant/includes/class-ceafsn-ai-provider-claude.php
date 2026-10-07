<?php
/**
 * Anthropic Claude provider adapter.
 *
 * Uses Claude Haiku 4.5 for completions. Anthropic does not offer
 * a public embeddings API, so when Claude is the active provider the plugin
 * routes embeddings to whichever other provider has a key configured
 * (OpenAI preferred, then Gemini). The admin settings page surfaces this
 * clearly so there is no silent failure.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Provider_Claude
 */
class CEAFSN_AI_Provider_Claude extends CEAFSN_AI_Provider {

	/** @var string Messages endpoint. */
	const CHAT_ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/** @var string Anthropic API version header. */
	const API_VERSION = '2023-06-01';

	/** @var string Fallback completion model. See CEAFSN_AI_Providers::DEFAULT_MODELS. */
	const CHAT_MODEL = 'claude-haiku-4-5';

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
		return 'Anthropic Claude';
	}

	/** {@inheritdoc} */
	public function key(): string {
		return 'claude';
	}

	/**
	 * Claude has no embeddings API.
	 *
	 * {@inheritdoc}
	 */
	public function supports_embeddings(): bool {
		return false;
	}

	/**
	 * Always returns null — embeddings are delegated to another provider.
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
				'model'      => $this->chat_model(),
				'system'     => $system_prompt,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $user_message ),
				),
				'max_tokens' => 600,
			),
			array(
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::API_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[CEAFSN AI] Claude complete error: ' . $response->get_error_message() );
			return null;
		}

		return $response['content'][0]['text'] ?? null;
	}
}
