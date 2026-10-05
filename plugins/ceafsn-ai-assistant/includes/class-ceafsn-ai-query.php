<?php
/**
 * Query engine for CE-AFSN AI Assistant.
 *
 * Embeds the user's question, retrieves the most semantically similar
 * content chunks via cosine similarity, builds a grounded prompt, sends
 * it to the active language model, and returns a structured answer with
 * source attribution.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Query
 */
class CEAFSN_AI_Query {

	/**
	 * Number of top-matching chunks to include in the prompt context.
	 *
	 * 5 chunks × ~800 chars ≈ 1 000 tokens of context. Enough for a
	 * precise answer without pushing into expensive long-context territory.
	 *
	 * @var int
	 */
	const TOP_K = 5;

	/**
	 * Minimum cosine similarity score to include a chunk.
	 *
	 * Chunks below this threshold are discarded even if they are the
	 * "best" match, preventing hallucination from irrelevant results.
	 * Range: 0.0 (unrelated) → 1.0 (identical). 0.30 is deliberately
	 * permissive for a small corpus.
	 *
	 * @var float
	 */
	const MIN_SIMILARITY = 0.30;

	/**
	 * Ask a question and return a grounded answer with source links.
	 *
	 * @param string $question Raw question from the visitor.
	 * @return array{
	 *   answer: string,
	 *   sources: array<int,array{label:string,url:string}>,
	 *   error: string
	 * }
	 */
	public static function ask( string $question ): array {
		$empty = array( 'answer' => '', 'sources' => array(), 'error' => '' );

		$question = sanitize_textarea_field( wp_unslash( $question ) );
		$question = trim( $question );

		if ( '' === $question ) {
			return array_merge( $empty, array( 'error' => __( 'Please enter a question.', 'ceafsn-ai' ) ) );
		}

		// --- Step 1: resolve providers ---
		$chat_provider  = CEAFSN_AI_Providers::active();
		$embed_provider = CEAFSN_AI_Providers::embeddings();

		if ( null === $chat_provider ) {
			return array_merge( $empty, array(
				'error' => __( 'The AI assistant is not configured yet. Please add an API key in the admin settings.', 'ceafsn-ai' ),
			) );
		}

		if ( null === $embed_provider ) {
			return array_merge( $empty, array(
				'error' => __( 'No embeddings provider is available. Please configure an OpenAI or Gemini API key.', 'ceafsn-ai' ),
			) );
		}

		// --- Step 2: embed the question ---
		$question_vector = $embed_provider->embed( $question );

		if ( null === $question_vector ) {
			return array_merge( $empty, array(
				'error' => __( 'Could not process your question. Please try again in a moment.', 'ceafsn-ai' ),
			) );
		}

		// --- Step 3: cosine similarity search ---
		$provider_key = $embed_provider->key();
		$candidates   = CEAFSN_AI_DB::get_chunks_with_embeddings( $provider_key );

		if ( empty( $candidates ) ) {
			return array_merge( $empty, array(
				'error' => __( 'The knowledge base is empty. Please run the index from the admin settings.', 'ceafsn-ai' ),
			) );
		}

		$scored = array();

		foreach ( $candidates as $chunk ) {
			$stored = $chunk->embedding ?? '';
			if ( '' === $stored ) {
				continue;
			}

			$vector = json_decode( $stored, true );
			if ( ! is_array( $vector ) ) {
				continue;
			}

			$score = self::cosine_similarity( $question_vector, array_map( 'floatval', $vector ) );

			if ( $score >= self::MIN_SIMILARITY ) {
				$scored[] = array(
					'score' => $score,
					'chunk' => $chunk,
				);
			}
		}

		if ( empty( $scored ) ) {
			return array_merge( $empty, array(
				'error' => __( 'No relevant content found. Try rephrasing your question.', 'ceafsn-ai' ),
			) );
		}

		// Sort by score descending, take top K.
		usort( $scored, static fn( $a, $b ) => $b['score'] <=> $a['score'] );
		$top = array_slice( $scored, 0, self::TOP_K );

		// --- Step 4: build prompt ---
		$context_parts = array();
		$sources       = array();
		$seen_urls     = array();

		foreach ( $top as $match ) {
			$chunk          = $match['chunk'];
			$context_parts[] = (string) $chunk->chunk_text;

			$url   = (string) ( $chunk->source_url ?? '' );
			$label = (string) ( $chunk->source_label ?? '' );

			if ( '' !== $url && ! in_array( $url, $seen_urls, true ) ) {
				$seen_urls[] = $url;
				$sources[]   = array( 'label' => $label, 'url' => $url );
			}
		}

		$context       = implode( "\n\n---\n\n", $context_parts );
		$system_prompt = self::build_system_prompt( $context );

		// --- Step 5: call the language model ---
		$answer = $chat_provider->complete( $system_prompt, $question );

		if ( null === $answer ) {
			return array_merge( $empty, array(
				'error' => __( 'The AI model did not respond. Please try again.', 'ceafsn-ai' ),
			) );
		}

		return array(
			'answer'  => wp_kses( $answer, array( 'p' => array(), 'br' => array(), 'strong' => array(), 'em' => array(), 'ul' => array(), 'ol' => array(), 'li' => array() ) ),
			'sources' => $sources,
			'error'   => '',
		);
	}

