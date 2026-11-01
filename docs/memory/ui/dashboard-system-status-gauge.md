---
name: dashboard-system-status-gauge
description: The System Status gauge, check chips and Release Notes dialog shared by dashboard widget 2 and the new dashboard's System Status card: functions, what stays byte for byte, the fixtures test
module: health_gauge
---

## Where is the System Status gauge drawn from?
- `pg_health_gauge($score)` in `includes/fn/health_gauge.php`: the `.pg-health` block (colour band, gradient laid along the arc, pixel matrix and its clip, groove, glow layers, readout) and the script that pauses an off-screen dial. Owner (2026-11-01): the new dashboard keeps this gauge, not the design's dot half ring.
- `pg_system_status_chips($checks, $role)`: one chip per check, sorted fail, warn, priority, rest; detail rows open under the chip; `software_repair.php` is dropped for whoever that screen refuses (`notification-links.md`). `pg_system_status_detail_row()` draws a detail row for the chips and for widget 2's job panels.
- `pg_changelog_dialog()` (`includes/fn/system_status.php`): the Release Notes dialog and its header button, built once by `welcome.php` and by the new card ("Details").

## What keeps widget 2 the same?
- The code moved as it was (#4756). `tests/dashboard_v2_status_test.php` compares the functions with `tests/fixtures/health_gauge/` (gauge at 0, 44, 100; chips for roles 0 and 2), written by widget 2's own code before the move. Change the gauge on purpose: write the fixtures again and say so in the PR.
- The SVG ids (`pg_health_gradient`, `pg_health_cell_clip`, …) are fixed, so one page draws one gauge; the two dashboards never share a page.

## How does the new dashboard's card differ?
- `includes/dashboard/v2/page_status.php`: role < 3 as widget 2; every check is a chip, the jobs too (the card has no jobs column; a job chip links where its widget 2 line does); its own popover binding on the card (`.status-popover`, hover and focus).
- Look: `pg_dashboard_v2_look()` reads the person's `look` JSON over the site's Appearance values; `main#content` takes `data-widget-theme`, `data-panel-backdrop` (the backend.src.css backdrop rules apply as is) and `pg-dash-compact` / `pg-dash-calm`. Lights off pauses the gauge and hides its bloom and glow layers.
