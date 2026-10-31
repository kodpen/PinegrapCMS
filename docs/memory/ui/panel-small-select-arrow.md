---
name: panel-small-select-arrow
description: A small select (select.form-control.form-control-sm) keeps its longest option clear of the arrow: 1.75rem end padding, the arrow 0.5rem in
---

## Why does a small select have its own padding and arrow place?
- The panel's small fields are `--bs-btn-input-sm-padding-x: 0.5rem` (the token block of backend.src.css, v5's size). Bootstrap 6 pads a select's end by three of them (1.5rem) and draws its 16px arrow 0.75rem from the edge, so the arrow began 0.25rem before the text could end: a `w-auto` select, as wide as its longest option, ran that option into the arrow ("Tüm departmanlar" in the Work Calendar's filters, every Workspace toolbar's small selects).
- `select.form-control.form-control-sm:not([multiple], [size]:not([size="1"]))` now sets `--bs-control-select-bg-position: right .5rem center` and `padding-inline-end: 1.75rem`, leaving 0.25rem between them. A select in `.input-group-sm` without `form-control-sm` keeps its own rule (three paddings).
- Found trying the Workspace screens, 2026-11-01.
