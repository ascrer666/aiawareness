<?php
/**
 * Çözümleme servisi — WordPress ile saf çözümleyici arasındaki köprü.
 *
 * YAN ETKİSİZDİR: veri yükler, çözümleyiciyi çağırır, sonucu döndürür.
 * Cache'e YAZMAZ. Yazma işi yalnızca SelectionCache'e aittir; açıklama
 * paneli bu servisi doğrudan kullandığı için sonucu etkileyemez.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\PageMedicalData;
use DLA\MedicalTrust\Domain\Resolution\ResolutionResult;
use DLA\MedicalTrust\Domain\Source;
use DLA\MedicalTrust\Repository\PageDataRepository;
use DLA\MedicalTrust\Repository\SourceRepository;
use DLA\MedicalTrust\Repository\TopicRepository;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ResolutionService {

	private TopicRepository $topics;
	private SourceRepository $sources;
	private PageDataRepository $pages;
	private SourceResolver $resolver;

	public function __construct(
		?TopicRepository $topics = null,
		?SourceRepository $sources = null,
		?PageDataRepository $pages = null,
		?SourceResolver $resolver = null
	) {
		$this->topics   = $topics ?? new TopicRepository();
		$this->sources  = $sources ?? new SourceRepository();
		$this->pages    = $pages ?? new PageDataRepository();
		$this->resolver = $resolver ?? new SourceResolver();
	}

	/**
	 * Sayfa için tam çözümleme. Cache'e dokunmaz.
	 */
	public function resolve_for_post( int $post_id ): ?ResolutionResult {
		$graph = $this->topics->graph();
		$page  = $this->pages->for_post( $post_id, $graph );

		if ( null === $page || ! $page->is_medical() ) {
			return null;
		}

		$primary       = $page->effective_primary_topic();
		$primary_topic = null !== $primary ? $graph->get( $primary ) : null;
		$policy        = Settings::policy( null !== $primary_topic ? $primary_topic->review_policy : null );

		$config = ResolverConfig::from_settings( $policy['max_source_age_years'] );

		// Aday havuzu, yakınlık tabanını geçebilecek TÜM konuların
		// term ID'lerinden toplanır — her dildeki term dahil.
		$eligible_uids = $this->eligible_topic_uids( $page, $graph, $config );
		$term_ids      = $graph->term_ids_for_uids( $eligible_uids );

		$candidates = [];
		foreach ( SourceType::values() as $slot ) {
			$candidates[ $slot ] = $this->sources->find_candidates( $term_ids, $slot, $graph );
		}

		return $this->resolver->resolve(
			$page,
			$graph,
			$candidates,
			$config,
			$this->load_overrides( $page, $graph )
		);
	}

	/**
	 * Tabanı geçebilecek konular. Taban altındaki basamaklar sorguya bile
	 * girmez — gereksiz aday yüklemesi yapılmaz.
	 *
	 * @return string[]
	 */
	private function eligible_topic_uids( PageMedicalData $page, \DLA\MedicalTrust\Domain\TopicGraph $graph, ResolverConfig $config ): array {
		$proximity = TopicProximity::build( $page, $graph );
		$out       = [];

		foreach ( $proximity['scores'] as $uid => $score ) {
			if ( $score >= $config->min_topic_proximity ) {
				$out[] = (string) $uid;
			}
		}

		return $out;
	}

	/**
	 * @return array<string,Source>
	 */
	private function load_overrides( PageMedicalData $page, \DLA\MedicalTrust\Domain\TopicGraph $graph ): array {
		$out = [];

		foreach ( $page->source_overrides as $slot => $post_id ) {
			// Yalnızca yayımlanmış kaynaklar override olabilir.
			if ( 'publish' !== $this->sources->status_of( (int) $post_id ) ) {
				continue;
			}

			$source = $this->sources->find( (int) $post_id, $graph );

			if ( null !== $source ) {
				$out[ (string) $slot ] = $source;
			}
		}

		return $out;
	}
}
