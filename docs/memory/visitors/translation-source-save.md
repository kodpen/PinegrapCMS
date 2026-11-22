---
name: translation-source-save
description: How a Languages and Translation save tells a changed source language: both sides resolved (an empty setting is the panel language), so an unchanged save keeps the kept index answers
module: translate
---

## How does the save tell that the source language changed?
- `includes/settings/languages.save.php` compares the source language before and after the save, both resolved by `pg_tr_effective_source_language($stored, $panel_language)` (`includes/fn/translate.php`): the setting, else the panel language (`config.software_language`), else English when that is empty or `undefined`. init.php resolves `TRANSLATION_SOURCE_LANGUAGE` the same way.
- Only a change, or a changed index policy, drops the pages' kept index answers (`translation-index-verdict.md`); otherwise only the drawn bodies go.
- The same resolved language is the one `pg_tr_prefixes_rewrite()` leaves without a prefix and the one Add language is checked against.

## Why are both sides resolved?
- The Source language list has no empty choice: it shows and posts the resolved language. A site that never chose one keeps `translation_source_language` empty, so the posted language against the raw column counted its first save as a change, with nothing edited.
- Symptom: one save of Site Settings > Languages and Translation took every language address out of sitemap.xml and the hreflang alternates until each page was drawn again in each language. Fixed in #11058.
- The first save still writes the shown language into the setting: from then on the source language no longer follows the panel language.

## Where is it tested?
- `tests/translate_source_language_test.php`: the helper, and that the save compares the resolved languages.

Related: docs/memory/visitors/translation-index-verdict.md, docs/memory/platform/language-address-prefix.md
