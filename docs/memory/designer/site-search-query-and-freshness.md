---
name: site-search-query-and-freshness
description: Advanced site search over search_items: the query text in MATCH, the binned-page filter, record and file labels, the index made due after a template install (#3550, #3551, #3552)
module: site_search
---

## Where is it?
`includes/fn/site_search.php`, used by the search widget (`_pg_sw_search_collect()` in `includes/fn/widgets.php`) and the legacy page (`get_search_results.php`). Both run the same three steps; only the advanced search (`SEARCH_TYPE = advanced`) reads `search_items`.

## What does the query accept?
- `pg_search_boolean_terms($query)` turns any text into a valid fulltext string: a word keeps one leading `+`/`-` and one trailing `*`; `< > ( ) ~ @`, inner `*` and lone operators go; a word with a joining character (`multi-recipient`, `v2.1`, `Pinegrap'in`) and a quoted phrase become `"…"`; a single plain word is quoted, as before. Nothing with a letter or digit left: `''`, and no query runs (the widget still lists its translated-page hits).
- `pg_search_needs_boolean_mode()`: `IN BOOLEAN MODE` only with quotes, `+`, `-` or `*`; plain words stay natural language mode. `pg_search_score_terms()` gives the words for the natural-mode score.
- The text is bound with `db_q()` placeholders through `pg_search_fulltext_items()`; a failed MATCH (false) logs one line and counts as no hits.
- Fixed (#3550): `*` or `+*` reached MySQL's boolean parser as a syntax error and `db_items()` ended the page with "Query failed." for any visitor.

## Why is a binned page no longer found?
The index is rebuilt once a day, so a page binned, archived or deleted after a run kept its rows and its link answered 404 (#3551). `pg_search_items_live_sql(pg_recycle_bin_folder_ids())` is added to both queries: a row with a page needs the page to exist in an existing, not archived, not binned folder; any row's `folder_id` must not be binned (a file has `page_id = 0`). Records carry their detail page's id, so they leave with it.

## When does the index see a new design?
`pg_design_template_install()` calls `pg_search_index_refresh_soon()` on success (panel and installer alike): it moves `cron_runs.last_run_at` of `update_search_index` back by the job's interval (`LEAST`, so never forward), and the dispatcher's next tick runs it. No new job; a run already holding the lock writes its own time at the end, so the request is lost then. Publish and moving to the bin do not trigger it; the live filter covers the bin.

## How is a result labelled?
`pg_search_item_type()`: `file` (no page), `page` (url is the page's own encoded name), `record` (any other address the page shows: `?r=`, a pretty record address, `?id=` events). The widget reads each row with its page's name and title (`LEFT JOIN page AS sp`); a record's `__result_type_label` is its detail page's title (the template's "Topic", "Guide"), else `lang('Record')`; a file says `lang('File')`. `__result_type` is `record`/`file` for them. The legacy page shows no type.

## What was left of #3552?
Items 1 ("Guide" Kılavuz/Rehber), 2 (page addresses `sifre-…` against "Parola" titles; `core/panel-turkish-terms.md` decides the panel word, not page slugs) and 4 (`lang` of untranslated design text) need a terminology or product decision; not done.

## Tests?
`tests/site_search_query_test.php`: the term helper (operators, phrases, Turkish letters, empty), mode and score, the live SQL builder, item type, source checks of both call sites and the template install. Nothing here ran against MySQL in CI.
