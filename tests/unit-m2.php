<?php
/**
 * M2 birim testleri — domain, resolver ve dil-kimlik saf mantığı.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

use DLA\MedicalTrust\Domain\Enum\PublicationType;
use DLA\MedicalTrust\Domain\Enum\SourceHealth;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\PageMedicalData;
use DLA\MedicalTrust\Domain\Resolution\Candidate;
use DLA\MedicalTrust\Domain\Source;
use DLA\MedicalTrust\Domain\Topic;
use DLA\MedicalTrust\Domain\TopicGraph;
use DLA\MedicalTrust\I18n\GroupIdentity;
use DLA\MedicalTrust\Resolver\ResolverConfig;
use DLA\MedicalTrust\Resolver\ScoringPolicy;
use DLA\MedicalTrust\Resolver\SourceResolver;
use DLA\MedicalTrust\Resolver\TieBreaker;
use DLA\MedicalTrust\Resolver\TopicProximity;

/** @param string[] $topics */
function m2_source(
	int $id,
	string $uid,
	string $type = SourceType::PUBLICATION,
	array $topics = [ 'top_main' ],
	?string $publication_type = PublicationType::SYSTEMATIC_REVIEW,
	bool $peer_reviewed = true,
	int $priority = 0,
	int $year = 2024,
	string $health = SourceHealth::UNKNOWN,
	?string $url = 'https://doi.org/10.1000/example',
	string $publisher = ''
): Source {
	return new Source( $id, $uid, 'Fixture ' . $uid, $type, $publication_type, $peer_reviewed, $priority, $year, $health, $topics, $url, $publisher, '', 'en' );
}

function m2_graph(): TopicGraph {
	return new TopicGraph(
		[
			new Topic( 'top_main', 'Main', 'main', [ 1 ], 'top_parent', [ 'top_related' ], 'standard', null, 0, null ),
			new Topic( 'top_secondary', 'Secondary', 'secondary', [ 2 ], null, [], 'standard', null, 0, null ),
			new Topic( 'top_related', 'Related', 'related', [ 3 ], null, [], 'standard', null, 0, null ),
			new Topic( 'top_parent', 'Parent', 'parent', [ 4 ], 'top_grandparent', [], 'standard', null, 0, null ),
			new Topic( 'top_grandparent', 'Grandparent', 'grandparent', [ 5 ], 'top_great_grandparent', [], 'standard', null, 0, null ),
			new Topic( 'top_great_grandparent', 'Great grandparent', 'great-grandparent', [ 6 ], null, [], 'standard', null, 0, null ),
			new Topic( 'top_general', 'General', 'general', [ 7 ], null, [], 'standard', null, 0, null ),
			new Topic( 'top_unrelated', 'Unrelated', 'unrelated', [ 8 ], null, [], 'standard', null, 0, null ),
		]
	);
}

function m2_page( string $group_uid = 'grp_page000001' ): PageMedicalData {
	return new PageMedicalData( 101, $group_uid, [ 'top_main', 'top_secondary' ], 'top_main', [] );
}

/** @param Source[] $publication @return array<string,Source[]> */
function m2_candidates( array $publication ): array {
	return [ SourceType::ACADEMIC => [], SourceType::AUTHORITY => [], SourceType::PUBLICATION => $publication ];
}

function m2_selected_uid( $result ): ?string {
	$selected = $result->slots[ SourceType::PUBLICATION ]->selected;

	return $selected instanceof Source ? $selected->uid : null;
}

function m2_candidate( $slot, string $uid ): ?Candidate {
	foreach ( $slot->candidates as $candidate ) {
		if ( $candidate->source->uid === $uid ) {
			return $candidate;
		}
	}

	return null;
}

/* ================================================================== */
T::group( 'M2.0 — grup kimliği ve konu yakınlığı' );

$graph     = m2_graph();
$page      = m2_page();
$proximity = TopicProximity::build( $page, $graph );

