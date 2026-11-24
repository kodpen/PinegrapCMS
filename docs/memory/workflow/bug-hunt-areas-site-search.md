---
name: bug-hunt-areas-site-search
description: Bug hunt of the visitor site search (area 20, 2026-11-24): four faults fixed in the hunt's PR, what was read clean, and the noindex question left as it is
---

## What was the area?
- The visitor site search: the Search Results widget (`_render_system_widget_search_results()`, `_pg_sw_search_collect()` in `includes/fn/widgets.php`), the legacy page `get_search_results.php`, `includes/fn/site_search.php` (query text, live filter), `site_search_tuning.php` (synonyms, promoted results), the rollup count, `assets/js/pg_search_highlight.js`, the catalog search it calls (`get_catalog_search_results()`).
- Asked: noindex, hidden, members-only or unpublished pages never listed or counted, escaping of the query in the page and the highlight, an empty or very long query, paging past the end.
- There is no visitor search suggestion (autocomplete) feature to read: no endpoint or script offers one.

## What did it fix? ("[iş] Bug hunt — site search" lists each with file and line)
- Advanced search: a page whose site search switch was turned off stayed listed until the daily full pass, because the hourly delta reads only searchable pages. `pg_search_items_live_sql()` now also asks `page.page_search = '1'`.
- Legacy page, simple search: the day's rollup counted featured and catalog items only, so a term that found pages was listed under searches with no result. `pg_search_legacy_result_count()` adds `$pages` for a simple search.
- A promoted ("Recommended") result still pinned a page in an archived folder or the Recycle Bin, a link answering 410. `pg_search_promoted_row()` leaves it out.
- The legacy page took a query of any length into its LIKE queries and the rollup; the widget cut it to 100 characters. Both now read the text through `pg_search_query_text()` (100 characters, '' for a list).

## What was read clean?
- Folder access: every list checks `check_view_access($folder, true)`; a membership folder needs a signed-in member, a private one its grant, a binned folder resolves private. Drafts sit in the private Drafts folder.
- Archived and binned pages: both simple paths and both advanced paths filter them (`$sql_live_pages`, `$binned`, `pg_search_items_live_sql()`); the translated page search too.
- Catalog: only enabled groups and products, inside the chosen group's tree; no detail page, no catalog results.
- Escaping: widget tokens through `pg_sw_escape_token_values()` (price HTML only), the legacy headings pass `h($query)` into `lang()`, the search box value `h()`. The highlight script reads `?highlight=` with `decodeURIComponent`, builds an escaped RegExp and writes marks with `textContent`.
- Empty query: no search runs, the heading asks for words, nothing is recorded. A query of operators only (`*`, `+*`) runs no MATCH.
- Paging past the end clamps to the last page, a page below 1 or not a number to the first; only the first page records the search.

## What is left as it is?
- noindex does not take a page out of the site search: the Page tab's site search switch and the Search Engines tab's noindex are separate settings, noindex speaks to outside engines. Changing that would be product behaviour, so the hunt left it.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/designer/site-search-query-and-freshness.md, docs/memory/designer/search-synonyms-promoted.md, docs/memory/visitors/site-search-rollup.md
