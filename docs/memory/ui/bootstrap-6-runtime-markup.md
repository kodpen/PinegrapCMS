---
name: bootstrap-6-runtime-markup
description: Bootstrap 5 leftovers in panel markup built at runtime (JS strings, swapped classes, PHP helpers shared with sites) that check_bs6.php does not scan, and what replaced them
module: returns
---

## Why can v5 markup survive `check_bs6.php --strict`?
- The checker reads panel markup in PHP templates; it does not follow class names built in JavaScript strings (`'text-bg-' + variant`), classes a script swaps at runtime, vendor integrations that write their own markup (DataTables Buttons), or PHP helpers shared with site output. Those are found by reading, and pinned by `tests/bs6_js_leftovers_test.php` instead.

## What was converted?
- Toasts (`pgToast` in `backend.src.js`, `toast()` in `workspace.js`, the explorer toast in `view_folders.php`): v6 has no `text-bg-*`, so they were drawn plain. Now `bg-<tone> fg-contrast-<tone>` (backend.src.css inks `.bg-*.fg-contrast-*` with `--pg-on-accent`); Workspace's default `dark` is `bg-inverse fg-contrast-inverse`. The close buttons lost `fg-white`, which was a white X on a white toast in the light theme.
- Paraşüt connection test (`backend.src.js`): the status box is drawn with `theme-<tone>`, which beats `.alert-*`; the script now swaps `theme-\S+`, so a successful Connect turns it green.
- Image tiles (image picker twin in `backend.src.js`, `product_builder.php`, `edit_contact.php`, Unsplash results in `editor_select_image.php`): v6 has no `.ratio`. The box is `style="aspect-ratio: 5 / 4"` with `p-0` and the img `w-100 h-100`; the contact avatar placeholder is `h-100` instead of its own 1:1.
- DataTables column visibility menu: `$.fn.dataTable.Buttons.defaults.dom.collection` is extended next to the `ext.classes` override, so the list is `dropdown-menu menu show` and each item `dropdown-item menu-item`. `dropdown-menu` stays because `datatables.css`'s `div.dt-button-collection .dropdown-menu` keeps the list in flow instead of the menu's absolute position. The vendor file is untouched.
- Return status badges: `pg_return_badge_class()` keeps `badge text-bg-*` for site output (`pg_return_present()`, the widget parts). The panel (`view_returns.php`, `view_return.php`, `pg_returns_panel_card_html()`) uses `pg_return_panel_badge_class()` → `badge theme-<tone>`, the same tone per status, secondary for an unknown one.
- `text-primary` / `text-success` with no v6 class: `erp_dashboard.php`'s card header and `toolbar.php`'s homepage icon are `fg-primary` / `fg-success`.
- Visual editor Settings rail (`includes/designer_screen.php`, `nav#settingsTabs`): `data-bs-toggle="pill"` did nothing, since the v6 Tab data API listens only to `[data-bs-toggle="tab"]`; the buttons are `tab` now, still drawn as pills by `nav-pills`.

## What was not checked in a browser?
- None of these was run in a sandbox: the toasts are transient, the column menu and the Settings rail need a running panel. The class choices were checked against `assets/lib/bootstrap-6/css` and `js` only.

Related: docs/memory/ui/bootstrap-6.md, docs/memory/ui/bootstrap-6-own-widget-swaps.md, docs/memory/ui/panel-css-tokens.md