T::is( 'birincil konu 100', $proximity['scores']['top_main'], 100 );
T::is( 'ikincil konu 75', $proximity['scores']['top_secondary'], 75 );
T::is( 'küratörlü ilişki 60', $proximity['scores']['top_related'], 60 );
T::is( 'üst konu 55', $proximity['scores']['top_parent'], 55 );
T::is( 'büyük üst konu 35', $proximity['scores']['top_grandparent'], 35 );
T::is( 'genel havuz 10', $proximity['scores']['top_general'], 10 );
T::is(
	'varsayılan dil UIDsi kanoniktir',
	GroupIdentity::choose_canonical(
		[ [ 'id' => 20, 'lang' => 'en', 'uid' => 'exp_aaaaaaaaaaaa' ], [ 'id' => 10, 'lang' => 'tr', 'uid' => 'exp_bbbbbbbbbbbb' ] ],
		'tr'
	),
	'exp_bbbbbbbbbbbb'
);
T::is(
	'varsayılan dil yoksa en düşük ID kazanır',
	GroupIdentity::choose_canonical(
		[ [ 'id' => 20, 'lang' => 'en', 'uid' => 'top_aaaaaaaaaaaa' ], [ 'id' => 10, 'lang' => 'de', 'uid' => 'top_bbbbbbbbbbbb' ] ],
		'tr'
	),
	'top_bbbbbbbbbbbb'
);
T::is( 'geçerli UID yoksa yeni üretim gerekir', GroupIdentity::choose_canonical( [ [ 'id' => 10, 'lang' => 'tr', 'uid' => 'invalid' ] ], 'tr' ), null );
T::is( 'sayfa seed’i post ID değil grup UID', $page->seed_key(), 'grp_page000001' );

/* ================================================================== */
T::group( 'M2.2 — dondurulmuş skorlama formülü' );

$config = new ResolverConfig( 55, 10, 6, 2026, 10 );
$scored = ScoringPolicy::score( m2_source( 1, 'src_score00001', SourceType::PUBLICATION, [ 'top_main' ], PublicationType::SYSTEMATIC_REVIEW, true, 3, 2024 ), 100, SourceType::PUBLICATION, $config );
$stale  = ScoringPolicy::score( m2_source( 2, 'src_score00002', SourceType::PUBLICATION, [ 'top_main' ], PublicationType::CASE_SERIES, false, 0, 2010 ), 100, SourceType::PUBLICATION, $config );
$auth   = ScoringPolicy::score( m2_source( 3, 'src_score00003', SourceType::AUTHORITY, [ 'top_main' ], PublicationType::CLINICAL_GUIDELINE, false, 0, 2025, SourceHealth::UNKNOWN, 'https://www.isaps.org/guide', 'ISAPS' ), 100, SourceType::AUTHORITY, $config );

T::is( 'tam skor formülü', $scored['score'], 136 ); // 100 + 3 + 12 + 8 + 13.
T::is( 'güncellik bileşeni', $scored['components']['recency'], 13 );
T::is( 'eskime cezası', $stale['components']['staleness'], -30 );
T::is( 'eskimiş kaynak tam skoru', $stale['score'], 72 ); // 100 + 2 - 30.
T::is( 'authority slot bonusu', $auth['components']['authority'], 10 );
T::is( 'Source DTO köken alanı içermez', property_exists( Source::class, 'discovered_via' ), false );

/* ================================================================== */
T::group( 'M2.2 — katı kısıtlar, tier ve override' );

$resolver = new SourceResolver();
$strict   = new ResolverConfig( 75, 10, 6, 2026, 10 );
$valid    = m2_source( 11, 'src_valid000001' );
$broken   = m2_source( 12, 'src_broken00001', SourceType::PUBLICATION, [ 'top_main' ], PublicationType::SYSTEMATIC_REVIEW, true, 20, 2024, SourceHealth::BROKEN );
$far      = m2_source( 13, 'src_far00000001', SourceType::PUBLICATION, [ 'top_parent' ], PublicationType::SYSTEMATIC_REVIEW, true, 20 );
$wrong    = m2_source( 14, 'src_wrong000001', SourceType::AUTHORITY, [ 'top_main' ] );
$wrong_broken = m2_source( 15, 'src_wrongbroken', SourceType::AUTHORITY, [ 'top_main' ], PublicationType::SYSTEMATIC_REVIEW, true, 0, 2024, SourceHealth::BROKEN );
$result   = $resolver->resolve( $page, $graph, m2_candidates( [ $valid, $broken, $far, $wrong, $wrong_broken ] ), $strict );
$slot     = $result->slots[ SourceType::PUBLICATION ];

