# Localization and Polylang

The plugin's source language is Turkish. Interface translations live in
`languages/dla-medical-trust.pot` and the release bundles include English,
German, French and Russian `.po` / `.mo` pairs. Expert biographies, page-level
expert commentary and medical page copy are editorial data, not gettext
strings; Polylang manages those as content translations.

## Polylang object model

`dla_expert` and `dla_medical_topic` are declared through Polylang's
`pll_get_post_types` and `pll_get_taxonomies` filters while plugin files load,
before Polylang creates its model. No Polylang settings checkbox is required.

`dla_source` is deliberately not declared translatable. The source library is
global: one verified source record is related to a language-neutral medical
topic UID and can therefore be resolved for the Turkish, English, German,
French and Russian versions of that topic. Duplicating source posts would make
curation, publication state and canonical citations diverge.

`IdentitySync` remains necessary because Polylang stores translation groups as
different WordPress objects. It normalizes `_dla_entity_uid` for expert groups
and `_dla_topic_uid` for medical-topic groups after Polylang saves a group;
it does not make sources translatable.

## Release build

After changing a user-facing interface string, regenerate the POT, then build
the PO files and compile each MO:

```powershell
php wp-cli.phar i18n make-pot . languages/dla-medical-trust.pot --domain=dla-medical-trust --exclude=tests,tools
php tools/build-translations.php
php wp-cli.phar i18n make-mo languages/dla-medical-trust-en_US.po languages/dla-medical-trust-en_US.mo
```
