---
name: translation-index-verdict
description: The "reviewed" policy's one rule for a translated page's robots meta, alternates and sitemap.xml: only the page's own words count; no kept answer means not offered; what drops or keeps it
---

## What does the verdict count?
- Only the page's own words (owner decision 2026-11-09, `workflow/decisions-made-product-38.md`): its texts, title and description, shared components, menus, custom HTML blocks, and on a record view the record it shows (`pg_tr_record_fields($fields, true)`, list rows `'own'`).
- `pg_tr_render_counter()` keeps them in `total` / `hits` / `machine`. A list's records, `pg_tr_db_text()` swaps (`pg_tr_render_db_texts()`), products and `pg_tr_data_text()` (search results, recent comments' titles, tag cloud) count in `data_*` under `pg_tr_render_scope('data')`: drawn in the source language until translated, never noindex. `google` counts in both (attribution).
- `pg_tr_render_noindex()` makes the robots verdict; `pg_tr_render_counts_reviewed()` the cached body's flag (`page_translations.reviewed`), so a cached page answers the same. A custom HTML block's machine output is counted at its node (`pg_tr_render_custom_html()`), as before. Rules `2026.11.0-3`. Tests: `tests/translate_own_words_test.php`.

## Which decision do sitemap.xml and the alternates follow?
- `pg_tr_index_decide($policy, $verdict)` (`includes/translate/seo.php`): `none` never, `all` always, `reviewed` only when the kept answer is `1`.
- `pg_tr_render_finalize()` writes the robots meta and keeps what it says (`pg_tr_robots_says_noindex()`, so `page.noindex` counts) in `page_translations.indexable` (`pg_tr_page_verdict_keep()`, schema 2026.4.9). Readers: `pg_tr_indexable()` (sitemap.xml, other languages' alternates); the drawn language uses the value just kept.

## What if no answer is kept?
- Not offered: out of sitemap.xml and every page's alternates. Before PR #1176 a fallback counted the page's own texts and listed pages whose meta said noindex.
- No answer: an address below a page (a record's own page, so it is never offered), a query string, a non-200 answer, an un-upgraded schema, a page not drawn since its answer was dropped.

## How does the home page map?
- Canonical `scheme://host` + `PATH`; `pg_tr_page_id_of_path()` maps the root (with or without the slash, also in a subfolder) to `pg_tr_page_index()['home']` (`tests/translate_index_test.php`).

## What drops a kept answer?
- `pg_tr_invalidate_pages($language, $page_id, $verdicts = true)` (`store.php`, `pg_tr_invalidate_sql()`) deletes body and answer; with `$verdicts = false` a `1` keeps its row, body emptied; `0` and empty rows go.
- Keeping: a reviewed translation (`pg_tr_write_keeps_verdicts()`), `pg_tr_set_status('reviewed')`, `pg_tr_review_all()`, the rules check below.
- Dropping: any machine translation; back to machine, clearing, an emptied box, the API's `pending`.
- Settings > Languages save: bodies always; answers only when the source language or an index policy changed.
- Page save: `pg_tr_owner_saved('page', id)`; a shared component's `update` / `content_update`: `pg_tr_owner_saved('shared', id)` for the pages whose `page_ref` rows name it.
- `pg_purge_caches()`: every row.

## Why are answers kept when the drawing rules change?
- `pg_tr_render_rules_check()` (a new `pg_tr_render_rules()`) drops bodies only: dropping answers would take every language address out of sitemap.xml until each page is visited in each language. An old `1` is corrected by the page's robots meta on its next draw; an old `0` answers like none.

## What is not hooked, and why no ageing?
- A product or menu change, legacy page screens: a `1` can stay while the page says noindex, until its next draw in the language. A list record no longer weighs; a record save opens a translation job (`core/content-translation-record-save-jobs.md`).
- Ageing by `rendered_at` was rejected: written with the body, not the answer.

Related: docs/memory/visitors/page-noindex-switch.md, docs/memory/platform/language-address-prefix.md
