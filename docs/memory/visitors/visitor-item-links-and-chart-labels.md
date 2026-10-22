---
name: visitor-item-links-and-chart-labels
description: How visitor report rows build item URLs, why chart and tooltip numbers differ, and why the home page is stored under many names.
---

## How is an item link built in the visitor report?
The recorded `page_name` is the template that SHOWS the item, not the item's address. Used alone the panel links to something like `/blog-post`. `pg_visitor_label_content_rows()` builds three shapes:
- Product / group: `{recorded page}/{address_name}` (pattern from `get_catalog.php`).
- Form, pretty URLs on: `{list page}/{address_name}` (`get_form_list_view.php`).
- Form, pretty URLs off: `{recorded page}?r={reference_code}` (`get_form_list_view.php`).
- With pretty URLs the recorded page CANNOT be used: `get_page.php` turns the page name into the form item view, but the link must target the LIST page (`/blog/...`, not `/blog-post/...`).
- "Pretty URLs on" is two conditions: `custom_form_pages.pretty_urls = 1` AND at least one `form_fields.rss_field = 'title'` field (the address name derives from it). Same rule as `check_if_pretty_urls_are_enabled()`, but one query per result set, not per row.

## Why can chart and tooltip disagree?
- `visitor_stats_hourly.new_visitors` = sessions that STARTED in that hour. `visitor_content_hourly.views` = views made in that hour. A visitor entering at 14:00 and reading 10 pages at 15:00 leaves 0 sessions + 10 views at 15:00.
- Both are right; side by side without labels they look contradictory (reported defect: tooltip "article - 10", chart 0). The widget tooltip prints both numbers with their names.

## Why is the home page stored under many names?
- `''`, `'/'`, `'index.php'`, legacy `'example.com/'` and the page's own name are all the same page.
- Live tracking is unaffected (`get_page.php` resolves the home page before tracking and writes the real name). Only the BACKFILL must merge them, otherwise the home page shows as two rows and its traffic splits. `pg_home_page_group_expression()` did this; since rollup groups by `page_id` it is no longer needed.

Related: docs/memory/visitors/visitor-rollup-tables.md
