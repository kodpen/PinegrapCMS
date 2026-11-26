---
name: designer-cleanup-removal
description: "Remove" of the Clean up window (CU3): the snapshot first, what the server re-checks and removes, rules and variables saved by the editor, the closing line, rows that stay
module: design_cleanup_remove
---

## What happens on "Remove {n}"?
- No second question. The editor first writes the local snapshot "Before clean-up · <day>" (tree, the asset fields with the stylesheets, the shared cache, and `vars`: the stored variables model the list answer carries as `model`). If the browser does not keep it, nothing is removed and the closing line says so.
- Then `designer/cleanup_remove` (`style_id`, `ticked`: kind => ids, variables by key; full designers, a dirty sub-action of `page_cache.php`) answers `pg_cleanup_remove()` (`includes/fn/design_cleanup_remove.php`).
- While it runs the button shows a spinner and "Removing…", the boxes are off; the window stays open and the lists are read again.

## What does the server remove?
- It reads the lists again (`pg_cleanup_design_lists()`) and takes only what is still listed (`pg_cleanup_remove_pick()`); anything else fails as `changed`.
- A rule is known by its id `<sheet place>-<start>-<md5 of its text, 10>` (`pg_cleanup_rule_id()`), so a rule that moved or changed is never cut. Rule rows carry `id`, `index` (place in `style_custom_css`), `start`, `end`.
- Files go to the Recycle Bin as the Files screen does (`pg_cleanup_bin_file()`: restore record, parked name, audit; refused without a bin). Shared components are deleted as the palette's bulk delete without `force` (rows, grants, audit, activity).
- Rules and variables are not written there: the answer carries `css` (the asset list with the rules and their line end cut, `pg_cleanup_cut_rules()`) and `variables` (the stored model without those own variables, `pg_cleanup_drop_variables()`).

## How do the rules and variables leave?
- The host (`_sdCleanupHost()`) puts `css` into `sd-custom-css-field` and the model into `sd-style-variables`, then saves as Save does (`saveNow`). A failed save puts both fields back and those rows stay with "The design could not be saved, so it was kept."
- Load of the snapshot (History, Local snapshots) restores the asset fields and, from `vars`, the Variables input: the rules and variables come back as unsaved work. Files and components do not: the Recycle Bin and nothing.

## What is seen after?
- The closing line is the Design examples' Result line over the lists: "Removed 10: 5 style rules, 2 files, 1 shared component, 2 variables. 1.2 MB lighter." or "Removed 9 of 10: …" (amber), then where the snapshot is kept.
- A row that stayed is listed with a warning icon, its reason and no box (`PgDesignCleanup.keep()`: a rule read again by sheet, media and text, else put back at its list's end); its list opens.

Tests: `tests/design_cleanup_remove_test.php`.
