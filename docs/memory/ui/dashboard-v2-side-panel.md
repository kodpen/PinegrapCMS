---
name: dashboard-v2-side-panel
description: New dashboard side panel (#4888): gallery.php data, pg_dash_panel.js (work area, gallery, Add card tile, top section, look) and its hooks with the edit script
---

## Where does the gallery's data come from?
- `includes/dashboard/v2/gallery.php`: `pg_dashboard_v2_gallery_cards()` gives every card of `cards.php` its line, `new` (1 when no old widget grew into it) and a sketch kind (`pg_dashboard_v2_sketches()`). Kept apart from `cards.php` so card steps flipping `$built` do not touch it. `pg_dashboard_v2_config()` copies them onto `config.cards` and sends each preset's `recommended` cut to the visible cards.
- Only built cards the person may see are in `config.cards`, so the gallery offers only those.

## How does the panel work?
- `pg_dash_panel.js` (bundle `pg_dash.min.js`, after `pg_dash_edit.js`). On entering edit mode the greeting/status row and the grid move into `.pg-dash-work-main` beside `aside.pg-dash-side` (wraps below under about 1000 px); leaving puts them back.
- The edit script calls `pgDash.panel.opened/closed/cancel/saved/changed`; the panel uses `pgDash.edit.state()`, `greetingChanged()`, `statusChanged()`, `cardsChanged()` and `remove(id)`.
- Gallery: search folds case, Turkish i forms and marks (`pgFoldTR`); pills All, Recommended, the categories; Add puts the card last at its default width and period; RECOMMENDED shows only while the card is off the dashboard.
- The Add card tile (`.pg-dash-addtile`, not drawn while locked) is the grid's last cell; `pgDash.place()` moves it back to the end.

## What do Top section and Look do?
- Top section switches set `state.top`; a greeting part switched off stays drawn but `hidden` (a part the server did not draw shows after Save's reload).
- Look picks show at once (`data-widget-theme`, `data-panel-backdrop` with Auto resolved from `choices.auto`, `pg-dash-compact`, `pg-dash-calm`). The look is sent with the layout only once the person picked one, so the site's Appearance values keep reaching them until then; Cancel restores it.

## What was checked?
- `tests/dashboard_v2_gallery_test.php`. The bundle ran headless in Chromium on a static page with a stubbed `$.ajax` at 1280 and 390 px, light and dark: gallery, search, Recommended, add/remove with focus kept, top switches, look, cancel. Not on a running panel.
