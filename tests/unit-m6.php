<?php
/** M6 pure contract immutability and review-applicability tests. */

declare( strict_types = 1 );

use DLA\MedicalTrust\Contract\TrustContract;
use DLA\MedicalTrust\Contract\TrustContractService;
use DLA\MedicalTrust\Domain\ReviewVisibility;

T::group( 'M6 - read-only trust contract' );

$contract = new TrustContract(
	[
		'contract_version' => TrustContractService::VERSION,
		'content'          => [ 'post_id' => 44, 'canonical_url' => 'https://example.test/article' ],
		'reviewer'         => null,
	]
);
$first = $contract->to_array();
$first['content']['post_id'] = 999;
$second = $contract->to_array();

T::is( 'contract version is independent from plugin release version', $second['contract_version'], 'dla-medical-trust/v1' );
T::is( 'contract returns a fresh nested array for consumers', $second['content']['post_id'], 44 );
T::true( 'current valid review is applicable', ReviewVisibility::is_applicable( 'reviewed', 'valid', [ 'entity_uid' => 'exp_aaaaaaaaaaaa' ], '2026-01-02' ) );
T::true( 'due valid review remains applicable', ReviewVisibility::is_applicable( 'reviewed', 'valid', [ 'entity_uid' => 'exp_aaaaaaaaaaaa' ], '2024-01-02' ) );
T::is( 'superseded review is never applicable', ReviewVisibility::is_applicable( 'reviewed', 'superseded', [ 'entity_uid' => 'exp_aaaaaaaaaaaa' ], '2026-01-02' ), false );
T::is( 'pending review is never applicable', ReviewVisibility::is_applicable( 'pending_review', 'valid', [ 'entity_uid' => 'exp_aaaaaaaaaaaa' ], '2026-01-02' ), false );
T::is( 'unreviewed content is never applicable', ReviewVisibility::is_applicable( 'none', 'valid', [ 'entity_uid' => 'exp_aaaaaaaaaaaa' ], '2026-01-02' ), false );
T::is( 'missing date cannot create a review claim', ReviewVisibility::is_applicable( 'reviewed', 'valid', [ 'entity_uid' => 'exp_aaaaaaaaaaaa' ], '' ), false );
T::is( 'missing expert cannot create a review claim', ReviewVisibility::is_applicable( 'reviewed', 'valid', null, '2026-01-02' ), false );
