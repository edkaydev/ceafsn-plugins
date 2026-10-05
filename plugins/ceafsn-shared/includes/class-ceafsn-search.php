<?php
/**
 * Search helpers shared by the CE-AFSN plugin suite.
 *
 * The plugins searched prose columns with `LIKE '%term%'`. A leading wildcard
 * cannot use an index, so every keystroke asked the database to read the whole
 * table. Replacing it with an indexed prefix match alone would have been faster
 * but silently wrong: `Health` has to keep matching "Public Health Survey", and
 * `LIKE 'health%'` stops matching it.
 *
 * So prose columns go to FULLTEXT boolean mode, which is index-backed and still
 * matches a word anywhere in the value. Each term also gets a trailing `*` so a
 * partially typed word matches the same rows it used to, which is the behaviour
 * an incremental search box needs.
 *
 * Identifier-shaped columns keep a prefix `LIKE`. A DOI or a licence code is
 * normally pasted from the start, `10.1234/abc` rather than a word in the
 * middle, and prefix matching is what a person means by it. FULLTEXT is the
 * wrong tool there because it tokenises `10.1234/abc` apart.
 *
 * @package CEAFSN_Shared
 */

defined( 'ABSPATH' ) || exit;

/**
 * Search clause construction.
 */
final class CEAFSN_Search {

	/**
	 * Characters that would otherwise let a visitor drive the boolean syntax.
	 *
	 * Boolean mode gives `+`, `-`, `~`, `*`, `"`, `(`, `)` and `@` meaning, so a
	 * search term is reduced to alphanumerics and whitespace before it reaches
	 * the query. Anything else is dropped rather than escaped: no legitimate
	 * search term is made of boolean operators.
	 *
	 * @var string[]
	 */
	private const UNSAFE = array( '+', '-', '~', '*', '"', '(', ')', '<', '>', '@', '\\', '/', '=', '!' );

	/**
	 * Shortest word InnoDB and MyISAM full-text indexes keep.
	 *
	 * Both engines discard tokens below this length, so searching "UK" or "AI"
	 * would match nothing at all. Any search containing a shorter word also gets
	 * a prefix-`LIKE` branch on the prose columns so those rows are still
	 * reachable. The value is the lower of the two engine defaults.
	 *
	 * @var int
	 */
	private const MIN_INDEXED_TOKEN = 3;

	/**
	 * Turn raw visitor input into a safe boolean-mode query.
	 *
	 * Every surviving word is required and matched as a prefix, which is the
	 * closest an indexed query gets to "contains this text": typing `heal`
	 * still finds "Health", and "health" finds "Public Health Survey".
	 *
	 * @param string $term Raw search input.
	 * @return string Boolean-mode expression, empty when nothing usable remains.
	 */
	public static function boolean_query( string $term ): string {
		$clean = self::scrub( $term );
		if ( '' === $clean ) {
			return '';
		}

		$terms = array();
		foreach ( self::words( $clean ) as $word ) {
			// + requires the term; the trailing * is the prefix match.
			$terms[] = '+' . $word . '*';
		}

		return implode( ' ', $terms );
	}

	/**
	 * Reduce a search term to characters that carry no query meaning.
	 *
	 * @param string $term Raw search input.
	 * @return string Safe text.
	 */
	public static function scrub( string $term ): string {
		$clean = str_replace( self::UNSAFE, ' ', $term );

		// Non-ASCII letters are legitimate in research titles, so keep anything
		// that is not whitespace, punctuation, or a control character.
		$clean = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $clean ) ?? '';

		return trim( preg_replace( '/\s+/u', ' ', $clean ) ?? '' );
	}

	/**
	 * Build a prefix pattern for an identifier-style column.
	 *
	 * @param string $term Raw search input.
	 * @return string LIKE pattern ending in a single wildcard.
	 */
	public static function prefix_pattern( string $term ): string {
		return self::scrub( $term ) . '%';
	}

	/**
	 * Split a scrubbed term into its words.
	 *
	 * @param string $clean Output of {@see self::scrub()}.
	 * @return string[] Non-empty words.
	 */
	private static function words( string $clean ): array {
		$words = array();
		foreach ( preg_split( '/\s+/', $clean ) ?: array() as $word ) {
			if ( '' !== $word ) {
				$words[] = $word;
			}
		}

		return $words;
	}

	/**
	 * Whether the term contains a word too short for a full-text index to keep.
	 *
	 * @param string $term Raw search input.
	 * @return bool True when a prefix-`LIKE` branch is needed alongside FULLTEXT.
	 */
	public static function has_short_word( string $term ): bool {
		foreach ( self::words( self::scrub( $term ) ) as $word ) {
			if ( self::length( $word ) < self::MIN_INDEXED_TOKEN ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Byte length of a UTF-8 string, or character length when mbstring is absent.
	 *
	 * A two-character accented word is not a one-character word, and full-text
	 * tokenisers count characters rather than bytes, so the comparison has to as
	 * well. Falling back to byte length only ever adds a LIKE branch early, which
	 * costs a redundant OR rather than changing which rows come back.
	 *
	 * @param string $word Word to measure.
	 * @return int Length.
	 */
	private static function length( string $word ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $word, 'UTF-8' ) : strlen( $word );
	}

	/**
	 * Build the WHERE fragment for a search across several columns.
	 *
	 * @param string[] $prose_columns  Columns searched with FULLTEXT.
	 * @param string[] $prefix_columns Columns searched with a prefix LIKE.
	 * @param string   $term           Raw search input.
	 * @return array{0:string,1:array<int,string>}|null Fragment plus bind values, or null when the term is unusable.
	 */
	public static function clause( array $prose_columns, array $prefix_columns, string $term ): ?array {
		$boolean = self::boolean_query( $term );
		$pattern = self::prefix_pattern( $term );
		$parts   = array();
		$values  = array();

		if ( '' !== $boolean && ! empty( $prose_columns ) ) {
			$parts[]  = '(MATCH (' . implode( ',', $prose_columns ) . ') AGAINST (%s IN BOOLEAN MODE))';
			$values[] = $boolean;
		}

		// Words the index discards, and identifier columns, still match by prefix.
		$needs_like = '' !== $pattern && '%' !== $pattern
			&& ( self::has_short_word( $term ) || empty( $prefix_columns ) === false );
		if ( $needs_like ) {
			// Only the short words need a LIKE on prose columns; the identifier
			// columns always do.
			$like_columns = $prefix_columns;
			if ( empty( $like_columns ) || self::has_short_word( $term ) ) {
				$like_columns = array_merge( $like_columns, $prose_columns );
			}

			$or = array();
			foreach ( array_unique( $like_columns ) as $column ) {
				$or[]     = "{$column} LIKE %s";
				$values[] = $pattern;
			}
			if ( ! empty( $or ) ) {
				$parts[] = '(' . implode( ' OR ', $or ) . ')';
			}
		}

		if ( empty( $parts ) ) {
			return null;
		}

		return array( '(' . implode( ' OR ', $parts ) . ')', $values );
	}
}