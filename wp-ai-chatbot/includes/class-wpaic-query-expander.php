<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wpaic-singular-stemmer.php';

/**
 * Single expansion pass for product searches: given a shopper query, produces
 * an ordered list of (variant query, tier) pairs up front. WPAIC_Search_Index
 * runs one search-and-merge loop over that list, honoring tier order — earlier
 * tiers rank first — instead of layering separate zero-result retry,
 * weak-result merge, and always-merge synonym mechanisms.
 *
 * Tier order:
 * 1. TIER_EXACT          — the query as given, plus its normalized form.
 * 2. TIER_SINGULAR       — every token reduced to singular.
 * 3. TIER_PHRASE_SYNONYM — PHRASE_SYNONYMS substitutions; merged into results
 *                          even when earlier tiers matched.
 * 4. TIER_TOKEN_SYNONYM  — SYNONYM_GROUPS substitutions; merged only when the
 *                          substituted token has no result-title match.
 * 5. TIER_TOKEN_FALLBACK — each significant token alone (brand-only matches);
 *                          searched only while results are still empty.
 */
class WPAIC_Query_Expander {

	public const TIER_EXACT          = 'exact';
	public const TIER_SINGULAR       = 'singular';
	public const TIER_PHRASE_SYNONYM = 'phrase_synonym';
	public const TIER_TOKEN_SYNONYM  = 'token_synonym';
	public const TIER_TOKEN_FALLBACK = 'token_fallback';

	/**
	 * Small synonym groups used to broaden searches. Members are singular,
	 * normalized (lowercase, hyphens already collapsed to spaces by
	 * normalize_query_text), so "t-shirt" appears here as "t shirt".
	 */
	private const SYNONYM_GROUPS = array(
		array( 'perfume', 'fragrance' ),
		array( 'shoe', 'sneaker', 'trainer' ),
		array( 't shirt', 'tshirt', 'tee' ),
	);

	/**
	 * Phrase-level synonyms, merged into results whenever the phrase appears
	 * in the query — unlike SYNONYM_GROUPS, which only broaden zero-result or
	 * weak-result searches. Catalogs name "running shoes" sneakers ("Sports
	 * Sneakers Off White Red"), and sneaker queries should also surface plain
	 * shoes as a fallback. Keys and replacements are singular, normalized
	 * (matching singularize_query_text output). Single-pass, no chaining.
	 */
	private const PHRASE_SYNONYMS = array(
		'running shoe' => 'sneaker',
		'trainer'      => 'sneaker',
		'kick'         => 'sneaker',
		'sneaker'      => 'shoe',
	);

	/**
	 * Ordered (variant query, tier) pairs for the query, deduped by query
	 * text. Token-synonym variants carry the substituted query token in
	 * `source` so the search loop can gate the weak-result merge on whether
	 * that token already names a result title.
	 *
	 * @return array<int, array{query:string, tier:string, source:?string}>
	 */
	public function expand( string $query ): array {
		$variants = array();

		$this->add_variant( $variants, trim( $query ), self::TIER_EXACT );

		$normalized = $this->normalize_query_text( $query );
		if ( '' === $normalized ) {
			return array_values( $variants );
		}
		$this->add_variant( $variants, $normalized, self::TIER_EXACT );

		$singular = $this->singularize_query_text( $query );
		$this->add_variant( $variants, $singular, self::TIER_SINGULAR );

		foreach ( $this->phrase_synonym_variants( $singular ) as $phrase_variant ) {
			$this->add_variant( $variants, $phrase_variant, self::TIER_PHRASE_SYNONYM );
		}

		foreach ( $this->token_synonym_variants( $query, $singular ) as $token_variant ) {
			$this->add_variant( $variants, $token_variant['query'], self::TIER_TOKEN_SYNONYM, $token_variant['source'] );
		}

		foreach ( $this->single_token_variants( $query ) as $token_variant ) {
			$this->add_variant( $variants, $token_variant, self::TIER_TOKEN_FALLBACK );
		}

		return array_values( $variants );
	}

	/**
	 * Append a variant unless blank or already present (first tier wins;
	 * keyed lowercase so the raw query never repeats as its normalized form).
	 *
	 * @param array<string, array{query:string, tier:string, source:?string}> $variants
	 */
	private function add_variant( array &$variants, string $variant_query, string $tier, ?string $source = null ): void {
		$variant_query = trim( $variant_query );
		if ( '' === $variant_query ) {
			return;
		}

		$key = strtolower( $variant_query );
		if ( isset( $variants[ $key ] ) ) {
			return;
		}

		$variants[ $key ] = array(
			'query'  => $variant_query,
			'tier'   => $tier,
			'source' => $source,
		);
	}

