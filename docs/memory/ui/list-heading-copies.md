---
name: list-heading-copies
description: The copies DataTables (scrollX) and FixedHeader make of a panel list's heading: which leave the Tab order, why the sizing copy stays readable, the floating heading no longer aria-hidden
---

## Which copies of a list heading are there?
- Every `table.chart` gets `scrollX` and `fixedHeader` (`backend.src.js`). With scrollX, DataTables moves the real `thead` into a head table and on every draw and `columns.adjust` puts a copy into the body table, inside 0px `div.dt-scroll-sizing` boxes. The copy keeps its sort links and the select-all box (without its id).
- While the list scrolls, FixedHeader moves the real `thead` into a floating table at the end of `<body>` marked `aria-hidden="true"` and leaves a placeholder copy with live links in place.

## What does the panel do about them? (#3477)
- `pgListHeadingCopies(api)` runs per table on `draw.dt column-sizing.dt fixedheader-mode.dt` and once after the init.
- The body table's sizing copy: `tabindex="-1"` on its links and controls, `aria-hidden` on its controls only. Before, Tab passed every heading link a second time with no visible focus, and a screen reader found a dead "Select/Deselect All" box.
- While the heading floats: `aria-hidden` comes off the floating table (axe aria-hidden-focus) and the placeholder is `inert` with `tabindex="-1"` on its links; focus on a placeholder link fell to `<body>` when FixedHeader removed it. FixedHeader rebuilds both on each mode change, so nothing is restored.

## What must not be done?
- No `inert` or `aria-hidden` on the sizing copy row itself: screen readers take the data cells' column headers from it.
- The vendored DataTables files are not edited; `tests/list_heading_copies_test.php` pins the handler and the shipped `datatables.js`.
- Out of scope: the double header a screen reader meets with scrollX, and the floating heading sitting last in Tab order (FixedHeader's design).
