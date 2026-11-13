---
name: translated-site-search
description: Plan (owner 2026-11-13, #9216): the site search on a page served in another language closes its gaps: words and ı/i, products, form records, a list's search box; steps TS1-TS5
---

## What is asked?
- Owner (2026-11-13), after asking in the agent channel whether a multi-language site searches in the chosen language or in common: "çeviri olayını planlara yazalım gelişsin eksikleri gitsin". Record: `workflow/decisions-made-product-50.md` (#9216).

## What does the search do today?
- Source language: only the source text is searched; no translation is read.
- A page served in another language (`pg_tr_language()` not empty): `_pg_sw_search_collect_one()` (`includes/fn/widgets.php`) asks `pg_tr_search_pages()` first. `pg_tr_search_pages_find()` (`includes/translate/seo.php`) reads the language's translations a visual page uses in its body (`page`) or its title and description (`page_seo`) with one `LIKE` on the typed text, counts the hits (title and description three times) and gives at most 100 pages out of 2,000 rows.
- Those pages come first; what the source text matched follows, each page once (`_pg_sw_search_merge_pages()`), in the simple and the advanced search alike. Titles and excerpts are drawn in the language; links get its directory.

## What is missing?
- The typed text is compared as one phrase: "blue shoes" finds no page that says "shoes in blue". ı and i differ (`designer/site-search-turkish-i.md` covers the source's advanced search only).
- Products and product groups: `get_catalog_search_results()` (`includes/fn/content.php`) reads no translation, so on `/en/` a product is found by its source words only. Its callers: the Search Results widget, the catalog's own search (`get_catalog.php`, twice) and the legacy search page.
- Form records: their translations (`form_records` uses, field `r<record>:<form field>`, `core/content-translation-uses.md`) are read neither by the site search, which lists records in the advanced search, nor by a list's own search box (`designer/widget-search-like-pattern.md`).

## What stays?
- The order: translated matches first, then what the source text matched. Whether such a page should list only its language's results is not decided by the owner and is not part of this plan.
- The fences of the source search: `page.page_search`, no archived folder, no Recycle Bin, `check_view_access()`. A product or a record is listed only where the source search could list it, at the same address.
- The source language's results: not one row changes there.
- Machine and reviewed translations are both searched: the visitor reads both.
- No schema change. The translated search stays a `LIKE` over `translations.text` joined to its uses. If that proves slow on a large store, a FULLTEXT index is a question for the owner, not a step here.

## What is not in the plan?
- The words of shared components (header, footer, menu): they would list every page; the search index leaves them out too (`designer/site-search-index-text.md`).
- Search sections' topics and comments (not translated). Synonyms and promoted results need nothing: each of their words already runs through the same search.

## Where are the steps?
- `translated-site-search-steps.md`. Claim as `[plan:translated-site-search] <step> — <topic>`.
- TS1 (M) Pages: the words and ı/i
- TS2 (M) Products and product groups
- TS3 (M) Form records in the site search
- TS4 (S) A list's own search box
- TS5 (S) Close-out
- Done: TS1 #9245
