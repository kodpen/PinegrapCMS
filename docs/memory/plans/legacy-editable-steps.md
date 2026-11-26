---
name: legacy-editable-steps
description: Plan legacy-editable, steps LE1 to LE12 with size and order: the measure, page_parts, one door, the true canvas, stock layouts, the layout converter by families, earlier designs, close-out
---

## Where are the plan and its parts?
- `plans/legacy-editable.md`; regions `-regions.md`, layouts `-layouts.md`, screens `-screens.md`. A step is marked done here with its PR number.

## Which steps?
- LE1 (S) done (#11128) the measure: `pg_classic_closed_census()` and the move screen's four figures; its PR body has both starting sites' base numbers.
- LE2 (M) done (#11159) the run's `page_parts` step, Login, Cart, Comments Block in `frame` and `page_parts`; the walk into the run's components; the dry run's counts. `includes/fn/legacy_to_designer_run.php`, `legacy_to_designer_plan.php`, `widgets_region_starters.php`.
- LE3 (M) done (#11227) one door and the dry run in three groups: "Open in the visual designer" lands on the move screen with the style or set ticked, the note on common regions, "Open the design" at the end; Edit Style's notice.
- LE4 (M) done (#11158) the true canvas: markers around every region pass in `get_page_content.php` while marking is on, `legacy_region_preview` answering every region node, the band and the Region panel's line and action. `includes/fn/layouts.php`, `style_designer.src.js` (`node --check`).
- LE5 (M) done (#11270) the seed's missing settings, the dry run's reasons (`designer/legacy-stock-types.md`).
- LE5b (M) done (#11290) the run's `types` step, a "Page types" row (`designer/legacy-types-step.md`).
- LE6 (M) done (#11308) `pg_layout_convert()`, `pg_layout_piece_map()`, a file page dropping nothing converts in the run; form list and item view.
- LE6b (M) done (#11331) the Region panel's dropped list and confirm, its Convert on a file page.
- LE7 (M) done (#11335) order form. LE8a-c done (#11362 gallery, #11393 catalog, #11436 catalog detail). LE9a-e done (#11466 cart, #11486 express order, #11628 the three steps, #11667 order preview, #11688 view order). LE10a done (#11710) login, password pages, profile, e-mail preferences. LE10b done (#11730) registration, membership entrance. LE10c (M) my account, address book (its address type). LE10d (S) search results.
- LE11 (S) done (#11309) the "Designs moved earlier" card, its dry run drawer and run.
- LE12 (S) close-out: `designer/legacy-editable-overview.md` written with the last numbers; `designer/legacy-overview.md`, `classic-style-to-design.md`, `canvas-closed-blocks-regions.md` and `layout-widget-offer.md` corrected; this plan's five files deleted; the changelog `[NEW]` line named in its PR.

## In which order?
- LE1 first. Then LE2 and LE4 side by side (the run's files; the render and the editor). LE3 after LE2. LE5 after LE2. LE5b after LE5. LE6 after LE5b and LE4, LE6b after LE6. LE7 to LE10d after LE6, one at a time: each adds its types' rows to the map. LE11 after LE5b. LE12 last.
- Shared sources (`style_designer.src.js`, `get_page_content.php`): check the open PRs first (`workflow/held-file-bundled-sources.md`).

## What does every step's PR carry?
- The measure before and after on both starting sites ("fully editable N of M", the reasons of the rest).
- For a step that changes what a visitor gets (LE2, LE5 to LE10): the sandbox pictures classic and moved, wide and phone, and Back to classic tried on one page.
- For LE3, LE4, LE6b and LE11: the Screen check at 1440 and 390 px against `legacy-editable-screens.md`.
- The twins changed together (editor and server), `php tools/check_bindings.php` and `check_canvas_samples.php` where a widget's tree is built.

## When does a step stop and ask the architect?
- A piece a visitor sees would be dropped without the designer's confirm; a setting the widget cannot hold on more than a few pages (a widget option may be the answer, and that is the owner's); a column or table seems needed; the Mobile Switch or PDF region is in the way.

Related: docs/memory/plans/legacy-editable.md, docs/memory/designer/legacy-to-designer-run.md
