---
name: panel-script-number-format
description: Panel scripts and size labels write figures with pg_number_separators(), never toLocaleString() or a bare toFixed()
module: innodb, geo
---

## How does a panel script write a number?
- With the separators `pg_number_separators()` gives (tr `1.234,56`, en `1,234.56`), printed into the script by the screen: `NUM` + `numberLabel()` in `view_folders.php` (Catalog Manager prices, quick-edit price box without grouping, upload window sizes), `NUM` + `fmtN()` in the Visitor Summaries widget (`includes/dashboard/widgets/widget_5.php`), `pgImageEditorConfig.decimal` (`pg_image_editor_decimal()`) for the image editor's figures, `cfg.moneySeparators` for `readableBytes()` on the product screen.
- Never `toLocaleString()` with no or an `undefined` locale (the browser's locale, not the panel's) and never a bare `toFixed(1)` for a figure a person reads: the server prints the figure beside it with `pg_format_number()`, and the two read differently.

## And on the server?
- `pg_format_number($n, $decimals)`; a count is `pg_format_number($n, 0)`, never `number_format($n)` ("3,456,789" reads as a decimal in Turkish). The location database row count (`pg_geo_settings_note()`) follows this.
- `pg_innodb_size_label($bytes, $separators = null)` writes database sizes (`2,5 GB` in Turkish) for the Database Engine screen, System Status, visitor retention and table maintenance. It falls back to `.`/`,` when `pg_number_separators()` is not loaded; the test runner's `lang()` returns the key, so the default shape stays English there.

## How does a panel screen write a file size?
- `convert_bytes_to_string($bytes, 'auto')`: B and KB whole, MB and up one decimal (`1,3 MB`), the same rule as `uploadSizeLabel()` in the upload window. File Manager rows, uploads, backups, ERP documents, optimize reports, All My Files / All Design Files and the editor pickers use it.
- An upload limit (`json_max`, derived from free memory) is written with `'floor'`, so `Up to 154,7 MB per file` never states more than the window and the server accept.
- The default (`$round = 0`, whole units) stays for site output: comment and form attachment labels and mail. Before this a 1,400,000-byte file read `1 MB` under the `Larger than 1 MB` filter and an optimize report read `1 MB -> 1 MB`. Pinned by `tests/size_label_test.php`.

## Which other screens follow it?
- The SEO panel over a site page writes each group weight with `'{var:1}%'` (`%40` in Turkish, `40%` in English) and the speed check labels (`pg_seo_check_label()`, shared with the panel SEO checklist) pass ms, request counts, percent and MB through `pg_format_number()` (0 or 1 decimal).
- The upgrade pre-flight card: `install_size_label()` (runner.php) returns `pg_innodb_size_label()` and `install_count_label()` writes the full row count with `pg_format_number($n, 0)` (no `1.5M`/`3k`); both keep a `function_exists()` fallback. Pinned by `tests/seo_speed_figures_test.php`.

## What was the symptom?
- A Turkish panel showed `1.5 MB`, `2.5 GB`, `3,456,789` and, with an English browser, `1,234.50` prices beside server figures in `1.234,50`; the visitor KPI switched shape after a period click. Pinned by `tests/panel_number_format_test.php`.

Related: docs/memory/ui/money-field-input-mask.md, docs/memory/erp/erp-number-input-and-display.md
