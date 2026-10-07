<?php
/**
 * Front-end shortcode and REST endpoint for CE-AFSN AI Assistant.
 *
 * Registers [ceafsn_ai_assistant] and the /wp-json/ceafsn-ai/v1/ask endpoint
 * that the JS calls asynchronously.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Public
 */
class CEAFSN_AI_Public {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'ceafsn_ai_assistant', array( $this, 'render_shortcode' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Render the [ceafsn_ai_assistant] shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ): string {
		$this->enqueue_public_assets();

		$atts = shortcode_atts(
			array(
				'placeholder_en' => '',
				'placeholder_pt' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'ceafsn_ai_assistant'
		);

		$placeholder_en = '' !== $atts['placeholder_en']
			? $atts['placeholder_en']
			: __( 'Ask a question about CE-AFSN research, fellowships, grants…', 'ceafsn-ai' );

		$placeholder_pt = '' !== $atts['placeholder_pt']
			? $atts['placeholder_pt']
			: __( 'Faça uma pergunta sobre pesquisa, bolsas, financiamentos da CE-AFSN…', 'ceafsn-ai' );

		$rest_url = rest_url( 'ceafsn-ai/v1/ask' );
		$nonce    = wp_create_nonce( 'wp_rest' );

		ob_start();
		require CEAFSN_AI_PLUGIN_DIR . 'public/partials/assistant.php';
		return (string) ob_get_clean();
	}

	/**
	 * Register the REST API route.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'ceafsn-ai/v1',
			'/ask',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_ask' ),
				'permission_callback' => '__return_true', // Public — all visitors.
				'args'                => array(
					'question' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value ) && mb_strlen( trim( $value ) ) >= 3;
						},
					),
				),
			)
		);
	}

	/**
	 * Handle a POST to /wp-json/ceafsn-ai/v1/ask.
	 *
	 * Rate-limited to 20 requests per IP per minute using a transient.
	 *
	 * The response body always has the same four keys — answer, sources,
	 * error, code — so a client can render a reply without branching on HTTP
	 * status. The status itself still follows HTTP conventions so that
	 * monitors, caches, and REST clients see something truthful:
	 *
	 *   200 ok, 400 empty_question, 404 no_match, 429 rate-limited,
	 *   502 embed_failed / model_failed (upstream provider failed),
	 *   503 no_chat_provider / no_embeddings_provider / no_index /
	 *       index_provider_mismatch (site not configured yet), 500 anything
	 *       unexpected.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function rest_ask( WP_REST_Request $request ): WP_REST_Response {
		// Basic rate limit: 20 questions per IP per minute.
		$ip_hash = 'ceafsn_ai_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$hits    = (int) get_transient( $ip_hash );

		if ( $hits >= 20 ) {
			return new WP_REST_Response(
				array(
					'answer'  => '',
					'sources' => array(),
					'error'   => __( 'Too many requests. Please wait a moment before asking again.', 'ceafsn-ai' ),
					'code'    => 'rate_limited',
				),
				429
			);
		}

		set_transient( $ip_hash, $hits + 1, 60 );

		$question = (string) $request->get_param( 'question' );
		$result   = CEAFSN_AI_Query::ask( $question );

		return new WP_REST_Response( $result, self::status_for( $result['code'] ) );
	}

	/**
	 * Map a CEAFSN_AI_Query result code to an HTTP status.
	 *
	 * Codes are produced by CEAFSN_AI_Query::ask(); anything that one day
	 * becomes an unknown string falls through to 500 rather than pretending
	 * the request succeeded.
	 *
	 * @param string $code Result code from CEAFSN_AI_Query::ask().
	 * @return int HTTP status code.
	 */
	private static function status_for( string $code ): int {
		$map = array(
			'ok'                    => 200,
			'empty_question'        => 400,
			'no_match'              => 404,
			'rate_limited'          => 429,
			'embed_failed'          => 502,
			'model_failed'          => 502,
			'no_chat_provider'      => 503,
			'no_embeddings_provider' => 503,
			'no_index'              => 503,
			'index_provider_mismatch' => 503,
		);

		return $map[ $code ] ?? 500;
	}

	/**
	 * Enqueue public stylesheet and script.
	 *
	 * @return void
	 */
	private function enqueue_public_assets(): void {
		if ( ! wp_style_is( 'ceafsn-ai-public', 'enqueued' ) ) {
			wp_enqueue_style(
				'ceafsn-ai-public',
				CEAFSN_AI_PLUGIN_URL . 'assets/css/ceafsn-ai-public.css',
				array(),
				CEAFSN_AI_VERSION
			);
		}

		if ( ! wp_script_is( 'ceafsn-ai-public', 'enqueued' ) ) {
			wp_enqueue_script(
				'ceafsn-ai-public',
				CEAFSN_AI_PLUGIN_URL . 'assets/js/ceafsn-ai-public.js',
				array(),
				CEAFSN_AI_VERSION,
				true
			);
		}
	}
}
