---
name: chart-text-equivalents
description: Text copies of canvas-only and tooltip-only figures: orders Top 5, Search Console by day, Daily Traffic, Sales Map table, seven-day bars; Sales Map role=img on the map layer
---

## Which charts carry their figures as text, and how?
- Orders list Top 5 products: the canvas name is `Top 5 products — <name>: <qty>, …` (`$top_summary` in `view_orders.php`, quantities through `pg_format_number(…, 0)`), like the Payment methods doughnut's `$payment_summary`.
- Search Console By day: a `visually-hidden` table `#search_console_days` (caption, Date / Clicks / Impressions, full date as `get_date_format_code() . '/Y'`) follows the chart; the canvas is `aria-hidden`, so the name is not read twice.
- Dashboard Visitors (widget 5): `#vs5_canvas` is `aria-hidden`; `table()` rebuilds the visually-hidden `#vs5_table` from `P[mode]` (caption eyebrow · New Visitors, a row per hour/day/month from `P[mode].axis`, one column per series, `fmtN`, a dash for a point not reached yet) on load and on every period switch. A series with `views`/`pages` (the day view) adds `<label> · Page Views` and `<label> · Most Visited` (name · count) columns, so the tooltip's afterLabel has no figure of its own. Built with `textContent`, so labels need no escaping; the hour page names are therefore sent unescaped through `json_encode(…, JSON_HEX_TAG | JSON_HEX_AMP)` (an `h()` there showed `Q&amp;A` in the canvas tooltip).
- Dashboard Sales Map (widget 1): each `data-smap-list` slot ends with a `visually-hidden` table (caption view · period; Country or Region, Total, Orders, Share of sales, Regions or Cities) of every place the map colours, built server-side in the tooltip loop. The list shows only the top eight regions, so on the World view a country's own figures were in the tooltip alone. The table has no links (an invisible focus stop) and sits inside the slot, so `show()` toggles it with the list.
- Seven-day bars (`pg_widget_bars($series, $label)`): the bars stay `aria-hidden`; a non-null `$label` adds a visually hidden `[$label: ]in 7 days N`. Activity filter tiles (`pg_widget_filter()` passes `''`) and the Search Console card (`'Clicks'`) use it; Google Analytics (widget 32) keeps its own day table and passes nothing. A filter tile's `count_text` (Activity: `{var:1} today`) is read in place of the bare count, which is `aria-hidden`.

## Why is role=img not on #pg_smap_canvas?
- Children of `role="img"` are presentational, so the Filter button (`#pg_smap_menu`, period and map switch) inside it was hidden from screen readers (axe nested-interactive). The script now sets `role="img"` and `aria-label` (`data.label`, Sales Map) on each `.pg-smap-layer` it appends. The tooltip is `aria-hidden` and only a mouse opens it; its figures are in the slot's hidden table. The `.pg-smap-canvas` class and its svg/path selectors are unchanged.

## What pins it, and what was not checked?
- `tests/chart_text_equivalents_test.php` and `tests/dashboard_chart_text_test.php` (source pins; the bars and filter tile rendered). Not rendered in a browser or a screen reader; the inline widget scripts were syntax-checked with `node --check`.

Related: docs/memory/ui/status-chart-colour-words.md, docs/memory/ui/dashboard-sales-map-panel.md
