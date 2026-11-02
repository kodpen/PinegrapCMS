---
name: dashboard-v2-chart-tips
description: New dashboard charts show each point's label and figure on hover and keyboard focus: pgDash.chartTips() in pg_dash.js, server texts from tips.php, which charts use it, keys
---

## What does a chart do on hover and focus?
- Owner rule (2026-11-01): every chart, ring, bar and sparkline shows its figure and label on hover and on keyboard focus. `pgDash.chartTips(target, texts, opts)` in `assets/js/pg_dash.js` makes the chart one tab stop. Moving the pointer over it, or pressing Left/Right/Home/End while it has focus, shows `texts[i]` in one floating `.pg-dash-tip` per page. Escape hides it.
- The box is `aria-hidden`. On focus and on each arrow key, the same text goes to a polite live region placed after the chart. The chart keeps its `role="img"` and its label (`<title>` or `aria-label`).
- Options: `parts` gives elements that are hovered one by one (ring arcs, stacked-bar parts); without it, the pointer's x picks the point. `at(i)` says where the box goes, in viewport pixels. `mark(i)` highlights point i: `pgDash.markBars(svg)` sets `data-active` on a bar; the line draws a thin vertical rule, because a stretched SVG (`preserveAspectRatio="none"`) would turn a dot into an oval.
- `draw()` hides the box before a card's markup is replaced, so it never outlives its chart.

## Where do the texts come from?
- From the server, already formatted: `pg_dashboard_v2_tips($labels, $values, $format, $compare, $compare_name)` in `includes/dashboard/v2/tips.php` gives "<label>: <figure>", plus " · <compare name>: <figure>" where the dashed line has a value. `pg_dashboard_v2_slot_labels()` labels hourly slots ("14:00") and daily slots (the site's date, read at the slot's noon). The card's data carries them as `tips`.
- The ring and the GA stacked bar join their own server texts in the script: "<part label>: <size or share>".
- Tests: `tests/dashboard_v2_tips_test.php`.

## Which charts use it?
- `visitors` line (visits per point, with yesterday / previous period / last year), `engagement` bars (n minutes ago), `firewall` bars (blocked, or "would be blocked" in monitor mode), `files` ring, `ga` sources bar (no longer `aria-hidden`: `role="img"` labelled by its heading).
- `pgDash.line()` and `pgDash.bars()` take the texts as their last argument. A later chart passes its own `tips` to them, or calls `pgDash.chartTips()` itself. The shop bars (N9) and the pipeline sparklines (N10a) do not pass tips yet.
