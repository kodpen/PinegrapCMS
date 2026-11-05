---
name: workspace-flows-for-each-count
description: Repeat N times (For each count mode, FL2) and value boxes inside a loop body: settings, items 1..N, checks, the pure-box loop rule, the reader's pass, a Wait box per round, canvas
---

## What is Repeat N times?
- A mode of the For each box (`workspace-flows-for-each.md`), not a new kind (`workflow/decisions-made-product-30.md`): `mode` `list` (default) or `count`; `count` is a whole number from 1 to `WS_FLOW_LOOP_ITEMS_MAX` (50) or one value alone (`ws_flow_loop_normalize()`). In count mode `fields` is emptied; `list`/`path` stay but are not read.
- `ws_flow_loop_source_key()` names the setting read: `ws_flow_node_texts()` gives only that one, so the reference check and the fill never look at the other.
- Items: `ws_flow_loop_count_items()` gives 1…N, kept as `items` JSON like a list, so `index` = `item` and the End box, pass rows, the 50 cap, `WS_FLOW_MAX_BOX_RUNS` and the side-effect cap work unchanged.

## What is checked?
- Publish: `ws_flow_loop_count_problem()` in `ws_flow_loop_problems()`: empty, out of range or not a number is `bad_config`; a value with a "When empty, write" text too (read as it is, like a list).
- Run: `ws_flow_loop_start_run()` fills the value and fails the box with the reason when it is not 1…50 (on failed when an arrow leaves on it). Box try: "Repeats N times." or the reason.

## May a value box read a box inside a loop?
- Yes when every box reading it (directly or through other value boxes) is in that loop's body: `ws_flow_loop_pure_of()` gives each `value`/`constants` box the loop of its readers ('' when read inside and outside, in two loops, or by nothing), and the reference check uses that as the box's own loop. Read from outside too: `bad_reference` as before.
- No runner change: `ws_step_run_values()` already gives `ws_flow_pure_values()` the values of the reader's pass (`ws_flow_loop_pass_values()`), so a derived box is worked out per round.

## A faster pace?
- The 5-minute schedule floor stays; a Wait box inside the body gives a minute per round. `ws_flow_steps_recheck()` asks no pass and `ws_flow_delay_recheck()` settles its own row, so a wait at pass > 0 wakes; the next item waits for it (`ws_flow_loop_walk()`).

## Canvas and API
- `loopFields()`: a mode select ("Go through": each item of a list / a number of times), then the list form or the count field (`valueField()`, takes a number value, no fallback). `fieldTexts()`/`openFields()` offer only the setting of the mode; the box reads "Repeat N times" (`fl_loop_count_meta`).
- Catalogue: `for_each.mode` values; Pinegrap AI hint on `for_each.count` (`ws_flow_ai_catalogue_hints()`).
- Tests: `tests/workspace_flow_loops_test.php` (count mode, the pure-box rule, the Wait box, the decision's counter line as one flow), `test_workspace_flows_canvas_for_each_count`.
