---
name: dashboard-v2-edit-mode
description: New dashboard edit mode (#4856): presets.php, dashboard_layout_save and its clean-up, pg_dash_edit.js (bar, grip, drag, card tools and settings, greeting and status parts), what Cancel and Save do
---

## Where does the layout a person sees come from?
- `dashboard_layout.layout` when stored; with nothing stored, the first default preset (`pg_dashboard_v2_preset_first()`: Ecommerce with the shop on, else Corporate) through `pg_dashboard_v2_preset_cards()` in `includes/dashboard/v2/presets.php`. The five presets are one list there, cards written as `id:width`; their shortcuts, tiles and chips stay in `pg_dashboard_v2_greeting_preset()` (`page_greeting.php`).
- `pg_dashboard_v2_config()` (`page_grid.php`) sends the script `edit`: locked, preset, top, actions, tiles, every preset's drawn cards, the shortcuts and figures the person may pick, and the texts (`pg_dashboard_v2_edit_strings()`).

## What does dashboard_layout_save check?
- `pg_panel_dashboard_layout_save()` (`includes/panel/dashboard.php`, exempt, token): signed in, token, not locked (`pg_dashboard_v2_role_locked()`: a role row with `user_id` 0, role = the person's or 9, `locked` 1).
- `pg_dashboard_v2_layout_clean()` (`layout.php`, pure): only cards the person may see, once each; width S/M/L, period and compare 0-2, badge 0/1; shortcuts and tiles only from the allowed pools (6 and 4 at most); top since/actions/tiles/status 0/1. Stored cards the person may not see go back at their old index. Goals, pins and notes are always taken from the stored layout (the edit mode does not send them). JSON over 64 KB is refused. A look, when sent, goes through `pg_dashboard_v2_look_clean()`.

## How does the script work?
- `pg_dash_edit.js` (bundle `pg_dash.min.js`, after `pg_dash.js`) arranges the grid only through `pgDash.place(entries)`, `pgDash.layout()`, `pgDash.frame(id)` and `pgDash.reload(entry)`. `place()` parks frames it takes off, so a card put back draws without a request; a card whose period or compare changed is asked for again.
- Entry: "Edit dashboard" (`#pg_dash_edit_btn` in the switch row, not drawn while locked) or a card's "…" (`.pg-dash-card-edit`, opens that card's settings). Cancel places the entries copied on entry and puts back the greeting's clone. Save posts the layout; when the greeting changed (preset, shortcuts, tiles, a top switch) the page loads again, since its figures are drawn by the server; else edit mode closes and a "Saved" chip shows for 4 s.
- Grip: arrows move one place and announce "Moved to place n of m" (`pgAnnounce`); drag uses pointer events with pointer capture: a mouse after 5 px, touch after a 200 ms hold (`touch-action: none` on the grip). Bodies are `inert` in edit mode.
- System Status: `top.status` 0 draws the card `hidden` with `data-off`; edit mode shows it dimmed with Show/Hide.
- Outside edit mode the period pill saves the layout at once (`pgDash.periodChanged`).

## What was checked?
- `tests/dashboard_v2_layout_test.php` (presets, clean-up, look, the script's texts and bundle order). The script ran headless on a static page with a stubbed `$.ajax`, not on a running panel.
