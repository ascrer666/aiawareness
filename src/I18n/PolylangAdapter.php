<?php
/**
 * Polylang adaptörü (M2.0).
 *
 * Tüm çağrılar `function_exists` ile korunur: Polylang devre dışı kalırsa
 * eklenti fatal error vermez, tek dilli davranışa düşer.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangAdapter implements LanguageAdapter {

	public static function is_available(): bool {
		return function_exists( 'pll_get_post_translations' )
			&& function_exists( 'pll_get_term_translations' )
			&& function_exists( 'pll_default_language' );
	}

	public function name(): string {
		return 'polylang';
	}

	public function is_multilingual(): bool {
		if ( ! function_exists( 'pll_languages_list' ) ) {
			return false;
		}

		return count( (array) pll_languages_list() ) > 1;
	}

	public function current_language(): string {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return $this->default_language();
		}

		$lang = pll_current_language( 'slug' );

		return is_string( $lang ) && '' !== $lang ? $lang : $this->default_language();
	}

	public function default_language(): string {
		$lang = pll_default_language( 'slug' );

		return is_string( $lang ) && '' !== $lang ? $lang : 'tr';
	}

	public function post_translations( int $post_id ): array {
		$map = pll_get_post_translations( $post_id );

		return $this->normalize( $map, $post_id, $this->post_language( $post_id ) );
	}

	public function term_translations( int $term_id ): array {
		$map = pll_get_term_translations( $term_id );

		return $this->normalize( $map, $term_id, $this->term_language( $term_id ) );
	}

	public function post_language( int $post_id ): string {
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return $this->default_language();
		}

		$lang = pll_get_post_language( $post_id, 'slug' );

		return is_string( $lang ) && '' !== $lang ? $lang : $this->default_language();
	}

	public function term_language( int $term_id ): string {
		if ( ! function_exists( 'pll_get_term_language' ) ) {
			return $this->default_language();
		}

		$lang = pll_get_term_language( $term_id, 'slug' );

		return is_string( $lang ) && '' !== $lang ? $lang : $this->default_language();
	}

	/**
	 * Polylang bazen boş dizi döndürür (henüz bağlanmamış çeviri). Nesnenin
	 * kendisi her zaman grubun üyesidir.
	 *
	 * @param mixed $map
	 * @return array<string,int>
	 */
	private function normalize( $map, int $self_id, string $self_lang ): array {
		$out = [];

		if ( is_array( $map ) ) {
			foreach ( $map as $lang => $id ) {
				$id = (int) $id;
				if ( $id > 0 && is_string( $lang ) && '' !== $lang ) {
					$out[ $lang ] = $id;
				}
			}
		}

		if ( ! in_array( $self_id, $out, true ) ) {
			$out[ $self_lang ] = $self_id;
		}

		return $out;
	}
}
