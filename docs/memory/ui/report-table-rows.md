---
name: report-table-rows
description: Order, visitor and shipping report tables: detail rows and product names as links, percent figures through '{var:1}%' (Search Console too), named yes marks
---

## How are the report percentages printed?
- Every percent cell of the order and visitor reports (the Grand Total's 100 included) and Search Console's `search_console_percent()` print `h(lang(array('string' => '{var:1}%', 'vars' => pg_format_number($x, 2))))`: Turkish gives `%45,23`, English `45.23%`. Before, `pg_format_number($x, 2) . '%'` showed `45,23%` on a Turkish panel, unlike the dashboard and ERP (`dashboard-keyboard.md`).
- Search Console's change badge keeps the `+` / `-` outside the template and passes `abs()` (`+%12`), as `widget_31.php` does.
- The ` %` column headers stay as they are.

## How do the detail rows open an order, a visitor or a product?
- With Show Detail on, the order number and the visitor id are `<a href>` links: `view_order.php?id=N&send_to=` + `urlencode()` of the report URL, and `view_visitor.php?id=N&visitor_report_id=…`. The href is built raw and printed with `h()`, not from an `escape_javascript()` string.
- The rows were `<tr class="pointer" onclick="window.location.href=…">` before, so no keyboard or screen reader could open an order or a visit from a report. The shipping report's product name cell had the same mouse-only onclick; it holds a link to `edit_product.php?id=N` now.

## How are the visitor detail marks drawn?
- Form submitted, Order created, Retrieved, Checked out and Completed print `$output_yes_mark`: a `task_alt` icon with `role="img"` and `aria-label` Yes (`list-and-table-screens.md`), not a bare `*` read as "star".

## What was left alone?
- The inline row colours (`#969696` and the rest): Bootstrap 6's `.table > :not(caption) > * > *` sets each cell's colour, so they have no visible effect.

Tests: `tests/report_tables_links_test.php`, `tests/report_screens_bs6_test.php`.

Related: docs/memory/ui/report-screen-controls.md, docs/memory/ui/dashboard-keyboard.md
