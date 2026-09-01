<?php
/**
 * Kaynak çözümleyici — DONDURULMUŞ sıra (Addendum A §2).
 *
 *   1. Katı uygunluk        (durum, sağlık, kanonik hedef, kimlik)
 *   2. Minimum konu yakınlığı
 *   3. Slot uyumu
 *   4. Skorlama
 *   5. Top-tier bandı / azami havuz boyutu
 *   6. Rendezvous hashing   ← çeşitlilik YALNIZCA burada
 *
 * Çeşitlilik sayfa ARASINDA oluşur; istek anına veya zamana bağlı hiçbir
 * rastgelelik yoktur. Aynı sayfa, aynı aday kümesiyle her zaman aynı
 * sonucu verir.
 *
 * SAF: WordPress fonksiyonu çağırmaz. Adaylar dışarıdan verilir; bu sayede
 * Addendum A §6'daki testler WordPress'siz koşabilir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\PageMedicalData;
use DLA\MedicalTrust\Domain\Resolution\Candidate;
use DLA\MedicalTrust\Domain\Resolution\ResolutionResult;
use DLA\MedicalTrust\Domain\Resolution\SlotResult;
use DLA\MedicalTrust\Domain\Source;
use DLA\MedicalTrust\Domain\TopicGraph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourceResolver {

	/**
	 * @param array<string,Source[]> $candidates_by_slot slot => aday kaynaklar
	 * @param array<string,Source>   $override_sources   slot => doğrulanmış override
	 */
	public function resolve(
		PageMedicalData $page,
		TopicGraph $graph,
		array $candidates_by_slot,
		ResolverConfig $config,
		array $override_sources = []
	): ResolutionResult {
		$proximity = TopicProximity::build( $page, $graph );

		$primary       = $page->effective_primary_topic();
		$primary_topic = null !== $primary ? $graph->get( $primary ) : null;
		$band          = null !== $primary_topic
			? $primary_topic->band( $config->diversity_band )
			: $config->diversity_band;

		$slots = [];

		foreach ( SourceType::values() as $slot ) {
			$slots[ $slot ] = $this->resolve_slot(
				$slot,
				$candidates_by_slot[ $slot ] ?? [],
				$override_sources[ $slot ] ?? null,
				$page,
				$proximity['scores'],
				$proximity['rungs'],
				$band,
				$config
			);
		}

		return new ResolutionResult(
			$page->post_id,
			$page->seed_key(),
			$slots,
			$proximity['scores'],
			$proximity['rungs'],
			$config->min_topic_proximity,
			$primary
		);
	}

	/**
	 * @param Source[]          $candidates
	 * @param array<string,int> $proximity_scores
	 * @param array<string,string> $proximity_rungs
	 */
	private function resolve_slot(
		string $slot,
		array $candidates,
		?Source $override,
		PageMedicalData $page,
		array $proximity_scores,
		array $proximity_rungs,
		int $band,
		ResolverConfig $config
	): SlotResult {
		$evaluated = [];

		/* ---- Sayfa düzeyi override ---------------------------------- */
		// Editörün açık tercihi skorlamayı ve bandı atlar; ancak KATI
		// uygunluk ve slot uyumu kısıtlarını atlayamaz.
		if ( null !== $override ) {
			$reason = $this->override_rejection( $override, $slot );

			if ( null === $reason ) {
				$best = TopicProximity::best_for( $override->topic_uids, $proximity_scores );

				$candidate           = new Candidate(
					$override,
					$best['proximity'],
					'override',
					$best['uid'],
					0,
					[],
					null,
					true,
					true
				);
				$evaluated[]         = $candidate;

				return new SlotResult( $slot, $override, $evaluated, $band, $config->max_tier_size, 1, true );
			}

			// Geçersiz override sessizce yok sayılır, otomatik çözüme düşülür.
			$evaluated[] = ( new Candidate( $override, 0, 'override', null, 0, [] ) )->reject( $reason );
		}

		/* ---- 1–3. Katı kısıtlar ------------------------------------- */
		$survivors = [];

		foreach ( $candidates as $source ) {
			// 3. Slot uyumu
			if ( $source->type !== $slot ) {
				$evaluated[] = ( new Candidate( $source, 0, 'none', null, 0, [] ) )->reject( Candidate::REJECT_SLOT );
				continue;
			}

			// 1. Katı uygunluk
			if ( ! $source->is_eligible() ) {
				$evaluated[] = ( new Candidate( $source, 0, 'none', null, 0, [] ) )
					->reject( Candidate::REJECT_INELIGIBLE . ':' . (string) $source->ineligibility_reason() );
				continue;
			}

			$best = TopicProximity::best_for( $source->topic_uids, $proximity_scores );
			$rung = null !== $best['uid'] ? ( $proximity_rungs[ $best['uid'] ] ?? 'none' ) : 'none';

			// 2. Minimum konu yakınlığı — editör önceliği dahil hiçbir puan
			//    bu kısıtı atlayamaz.
			if ( $best['proximity'] < $config->min_topic_proximity ) {
				$evaluated[] = ( new Candidate( $source, $best['proximity'], $rung, $best['uid'], 0, [] ) )
					->reject( Candidate::REJECT_PROXIMITY );
				continue;
			}

			/* ---- 4. Skorlama ---------------------------------------- */
			$scored = ScoringPolicy::score( $source, $best['proximity'], $slot, $config );

			$survivors[] = new Candidate(
				$source,
				$best['proximity'],
				$rung,
				$best['uid'],
				$scored['score'],
				$scored['components']
			);
		}

		if ( empty( $survivors ) ) {
			return new SlotResult( $slot, null, $evaluated, $band, $config->max_tier_size, 0, false, $this->override_reason_of( $evaluated ) );
		}

		/* ---- 5. Top-tier bandı ve azami havuz boyutu ----------------- */
		$max_score = 0;
		foreach ( $survivors as $candidate ) {
			$max_score = max( $max_score, $candidate->score );
		}

		// Kararlı sıralama: skor azalan, eşitlikte UID artan.
		usort(
			$survivors,
			static function ( Candidate $a, Candidate $b ): int {
				if ( $a->score !== $b->score ) {
					return $b->score <=> $a->score;
				}

				return strcmp( $a->source->uid, $b->source->uid );
			}
		);

		$threshold = $max_score - $band;
		$tier      = [];

		foreach ( $survivors as $candidate ) {
			if ( $candidate->score < $threshold ) {
				$candidate->reject( Candidate::REJECT_OUT_OF_BAND );
				continue;
			}

			if ( count( $tier ) >= $config->max_tier_size ) {
				$candidate->reject( Candidate::REJECT_TIER_TRIM );
				continue;
			}

			$candidate->in_tier = true;
			$tier[]             = $candidate;
		}

		/* ---- 6. Rendezvous hashing ---------------------------------- */
		$tier_sources = array_map( static fn( Candidate $c ): Source => $c->source, $tier );
		$selected     = TieBreaker::pick( $page->seed_key(), $slot, $tier_sources );

		foreach ( $tier as $candidate ) {
			$candidate->rendezvous_weight = TieBreaker::weight( $page->seed_key(), $slot, $candidate->source->uid );

			if ( null !== $selected && $candidate->source->uid === $selected->uid ) {
				$candidate->selected = true;
			} else {
				$candidate->reject( Candidate::REJECT_NOT_SELECTED );
			}
		}

		$evaluated = array_merge( $evaluated, $survivors );

		return new SlotResult(
			$slot,
			$selected,
			$evaluated,
			$band,
			$config->max_tier_size,
			count( $tier ),
			false,
			$this->override_reason_of( $evaluated )
		);
	}

	/**
	 * Override yalnızca katı kısıtlarda reddedilebilir.
	 */
	private function override_rejection( Source $override, string $slot ): ?string {
		if ( $override->type !== $slot ) {
			return Candidate::REJECT_SLOT;
		}

		if ( ! $override->is_eligible() ) {
			return Candidate::REJECT_INELIGIBLE . ':' . (string) $override->ineligibility_reason();
		}

		return null;
	}

	/**
	 * @param Candidate[] $evaluated
	 */
	private function override_reason_of( array $evaluated ): ?string {
		foreach ( $evaluated as $candidate ) {
			if ( 'override' === $candidate->proximity_rung && null !== $candidate->rejected_reason ) {
				return $candidate->rejected_reason;
			}
		}

		return null;
	}
}
