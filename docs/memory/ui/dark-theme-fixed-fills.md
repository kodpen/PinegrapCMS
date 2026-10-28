---
name: dark-theme-fixed-fills
description: Inline light colours in panel markup and scripts (label designer, export overlay, hosting widget, field form ticks) moved onto --pg-* tokens; what stays white on purpose and what is left
---

## Why did these places stay light in the dark theme?
- Inline styles written before the Bootstrap 6 switch hard-code v5 light greys (`#f8f9fa`, `#dee2e6`, `#ced4da`, `#6c757d`, `#e5e7eb`, `#e3e3e3ab`). The token layer (`panel-css-tokens.md`) only reaches stylesheets, so these kept light boxes and grey text on the dark background.

## What was changed?
- `PgBarcode.LabelEditor` in `assets/js/backend.src.js`: the property box and the Label Size box read `var(--pg-bg-1)` and `var(--pg-border-color)`; their inputs and selects `var(--pg-border-color)`, `var(--pg-bg-body)` and `var(--pg-fg-body)`; the note text `var(--pg-fg-2)`.
- The Parasut order export "Please Wait" overlay in `backend.src.js`: `color-mix(in srgb, var(--pg-bg-body) 70%, transparent)` with `var(--pg-fg-body)` text.
- The hosting widget track (`widget_14.php`): `var(--pg-bg-2)`.
- The rows/columns corner ticks of `includes/forms/field_form.php`: `var(--pg-border-color)`.
- `tests/dark_theme_leftovers_test.php` pins these strings.

## What stays white on purpose?
- The label canvas and the label print document: they stand for the paper.
- `backend.src.js` is served as is (not in `pg_js_bundles()`), so no build step follows an edit.

## What is left?
- The `.pg-table-light` rule in `backend.src.css` (kept fixed at the switch to match v5) needs a PR with before/after screenshots.
- The `.pg-sortable` block of `backend.src.js` (a later B12 slice).
