---
name: language-address-prefix
description: A target language's address prefix (/en/, /pt-br/): lower case everywhere, fallback to the lower-cased code, collision check in the languages save, router matching
---

## Where does a prefix live and who reads it?
`site_languages.prefix` per language; `pg_tr_prefixes_rewrite()` writes `config.translation_prefixes` ("en=en,pt-br=pt-BR") from the enabled rows after every save of Settings › Languages and Translation (`includes/settings/languages.save.php`). `router.php` reads that column with raw mysqli (`router_translation_prefixes()`) and matches the first address segment against `[a-z]{2,8}(-[a-z]{2,4})?`. Links, the language switcher, hreflang, the sitemap and the external API build addresses with `pg_tr_prefix()`.

## Why is a prefix always lower case?
The router matches the address in lower case only, and the screen's field has `pattern="[a-z]{2,8}(-[a-z]{2,4})?"`. A language whose code has capitals (pt-BR, zh-CN, zh-TW) is served under the lower-cased code. Before #843 (PR #1013) an emptied or taken prefix fell back to the raw code: `pt-BR` was saved, `/pt-br/` and `/pt-BR/` both gave 404 while the links and the sitemap kept pointing at `/pt-BR/`, and the next save could be blocked by the field pattern.

## What does the save do with an empty, malformed or taken prefix?
The prefixes are settled before anything is written, in the screen's order, by `pg_tr_prefixes_settle()` (`includes/translate/store.php`, tested in `tests/translate_prefix_test.php`): a valid, free one is kept; otherwise the language gets its code lower-cased. When another language already holds that as its prefix, the save stops with "The address prefix of {var:1} is used by another language. Give it a prefix of its own." on that row (`languages[<code>][prefix]`); a language being added gets the same check against its lower-cased code. A removed language reserves nothing.

## What about a site that already stored "pt-BR"?
It heals without a migration: `router_translation_prefixes()` and `pg_tr_prefix_map()` lower-case the prefix keys, `pg_tr_prefix()` and `pg_tr_prefixes_rewrite()` lower-case what they hand out, and the screen shows the stored prefix lower-cased so the next save writes it that way.
