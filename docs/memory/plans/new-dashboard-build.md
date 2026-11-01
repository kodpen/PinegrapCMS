---
name: new-dashboard-build
description: New dashboard plan: the page branch in welcome.php, the files (page, script bundle, stylesheet), charts, refresh, light and dark, phone, keyboard, texts; how card steps stay out of each other's files
---

## How does welcome.php reach the new page?
- After the person is loaded and before any output: when their row says enabled, `require` `includes/dashboard/v2/page.php` and stop. Plus the switch row before `.pg-clockbar`. Nothing else in `welcome.php` changes; the old page's ids, scripts and widgets stay (`ui/dashboard-welcome-card.md`).
- `page.php` draws `pg_page_shell()` as `welcome.php` does (same title and heading), the switch row, the greeting and status cards on the server, the grid with every card's frame and a skeleton, and one JSON config (layout, look, card list with titles, icons, categories, widths, periods, presets, strings).

## Which files?
- PHP: `includes/dashboard/v2/` (gate constant as `widgets/`): `page.php` and its parts (`page_greeting.php`, `page_status.php`, `page_grid.php`), `layout.php` (read, merge, validate, save), `cards.php` (the 30 cards: id, title, icon, category, width, period, gate), `presets.php`, `figures.php`, `cards/<id>.php`.
- Script: bundle `assets/js/pg_dash.min.js` in `pg_js_bundles()` from `pg_dash.js` (grid, card frame, loading, refresh), `pg_dash_edit.js` (edit mode, drag, keyboard, gallery, side panel) and one renderer file per card group, `pg_dash_cards_<group>.js` (visitors, content, security, team, shop, work, assistant), each registering its cards' draw functions. A card step adds its file and appends one line to the bundle list. (Bundle and stylesheet names take no digits: `tests/js_bundles_test.php`, `tests/css_minify_test.php`.)
- Styles: `assets/css/pg_dash.src.css` in `pg_css_bundles()`, as the image editor's, one section per group; `php tools/build_css.php`, `php tools/build_js.php`.
- Tests: `tests/dashboard_v2_<topic>_test.php` for layout validation, presets, figures and each card function's shape.

## How does it look?
- On the panel tokens (`--pg-*`), light and dark through `[data-bs-theme]`; the prototype's dark colours are the dark side. Card style and backdrop: the page sets `data-widget-theme` and `data-panel-backdrop` on its root from the person's look and reuses the values of the current rules (`ui/settings-dashboard-card.md`); compact and lights-off are classes on the root.
- Three tracks over about 1000 px, two to 640 px, one below; dense packing; Full spans every track, Medium two (one on a phone).
- Charts are inline SVG drawn by the script (no Chart.js on this page), each with `role="img"` and a label saying what it shows; no colour carries meaning alone (tones also differ in text).
- The gauge twinkle and every transition stop under `prefers-reduced-motion` and with lights off; forced colours get one block (`ui/panel-forced-colors.md`).

## How does it refresh?
- Engagement, firewall and visitors at the session's refresh time (`pg_dashboard_refresh_seconds()`); the rest on load and when a period changes; a hidden tab pauses; markup of a card is drawn again in place and focus is kept (`ui/dashboard-keyboard.md`).

## Phone, keyboard and texts?
- Phone (screen 20): one column, the edit bar, tool rows and shortcuts wrap, the side panel under the grid, drag by touch after a short hold.
- Keyboard: every control a named button; grip arrows announce "Moved to place 3 of 15" in a live region; Escape closes a card's settings.
- Every text through `lang('English')` with Turkish added by `php tools/tr_add.php`; counts by `{suffix}` keys (`ui/greeting-counts.md`); numbers, money and dates by the panel's helpers; no text in the script that the server did not send.
