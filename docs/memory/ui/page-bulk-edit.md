---
name: page-bulk-edit
description: The two bulk page edits (Pages list Modify Selected, File Manager bulk page panel) share pg_page_bulk_decide()/apply(): style rule, sitemap by type and noindex, SEO stale mark, tag cloud
module: page_bulk
---

## Which screens edit many pages at once?
- The Pages list's Modify Selected (`edit_pages.php`, action `edit`) and the File Manager's bulk page panel (`pg_explorer_handle_pages_bulk_edit()`, `includes/explorer/handle_bulk_edit.php`). Both read each page with `pg_page_bulk_fetch()`, decide with `pg_page_bulk_decide()` and write with `pg_page_bulk_apply()` (`includes/fn/page_bulk.php`, loaded with `require_once` by the two callers, not by `functions.php`).

## What does the decision apply?
- Style: only role < 3 (as `edit_page.php`); a role 3 user is not shown the two style pick lists on `edit_pages.php` and a posted style is ignored. A page the visual editor opens (`pg_page_visual_design_sql()`: its own style is a visual design, or it has a tree) keeps its style; the screens say so with the key "The style was left alone on {var:1} of them, ...".
- Sitemap: turned on only for the types the page screen stores it for (`pg_page_bulk_sitemap_types()` = `pg_sitemap_page_types()` + form item view, calendar event view, catalog detail; the detail types' switch lists their records), off for any type, and always 0 while noindex is 1 (`pg_page_form_index_flags()`). nofollow is kept only while noindex is 1.
- SEO stale: `seo_analysis_current = 0` when folder, search, sitemap, noindex or nofollow actually change, or a style is written to the page (not to `preview_styles`). Comments do not mark it.
- Tag cloud: `update_tag_cloud_keywords_for_page()` when `page_search` flips.
- Unchanged values are not written.

## What did this fix?
- `edit_pages.php` let a role 3 user restyle pages, overwrote the style of visual-editor pages, never marked the SEO score stale (the Pages SEO column, weak-SEO filters and the dashboard brief showed the old score up to 7 days) and set sitemap = 1 on noindex pages. The File Manager panel wrote sitemap regardless of type and noindex, and skipped the tag cloud update when search was switched.
- `sitemap.xml` itself was never wrong: `get_sitemap_info()` leaves noindex pages out; the stored flag and the Pages list check mark were.

## Where is it tested?
- `tests/page_bulk_edit_test.php` (the decisions); `tests/audit_log_test.php` checks the File Manager reads the audit rows before `pg_page_bulk_apply()`.

Related: docs/memory/visitors/page-noindex-switch.md, docs/memory/visitors/sitemap-listed-pages.md