	/**
	 * Variants with each PHRASE_SYNONYMS phrase found in the singularized
	 * query substituted by its synonym ("running shoos" → "sneaker",
	 * "red trainers" → "red sneaker"). Phrase tokens match query tokens
	 * exactly or within a one-letter typo.
	 *
	 * @return array<string>
	 */
	private function phrase_synonym_variants( string $singular_query ): array {
		if ( '' === $singular_query ) {
			return array();
		}

		$query_tokens = explode( ' ', $singular_query );
		$variants     = array();

		foreach ( self::PHRASE_SYNONYMS as $phrase => $replacement ) {
			$phrase_tokens = explode( ' ', (string) $phrase );
			$position      = $this->find_phrase_position( $query_tokens, $phrase_tokens );
			if ( null === $position ) {
				continue;
			}

			$substituted = implode(
				' ',
				array_merge(
					array_slice( $query_tokens, 0, $position ),
					array( $replacement ),
					array_slice( $query_tokens, $position + count( $phrase_tokens ) )
				)
			);
			if ( '' !== $substituted && $substituted !== $singular_query ) {
				$variants[ $substituted ] = true;
			}
		}

		return array_keys( $variants );
	}

	/**
	 * Position of the first occurrence of the phrase within the query tokens,
	 * each phrase token matching exactly or within a one-letter typo, or null
	 * when the phrase does not appear.
	 *
	 * @param array<string> $query_tokens
	 * @param array<string> $phrase_tokens
	 */
	private function find_phrase_position( array $query_tokens, array $phrase_tokens ): ?int {
		$last_start = count( $query_tokens ) - count( $phrase_tokens );
		for ( $start = 0; $start <= $last_start; $start++ ) {
			$matches = true;
			foreach ( $phrase_tokens as $offset => $phrase_token ) {
				if ( ! $this->tokens_equivalent( $query_tokens[ $start + $offset ], $phrase_token ) ) {
					$matches = false;
					break;
				}
			}
			if ( $matches ) {
				return $start;
			}
		}
		return null;
	}

	/**
	 * SYNONYM_GROUPS substitutions on the singularized query, one matched
	 * occurrence swapped at a time ("chanel perfume" → "chanel fragrance",
	 * "running shoos" → "running shoe"/"running sneaker"/"running trainer").
	 * Multi-word members match exactly; single-word members match a query
	 * token exactly or within a one-letter typo. Each variant records the
	 * matched query text as its source.
	 *
	 * @return array<int, array{query:string, source:string}>
	 */
	private function token_synonym_variants( string $query, string $singular_query ): array {
		if ( '' === $singular_query ) {
			return array();
		}

		$singular_tokens = array();
		foreach ( $this->significant_query_tokens( $query ) as $token ) {
			$singular_tokens[ $this->singularize_token( $token ) ] = true;
		}
		$singular_tokens = array_keys( $singular_tokens );

		$variants = array();
		foreach ( self::SYNONYM_GROUPS as $group ) {
			foreach ( $this->matched_group_occurrences( $group, $singular_query, $singular_tokens ) as $matched_token ) {
				foreach ( $group as $replacement ) {
					if ( $replacement === $matched_token ) {
						continue;
					}
					$substituted = preg_replace(
						'/\b' . preg_quote( $matched_token, '/' ) . '\b/',
						$replacement,
						$singular_query
					);
					if ( is_string( $substituted ) && '' !== trim( $substituted ) && $substituted !== $singular_query ) {
						$variants[] = array(
							'query'  => $substituted,
							'source' => $matched_token,
						);
					}
				}
			}
		}

		return $variants;
	}

	/**
	 * Query text matching members of one synonym group, in member order:
	 * multi-word members present verbatim match as the member itself;
	 * single-word members match a significant (singularized) query token
	 * exactly or within a one-letter typo ("shoo" → "shoe"). A token inside
	 * an exactly-matched multi-word member ("shirt" within "t shirt") is not
	 * typo-matched again — re-substituting it only yields degenerate variants
	 * like "t tshirt".
	 *
	 * @param array<string> $group
	 * @param array<string> $singular_tokens
	 * @return array<string>
	 */
	private function matched_group_occurrences( array $group, string $singular_query, array $singular_tokens ): array {
		$occurrences    = array();
		$covered_tokens = array();

		foreach ( $group as $member ) {
			if ( ! str_contains( $member, ' ' ) ) {
				continue;
			}
			if ( preg_match( '/\b' . preg_quote( $member, '/' ) . '\b/', $singular_query ) ) {
				$occurrences[ $member ] = true;
				foreach ( explode( ' ', $member ) as $member_word ) {
					$covered_tokens[ $member_word ] = true;
				}
			}
		}

		foreach ( $group as $member ) {
			if ( str_contains( $member, ' ' ) ) {
				continue;
			}
			foreach ( $singular_tokens as $token ) {
				if ( ! $this->tokens_equivalent( $token, $member ) ) {
					continue;
				}
				if ( $token !== $member && isset( $covered_tokens[ $token ] ) ) {
					continue;
				}
				$occurrences[ $token ] = true;
			}
		}

		return array_keys( $occurrences );
	}

