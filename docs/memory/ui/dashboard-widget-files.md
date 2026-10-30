---
name: dashboard-widget-files
description: Adding a dashboard widget: includes/dashboard/widgets/widget_<id>.php and its function contract, the welcome.php registry entry, retired ids never reused, the refresh and solo lists, charts.
---

## Where does a widget's code go?
- One file per widget, `includes/dashboard/widgets/widget_<id>.php` (gate `PG_DASHBOARD_WIDGETS`, defined by `includes/dashboard/widgets.php`), defining `pg_dashboard_widget_<id>($request, $user)`.
- It returns `array('status' => 'success'|'error', 'message' => ..., 'data' => <card HTML>)` and never echoes or exits: `api.php`'s `get_widget_data` / `get_widgets_data` `respond()` with what the loader (`pg_dashboard_widget_run()`) returns, and a request loads only the widgets it asks for.
- The widget checks its own access: those actions are exempt from `api.php`'s role gate, and the registry's `show` only decides what the dashboard draws.
- Shared helpers (list row and section heading, headline with sparkline, eight-day counts, tile ink colour) are defined in `includes/dashboard/widgets.php`.

## What goes into welcome.php?
- An entry `$widget_registry[<id>]`: `title`, `icon`, `body` (the loading skeleton), `wide`, `show` (may this user see it). Position comes from the stored order; a widget the stored order does not know appears at the end, with no migration.
- Ids are permanent (dashboards store them): 6, 10, 11, 12, 13, 17, 19, 22, 24 and 26 are retired and never reused. `widget_22.php` still exists: widget 21 calls it for the Firewall card's threat half. Id 1 was reused for the sales map on purpose (`dashboard-sales-map-panel.md`).
- A card whose content moves minute to minute goes into the `refresh` list of the dashboard script in `welcome.php`; a slow card goes into `solo` (asked for beside the batch).

## What about charts?
- Markup holding charts is replaced only after `destroy_widget_charts()` (`chart-destroy-before-markup-change.md`).

## In which unit does a widget print an inline text size?
- In rem at the 16 px base (`10px` → `.625rem`, `17px` → `1.0625rem`), never px; `tests/dashboard_text_size_test.php` pins it.
- The panel sets no root font size, so 1rem is the browser's text size. The card's grid track (`--pg-widget-min: 15.5rem`), its square shape and its title follow it; px text inside stayed at 9–22 px in a card twice the size (#PR).
- The shared `.pg-row`/`.pg-vs`/`.pg-eg` rules in `backend.src.css` still use px; converting them is separate work.
