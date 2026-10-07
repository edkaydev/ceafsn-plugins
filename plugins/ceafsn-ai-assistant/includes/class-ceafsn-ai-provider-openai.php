<?php
/**
 * OpenAI provider adapter.
 *
 * Uses one API key for both the chat and the embeddings endpoint. The model
 * identifiers come from CEAFSN_AI_Providers::chat_model()/embed_model() so an
 * operator can move to a newer model when OpenAI retires one, without a
 * plugin update.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Provider_OpenAI
 */
class CEAFSN_AI_Provider_OpenAI extends CEAFSN_AI_Provider {

	/** @var string Chat completions endpoint. */
	const CHAT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/** @var string Embeddings endpoint. */
	const EMBED_ENDPOINT = 'https://api.openai.com/v1/embeddings';

	/** @var string Fallback completion model. See CEAFSN_AI_Providers::DEFAULT_MODELS. */
	const CHAT_MODEL = 'gpt-4o-mini';

	/** @var string Fallback embedding model. See CEAFSN_AI_Providers::DEFAULT_MODELS. */
	const EMBED_MODEL = 'text-embedding-3-small';

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
		return 'OpenAI';
	}

	/** {@inheritdoc} */
	public function key(): string {
		return 'openai';
	}

	/** {@inheritdoc} */
	public function supports_embeddings(): bool {
		return true;
	}

	/** {@inheritdoc} */
	public function embed( string $text ): ?array {
		$response = $this->http_post(
			self::EMBED_ENDPOINT,
			array(
				'model' => $this->embed_model(),
				'input' => $text,
			),
			array( 'Authorization' => 'Bearer ' . $this->api_key )
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[CEAFSN AI] OpenAI embed error: ' . $response->get_error_message() );
			return null;
		}

		$vector = $response['data'][0]['embedding'] ?? null;
		return is_array( $vector ) ? array_map( 'floatval', $vector ) : null;
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
			error_log( '[CEAFSN AI] OpenAI complete error: ' . $response->get_error_message() );
			return null;
		}

		return $response['choices'][0]['message']['content'] ?? null;
	}
}
