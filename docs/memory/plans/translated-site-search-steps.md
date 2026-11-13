---
name: translated-site-search-steps
description: Translated site search plan, steps TS1-TS5: the words and ı/i for pages, products and product groups, form records in the site search, a list's own search box, close-out
---

## Where does the code go?
- A new `includes/translate/search.php`, loaded with the other translate files (`pg_tr_load()`): `pg_tr_search_pages_find()` moves there from `seo.php`; TS2 to TS4 add theirs. The callers keep asking through `includes/fn/translate.php` (`pg_tr_search_pages()`, then `pg_tr_search_catalog()` and `pg_tr_search_records()`), which answer empty in the source language.
- Every step: pure functions for the words and the ranking, tested in `tests/translate_search_test.php`; no schema; no new user-facing text is expected.

## TS1 (M) Pages: the words and ı/i — done (#9245)
- Done: `pg_tr_search_words()`, `pg_tr_search_like_sql()`, `pg_tr_search_rank()`, `pg_tr_search_fold()` (also drops Latin marks, as the collation does).
- `pg_tr_search_words($query)` (pure): the typed text cut into words, a quoted phrase kept whole, at most eight; each word in its readings of `pg_search_i_variants()` (`includes/fn/site_search.php`).
- The query takes the rows where any reading of any word matches; PHP groups them by page and compares folded (`_pg_search_fold()`). A page is a result when every word is found in its title, description and body taken together. Rank: the whole phrase first, then by hits; title and description count three times, as today.
- Today's limits stay (2,000 rows, 100 pages). The fences are untouched.

## TS2 (M) Products and product groups — done (#9266)
- Done: `get_catalog_search_results_translated()` runs the found ids through the source loop (`get_catalog_search_results_for_condition()`).
- `pg_tr_search_catalog_find($query)`: the products and product groups whose translated texts match, by TS1's words. It reads the uses of `product` and `product_group`, in the fields the catalog extraction stores (`includes/translate/content.php`: short_description, full_description, details, keywords, title, meta_description).
- `get_catalog_search_results()` itself puts them first on a page in another language, then its own results, each item once, so every caller gets it. Only items its own rules allow (enabled, inside the searched group) are listed.
- A test proves the source language's answer is unchanged.

## TS3 (M) Form records in the site search — done (#9285)
- Done: `pg_tr_search_record_items()` lists only the record's own `search_items` row, behind the caller's fences; TS4 can reuse `pg_tr_search_record_id()`.
- The advanced search only; the simple search lists no record in the source either.
- `pg_tr_search_records_find($query)`: the records whose translated texts match (`form_records` uses; the record is read from `field`). A record is listed only when the search index holds an item for it (`search_items`, written for the records a public list reaches), at that item's address and behind its folder fence, before the source's results.

## TS4 (S) A list's own search box
- The form data list search of the system widgets: on a page in another language a record also matches when its translated text does, for a list whose records are in the translation. The count and the paging read the same condition.

## TS5 (S) Close-out
- One topic file `designer/site-search-translated.md`: what the search reads in another language and what it leaves out. `designer/site-search-turkish-i.md` corrected; this plan's two files deleted.

## In which order?
- TS1 first: the rest use its words and readings. Then TS2, TS3, TS4 one after the other, as all add to `includes/translate/search.php`. One step per PR. The changelog gets no line of its own.