T::is( 'uygun aday seçilir', m2_selected_uid( $result ), 'src_valid000001' );
T::true( 'bozuk kaynak katı uygunlukta reddedilir', str_starts_with( (string) m2_candidate( $slot, 'src_broken00001' )->rejected_reason, 'ineligible:health_broken' ) );
T::is( 'taban altı kaynak reddedilir', m2_candidate( $slot, 'src_far00000001' )->rejected_reason, 'below_min_proximity' );
T::is( 'yanlış slot reddedilir', m2_candidate( $slot, 'src_wrong000001' )->rejected_reason, 'slot_mismatch' );
T::true( 'katı uygunluk slot reddinden önce gelir', str_starts_with( (string) m2_candidate( $slot, 'src_wrongbroken' )->rejected_reason, 'ineligible:health_broken' ) );

$override_bad = m2_source( 16, 'src_overridebad', SourceType::PUBLICATION, [ 'top_main' ], PublicationType::SYSTEMATIC_REVIEW, true, 0, 2024, SourceHealth::UNKNOWN, null );
$override_ok  = m2_source( 17, 'src_overrideok0', SourceType::PUBLICATION, [ 'top_general' ] );
$rejected     = $resolver->resolve( $page, $graph, m2_candidates( [ $valid ] ), $config, [ SourceType::PUBLICATION => $override_bad ] );
$applied      = $resolver->resolve( $page, $graph, m2_candidates( [ $valid ] ), $config, [ SourceType::PUBLICATION => $override_ok ] );

T::is( 'geçersiz override otomatiğe düşer', m2_selected_uid( $rejected ), 'src_valid000001' );
T::true( 'geçersiz override nedeni korunur', str_starts_with( (string) $rejected->slots[ SourceType::PUBLICATION ]->override_rejected_reason, 'ineligible:no_canonical_url' ) );
T::is( 'geçerli override seçilir', m2_selected_uid( $applied ), 'src_overrideok0' );
T::true( 'override uygulanma işareti', $applied->slots[ SourceType::PUBLICATION ]->override_applied );

/* ================================================================== */
T::group( 'M2.2 — deterministik rendezvous ve sınırlı etki' );

$tier = [ m2_source( 21, 'src_tier0000001' ), m2_source( 22, 'src_tier0000002' ), m2_source( 23, 'src_tier0000003' ) ];
$tier_config = new ResolverConfig( 55, 0, 6, 2026, 10 );
$first       = m2_selected_uid( $resolver->resolve( $page, $graph, m2_candidates( $tier ), $tier_config ) );

for ( $i = 0; $i < 20; ++$i ) {
	T::is( 'tekrar ' . $i . ' aynı seçim', m2_selected_uid( $resolver->resolve( $page, $graph, m2_candidates( $tier ), $tier_config ) ), $first );
}

T::is( 'girdi sırası seçimi değiştirmez', m2_selected_uid( $resolver->resolve( $page, $graph, m2_candidates( array_reverse( $tier ) ), $tier_config ) ), $first );
T::is( 'rendezvous aynı seed/slot/UID için sabit', TieBreaker::weight( 'grp_page000001', SourceType::PUBLICATION, 'src_tier0000001' ), TieBreaker::weight( 'grp_page000001', SourceType::PUBLICATION, 'src_tier0000001' ) );

$distribution = [];
$before       = [];
for ( $i = 0; $i < 60; ++$i ) {
	$page_i                 = m2_page( 'grp_diverse' . str_pad( (string) $i, 5, '0', STR_PAD_LEFT ) );
	$before[ $i ]           = m2_selected_uid( $resolver->resolve( $page_i, $graph, m2_candidates( $tier ), $tier_config ) );
	$distribution[ $before[ $i ] ] = ( $distribution[ $before[ $i ] ] ?? 0 ) + 1;
}
T::true( 'aynı konudaki sayfalarda çeşitlilik var', count( $distribution ) > 1 );
T::true( 'hiçbir tier adayı yüzde 80den fazlasını almaz', max( $distribution ) <= 48 );

