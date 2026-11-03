---
name: page-list-paging
description: The pages list (view_pages.php) on the list table driver (paged since #6114, issues #6076 #6088): views, source, bulk actions by selection, folder access in SQL, inherited styles
module: page_list
---

## Where does the list live?
- `includes/fn/page_list.php`: the views (`pg_page_list_filters()`: label = heading, description, condition, the access type a view keeps to, the RSS and custom form joins), the view (`pg_page_list_view()`: sort columns, device type, theme preview) and the driver source (`pg_page_list_source($user, {filter})`, list `view_pages`): columns (`pg_page_list_columns()`, name first, actions last; style unsortable while another theme is previewed; impact sorts by `pg_seo_impact_sql()`, the expression without its alias, because the keyset compares it in WHERE), rows keyed by column (`pg_page_list_rows()`).
- The source is the gate (role ≤ 3, a basic user needs `no_acl_check()`); the rows come from `list_table_rows` (`ui/list-table-driver.md`). The old DataTables action `page_list_rows` is gone.
- `view_pages.php` prints the toolbar (search box `data-pg-list-search="view_pages"`, the desktop/mobile style toggle, sort menu, view), the SEO summary strip and the first 100 rows; the name links to the editor (`data-pg-open`).

## How do the bulk actions find their pages?
- Modify Selected and Delete Selected sit in the selection bar and post `selection` (the driver's payload) and `list_params` to `edit_pages.php`, which reads the ids with `pg_page_list_selected_ids()`: the list's own query and rights, so Ctrl+A acts on the whole view (search included, exceptions left out), not only the rows loaded. Before, they posted the ticked `pages[]` of the drawn page.
- The page table has no creation time, so the source has no `created`: a whole-view selection is not held to its moment.

## Why was it changed?
- #6076: every page was printed (~3 KB a row, 1.09 MB HTML for 299 pages) and DataTables showed 100.
- #6088: a page without its own style called `get_style()` (one query per folder level) and read the style name per row; the archive view ran 4,592 queries.

## How are folder access and access-type views done?
- In SQL: `pg_page_list_folder_ids()` gives the folders a basic user can edit and/or whose `get_access_control_type()` matches the view (public, private…); the condition is `page.page_folder IN (…)`, `(1 = 0)` when none. `null` means no restriction (manager and above, no access-type view). Before, these were filtered row by row in PHP.

## How is the style column built?
- `pg_page_list_folder_styles()` walks the folder chain in memory (as `get_style()` does) for desktop and mobile from one `SELECT … FROM folder` per request; names come from one `IN (…)` query per page window. Mobile without a mobile style falls back to the desktop one in red. A previewed theme's styles are read with one `preview_styles` query per window.

## Where did the lazy SEO recalculation go?
- The screen recalculates up to 200 stale pages of the view (last changed first, 3 s budget) before the list fetches, as the products screen does; the action only reads.
