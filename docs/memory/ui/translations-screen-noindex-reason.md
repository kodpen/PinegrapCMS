---
name: translations-screen-noindex-reason
description: Translations screen: a page's search verdict in the page list, its own-noindex state, the "Why?" list of texts keeping it out (blocking filter) and "Keep as in the source and approve"
---

## Why did a page read 160/160 and still get noindex?
The page list counter (`pg_tr_page_stats()`) counts only the page's own texts (`page`, `page_seo`). Under the `reviewed` index policy the robots decision counts every text drawn on the page (`pg_tr_render_finalize()`): shared components, menus, the records a list widget includes (`form_records`). On kodpen.com (2026-10-21) the 112 pending texts of `/en/` were records a list widget showed on the home page.

## What does the page list show?
Only for a language whose `index_policy` is `reviewed` and when `pg_tr_page_verdict_ready()`: an icon after the counter from `pg_tr_page_verdicts($language)` (one query), mapped by `pg_tr_screen_verdict_state()`: search = indexed, eye-slash = kept out, hourglass = not drawn since the translations changed (`pg_tr_invalidate_pages()` drops the row on every write), slash-circle = `own_noindex`.

## What does a page closed by its own setting show?
`own_noindex`: `pg_tr_screen_verdict_state($verdicts, $id, page.noindex === 1)` returns it before the stored verdict is read. The stored verdict is made from the translation counters only (`render.php` `$noindex`), while `page.noindex` reaches the robots meta separately (`pg_page_robots_content()`). Before the fix such a page stored 1 and read "In search results" while it served noindex; with a verdict taken from the final robots meta it would store 0, be blamed on translations, and an empty reason list sent the operator round "Update this page". The note now says the page's own setting keeps it out, with no "Why?" and no update advice; the reason list still shows texts that can be approved.

## Where is the "Why?"?
Page items are links, so nothing clickable sits inside them. Selecting a page shows a line above its texts; "Why?" (for an unchecked page "Show the texts that would keep it out") opens `filter=blocking`. The chip "Keeping pages out of search" is the same filter for any scope.

## What does the blocking filter list?
Texts with no translation, or one that is not reviewed and differs from the source (`pg_tr_screen_blocking_sql()`, the test of `pg_tr_render_text()` / `pg_tr_index_stats()`). With a page selected the owners are `pg_tr_page_owners()`. Only in this view `pg_tr_screen_reasons()` counts per owner, `pg_tr_screen_reason_lines()` orders them (page, title/description, then most texts first) and each row gets an owner badge (`pg_tr_screen_string_owners()`).

## Is the list exact?
No, and the screen says so: it is the texts as last extracted. A widget may draw texts that are not extracted (products, form fields), and the decision is made again on the next draw.

## What does "Keep as in the source and approve" do?
Unreviewed rows get a checkbox and a dialog with the count. `translations_action.php` `accept_source` (JSON, session token, manager only) validates ids with `pg_tr_screen_string_ids()` (positive, unique, over 500 refused whole) and calls `pg_tr_accept_source()`: `pg_tr_save_translation(..., 'manual', 'reviewed', ...)` per text not already reviewed (`kept`). The text comes from `pg_tr_accept_source_text()`: the sanitized source only when `pg_tr_sanitize_incoming()` equals `pg_tr_normalize()` of it. A plain source is stored decoded and printed through `h()`, so the sanitizer's `strip_tags()` would save `Size <M>` as `Size`, reviewed and never flagged again; such rows are skipped, counted as `changed` and named in the message. A differing machine translation is replaced. The write drops the language's stored verdicts.

## Where are the helpers and tests?
`includes/translate/screen.php` (loaded by `pg_tr_load()`), `tests/translations_screen_test.php`. PR #1175 (issue #1096).
