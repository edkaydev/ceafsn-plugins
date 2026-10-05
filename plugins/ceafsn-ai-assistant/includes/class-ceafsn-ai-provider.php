<?php
/**
 * Abstract base class for AI provider adapters.
 *
 * Every provider (OpenAI, Gemini, Grok, Claude) implements this contract.
 * The query engine and indexer only talk to this interface, so switching
 * providers is a single option change with no code edits.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Abstract CEAFSN_AI_Provider
 */
abstract class CEAFSN_AI_Provider {

	/**
	 * API key for this provider.
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Provider API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Human-readable provider name.
	 *
	 * @return string
	 */
	abstract public function name(): string;

	/**
	 * Machine-readable provider key (matches the option value).
	 *
	 * @return string
	 */
	abstract public function key(): string;

	/**
	 * Whether this provider also supplies its own embeddings API.
	 *
	 * Claude does not; callers will fall back to another provider's embeddings
	 * or skip the check.
	 *
	 * @return bool
	 */
	abstract public function supports_embeddings(): bool;

	/**
	 * Generate an embedding vector for a single piece of text.
	 *
	 * @param string $text Text to embed.
	 * @return float[]|null Float vector, or null on failure.
	 */
	abstract public function embed( string $text ): ?array;

	/**
	 * Send a prompt to the language model and return the answer text.
	 *
	 * @param string $system_prompt Instructions to the model.
	 * @param string $user_message  The user's question.
	 * @return string|null Answer text, or null on failure.
	 */
	abstract public function complete( string $system_prompt, string $user_message ): ?string;

	// -------------------------------------------------------------------------
	// Shared HTTP helper
	// -------------------------------------------------------------------------

	/**
	 * Make a JSON POST request using the WordPress HTTP API.
	 *
	 * Keeping all HTTP calls through wp_remote_post() means WordPress's
	 * built-in proxy settings, SSL verification, and timeout controls apply
	 * consistently. It also makes the calls testable with WP_Http mocks.
	 *
	 * @param string               $url     Endpoint URL.
	 * @param array<string,mixed>  $payload Request body.
	 * @param array<string,string> $headers Extra headers (Authorization etc.).
	 * @param int                  $timeout Seconds before giving up. Default 30.
	 * @return array<string,mixed>|WP_Error Decoded response or WP_Error.
	 */
	protected function http_post( string $url, array $payload, array $headers = array(), int $timeout = 30 ): array|WP_Error {
		$default_headers = array(
			'Content-Type' => 'application/json',
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array_merge( $default_headers, $headers ),
				'body'    => (string) wp_json_encode( $payload ),
				'timeout' => $timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? ( $data['error']['message'] ?? $body ) : $body;
			return new WP_Error(
				'ceafsn_ai_api_error',
				sprintf( '[%d] %s', $code, $message )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
