---
name: look-report-screens
description: Report screens in look A (DL5e, #8981): order, visitor, shipping and ERP tax reports; head, strip, tools row, table frame, drawer, the choices made and what is not done
module: report_view
---

## Where do the report screens' look rules sit?
- `backend.src.css` block "Look A: report screens" at the file's end: width (`body.pg-measure-report`), head, strip, tools row, figures, chart, tooltip, bars, map, no-chart line, table frame and table, settings drawer. The drawing: `plans/panel-design-language-report.md`.
- The view (figures, chart) is drawn by `assets/js/pg_report_view.js` (`report-view.md`).

## What does each part use?
- Head (order, visitor): `pg_screen_toolbar()` class `pg-report-head`. Grow: `h2.pg-title-screen` with the report's name and, without an `id`, the amber pill "Unsaved". View: "Report settings" (quiet) and, without an `id`, "Save" (filled, `form="edit_form"`, `name="submit_save"`).
- Strip: `nav.pg-report-strip`, a label and a `.pg-seg` of ready reports (`is-active`, `aria-current="page"`); the visitor date presets are a `.pg-seg` too.
- Tools row `.pg-report-tools`: per unit a `.pg-seg` of previous, unit, next (`.pg-report-step`) and the period as text, `.pg-report-range`.
- Shipping: slot 2 "Ship Date Adjustments" (quiet); the search slot holds the status as `.pg-seg` links (`?status=`, kept in the session) and the period stepper. ERP tax: the period form is the tools row, "Excel" quiet at the right.
- Tables: `section.pg-card.pg-report-frame` (head: title, period and count; body scrolls across) around `table.pg-report-table`. Rows `pg-report-l1/l2/l3/detail/total` replace the inline colours; `pg-report-share` makes the last column the 72 px share. The ERP tax tables are frames too.
- Drawer `#report_settings`, 430 px: `section.pg-report-section` (report, "Summarize by · up to three levels", "Filters", "How It Works"), numbered `.pg-report-level`s. Filter rows are laid out by CSS only (`backend.src.js` untouched, column headings hidden, controls keep their labels). Foot: "Save & Run" filled, "Close" quiet, "Delete" danger at the right.

## Which choices were made, and why?
- The range is text, not a 34 px field: there is no range picker, and a field would promise one.
- Shipping and ERP tax print no `h2`: the top bar names them already. They drop `pg_dash.min.js` and the dashboard sheet (no map); order and visitor keep both for the map.
- Figures: up to five share a row, more go in two even rows of at most four; a phone draws two a row.
- A level with no chart prints one line saying so (`p.pg-report-nochart`), never an empty plot.
- Bootstrap's `.switch` (a settings card's, the drawer's): `--bs-switch-border-width: 0px`, since a bare 0 made the knob's size invalid and it vanished; the knob is white, where Bootstrap painted it dark when on.

## What is not done?
- Each is a behaviour, not only a look: compare with the previous period (dashed line, change marks), a segmented control choosing the figure, period presets on the order report, filters as pills with "Add filter" in the tools row, Export in the head.
- In the table: "Show detail" and the columns button in its head, collapsing level-1 rows, the sort arrow, the 64 x 4 meter. In the drawer: grip, add and remove of levels, an "Options" section, the foot names Apply / Cancel / Reset.

Tests: `tests/look_report_screens_test.php`; the screens' own tests are listed in `report-view.md`.
