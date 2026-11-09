---
name: workspace-flows-row-choice
description: How Read a row (row_get) and Change a row (row_set) find their row (PR #8384): by its number in the table, its id or the last row; what the row field takes; one check; where its values come from
---

## Why is there a choice?
- On dev (2026-11-09), the owner's flow UNNN read the row "1" of the table Fırın. The box took "1" as the row's id, but the table screen numbers rows by `position` (1, 2, 3) and shows no ids. The table's only row had the id 14.
  - So the box was done with found 0 and empty cells, and the next post box failed: "Mesaj boş".
  - A typed "0" was refused with "names its row by its id or by one value", which did not say what was wrong.
- The owner left the ways to the session (`workflow/decisions-made-product-40.md`, plan `plans/tables-relational.md`).

## Which ways are there?
- `by` in both boxes' settings (`ws_step_row_way()`, `ws_step_row_bys()`):
  - `id`: the first of the list, so a box saved before the choice, or sent over the API without `by`, keeps its meaning. An unknown word is also `id`.
  - `position`: the row's number as the table lists it, `ws_wtable_row_at()`. When two rows share a number (two rows added at once), the lower id wins.
  - `last`: the bottom row, `ws_wtable_row_last()` (position, then id, descending). The row text is emptied.
- `ws_step_row_find()` is the single reader, used by `ws_step_row_get_do()` and by the try loop of `ws_step_row_do()`. The loop finds the row again on every try, because a row named by its number may move.
- A new canvas box: Read starts at `position`, row "1", and passes its own check. Change starts at `position` with no row, so a write never lands on row 1 unasked.

## What does the row field take?
- One rule, `ws_step_row_text_fault()`. Its result is '', `empty`, `below_one` (0 or less) or `not_row`.
  - It accepts a whole number from 1, leading zeros allowed, at most ten digits (`ws_step_row_id()`, the same parser the run uses after the fill).
  - It also accepts one value alone, a `{{…}}` with a fallback allowed.
- Three places word that result:
  - the editor's problems name the box (`ws_step_row_problem()`);
  - publish, run and try refuse without the name (`ws_step_row_refusal()`);
  - a missing row is reported by `ws_step_row_missing_text()`.
- Change by `id` refuses a typed id with no row at publish, since an id never comes back. A number or the last row may be there later.
- Read leaves a missing row done with found 0 ("… has no row 3. Nothing was read.").
- Templates keep a row number and clear only a typed id (`ws_flow_template_table_cleared()`).

## Where do its values come from?
- "Add value" (`valueGroups()`) offers three kinds of values:
  - the start's trigger outputs (manual and schedule give none);
  - the boxes before it along the arrows;
  - value boxes.
- With nothing to offer, `valuesNone()` names what comes before the box ("Elle başlatılır"). Before, it asked to connect a box that was already connected.
- The help (`rowByField()`) names the found value with its label from `FL.outputs.row_get`. Before, the Turkish text had the raw word "found" in it.

## Tests
- `tests/workspace_flow_row_choice_test.php`, the row read and write tests, `test_workspace_flows_canvas_row_choice`, `test_workspace_flows_canvas_values_none_names_the_start`.
