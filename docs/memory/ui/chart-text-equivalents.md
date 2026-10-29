---
name: chart-text-equivalents
description: Text equivalents for canvas-only charts (orders Top 5 products, Search Console by day, dashboard Daily Traffic); Sales Map role=img on the map layer, not the Filter menu box
---

## Which charts carry their figures as text, and how?
- Orders list Top 5 products: the canvas name is `Top 5 products — <name>: <qty>, …` (`$top_summary` in `view_orders.php`, quantities through `pg_format_number(…, 0)`), like the Payment methods doughnut's `$payment_summary`.
- Search Console By day: a `visually-hidden` table `#search_console_days` (caption, Date / Clicks / Impressions, full date as `get_date_format_code() . '/Y'`) follows the chart; the canvas is `aria-hidden`, so the name is not read twice.
- Dashboard Visitors (widget 5): `#vs5_canvas` is `aria-hidden`; `table()` rebuilds the visually-hidden `#vs5_table` from `P[mode]` (caption eyebrow · New Visitors, a row per hour/day/month from `P[mode].axis`, one column per series, `fmtN`, a dash for a point not reached yet) on load and on every period switch. Built with `textContent`, so labels need no escaping.

## Why is role=img not on #pg_smap_canvas?
- Children of `role="img"` are presentational, so the Filter button (`#pg_smap_menu`, period and map switch) inside it was hidden from screen readers (axe nested-interactive). The script now sets `role="img"` and `aria-label` (`data.label`, Sales Map) on each `.pg-smap-layer` it appends. The tooltip is `aria-hidden`: it repeats the list beside the map and only a mouse opens it. The `.pg-smap-canvas` class and its svg/path selectors are unchanged.

## What pins it, and what was not checked?
- `tests/chart_text_equivalents_test.php` (source pins). Not rendered in a browser or a screen reader; the inline widget scripts were syntax-checked with `node --check`.

Related: docs/memory/ui/status-chart-colour-words.md, docs/memory/ui/dashboard-sales-map-panel.md
