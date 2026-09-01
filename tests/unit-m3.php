<?php
/** M3 saf domain testleri. */

declare( strict_types = 1 );

use DLA\MedicalTrust\Domain\Enum\ChangeClassification;
use DLA\MedicalTrust\Domain\Enum\ReviewFreshness;
use DLA\MedicalTrust\Review\ContentHasher;
use DLA\MedicalTrust\Review\ExpirationEvaluator;
use DLA\MedicalTrust\Review\ReviewLog;

T::group( 'M3 — ContentHasher' );

$hash_a = ContentHasher::hash( 'Başlık', "<!-- wp:paragraph -->\n<p>Hasta için önemli bilgi.</p>\n<!-- /wp:paragraph -->" );
$hash_b = ContentHasher::hash( 'Başlık', "<p>Hasta   için önemli bilgi.</p>" );
$hash_c = ContentHasher::hash( 'Başlık', '<p>Hasta için önemli olmayan bilgi.</p>' );
T::is( 'blok yorumları ve boşluk farkı aynı hash', $hash_b, $hash_a );
T::is( 'relevant metin değişirse hash değişir', $hash_c === $hash_a, false );
T::is( 'Fusion kabuğu hashte temsil farkıdır', ContentHasher::hash( '', '[fusion_text]<p>Tıbbi metin</p>[/fusion_text]' ), ContentHasher::hash( '', '<p>Tıbbi metin</p>' ) );

T::group( 'M3 — ExpirationEvaluator' );

$now = new DateTimeImmutable( '2026-09-01 00:00:00', new DateTimeZone( 'UTC' ) );
T::is( 'stable politika current', ExpirationEvaluator::evaluate( '2024-10-01', 36, $now ), ReviewFreshness::CURRENT );
T::is( 'standard politika due', ExpirationEvaluator::evaluate( '2024-08-31', 24, $now ), ReviewFreshness::DUE );
T::is( 'volatile politika due', ExpirationEvaluator::evaluate( '2025-08-31', 12, $now ), ReviewFreshness::DUE );
T::is( 'inceleme tarihi yoksa freshness yok', ExpirationEvaluator::evaluate( null, 24, $now ), null );
T::is( 'cron bilgisi olmadan tarih belirleyicidir', ExpirationEvaluator::evaluate( '2024-08-31', 24, $now ), ReviewFreshness::DUE );

T::group( 'M3 — append-only ReviewLog' );

$log = [];
for ( $i = 0; $i < 27; ++$i ) {
	$log = ReviewLog::append( $log, [ 'id' => 'event-' . $i, 'type' => 'review_recorded' ] );
}
T::is( 'log son 25 olayla sınırlı', count( $log ), 25 );
T::is( 'log en eski yeni olayı korur', $log[0]['id'], 'event-2' );
T::is( 'log son olayı korur', ReviewLog::latest( $log )['id'], 'event-26' );
T::true( 'sınıflandırma kapalı listedir', ChangeClassification::is_valid( ChangeClassification::MINOR_EDIT ) );
T::is( 'sınıflandırma için varsayılan yoktur', ChangeClassification::coerce( null ), null );