	/**
	 * Each significant token alone, raw then singular, plus its synonyms —
	 * catches brand-only matches ("chanel perfume" → "chanel"). Only produced
	 * for multi-token queries.
	 *
	 * @return array<string>
	 */
	private function single_token_variants( string $query ): array {
		$tokens = $this->significant_query_tokens( $query );
		if ( count( $tokens ) <= 1 ) {
			return array();
		}

		$variants = array();
		foreach ( $tokens as $token ) {
			$singular_token = $this->singularize_token( $token );
			$variants[]     = $token;
			$variants[]     = $singular_token;
			foreach ( self::SYNONYM_GROUPS as $group ) {
				if ( in_array( $singular_token, $group, true ) ) {
					foreach ( $group as $replacement ) {
						if ( $replacement !== $singular_token ) {
							$variants[] = $replacement;
						}
					}
				}
			}
		}

		return $variants;
	}

	/**
	 * Whether two singular tokens are the same word, exactly or within a
	 * single-letter typo for words long enough to be distinctive
	 * ("shoo" → "shoe").
	 */
	private function tokens_equivalent( string $token, string $member ): bool {
		if ( $token === $member ) {
			return true;
		}
		return strlen( $token ) >= 4 && strlen( $member ) >= 4 && 1 === levenshtein( $token, $member );
	}

	/**
	 * Lowercase, collapse non-alphanumerics (hyphens included) to single spaces.
	 */
	private function normalize_query_text( string $query ): string {
		$normalized = preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $query ) );
		return is_string( $normalized ) ? trim( $normalized ) : '';
	}

	/**
	 * Normalized query with every token reduced to singular — the canonical
	 * form synonym matching operates on.
	 */
	private function singularize_query_text( string $query ): string {
		$normalized = $this->normalize_query_text( $query );
		if ( '' === $normalized ) {
			return '';
		}
		return implode(
			' ',
			array_map(
				array( $this, 'singularize_token' ),
				explode( ' ', $normalized )
			)
		);
	}

	/**
	 * Reduce a simple English plural to its singular form ("shirts" → "shirt",
	 * "watches" → "watch", "accessories" → "accessory"). Delegates to the TNT
	 * stemmer so query/haystack tokenization and the index share one rule set.
	 */
	private function singularize_token( string $token ): string {
		return WPAIC_Singular_Stemmer::stem( $token );
	}

	/**
	 * Extract lowercased query tokens worth searching or filtering against.
	 * Drops short fragments and generic stopwords that would otherwise reject
	 * relevant products. Shared with WPAIC_Search_Index's relevance filter so
	 * expansion and relevance agree on what counts as a significant token.
	 *
	 * @return array<string>
	 */
	public function significant_query_tokens( string $query ): array {
		$normalized = strtolower( $query );
		$normalized = preg_replace( '/[^a-z0-9]+/', ' ', $normalized );
		if ( ! is_string( $normalized ) ) {
			return array();
		}

		$stopwords = array(
			'a', 'an', 'the', 'and', 'or', 'but', 'of', 'for', 'with', 'to', 'in', 'on',
			'at', 'by', 'is', 'are', 'be', 'this', 'that', 'these', 'those', 'i', 'me',
			'my', 'we', 'our', 'you', 'your', 'show', 'find', 'list', 'give', 'me',
			'some', 'any', 'please', 'looking', 'need', 'want', 'have', 'hav', 'has', 'do',
			'does', 'can', 'could', 'would', 'should', 'product', 'products', 'item',
			'items', 'price', 'prices', 'picture', 'pictures', 'image', 'images',
			'actual', 'really', 'real',
		);

		$tokens = array();
		foreach ( preg_split( '/\s+/', trim( $normalized ) ) ?: array() as $token ) {
			if ( '' === $token ) {
				continue;
			}
			if ( strlen( $token ) < 3 ) {
				continue;
			}
			if ( in_array( $token, $stopwords, true ) ) {
				continue;
			}
			$tokens[ $token ] = true;
		}

		return array_keys( $tokens );
	}
}
