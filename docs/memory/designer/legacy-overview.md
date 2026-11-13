---
name: legacy-overview
description: Start file for moving classic styles to the visual designer: owner decisions, the Legacy design of a set, which file holds each step (LD, LG1-LG7), the rules kept, what is open
---

## What is it?
- A designer moves classic styles to the visual designer from Move to the Page Design Studio (`legacy_to_designer.php`, under All Page Styles): a report, a dry run, then a run. A style moved alone becomes a design itself; a set (styles with the same ordered CSS and JS, collection and theme) becomes one new Legacy design, badge "Legacy" / "Eski".
- Owner (2026-11-11, #8671, `workflow/decisions-made-product-43.md`): a set moves as one design; the page content is edited in the page; a back-to-classic button; a helper towards Bootstrap 5.3.8 as a plan of its own. Not chosen: joining designs moved one by one earlier to their set's design; never propose that merge.

## Which file holds what?
- The report and its "What stays classic" card: `legacy-to-designer-report.md`. The dry run and the editor steps' server twins: `legacy-to-designer-bulk-run.md`. The run, cursor, lock and kept reports: `legacy-to-designer-run.md`.
- How a classic style's code becomes a frame: `classic-style-import.md`, `classic-style-to-design.md`, its own CSS/JS files: `classic-style-design-files.md`; classic regions: `classic-regions-in-designer.md`; the canvas preview of a custom layout: `legacy-region-preview.md`.
- LG1 the theme file (#8694): `legacy-design-theme.md`. LG2 `{path}` in addresses (#8732): `design-asset-path-placeholders.md`. LG3 the `legacy` key and badge (#8757): `legacy-design-framework.md`.
- LG4 sets in the report and the dry run (#8776): `legacy-set.md`. LG5 the set's run (#8795): `legacy-set-run.md`. LG6 the frame split into parts, page content in the page's tree (#8819): `legacy-frame-split.md`. LG7 Back to classic (#8848): `legacy-to-classic.md`.

## What did every step keep?
- No schema. Nothing classic is removed: the classic `style` rows and their code stay (#6006); a page changes its style only in a run a designer starts after the dry run (#6251), or when Back to classic sends it back.
- A step that changes what a visitor gets is seen in a sandbox, classic and moved page side by side, wide and phone: a unit test cannot reach the cascade. The look on dev is the owner's.
- Designs moved before LG6 keep their whole frame; no step splits them.

## What is still open?
- A style moved alone: the run writes no stylesheet or script rows, so the classic head's other files are missing (`legacy-design-theme.md`). A set's design has them (`pg_ltd_set_assets()`).
- Photos in a `.background-image-holder`: a common region made a shared component draws its `<img>` in a `<picture data-pg-picture>`, and a classic theme's scripts.js reading `children('img')` sets `url(undefined)`, so slider and parallax photos are lost (`legacy-set-run.md`, `responsive-images.md`).
- The Bootstrap 5.3.8 helper: not built; its plan is `plans/legacy-bootstrap-helper.md` (#8856), steps LB1-LB7. LB1's census and hand trial: `legacy-bootstrap-census.md`, `legacy-bootstrap-trial.md`.
- A design left with no page after Back to classic stays until deleted; a later move makes a new one (`legacy-to-classic.md`).
