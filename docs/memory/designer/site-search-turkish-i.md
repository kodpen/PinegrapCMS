---
name: site-search-turkish-i
description: Advanced site search finds "kahvaltı" for KAHVALTI and kahvalti: each word with i, I, ı or İ is searched in its i/ı readings (#4352); the simple LIKE search does not fold yet
module: site_search
---

## Why did KAHVALTI find nothing?
`search_items` columns are `utf8mb4_unicode_ci`: ö=o and ş=s, I and İ fold to i, but ı (U+0131) is its own letter. So `MATCH ... AGAINST('KAHVALTI')` or `'kahvalti'` missed every "kahvaltı" row, while `KAVURMA`, `ATOLYE` worked (#4352).

## What does the query do now?
- `pg_search_i_variants($word)` (`includes/fn/site_search.php`): a word with an i letter is lower cased (I read as ı, İ as i, a U+0307 dot joins its letter), then with up to two such letters every i/ı mix is given, past two the typed reading, all i and all ı. "Diyarbakir" gives four, "kahvalti" two; a word without one stays as typed.
- `pg_search_boolean_terms()` writes the readings side by side: plain words in natural mode (`kahvaltı kahvalti menu`, still no boolean mode), a single word or phrase as quoted alternatives (`"kahvaltı" "kahvalti"`), with `+`/`-` grouped (`-(kahvalti* kahvaltı*)`). `pg_search_score_terms()` drops the parentheses too.
- Query time only: no schema, no folded copy in the index, no reindex. Applied on every site (a reading with ı finds nothing in English text, so it only widens), unlike the panel lists' Turkish-only fold (#3742).

## What is not covered?
The simple search (`SEARCH_TYPE` not advanced, the LIKE path of `_pg_sw_search_collect()`) and its PHP hit counting still compare as typed; ı and i differ there.

## Tests?
`tests/site_search_query_test.php` (`turkish_i_variants*`). Not run against MySQL in CI.
