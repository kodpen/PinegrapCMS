---
name: panel-script-number-format
description: Panel scripts and size labels write figures with pg_number_separators(), never toLocaleString() or a bare toFixed()
module: innodb, geo
---

## How does a panel script write a number?
- With the separators `pg_number_separators()` gives (tr `1.234,56`, en `1,234.56`), printed into the script by the screen: `NUM` + `numberLabel()` in `view_folders.php` (Catalog Manager prices, quick-edit price box without grouping, upload window sizes), `NUM` + `fmtN()` in the Visitor Summaries widget (`includes/dashboard/widgets/widget_5.php`), `window.pgImageEditorNumber` (printed by `get_image_editor_includes()`) for the image editor's size line, `cfg.moneySeparators` for `readableBytes()` on the product screen.
- Never `toLocaleString()` with no or an `undefined` locale (the browser's locale, not the panel's) and never a bare `toFixed(1)` for a figure a person reads: the server prints the figure beside it with `pg_format_number()`, and the two read differently.

## And on the server?
- `pg_format_number($n, $decimals)`; a count is `pg_format_number($n, 0)`, never `number_format($n)` ("3,456,789" reads as a decimal in Turkish). The location database row count (`pg_geo_settings_note()`) follows this.
- `pg_innodb_size_label($bytes, $separators = null)` writes database sizes (`2,5 GB` in Turkish) for the Database Engine screen, System Status, visitor retention and table maintenance. It falls back to `.`/`,` when `pg_number_separators()` is not loaded; the test runner's `lang()` returns the key, so the default shape stays English there.

## What was the symptom?
- A Turkish panel showed `1.5 MB`, `2.5 GB`, `3,456,789` and, with an English browser, `1,234.50` prices beside server figures in `1.234,50`; the visitor KPI switched shape after a period click. Pinned by `tests/panel_number_format_test.php`.

Related: docs/memory/ui/money-field-input-mask.md, docs/memory/erp/erp-number-input-and-display.md
