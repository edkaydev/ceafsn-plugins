<?php
/**
 * Anthropic Claude provider adapter.
 *
 * Uses claude-3-5-haiku-20241022 for completions. Anthropic does not offer
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

	/** @var string Completion model. */
	const CHAT_MODEL = 'claude-3-5-haiku-20241022';

	/** {@inheritdoc} */
	public function name(): string {
		return 'Anthropic Claude 3.5 Haiku';
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
				'model'      => self::CHAT_MODEL,
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
