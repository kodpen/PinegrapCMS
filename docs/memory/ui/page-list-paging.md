---
name: page-list-paging
description: The pages list (view_pages.php) paged on the server: views, folder access in the query, the style column worked out per folder, search, stale SEO scores per page of rows (issues #6076, #6088)
module: page_list
---

## Why is the pages list paged on the server?
- It printed every page (299 rows, 1.09 MB HTML, ~3 KB a row with two SEO links) while DataTables showed 100 (#6076). The archive view (1,508 rows) ran 4,592 queries: `get_style()` walked the folder chain and the style name was read once per row whose style comes from its folder (#6088).
- Now the screen draws the toolbar and headings only; the `page_list_rows` action (`includes/panel/lists.php`) answers one page.

## Where does the list live?
- `includes/fn/page_list.php`: the views (`pg_page_list_filters()`: label, description, condition, access type, joins for the RSS and custom form views; SEO views only once their columns exist), `pg_page_list_where()`, `pg_page_list_sort_columns()`, `pg_page_list_view()`, the style cells, one row, the page read.
- An unknown filter, or an SEO view not installed yet, is `default` (All My Pages).
- Sort stays the heading links and the session (`pg_list_state('view_pages', …)`); the page id is the last sort level.

## How are folder access and the access views in the query?
- A basic user gets `page.page_folder IN (…)` from `get_folders_that_user_has_access_to()`; an access view (public, guest, …, the site map and orphan views) the folders whose `get_access_control_type()` matches, asked once per distinct `page.page_folder`. Both together intersect; none is `(1 = 0)`.

## How is the style column worked out?
- Own style: the joined `style.style_name`. Folder style: `pg_page_list_folder_styles()` walks one read of the folder table in memory (desktop `folder_style`, mobile `mobile_style_id`, as `get_style()`), only when a row on the page needs it; the names of a page of rows come in one `IN (…)` query. A mobile page with no mobile style shows the desktop one in red. Preview styles ([P]) are one query per page of rows.

## What does the search box find?
- Page name, folder name, own style name, last modifier, form name (custom form view), page type by its label. A style inherited from the folder ("Default: …") is not searched.

## What about stale SEO scores?
- The stale rows of the drawn page are scored before they are drawn (`pg_seo_recalculate('page', ids, 3)`), not every stale row of the view.

Related: docs/memory/ui/server-paged-lists.md
