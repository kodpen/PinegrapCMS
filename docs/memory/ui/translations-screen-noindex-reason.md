---
name: translations-screen-noindex-reason
description: Translations screen: a page's search verdict in the page list, the "Why?" list of texts keeping it out (blocking filter) and "Keep as in the source and approve"
---

## Why did a page read 160/160 and still get noindex?
The page list counter (`pg_tr_page_stats()`) counts only the page's own texts (`owner_type` `page`, `page_seo`). Under the `reviewed` index policy the robots decision counts every text drawn on the page (`pg_tr_render_finalize()`): shared components and widget settings, menus, the records a list widget includes (`form_records`). On kodpen.com (2026-10-21) the 112 pending texts of `/en/` were records of the form a list widget showed on the home page.

## What does the page list show now?
For the selected language, only when its `index_policy` is `reviewed` and `pg_tr_page_verdict_ready()`: an icon after the counter from `pg_tr_page_verdicts($language)` (one query): search icon = indexed, eye-slash = kept out, hourglass = not drawn since the translations last changed (`pg_tr_invalidate_pages()` drops the row on every write). `pg_tr_screen_verdict_state()` maps it.

## Where is the "Why?"?
Page items are links, so nothing clickable sits inside them. Selecting a page shows a line above its texts with its verdict; "Why?" (or, for an unchecked page, "Show the texts that would keep it out") opens the same list with `filter=blocking`. The chip "Keeping pages out of search" is the same filter for any scope.

## What does the blocking filter list?
Texts with no translation, or a translation that is not reviewed and differs from the source (`pg_tr_screen_blocking_sql()`, the test of `pg_tr_render_text()` / `pg_tr_index_stats()`). With a page selected the owners are `pg_tr_page_owners()`: page, page_seo and the `page_ref` rows (shared, nested shared, menu, form_records). Only in this view the heavy part runs: `pg_tr_screen_reasons()` counts per owner, `pg_tr_screen_reason_lines()` orders them (page, title/description, then most texts first) and each row gets a badge naming its owner (`pg_tr_screen_string_owners()`).

## Is the list exact?
No, and the screen says so: it is the texts as last extracted. A widget may draw texts that are not extracted (products, form fields), a cached body carries the flags it was drawn with, and the decision is made again on the next draw. An empty list on a noindex page tells the operator to update the page and open it once.

## What does "Keep as in the source and approve" do?
Rows that are not reviewed get a checkbox; "Select all on this page" and the toolbar button above the rows open a dialog with the count. `translations_action.php` action `accept_source` (JSON, session token, manager only - the page-only gate allows just node/save/status) validates the ids with `pg_tr_screen_string_ids()` (positive ints, unique, over 500 refused whole) and calls `pg_tr_accept_source()`: for each string not already reviewed, `pg_tr_save_translation($id, $lang, sanitized source, 'manual', 'reviewed', $user, 0, true)` - the same write and engine as a translation typed by hand. A reviewed translation is left as it is and counted as `kept`. A machine translation that differs is replaced. The write drops the stored verdicts of the language, so pages show "not checked" until they are drawn again.

## Where are the helpers and tests?
`includes/translate/screen.php` (loaded by `pg_tr_load()`); `tests/translations_screen_test.php` Added in PR #1175 (issue #1096).
