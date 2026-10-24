---
name: settings-dashboard-card
description: Dashboard card in Site Settings > Appearance (pgset-dashboard): card style, panel backdrop, per-sign-in refresh time, Reset Widgets; role < 2 gate; how a save reaches the dashboard behind the dialog
---

## Where are the dashboard's look and refresh set?
- On the Dashboard card of Site Settings > Appearance (`pgset-dashboard`, between Theme and Rich-text Editor; `includes/settings/appearance.php`, written by `appearance.save.php`). They used to be in the Panel row's menu panel on the dashboard (`output_menu()`); the owner moved them here when Panel became a single button.
- Card Style (`dashboard.widget_theme`: flat, neon, glass, aurora) and Panel Backdrop (`dashboard.panel_backdrop`: auto, none, mesh, dusk, ember) are site-wide, on the dashboard row. The accepted values, the "auto" mapping and the fallbacks live in `includes/fn/dashboard.php` (`pg_dashboard_appearance_choices()`, `pg_dashboard_appearance()`), shared with `welcome.php` and api.php's `update_dashboard_appearance`; `tests/dashboard_appearance_test.php`.
- Refresh Time stays per sign-in: `$_SESSION['software']['welcome']['widget']['refresh']`, no column. The save and `welcome.php?refresh=N` both go through `pg_dashboard_refresh_seconds()`: 10 seconds to a day; text keeps the time in use. Above about 24.8 days a browser timer fires at once.
- Reset Widgets is a named submit button (`dashboard_reset_widgets`, `settings-card-own-button.md`): the save writes the settings, then `order_widgets = 'default'`, the factory order.

## Who sees and saves it?
- Designers and administrators only (`pg_dashboard_appearance_allowed()`, role < 2), the reach the menu controls and api.php's general gate have. The dialog itself is open to managers.
- For anyone else the card is not drawn, `pg_settings_categories()` drops the section and its keywords (no chip, no palette entry) and the Appearance description loses "the dashboard".
- The save asks the same gate before reading any dashboard field: a manager's save of Appearance, or a forged one carrying the fields, leaves the row and the session's refresh time as they were. Checked in the browser for both the dialog endpoint and the page path.
- A select that was not drawn (no 2026.4.4 columns yet) is not posted and its column is not written; a value outside the list marks the field and the category writes nothing (`settings-invalid-vs-error.md`).

## How does a save show on the dashboard behind the dialog?
- The card carries the stored values as `data-pg-dashboard-*` attributes and its script (`$pg_settings_scripts`) runs on every pane read, the one after a save included.
- Over `welcome.php` (`main#content[data-widget-theme]`) it writes the card style and the resolved backdrop onto `#content` at once: both are only read by the stylesheet.
- A new refresh time (compared with `pg_widgets.interval`) or a reset (a one-shot session flag the save leaves and `prep.php` takes) needs the page drawn again. The dashboard is loaded again when the dialog closes, at `location.pathname`: without `?refresh=N`, which would set the old time again, and without the `#settings/...` hash, which would reopen the dialog.
- On any other screen and on `settings_appearance.php` the script does nothing.

Related: docs/memory/ui/settings-modal-architecture.md, docs/memory/ui/dashboard-widget-files.md
