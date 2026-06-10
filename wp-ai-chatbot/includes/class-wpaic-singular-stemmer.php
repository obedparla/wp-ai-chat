<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TeamTNT\TNTSearch\Stemmer\Stemmer;

/**
 * TNT stemmer reducing simple English plurals to singular form ("sneakers" →
 * "sneaker", "watches" → "watch", "accessories" → "accessory").
 *
 * TNT applies the same stemmer to indexed document tokens and to query tokens,
 * which is exactly what the product search needs: TNT only fuzzy-expands a
 * query term when it has NO exact wordlist match, so an index storing raw
 * "sneakers" (plural titles) alongside raw "sneaker" (another product's
 * description) makes the query "sneaker" exact-match only the singular doc and
 * never reach the plural ones. Canonicalizing every token to singular at index
 * AND query time removes that trap.
 *
 * The class name is persisted in the index info table by TNTIndexer, so it must
 * stay loadable wherever searches run.
 */
class WPAIC_Singular_Stemmer implements Stemmer {

	/**
	 * Reduce a simple English plural to its singular form. Applied identically
	 * to document tokens and query tokens, so even imperfect stems stay
	 * consistent.
	 *
	 * @param string $word Token to stem.
	 * @return string
	 */
	public static function stem( $word ) {
		$word   = (string) $word;
		$length = strlen( $word );
		if ( $length <= 3 ) {
			return $word;
		}
		if ( $length > 4 && str_ends_with( $word, 'ies' ) ) {
			return substr( $word, 0, -3 ) . 'y';
		}
		if ( preg_match( '/(ss|sh|ch|x|z)es$/', $word ) ) {
			return substr( $word, 0, -2 );
		}
		if ( str_ends_with( $word, 's' )
			&& ! str_ends_with( $word, 'ss' )
			&& ! str_ends_with( $word, 'us' )
			&& ! str_ends_with( $word, 'is' ) ) {
			return substr( $word, 0, -1 );
		}
		return $word;
	}
}