$added   = m2_source( 99, 'src_tier0000004' );
$changed = 0;
for ( $i = 0; $i < 60; ++$i ) {
	$page_i = m2_page( 'grp_diverse' . str_pad( (string) $i, 5, '0', STR_PAD_LEFT ) );
	$after  = m2_selected_uid( $resolver->resolve( $page_i, $graph, m2_candidates( [ ...$tier, $added ] ), $tier_config ) );
	if ( $after !== $before[ $i ] ) {
		++$changed;
		T::is( 'eklenen kaynak dışındaki seçim değişmez ' . $i, $after, 'src_tier0000004' );
	}
}
T::true( 'kaynak ekleme tüm sayfaları değiştirmez', $changed < 60 );

$removed_uid = 'src_tier0000002';
for ( $i = 0; $i < 60; ++$i ) {
	$page_i = m2_page( 'grp_diverse' . str_pad( (string) $i, 5, '0', STR_PAD_LEFT ) );
	$after  = m2_selected_uid( $resolver->resolve( $page_i, $graph, m2_candidates( [ $tier[0], $tier[2] ] ), $tier_config ) );
	if ( $before[ $i ] !== $removed_uid ) {
		T::is( 'silinen dışındaki seçim sabit ' . $i, $after, $before[ $i ] );
	}
}

$unrelated = m2_source( 100, 'src_unrelated01', SourceType::PUBLICATION, [ 'top_unrelated' ] );
T::is( 'ilgisiz konu sonucu değiştirmez', m2_selected_uid( $resolver->resolve( $page, $graph, m2_candidates( [ ...$tier, $unrelated ] ), $tier_config ) ), $first );

$migrated = [ m2_source( 1021, 'src_tier0000001' ), m2_source( 1022, 'src_tier0000002' ), m2_source( 1023, 'src_tier0000003' ) ];
T::is( 'staging/canlı ID değişimi seçimi değiştirmez', m2_selected_uid( $resolver->resolve( $page, $graph, m2_candidates( $migrated ), $tier_config ) ), $first );

/* ================================================================== */
T::group( 'M2.2 — öncelik ve kanıt kalite sınırları' );

$normal = m2_source( 201, 'src_priority001' );
$high   = m2_source( 202, 'src_priority002', SourceType::PUBLICATION, [ 'top_main' ], PublicationType::SYSTEMATIC_REVIEW, true, 15 );
T::true( 'öncelik skoru yükseltir', ScoringPolicy::score( $high, 100, SourceType::PUBLICATION, $tier_config )['score'] > ScoringPolicy::score( $normal, 100, SourceType::PUBLICATION, $tier_config )['score'] );

$priority_far    = m2_source( 203, 'src_priorityfar', SourceType::PUBLICATION, [ 'top_parent' ], PublicationType::SYSTEMATIC_REVIEW, true, 20 );
$priority_result = $resolver->resolve( $page, $graph, m2_candidates( [ $normal, $priority_far ] ), new ResolverConfig( 75, 20, 6, 2026, 10 ) );
T::is( 'öncelik yakınlık tabanını atlayamaz', m2_selected_uid( $priority_result ), 'src_priority001' );

$high_evidence = m2_source( 204, 'src_evidencehigh' );
$low_evidence  = m2_source( 205, 'src_evidencelow0', SourceType::PUBLICATION, [ 'top_main' ], PublicationType::CASE_SERIES, false, 0, 2010 );
$evidence      = $resolver->resolve( $page, $graph, m2_candidates( [ $high_evidence, $low_evidence ] ), new ResolverConfig( 55, 10, 6, 2026, 10 ) );
T::is( 'düşük kanıt bandın dışında kalır', m2_candidate( $evidence->slots[ SourceType::PUBLICATION ], 'src_evidencelow0' )->rejected_reason, 'outside_diversity_band' );
T::is( 'düşük kanıt seçilemez', m2_selected_uid( $evidence ), 'src_evidencehigh' );
