---
name: search-and-audits-gaps-steps
description: Steps SG1-SG3 of plans/search-and-audits-gaps.md: the three jobs on for a new install, parts inside an index item, close-out; claim as [plan:search-and-audits-gaps] SG<n>
---

## Which run side by side?
- SG1 and SG2 at once: `install/index.php` and `includes/fn/search_audits.php` against `update_search_index.php` and `includes/fn/site_search.php`. SG3 after both.
- S under 400 product lines, M about 1,000. Texts through `tr_add.php`; tests red first.

## Which steps are there?
- SG1 (S) A new install switches the three jobs on. `free`.
  - `install/index.php`, beside the starter defaults (after the WebP switch, under `get_starter_site_language($install_directory_path) !== ''`, both columns checked with `install_column_exists()`): `job_dispatch_enabled = 1` and `job_dispatch` = the stored list plus `update_search_index`, `seo_analyze_job` and `seo_a11y_job` in catalogue order (`pg_search_audits_switch_list()` over `pg_search_audits_dispatchable()`, or a pure helper on them), one install step naming them and Site Settings › Updates and maintenance › Cron Jobs. No upgrade step: a running site and a restored backup keep their switch; SEO scores stay off (the decision names three). Tests: the list (order, nothing doubled, names already there kept) and the wiring in the starter branch. Memory: `platform/starter-site-defaults.md` (which jobs a new site runs), a line in `platform/search-and-audits-screen.md`.

- SG2 (M) Parts inside an index item. `free`.
  - Parts: a page item's own render, then each calendar event, then each classic record; a record-list host's records, list by list in widget order, records by id. A part's key is its URL, known before its render. Pure rules beside `pg_search_index_begin()`: (a) the budget is checked after each part; out of time inside an item, the tick saves the item as paused at its last finished part, and the next tick resumes after it: nothing skipped, nothing named; (b) a tick that died inside a part: the next one skips that part only, keeps its row (`pg_search_index_keep_item()`), names it ("A run ended while reading it") and reads the rest; (c) died inside an item outside any part (a host's list queries): the item is skipped as now and keeps its rows with `UPDATE search_items SET timestamp = <started> WHERE page_id IN (…)`: the page's id for a page item (its row, events and classic records carry it), the lists' detail pages for a host (their records carry those); a file keeps its URL. A row kept one pass too long is acceptable, a live row swept is not.
  - The carry must match the place it is saved with (the files stage reads the linked names): checkpoint the cursor with its carry at pauses and every few seconds, and before each part write only a small marker naming it (the carry is large on a big site). A resumed item starts after the checkpoint and skips the part the marker names. A cursor written before this step reads as today. Delta runs keep no cursor and do not change. `pg_search_index_render()` and the per-record `try` stay; the Site Log stop line names the part.
  - Tests (`tests/search_index_resume_test.php`): pause and resume at a part, a death inside a part skips only it, a death outside parts keeps the item's rows by page id, an old cursor. Memory: a new `designer/search-index-item-parts.md` (both topic files are at 4 KB), linked from them.

- SG3 (S) Close-out. After SG1 and SG2.
  - What lasts goes to the topic files named above; both plan files are deleted; `php tools/memory_index.php`.
