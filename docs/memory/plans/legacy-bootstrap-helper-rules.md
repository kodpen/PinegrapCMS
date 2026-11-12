---
name: legacy-bootstrap-helper-rules
description: Legacy to Bootstrap helper after LB1 (#8942): the owner's answers, the map rules beyond renames, the trial's own scripts.js and compatibility sheet, a design that is not Bootstrap
---

## What did the owner answer after LB1? (2026-11-12, #8942)
- The trial is near enough: the plan goes on as written.
- LB6's twins: photo background, slider, lightbox. No navbar: the starting sites never use Bootstrap 3's.
- The theme becomes a palette and a look (LB7), after the pages are moved (LB5).
- "bazı sitelerde materialize bile olan var bir de bootstrap dışı bir tasarımla da test etmek gerekecek.": step LB1b.
- Record: `workflow/decisions-made-product-46.md`.

## Which rules does the map carry beyond one-to-one renames? (architect)
- The `visible-*` and `hidden-*` names of one element are merged into one `d-*` set: renamed one by one, `d-none` wins and the phone's menu button is gone.
- Push or pull on a column outside a `.row` has no twin: kept, counted "by hand" in the report.
- Theme names that 5.3.8 owns as `!important` utilities (`bg-secondary` 92 uses, `bg-dark` 71, the rest in `designer/legacy-bootstrap-census.md`) get a name of the trial's own, in its markup and in its sheet copies (`pg_lbs_css()`).
- A `list-inline` list gets `list-inline-item` on each `li`: without it the footer's icons stack.
- `data-toggle` and its family become `data-bs-*`.

## What does the trial design get beside its sheets? (architect)
- Its own copy of the theme's `scripts.js` with the tooltip line changed to `bootstrap.Tooltip.getOrCreateInstance()` on `[data-bs-toggle="tooltip"]`: under 5.3.8 the old line throws and every theme script after it stops (`designer/legacy-bootstrap-trial.md`). LB4 makes the copy as it makes the sheet copies.
- A small compatibility sheet for the Bootstrap 3 form names the page types' layouts print (`form-group`, `text-right`, `input-group-btn`, `input-group-addon`, `visible-xs-inline`, `btn-default`, `checkbox`, `help-block`, `img-responsive`, `glyphicon`), loaded on the trial design only. It leaves as `legacy-layouts-bootstrap.md` converts the layouts.
- The Legacy design's own files are never touched.

## What does the helper do on a design that is not Bootstrap?
- Until LB1b's pictures were shown to the owner: it runs only where the design's census finds Bootstrap 3 (its sheet row or its names). On any other design the card says that the design does not use Bootstrap 3 and offers nothing.
- LB1b: a hand trial as LB1's on a Legacy design whose theme loads Materialize, a sandbox fixture, nothing added to the product. Pictures side by side, wide and phone; the names both frameworks own (`row`, `col`, `container`, `btn`, `card`) and what each sheet does to the other's. Then the architect asks the owner what the helper does on such a design.

## Why are the custom layouts not in this plan?
- `data/layouts/<id>.php` and `includes/templates/` serve the classic styles and every Legacy design too, and the helper never changes what a Legacy design uses. Their move is `legacy-layouts-bootstrap.md`.
