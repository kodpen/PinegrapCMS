---
name: translation-index-verdict
description: One rule for a translated page's robots meta, hreflang alternates and sitemap.xml under the "reviewed" policy; no kept answer means not offered; what drops or keeps the answer
---

## Which decision do sitemap.xml and the alternates follow?
- `pg_tr_index_decide($policy, $verdict)` (`includes/translate/seo.php`): `none` never, `all` always, `reviewed` only when the kept answer is `1`.
- The page produces the answer: `pg_tr_render_finalize()` writes the robots meta and keeps what it says (`pg_tr_robots_says_noindex()`, so `page.noindex` counts) in `page_translations.indexable` (`pg_tr_page_verdict_keep()`, schema 2026.4.9).
- Readers: `pg_tr_indexable()` (sitemap.xml, other languages' alternates); the drawn language uses the value `pg_tr_page_verdict_keep()` returns.

## What if no answer is kept?
- Not offered: out of sitemap.xml and every page's alternates.
- Before PR #1176 the fallback counted only the page's own texts (`pg_tr_index_stats()`, removed) while the robots meta weighs components, menus, list records, catalog and data values: kodpen.com listed `/en/` while it said `noindex, follow`; `/en/404` (never kept) was listed too.
- No answer: an address below a page, a query string, a non-200 answer, an un-upgraded schema, a page not drawn since its answer was dropped.

## How does the home page map?
- Its canonical is `scheme://host` + `PATH`. `pg_tr_page_id_of_path()` maps the root (with or without the slash, also in a subfolder) to `pg_tr_page_index()['home']`. Tested in `tests/translate_index_test.php`.

## What drops a kept answer?
- `pg_tr_invalidate_pages($language, $page_id, $verdicts = true)` (`store.php`, statements from `pg_tr_invalidate_sql()`) deletes body and answer. With `$verdicts = false` a `1` keeps its row, body emptied; `0` and empty rows are deleted.
- Keeping: `pg_tr_write_keeps_verdicts()` (a reviewed translation, or a text's first one: a page answering `1` drew no untranslated text), `pg_tr_set_status('reviewed')`, `pg_tr_review_all()`, the rules check below.
- Dropping: overwriting a machine translation, forcing over a reviewed one, back to machine, clearing, an emptied box, the API's `pending`.
- Page save: `pg_designer_save_page()` -> `pg_tr_owner_saved('page', id)` (`includes/fn/translate.php`); an editor edit of a shared component reaches its pages as carried saves.
- Panel shared component `update` / `content_update` -> `pg_tr_owner_saved('shared', id)`: pages whose `page_ref` rows name it.
- `pg_purge_caches()` drops every row ("Translated pages (n)").

## Why are answers kept when the drawing rules change?
- `pg_tr_render_rules_check()` (a new `pg_tr_render_rules()`, e.g. 2026.11.0 in #1171) drops bodies only. Dropping answers too would take every language address out of sitemap.xml and the alternates until each page is visited in each language.
- An old `1` is corrected by the page's own robots meta on its next draw and search engines follow that meta (a soft error); an old `0` answers like none.

## What is not hooked, and why no ageing?
- A new list record, a product or menu change, legacy page screens: a `1` can stay while the page says noindex, until its next draw in the language (the crawl that would read it).
- Ageing by `rendered_at` was rejected: it is written with the body, not the answer, and would drop alternates periodically.

## Known trade-off
- After a drop the address is missing until somebody (a visitor, a crawler following the switcher) opens it; a purge does this site-wide.
- `robots.txt`-blocked pages: docs/memory/visitors/page-noindex-switch.md (decided).

Related: docs/memory/visitors/page-noindex-switch.md, docs/memory/platform/language-address-prefix.md
