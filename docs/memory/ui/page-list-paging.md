---
name: page-list-paging
description: The pages list (view_pages.php) paged on the server (PR #6114, issues #6076 #6088): views, folder access in SQL, inherited styles from one folder read, SEO refresh before the fetch
module: page_list
---

## Where does the list live?
- `includes/fn/page_list.php`: the views (`pg_page_list_filters()`: label = heading, description, condition, the access type a view keeps to, the RSS and custom form joins), the view (`pg_page_list_view()`: sort columns, `ORDER BY` with `page.page_id` last, device type, theme preview), one page (`pg_page_list_page()`) and its rows.
- Action `page_list_rows` (`includes/panel/lists.php`): the screen's gate (role ≤ 3, a basic user needs `no_acl_check()`), filter from `data-pg-list-params`, sort from the session.
- `view_pages.php` keeps the toolbar, the SEO summary strip, the Refresh SEO Scores link and the headings; the table body is empty.

## Why was it changed?
- #6076: every page was printed (~3 KB a row, 1.09 MB HTML for 299 pages) and DataTables showed 100.
- #6088: a page without its own style called `get_style()` (one query per folder level) and read the style name per row; the archive view ran 4,592 queries.

## How are folder access and access-type views done?
- In SQL: `pg_page_list_folder_ids()` gives the folders a basic user can edit and/or whose `get_access_control_type()` matches the view (public, private…); the condition is `page.page_folder IN (…)`, `(1 = 0)` when none. `null` means no restriction (manager and above, no access-type view). Before, these were filtered row by row in PHP.

## How is the style column built?
- `pg_page_list_folder_styles()` walks the folder chain in memory (as `get_style()` does) for desktop and mobile from one `SELECT … FROM folder` per request; names come from one `IN (…)` query per page window. Mobile without a mobile style falls back to the desktop one in red. A previewed theme's styles are read with one `preview_styles` query per window.

## Where did the lazy SEO recalculation go?
- The screen recalculates up to 200 stale pages of the view (last changed first, 3 s budget) before the list fetches, as the products screen does; the action only reads.
