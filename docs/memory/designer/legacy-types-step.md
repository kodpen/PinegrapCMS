---
name: legacy-types-step
description: The move run's types step (LE5b): a moved page's own System region becomes its page type's widget from a starter built in the browser at Convert; the "Page types" dry run row
module: legacy_to_designer_run, legacy_to_designer_plan
---

## What does the types step do?
- Last step of every style, alone and in a set (`pg_ltd_steps()`): `pg_ltd_run_types()` reads each moved page whose tree still holds its own System region (`pg_ltd_tree_has_own_system()`), a set's pages by their `origins`.
- Only a `convert` target (`pg_ltd_type_target()`: stock layout or a file dropping nothing, every setting held) is converted; `held` and `file` pages stay System regions (`legacy-stock-types.md`). A file target's tree comes from `pg_ltd_type_file_widget()` ([[layout-type-converter]]).
- The widget (`pg_ltd_type_widget()`, pure): settings `regionType` plus `pg_layout_widget_seed()`; layout the kind's kept starter through `pg_layout_widget_tree_apply()`. It takes the region's place (`pg_ltd_type_splice()`, the region's `_id` kept) and the page is saved through `pg_ltd_save_page()`.
- One widget a page, named `<page-slug>-<kind-slug>-widget` (`pg_ltd_widget_name($page_name, $kind)`); a resumed step finds it again by name and settings (`pg_ltd_widget_row()` with the config and tree passed in).

## Where do the starter layouts come from?
- Architect's answer: `legacy-run-starters.md`. The Convert form (`#ltd_convert`, `data-kinds` = `pg_ltd_plan_type_kinds()`) loads `style_designer.min.js` only when pressed, asks the editor's texts (`ajax=1`, `step=i18n`, token checked, `pg_designer_i18n_map()`), calls `StyleDesigner.starterTree(kind)` and posts the trees as `starters` (`pg_ltd_convert_script()`).
- The screen keeps only the kinds its dry run converts and trees rooted at `root` placing no shared component, 8 MB at most (`pg_ltd_starters_clean()`); `pg_ltd_run_start()` passes each through `_validate_and_clean_tree_json()`.
- Kept in `data/temp/legacy_to_designer_starters.json` (`pg_ltd_starters_path()`) beside the cursor; removed when the run ends or the next starts.

## What if a kind has no starter?
- The page keeps its System region and the report says so ("… keeps its System region: its widget's layout did not come with Convert."). Never an empty widget.
- If the editor script cannot load, the screen says so; the next Convert press moves without starters.

## What does the dry run show?
- "Page types" under Becomes editable: the `convert` pages by name (`pg_ltd_plan_groups()`, key `types`).

## Tests?
- `tests/legacy_editable_types_test.php`: kinds, posted trees dropped, settings put in, a missing kind, the splice, the row, the wiring. `tests/legacy_to_designer_run_test.php`: the steps with `types`.

Related: docs/memory/designer/legacy-stock-types.md, docs/memory/designer/legacy-to-designer-run.md, docs/memory/plans/legacy-editable-steps.md
