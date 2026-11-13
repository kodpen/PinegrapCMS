---
name: legacy-bootstrap-rules
description: Legacy to Bootstrap 5 helper rules beyond renames: visibility names, push/pull, theme names 5.3.8 owns, list-inline, data-bs, the trial's scripts.js and compatibility sheet, non-Bootstrap designs
---

## Which rules does the map carry beyond one-to-one renames? (architect)
- The `visible-*` and `hidden-*` names of one element are merged into one `d-*` set: renamed one by one, `d-none` wins and the phone's menu button is gone.
- Push or pull on a column outside a `.row` has no twin: kept, counted "by hand" in the report.
- Theme names that 5.3.8 owns as `!important` utilities (`bg-secondary` 92 uses, `bg-dark` 71, the rest in `legacy-bootstrap-census.md`) get a name of the trial's own, in its markup and in its sheet copies (`pg_lbs_css()`).
- A `list-inline` list gets `list-inline-item` on each `li`: without it the footer's icons stack.
- `data-toggle` and its family become `data-bs-*`.

## What does the trial design get beside its sheets? (architect)
- Its own copy of the theme's `scripts.js` with the tooltip line changed to `bootstrap.Tooltip.getOrCreateInstance()`: under 5.3.8 the old line throws and every theme script after it stops (`legacy-bootstrap-trial.md`).
- A small compatibility sheet for the Bootstrap 3 form names the page types' layouts print, loaded on the trial design only; what the layout pass now renames left it (`legacy-layouts-left.md`).
- The Legacy design's own files are never touched.

## What does the helper do on a design that is not Bootstrap?
- Nothing (owner, 2026-11-12, #8967, after the Materialize pictures, `legacy-materialize-trial.md`): the card says that the design does not use Bootstrap 3 and offers no report and no trial design (`workflow/decisions-made-product-48.md`).
- Bootstrap 3 is found by the design's classic framework sheet row (`bootstrap.css`, `bootstrap.min.css`), never by class names: Materialize shares `row`, `container`, `btn`, `badge`, `active` and `right` with it.

## Why are the custom layouts not part of the helper?
- `data/layouts/<id>.php` and `includes/templates/` serve the classic styles and every Legacy design too, and the helper never changes what a Legacy design uses; their move is the layout output pass (`legacy-layouts-output-pass.md`).
