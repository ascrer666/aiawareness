<?php
/**
 * Seçim cache'i (M2.3).
 *
 * Çözülmüş seçim `_dla_resolved_sources` meta'sında kütüphane sürümü damgalı
 * olarak saklanır. Sürüm uyuşmazsa tembel olarak yeniden hesaplanır.
 *
 * Geçersiz kılma maliyeti O(1): tek bir global sayaç artar. Binlerce meta
 * silmek, toplu purge veya cron süpürmesi YOKTUR.
 *
 * KRİTİK: `library_version` yalnızca burada — cache geçerliliğinde —
 * kullanılır. Seçim algoritmasına (rendezvous seed'ine) ASLA girmez.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SelectionCache {

	private ResolutionService $service;

	public function __construct( ?ResolutionService $service = null ) {
		$this->service = $service ?? new ResolutionService();
	}

	/**
	 * Seçilen kaynak ID'leri. Gerekiyorsa çözümler ve yazar.
	 *
	 * @return array<string,int> slot => kaynak post ID (0 = boş slot)
	 */
	public function get( int $post_id ): array {
		$cached = $this->read( $post_id );

		if ( null !== $cached ) {
			return $cached;
		}

		$result = $this->service->resolve_for_post( $post_id );

		if ( null === $result ) {
			return $this->empty_slots();
		}

		$ids = $result->selected_ids();
		$this->write( $post_id, $ids );

		return $ids;
	}

	/**
	 * Yalnızca okur; cache yoksa null döner ve HİÇBİR ŞEY yazmaz.
	 *
	 * @return array<string,int>|null
	 */
	public function peek( int $post_id ): ?array {
		return $this->read( $post_id );
	}

	public function invalidate( int $post_id ): void {
		delete_post_meta( $post_id, MetaRegistry::PAGE_RESOLVED_SOURCES );
	}

	/**
	 * @return array<string,int>|null
	 */
	private function read( int $post_id ): ?array {
		$stored = get_post_meta( $post_id, MetaRegistry::PAGE_RESOLVED_SOURCES, true );

		if ( ! is_array( $stored ) || ! isset( $stored['v'], $stored['slots'] ) ) {
			return null;
		}

		// Sürüm damgası uyuşmuyorsa cache bayattır.
		if ( (int) $stored['v'] !== Settings::library_version() ) {
			return null;
		}

		if ( ! is_array( $stored['slots'] ) ) {
			return null;
		}

		$out = $this->empty_slots();

		foreach ( SourceType::values() as $slot ) {
			$out[ $slot ] = (int) ( $stored['slots'][ $slot ] ?? 0 );
		}

		return $out;
	}

	/**
	 * @param array<string,int> $ids
	 */
	private function write( int $post_id, array $ids ): void {
		update_post_meta(
			$post_id,
			MetaRegistry::PAGE_RESOLVED_SOURCES,
			[
				'v'     => Settings::library_version(),
				'slots' => $ids,
				'at'    => time(),
			]
		);
	}

	/**
	 * @return array<string,int>
	 */
	private function empty_slots(): array {
		$out = [];

		foreach ( SourceType::values() as $slot ) {
			$out[ $slot ] = 0;
		}

		return $out;
	}
}
