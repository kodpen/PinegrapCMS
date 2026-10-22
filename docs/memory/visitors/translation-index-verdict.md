---
name: translation-index-verdict
description: One rule for a translated page's robots meta, its hreflang alternates and sitemap.xml under the "reviewed" index policy; no kept answer means not offered; what drops the answer; purge
---

## Which decision do sitemap.xml and the hreflang alternates follow?
- `pg_tr_index_decide($policy, $verdict)` (`includes/translate/seo.php`): `none` never, `all` always, `reviewed` only when the kept answer is `1`.
- The answer is produced by the page itself: `pg_tr_render_finalize()` (`render.php`) writes the robots meta, then keeps what the meta says (`pg_tr_robots_says_noindex()`, so `page.noindex` counts too) in `page_translations.indexable` (`pg_tr_page_verdict_keep()`, schema 2026.4.9).
- Readers: `pg_tr_indexable()` for sitemap.xml and for the alternates of the other languages on any page; the drawn language names itself by the value `pg_tr_page_verdict_keep()` returns. Same function, same answer everywhere.

## What happens when no answer is kept?
- Not offered: the address is left out of sitemap.xml and out of every page's alternates.
- Before PR #1176 the fallback counted the page's own texts (`pg_tr_index_stats()`, removed). The robots meta also weighs shared components, menus, list records, the catalog and data values, so the two disagreed: kodpen.com listed `/en/` while `/en/` said `noindex, follow`, and `/en/404` (a 404, never kept) is listed the same way.
- No answer: an address below a page, one with a query string, a non-200 answer, an un-upgraded schema, and any page not drawn in the language since its answer was dropped.

## How does the home page map?
- Canonical of the home page is `scheme://host` + `PATH`. `pg_tr_page_id_of_path()` maps the root (with or without the closing slash, also for a subfolder install) to `pg_tr_page_index()['home']`; `/en/` is never passed (the source address is). Tested in `tests/translate_index_test.php`.

## What drops a kept answer?
- `pg_tr_invalidate_pages($language, $page_id, $verdicts = true)` (`store.php`) deletes body and answer.
- With `$verdicts = false` a `1` stays and only the body goes; `0` and empty rows are deleted. Used where a write can only let pages be indexed: `pg_tr_write_keeps_verdicts()` (a reviewed translation, or the first translation of a text: a page answering `1` cannot have drawn an untranslated text), `pg_tr_set_status('reviewed')`, `pg_tr_review_all()`. Overwriting a machine translation, forcing over a reviewed one, back to machine, clearing, emptying a box and the API's `pending` drop answers.
- Page save: `pg_designer_save_page()` calls `pg_tr_owner_saved('page', id)` (`includes/fn/translate.php`). A shared component edit in the editor reaches every page that shows it as a carried save, so those pages drop too.
- Panel shared component `update` / `content_update`: `pg_tr_owner_saved('shared', id)` drops the pages whose `translation_uses` `page_ref` rows name it (as of the last extraction).
- `pg_purge_caches()` drops every row ("Translated pages (n)").

## What is not hooked, and why no ageing?
- A new form record in a list, a product change, a menu edit, legacy page screens: the answer can stay `1` while the page now says noindex. It is corrected by the next draw of that page in the language, which is also the crawl that would read the noindex.
- Ageing (`rendered_at` older than N hours = no answer) was rejected: `rendered_at` is written with the body, not with the answer, so keeping it fresh needs a write per visit, and with "no answer = not offered" every page's alternates would drop out periodically.

## Known trade-off
- After a drop the language address is missing from sitemap.xml and the alternates until somebody (a visitor or a crawler following the language switcher) opens it. Purging caches does this for the whole site.
- `robots.txt`-blocked pages: see docs/memory/visitors/page-noindex-switch.md (decided, unchanged).

Related: docs/memory/visitors/page-noindex-switch.md, docs/memory/platform/language-address-prefix.md
