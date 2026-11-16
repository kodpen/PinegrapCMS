---
name: visitors-clicks-section
description: The Clicks and scrolling section of the Visitors overview (includes/visitors/clicks.php, CM3): the switch-off empty state, the pages list, a page's own view, the device filter, labels as text
---

## What does the section show?
- (#9913) `includes/visitors/clicks.php`, after Page Speed in `pg_visitors_sections()`, icon `bi-hand-index`. Figures only through `includes/fn/page_clicks.php` (`visitors/page-clicks.md`).
- The list: the period's pages with counted views, at most 20 (`PG_VISITORS_CLICKS_PAGES`), most viewed first: views, the tenth half the views reach, the most clicked element and its share. Ten rows, the rest behind "Show all". Each page's top element is `pg_page_clicks_page(…, 1)`: one read a page, no query of its own.
- A page's view (`clicks_page=<id>`): "All pages" back link, the page's name, tiles (views, half reach, elements clicked), the elements table (part, kind, label, address, clicks, share) with "Other elements" as one last line, and one bar a tenth for the share of views reaching it.
- The device filter `clicks_device` (all, desktop, mobile, tablet) sits at the heading's end and is kept by both views; links keep the period (`pg_visitors_clicks_url()`).

## What while the switch is off or nothing is there?
- Off (C17): two lines, what would be counted and what is never kept, then for a manager (role up to 2, who opens Site Settings) "Turn on in Site Settings" to `pg_settings_link('features', 'pgset-features')`; anyone else reads that a manager can turn it on. The section is never hidden.
- On before the update has made `page_click_daily`: the line "The database has not been upgraded yet." On with no rows in the period: "No page views were counted in this period yet."
- The switch is read through `pg_page_clicks_switch_on()` (`visitors/page-clicks.md`).

## How are visitors' words printed?
- Labels and addresses come from browsers: only as text cells of `pg_visitors_table()` (escaped there), never as markup; the page name through `h()` (C7, `tests/visitors_clicks_test.php`).

## What does the API give?
- `GET /reports/page-clicks` (`includes/api/resources/page_clicks.php`, scope `visitors:read`): the same figures; see `api/api-visitor-reports.md`.
