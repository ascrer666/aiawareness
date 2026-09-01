# Medical Trust Contract v1

`dla_medical_trust_get_contract(?int $post_id = null): ?array` is the public,
read-only hand-off for schema generators and other integrations. It returns
`null` for a post outside the configured medical scope (including a page with
no valid medical topic). With no argument it resolves the queried singular
post, just like the M4 shortcode in an Avada global layout.

The contract version is `dla-medical-trust/v1`. It is independent of the
plugin release number: consumers must branch on `contract_version`, not the
plugin header version. The returned array is a fresh scalar-only copy. It is
not a write API, has no contract cache, and emits neither JSON-LD nor markup.

## Shape

```php
$contract = dla_medical_trust_get_contract();

// Every successful response has these top-level keys:
[
    'contract_version'   => 'dla-medical-trust/v1',
    'content'            => [
        'post_id'        => 123,
        'group_uid'      => 'grp_...', // nullable translation-group identity
        'canonical_url'  => 'https://example.test/article/',
        'content_type'   => 'post',
        'language'       => 'tr',
        'locale'         => 'tr_TR',
    ],
    'organization'       => [ 'name' => '...', 'url' => '...', 'logo_id' => 7 ], // or null
    'authorship'         => [
        'mode'           => 'organization', // or expert
        'organization'   => [ /* configured organization */ ], // or null
        'expert'         => null, // or expert identity below
    ],
    'reviewer'           => null, // expert identity only for an applicable review
    'medical_review'     => [
        'status'         => 'none|pending_review|reviewed',
        'validity'       => null, // valid|superseded only for reviewed state
        'freshness'      => null, // current|due only when applicable
        'applicable'     => false,
        'review_date'    => null, // YYYY-MM-DD only when applicable
    ],
    'topics'             => [
        'primary'        => [ 'uid' => 'top_...', 'label' => '...', 'schema_type_hint' => null ],
        'secondary'      => [],
    ],
    'expert_commentary' => [
        'content'              => null,
        'presentation_enabled' => true,
    ],
    'sources'           => [],
    'visibility'        => [
        'review_applicable'               => false,
        'commentary_presentation_enabled' => true,
        'sources_presentation_enabled'    => true,
    ],
];
```

An expert identity contains `entity_uid`, `name`, `honorific`, `specialty`,
`credentials` (a string list), `profile_url`, and `same_as` (a URL list).
`reviewer` is never a WordPress user. `recorded_by`, sign-off material,
internal review logs, cache values, resolver scores and source priorities are
intentionally absent.

Each `sources` item is a final M2-selected, published, eligible source only:
`source_uid`, `type`, `title`, `canonical_url`, `publisher`, `journal`,
`publication_year`, `publication_type`, `peer_reviewed`, and an `identifiers`
object with `doi`, `pmid`, and `pmc_id`. Candidate, rejected, discovery, score,
priority, and cache data are not contract facts.

## Review truth and presentation flags

`medical_review.applicable` is true only for `reviewed` + `valid` state with a
published reviewer expert and a valid historical date. A `due` review remains
applicable; it is still a valid historical review. `pending_review`, `none`, a
missing reviewer/date, and `superseded` are never applicable and never expose
a reviewer or review date.

This is the same applicability rule used by the M4 Trust Box. M6 does not use
post author, post modified date, Rank Math, or any fallback to invent medical
facts.

`expert_commentary.presentation_enabled` and
`visibility.sources_presentation_enabled` report M5 presentation choices.
They do not remove an otherwise valid commentary or selected source from this
canonical contract. Conversely, a presentation flag can never make an invalid
review representable. Commentary is read from the requested page only; it is
not inherited from another language.

## Consumer examples

```php
$contract = dla_medical_trust_get_contract( get_the_ID() );

if ( null !== $contract && $contract['medical_review']['applicable'] ) {
    $reviewer_uid = $contract['reviewer']['entity_uid'];
    $review_date  = $contract['medical_review']['review_date'];
}
```

```php
// A schema plugin may build its own output from facts, but this plugin does
// not generate JSON-LD and must not be asked to maintain a second schema path.
foreach ( $contract['sources'] ?? [] as $source ) {
    $url = $source['canonical_url'];
}
```
