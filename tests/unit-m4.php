<?php
/** M4 pure presentation-fixture boundary tests. */

declare( strict_types = 1 );

use DLA\MedicalTrust\Domain\TrustData;
use DLA\MedicalTrust\Settings\Settings;

T::group( 'M4 — TrustData presentation boundary' );

$reviewer = [ 'id' => 17, 'name' => 'Op. Dr. Leyla Arvas', 'specialty' => 'Cerrahi' ];
$source   = [ 'id' => 33, 'title' => 'Qualified source', 'type' => 'academic', 'url' => 'https://doi.org/10.1000/test' ];
$full     = new TrustData( 5, 'organization', null, $reviewer, $reviewer, '2026-01-02', 'current', 'reviewed', 'valid', '<p>Commentary</p>', [ $source ] );
$empty    = new TrustData( 6, 'organization', null, null, null, null, null, 'none', 'valid', '', [] );
$due      = new TrustData( 7, 'organization', null, $reviewer, $reviewer, '2024-08-31', 'due', 'reviewed', 'valid', '', [] );
$old      = new TrustData( 8, 'organization', null, null, null, null, null, 'reviewed', 'superseded', '', [] );

T::true( 'resolved fixture has visible facts', $full->has_visible_facts() );
T::true( 'valid reviewer/date is a displayable review', $full->has_valid_review() );
T::is( 'due review retains its valid review fact', $due->has_valid_review(), true );
T::is( 'empty non-medical fixture has no visible facts', $empty->has_visible_facts(), false );
T::is( 'superseded fixture has no current review fact', $old->has_valid_review(), false );
T::is( 'automatic injection defaults to disabled', Settings::defaults()['automatic_injection'], false );