	// -------------------------------------------------------------------------
	// Prompt construction
	// -------------------------------------------------------------------------

	/**
	 * Build the system prompt that frames every answer.
	 *
	 * The prompt instructs the model to:
	 * - Stay strictly within the provided context
	 * - Answer in the same language as the question (EN/PT)
	 * - Admit when the answer is not in the context rather than guess
	 *
	 * @param string $context Retrieved chunk text joined by separators.
	 * @return string System prompt.
	 */
	private static function build_system_prompt( string $context ): string {
		return <<<PROMPT
You are the official AI assistant for CE-AFSN (Centre of Excellence in Agri-Food Systems and Nutrition) at Eduardo Mondlane University in Mozambique.

Your role is to answer questions about CE-AFSN's research programs, fellowships, grants, publications, open datasets, nutrition policy models, and monitoring & evaluation indicators.

Rules you must follow:
1. Answer ONLY using the context provided below. Do not use outside knowledge.
2. Detect the language of the user's question and reply in the SAME language (English or Portuguese). Never mix languages in a single answer.
3. If the answer is not in the context, say clearly: "I don't have that information in the CE-AFSN knowledge base. Please contact the CE-AFSN team directly." — in the same language as the question.
4. Be concise and factual. Avoid speculation, filler phrases, or invented details.
5. When listing items (fellowships, grants, publications), use a short bulleted list.

Context from CE-AFSN knowledge base:
---
{$context}
---
PROMPT;
	}

	// -------------------------------------------------------------------------
	// Maths
	// -------------------------------------------------------------------------

	/**
	 * Cosine similarity between two float vectors.
	 *
	 * Returns a value in [-1, 1]. In practice embedding vectors for related
	 * text score > 0.6; unrelated text typically scores < 0.2.
	 *
	 * Both vectors must have the same length. Mismatched lengths return 0.0
	 * (treated as unrelated) rather than crashing.
	 *
	 * @param float[] $a First vector.
	 * @param float[] $b Second vector.
	 * @return float Similarity score.
	 */
	public static function cosine_similarity( array $a, array $b ): float {
		$len_a = count( $a );
		$len_b = count( $b );

		if ( 0 === $len_a || $len_a !== $len_b ) {
			return 0.0;
		}

		$dot    = 0.0;
		$norm_a = 0.0;
		$norm_b = 0.0;

		for ( $i = 0; $i < $len_a; $i++ ) {
			$dot    += $a[ $i ] * $b[ $i ];
			$norm_a += $a[ $i ] * $a[ $i ];
			$norm_b += $b[ $i ] * $b[ $i ];
		}

		$denom = sqrt( $norm_a ) * sqrt( $norm_b );

		return $denom > 0.0 ? (float) ( $dot / $denom ) : 0.0;
	}
}
