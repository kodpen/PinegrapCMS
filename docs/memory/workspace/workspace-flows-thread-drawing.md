---
name: workspace-flows-thread-drawing
description: Data threads on the Flows canvas (T1, V6): read from the fields, the row under a box within its width, thread paths around boxes, tones, dimming, the toggle, the outline lines
---

## Where do the threads come from?
- `dataThreads()` in workspace_flow.js reads the `{{node.<id>.<key>}}` and `{{trigger.<key>}}` of each box's filled fields (`fieldTexts()`: `VALUE_FIELDS`, the same keys as `ws_flow_node_texts()`; a value condition's `rule.ref`, field `rule`). Nothing else stores them: a removed chip takes its thread.
- The source is `valueSources(node)` (the value picker's `valueGroups()`): a start box gives `trigger.*`; a value no box before gives has `from: ''` (no line, a red chip and outline line); one from a box that may be skipped is dashed. One thread per value and field, a value written twice in a field once.
- Test `test_workspace_flows_canvas_data_threads_come_from_the_fields`.

## How are they drawn?
- `drawData()` (end of `drawEdges()`, and `refreshNode()` as a field is typed in): under each box a row half over its bottom edge, the field chips it fills first, then the value dots it gives (18 px ring with `#` or `T`, its label). The row is `aria-hidden`.
- Never wider than its box (V6 of plans/workspace-flows-look.md, PR #3134): `dataRowSlots(fields, outs)` gives each item `{ left, width }` inside the 208 px box (8 px margins): full width (chip 76, dot 84) while all fit, else an equal share, a dot never under its ring (18 px), the label cut with an ellipsis. The width is `--wsf-w`; hovering an item shows its whole name over its neighbours; `title` holds it too (a keyboard reads names in the outline and "Pass to a field…"). Before, the row ran on (76 + 84 px each), so "Ask for approval" (four values) spread under its neighbours.
- Chips are ordered by the boxes they read from, dots by the boxes they reach, left to right. A value no thread takes is drawn only while its box is selected (last, dashed).
- A 1 px quadratic path from the dot to the chip's bottom, control point 10 px + 0.06 × horizontal distance below the lower end; threads of one dot 4 px apart, the farthest on the left; into one chip 6 px apart; threads between the same two boxes hang 4 px more each. `threadPath()`: a curve that would pass under another box (its rect and data row) hangs 16 px deeper at a time until it passes below, at most `DATA_ROW.deep` (160) more, else keeps its sag.
- Tests `test_workspace_flows_canvas_data_row_stays_within_the_box`, `_thread_bends_around_a_box`, `_data_row_drawing`.
- Types number and text, three tones each (`.wsf-tone-<type>-<n>`, `--wsf-tone`), taken in turn; dark uses the visual spec's colours, light deeper ones. Other types of the spec wait for outputs that declare them.
- A template's motion threads (`ws_flow_template_threads()`: from, to, setting) grow the paths drawn for them (`drawThreads()` matches `data-from`/`data-to`/`data-field`).

## Dimming, toggle, outline?
- `markThreads()` (from `markSelected()`): a box selected, threads and row items whose `data-ends` lack it get `.wsf-dim` (opacity .25; the fade only without reduced motion).
- "Data threads" (`threadsButton()`, toolbar, not in the outline view) hides them with `.wsf-threads-off` on the stage; kept per browser in localStorage `pg_wsf_threads`.
- Outline: `drawReads()` lists under each box's heading "<field> reads <box › value>", a missing one with `fl_values_missing`, a skippable one "(Empty if skipped)".

## How is a thread drawn into a field? (T2)
- `workspace-flows-thread-binding.md`.

## What is left?
- The red dashed thread of a deleted box. The derived value box's own fields take threads like any box's (workspace-flows-derived-box.md). Threads in the run view: workspace-flows-run-view.md.
