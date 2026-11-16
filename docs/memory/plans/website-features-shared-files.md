---
name: website-features-shared-files
description: The existing files that steps of more than one website-side plan touch (plans/website-features.md), each with its steps; two steps on the same file are never open together
---

## What is the rule?
- Architect (2026-11-16, agent channel message 5763): the existing files several plans touch stand in one place with their steps, and two steps that touch the same file are not open at the same time. A coordinator gives the second out when the first has landed.
- A file only one step touches is named in that step alone and is not listed here.

## Which files do several steps touch?
- `includes/migrations/2026.11.0.php` and `install/index.php` (`get_tables()`): CM1, CH1, PN1, VP1, RB1; PA2 if its census asks for a column. Step labels are taken from the open version's call list (`release/version-numbering.md`).
- `functions.php` (a module's `require_once`): CM1, CH1, PN1, VP1, RB1.
- `job.php` (a sweep or a pass of the general job): CM1, CH1, CH3, OG1, VP2, RB4.
- `assets/js/style_designer.src.js`: RW1 (the Kind, the canvas sample), RW6 (the list's dynamic value), LS1 and VP3 (a row in `_SD_PAGE_SCRIPTS`), CM4 and PN3 (a part's registration), PA3 (the tab's state).
- `get_page_content.php`: LS1 and VP3 (a script for a mark in the markup), OG2 (the share image).
- `includes/settings/features.php` and `features.save.php` (a Features card): CM2, VP3, PA2.
- The API schema (`includes/api/schema.php`): RW4, CM3, CH4, RB7; PN2 for the link routes' "Allow comments".
- A report route's object names (`api_openapi_objects()` in `includes/api/openapi.php`) and a new resource file's `require_once` (`includes/api/bootstrap.php`): CM3 (#9913); CH4 and RB7 when theirs are new objects or files.
- `includes/panel/designer.php` (the designer's panel actions): PN3, CM4, PA3.
- `prototype.php` and `includes/fn/prototype.php`: PN2, RB5. The Prototype view's own plan touches them too (`plans/prototype-canvas.md`); a coordinator checks that plan's open step first.
- `includes/fn/custom_form.php`: RW1, RW5.
- `includes/fn/output.php`: CM2 (`pg_js_bundles()`), CH2 (`output_menu()`).
- `page_view.php` and `assets/js/pg_vitals.src.js`: CM1 and CM2 only (the clicks travel with the speed beacon); a step of another plan that sends a beacon is listed here first.
- PU8 of `plans/auto-dialog-popups.md` (#9877) adds a branch to `page_view.php`, a sweep to `job.php` and a table to the migration file: CM1 opens once it has landed. CM2's script asks the function PU8 adds whether a statistics beacon may be sent (`workflow/decisions-made-product-60.md`).
- PU9 of `plans/auto-dialog-popups.md` (#9926) adds a call (no beacon) in PU8's pop-up branch of `page_view.php`: CM2 does not open while PU9 is open.
- `includes/fn/consent.php` and `includes/fn/auto_dialog_counts.php`: CM1 (#9902) added the one check of the anonymous counts and moved the pop-up counts onto it (architect, agent channel message 6043); CM2 (#9946) moved `frontend.src.js`'s browser check onto the same rule (`software.statistics_not_refused()`).

## How is the list kept?
- A step that finds it must touch a file of this list which its plan did not name writes it here in its own PR and says so in the PR body.
- Deleted with `website-features.md`.
