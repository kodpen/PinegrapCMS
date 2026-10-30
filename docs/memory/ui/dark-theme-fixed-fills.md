---
name: dark-theme-fixed-fills
description: Fixed light colours in panel markup and scripts (label designer, tracks, Chart.js ink, SEO band, chat dot, order tick, menu pictures, comment stars) moved onto theme colours
---

## Why did these places stay light in the dark theme?
- Inline styles written before the Bootstrap 6 switch hard-code v5 light greys (`#f8f9fa`, `#dee2e6`, `#ced4da`, `#6c757d`, `#e5e7eb`, `#e3e3e3ab`). The token layer (`panel-css-tokens.md`) only reaches stylesheets, so these kept light boxes and grey text on the dark background.

## What was changed?
- `PgBarcode.LabelEditor` in `assets/js/backend.src.js`: the property box and the Label Size box read `var(--pg-bg-1)` and `var(--pg-border-color)`; their inputs and selects `var(--pg-border-color)`, `var(--pg-bg-body)` and `var(--pg-fg-body)`; the note text `var(--pg-fg-2)`.
- The Parasut order export "Please Wait" overlay in `backend.src.js`: `color-mix(in srgb, var(--pg-bg-body) 70%, transparent)` with `var(--pg-fg-body)` text.
- The hosting widget track (`widget_14.php`): `var(--pg-bg-2)`.
- The rows/columns corner ticks of `includes/forms/field_form.php`: `var(--pg-border-color)`.
- The dashboard tracks of widgets 18, 21 (and its hit count pill, which keeps `color:inherit`), 22 and 23: `rgba(0,0,0,.06)` vanished on the dark card, now `var(--pg-bg-2)` (#2569).
- Chart.js charts in `erp_profit.php`, `search_console.php` and the Orders statistics strip (`view_orders.php`, which read the undefined `--bs-secondary-color` and so always drew `#6c757d`, under 4.5:1 on both cards) drew Chart.js defaults (#666 labels, black 10% grid). They resolve an ink from `var(--pg-fg-2)` through a probe element (`getComputedStyle(probe).color`) and pass it to the legend and every scale's ticks, with a `rgba(128,128,128,.2)` grid. Never hand `getPropertyValue('--pg-*')` to Chart.js: it returns `light-dark(...)` text a canvas cannot parse (#2569).
- `pg_seo_score_color()` in `seo.php`: the 30-54 band returned `var(--bs-orange)`, undefined in the panel, so its bar and badge were transparent; it returns `#fd7e14` (as `pg_seo_score_hex()`), the badge carries `color:var(--pg-on-accent)` (#2569).
- The chat AI row's presence dot is built with `border-white` in `chat_backend.src.js`; an unlayered `#pg-chat-root .pg-chat-dot { border-color: var(--pg-bg-body); }` in `chat.php` beats the layered utility (#2569).
- View Order's Address Verified mark was a black 7px `check_mark.gif`, invisible on the dark card: now `bi-check-lg fg-success` with a visually hidden `lang('Yes')`. `print_order.php` keeps the GIF (light paper, `print-views-light-paper.md`).
- Create/Edit Menu's Submenu Effect pictures are opaque light greyscale PNGs: a screen-level `html[data-bs-theme="dark"] .pg-menu-effect-picture { filter: invert(1); }` in `menu_form.php`; accordion and none are drawn at their own 56px width.
- Edit Comment's rating stars were black background pictures inverted in dark, which also inverted the focus outline (2:1). The star is now a `mask-image` on `label::before` painted with `currentColor` (a mask on the label itself would clip its outline), so the outline keeps `var(--pg-primary-ink)`. Forced colours paint that background Canvas, so all five stars vanished: an `@media (forced-colors: active)` rule paints `.rating label::before` `CanvasText` (`tests/forced_colors_widgets_test.php`).
- `tests/dark_theme_leftovers_test.php`, `panel_chart_colours_test.php`, `order_view_marks_test.php`, `content_edit_names_test.php` and `comment_calendar_names_test.php` pin these strings.

## What stays white on purpose?
- The label canvas and the label print document: they stand for the paper.
- `backend.src.js` is served as is (not in `pg_js_bundles()`), so no build step follows an edit.

## What is left?
- The `.pg-table-light` rule in `backend.src.css` (kept fixed at the switch to match v5) needs a PR with before/after screenshots.
