---
name: visitors-content
description: The content section of visitors.php: most viewed pages, products, groups and form entries from the content rollup, landing pages with the home page folded, each with the earlier figure
---

## What does the section show?
- `includes/visitors/content.php`, `pg_visitors_section_content()`, anchor `#content`.
- Up to ten rows each: pages, products and product groups (only with `ECOMMERCE`), form entries; a kind with no view in the period is left out. Name linked to the site address, views and, with comparing on, the earlier views and the change.
- Landing pages, full width: visits, forms submitted, orders completed (only with `ECOMMERCE`), the earlier visits and the change; every name of the home page is one "Homepage" row.
- Nothing at all in the period: one empty line under the heading.

## Where do the figures come from?
- Views: `visitor_content_hourly`, read by `pg_visitors_content_read()`. A page is one row per `page_id` (per recorded name when 0, a deleted page); an item one row per `item_id`, whichever page showed it. Labels and links: `pg_visitor_label_content_rows()`, as the dashboard's content widget.
- The window is cut by hour (`pg_visitors_content_window()`): the earlier period of a period ending today stops at the same hour, as the totals do.
- Landing pages: `pg_visitor_breakdown('landing_page_name', ...)` 200 deep, folded by `pg_visitors_content_landing_rows()` (home aliases `''`, `/`, `index.php`, `example.com/` and every `page_home = 'yes'` name; the rest without regard to case), then cut to ten. Forms is `forms_submitted`, orders `orders_completed`.
- The earlier list is read 200 deep so a row new in the top still finds its figure; rows meet by item, page id or name (`pg_visitors_content_key()`).

## Why do views and visits not add up?
- Views are counted per page shown (rollup), landing visits per visit that began there (summary or rows): a landing page's visits are not its views.
- Rows backfilled from before the rollup carry no item; their views sit on the landing page (`visitor-item-links-and-chart-labels.md`).

## How is it tested?
- `tests/visitors_content_test.php`: the hour cut, the kinds with commerce off, the row key, the earlier views, the home fold with Turkish names, escaping and the optional columns.
