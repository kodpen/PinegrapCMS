---
name: legacy-bootstrap-helper-steps
description: Steps LB1-LB7 of the Legacy to Bootstrap 5.3.8 helper: the census, the map and converter, the report, the trial design and its preview, moving pages and back, theme parts, palette and look
---

## How are the steps worked?
- One step is one PR of about 1,000 product lines, in order; a step waits for the one before it to land. A done step is marked with its PR number. What the helper is: `legacy-bootstrap-helper.md`.

## Which rules does every step keep?
- No schema change. The converter is pure functions with tests, the map one array (`includes/fn/legacy_bootstrap.php`).
- A step that changes what a visitor gets is seen in a sandbox, Legacy and trial side by side, wide and phone: a unit test cannot reach the cascade. The look on dev is the owner's.

## Which steps?
- LB1 (S, no product code) The census. A sandbox move of each set of the starting sites, then for each Legacy design: which Bootstrap 3 classes and components, which theme classes and scripts it uses, how much the map would cover. One design tried by hand with 5.3.8 in place of the classic framework sheet, pictures side by side, wide and phone. Findings into `designer/legacy-bootstrap-census.md`; the architect asks the owner the plan's questions from it, and LB6 and LB7 are cut again.
- LB2 (M) The map and the converter. `includes/fn/legacy_bootstrap.php`: `pg_lbs_class_map()`, `pg_lbs_tree()` (a tree in, a tree and counts out, the parts' refs untouched), `pg_lbs_css()` (selectors renamed, the rest byte for byte), `pg_lbs_report()`. Tests on the starting sites' frames; a second pass changes nothing.
- LB3 (M) The card and the report. "Towards Bootstrap 5" on Move to the Page Design Studio: the Legacy designs, Report per design (converted, kept, by hand, the files left out). Nothing written.
- LB4 (L) The trial design. "Make a trial design": the row (`bootstrap5`, the Theme file named), the converted parts, the asset rows without the classic framework sheet, own copies of the sheets whose selectors changed. A page previewed in it beside the Legacy one. "Delete the trial design" while no page uses it.
- LB5 (L) Move pages and back. All or chosen pages: the revision, the converted tree, the design. Lock, cursor and kept report as the bulk run (`designer/legacy-to-designer-run.md`, reports in `data/legacy_to_bootstrap/`); the refusals of Back to classic (an open experiment, a held page). "Send back to Legacy", one page or all.
- LB6 (L, cut again after LB1) Theme parts to designer twins: slider, photo backgrounds, lightbox, navbar. A part with no twin stays with its script.
- LB7 (M, after the owner's answer) The theme as a palette and a look: colours and fonts read from the theme file, saved as `file-<id>`, offered on the trial design; once worn, the Theme file can be taken off in the editor.

## What is not in this plan?
- The two open faults of a Legacy design itself, a style moved alone without asset rows and the photos in `.background-image-holder`: `designer/legacy-overview.md`. LB6 only gives the trial design its own photo backgrounds.
- Bootstrap 6 on sites: a later key of the registry (`designer/design-framework.md`).
- Joining the designs moved one by one to their set's design: never proposed (#8671).
