---
name: status-chart-colour-words
description: System Status chips and detail rows, the orders Payment methods doughnut and the Search Console day chart tell states and series apart by words, dashes and shapes, not colour alone
---

## What does a System Status chip show when the check has no value of its own?
- `pg_system_status()` fills the generic word (OK / Warning / Problem / Not applicable) and marks it `generic`. `widget_2.php` prints it in `.pg-check-value` on warn and fail chips, the ones worth looking at, and as a `visually-hidden` span on OK and info chips. Before, the word was dropped and several checks (Unknown Bot Filtering is `bi-robot` in every state) showed their state only by the chip tint.

## How does a detail row say its state?
- Chip and job panels draw rows through one closure, `$status_detail_row`. The 6px dot is `aria-hidden`; ok, warn and fail rows put OK / Warning / Problem before the time (`Problem · 3 hours ago`). Info rows carry no word, their time already says Never or Missing. This tells a stalled scheduled task from a healthy one, a required missing server rule from a recommended one, an operations error from a warning.

## How is the Payment methods doughnut read without colour?
- `view_orders.php` names the canvas `Payment methods — <method>: <count>, …` (`$payment_summary`, counts through `pg_format_number(…, 0)`), and the legend's `generateLabels` wraps Chart.js's doughnut default to add each count. The tooltip keeps `label: count`.

## How are Clicks and Impressions told apart on Search Console?
- Impressions is dashed (`borderDash: [6, 4]`) with `pointStyle: "rectRot"`, the legend uses point styles, and both y-axes carry the Clicks / Impressions titles. The current 7/28/90-day link has `aria-current="page"`.

## What pins it, and what was not checked?
- `tests/status_colour_words_test.php` (source pins). Nothing was rendered in a browser; backend.src.css was not touched.

Related: docs/memory/ui/dashboard-keyboard.md, docs/memory/ui/panel-accessible-names.md
