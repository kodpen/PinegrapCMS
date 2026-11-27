---
name: bug-hunt-areas-designer-home
description: Bug hunt of the Page Design Studio home and the New design window (2026-11-27): the four faults fixed, the one written as an issue, what was read clean
---

## Which area was it?
- The Page Design Studio home and the New design window: `assets/js/designer_home.js`, `view_system_styles.php`, the home functions of `includes/designer_screen.php` and the sub-actions `design_publish`, `design_rename`, `design_delete`, `template_from_design` in `includes/panel/designer.php`. `designer_screen.php` and `includes/fn/designer.php` were read, not changed: open PRs held them.

## What was fixed?
- "Put on the site" drew every page chip as live, though a draft a prototype link holds stays a draft: `design_publish` answers `draft_ids`, and `chipDraft()` paints the chips from it.
- A refused name in the rename box was sent again each time the box lost the focus, which took the focus back; on a phone (no Escape) only emptying the box got out. Leaving the box with the refused name now puts the old name back (`renameStep()`); Enter still tries again.
- `?start=` stayed in the address, so a reload, the one after deleting the large design among them, opened the New design window again: the script drops it once read (`withoutStart()`).
- Deleting a design under a typed search gave the focus to a design the search hid, and the "No design matches" line did not come when the last match went (`nextShown()`; the search runs again).
- Tests: `tests/designer_home_js_test.php` (Node, `window.PgDesignerHome`).

## What became an issue?
- #12019: a classic style whose only Page Design Studio page is in the Recycle Bin stays on the home (and in the design chooser) as an empty design whose Delete is refused: the `EXISTS` on `page_tree_json` in `pg_designer_list_designs()` and `pg_designer_design_targets()` has no bin filter.

## What was read clean?
- Menu rows and rights: every row the menu offers is gated again on the server (the API's full-designer allow-list, `pg_design_template_from_style()` for role ≤ 1); F2 and Delete check `canChange`.
- A deleted design leaves `style` (or goes to the bin with its row deleted), so the home never lists it; the large card's delete draws the page again.
- The first visit's three starts, the narrow list and pane with its history entry, the chips' fitting on a phone.
