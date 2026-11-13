---
name: site-search-translated
description: What the site search reads on a page served in another language (pages, products and groups, form records, a list's own search box), the words and ı/i, the order, the fences, and what it leaves out
module: translate
---

## When does the search read translations?
- Only on a page served in another language (`pg_tr_language()` not empty). In the source language no translation is read and not one result changes.
- Code: `includes/translate/search.php` (loaded by `pg_tr_load()`), asked through `includes/fn/translate.php`: `pg_tr_search_pages()`, `pg_tr_search_catalog()`, `pg_tr_search_records()`, `pg_tr_search_list_records()`. Each answers empty in the source language.
- Machine and reviewed translations are both searched.

## How are the typed words compared?
- `pg_tr_search_words()`: the text cut at spaces, a quoted phrase kept whole, at most eight words, each with its i/ı readings (`pg_search_i_variants()`).
- SQL reads up to 2,000 `translations.text` rows holding any reading of any word (`pg_tr_search_like_sql()`); PHP keeps an item only when every word is in its texts taken together, compared folded (`pg_tr_search_fold()`: lower case, ı and i one letter, Latin marks dropped as the collation drops them).
- Rank (`pg_tr_search_rank()`): items holding the whole phrase first, then by hits; a page's title and description, a product's name line, title and keywords count three times.

## What does it find?
- Visual pages: their body (`page` uses) and title and description (`page_seo`), at most 100 (`pg_tr_search_pages_find()`).
- Products and product groups: the fields the catalog extraction stores (`product`, `product_group` uses). `get_catalog_search_results()` lists them first, then its own results, each item once, so the Search Results widget, the catalog search and the legacy search page all get them (#9266).
- Form records, advanced search only: `form_records` uses; a record is listed only through its own `search_items` row, at that item's address and behind the caller's folder fence (`pg_tr_search_record_items()`, #9285).
- A list's own search box (form data list widget) with `translate_records` on: the records whose translated texts, in the fields the box searches, hold every word join the source match as `OR forms.id IN (…)`; the count and the paging read the same condition (#9301, `widget-search-like-pattern.md`).

## In which order, behind which fences?
- Translated matches first, then what the source text matched, each item once (`_pg_sw_search_merge_pages()` for pages). Titles and excerpts in the language; links get its directory.
- The source fences stay: `page.page_search`, no archived folder, no Recycle Bin, `check_view_access()`; a product or a record only where the source search could list it, at the same address.
- Whether such a page should list only its language's results is not decided by the owner (`workflow/decisions-made-product-50.md`).

## What does it leave out?
- The words of shared components (header, footer, menu): they would list every page; the search index leaves them out too (`site-search-index-text.md`).
- Search sections' topics and comments (not translated). Synonyms and promoted results need nothing: each of their words runs through the same search.
- No schema change: a `LIKE` over `translations.text` joined to its uses. A FULLTEXT index for a large store is a question for the owner.

## Tests?
- `tests/translate_search_test.php`: words, readings, rank, catalog and record texts, record items, the list's field filter, source language empty. Not run against MySQL in CI.
