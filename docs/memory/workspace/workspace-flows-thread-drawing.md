---
name: workspace-flows-thread-drawing
description: Data threads on the Flows canvas (T1, T2): read from the fields, chips and dots under a box, tones, dimming, the toggle, the outline lines, drawing a thread into a field
---

## Where do the threads come from?
- `dataThreads()` in workspace_flow.js reads the `{{node.<id>.<key>}}` and `{{trigger.<key>}}` of each box's filled fields (`fieldTexts()`: `VALUE_FIELDS`, the same keys as `ws_flow_node_texts()`; a value condition's `rule.ref`, field `rule`). Nothing else stores them: a removed chip takes its thread.
- The source is `valueSources(node)` (the value picker's `valueGroups()`): a start box gives `trigger.*`; a value no box before gives has `from: ''` (no line, a red chip and outline line); one from a box that may be skipped is dashed. One thread per value and field, a value written twice in a field once.
- Test `test_workspace_flows_canvas_data_threads_come_from_the_fields`.

## How are they drawn?
- `drawData()` (end of `drawEdges()`, and `refreshNode()` as a field is typed in): under each box a row half over its bottom edge, the field chips it fills first (76 px), then the value dots it gives (84 px: an 18 px ring with `#` or `T`, its label). The row is `aria-hidden` and takes no pointer.
- A 1 px quadratic path from the dot to the chip's bottom, control point 10 px + 0.06 × horizontal distance below the lower end; threads of one dot 4 px apart, the farthest on the left; into one chip 6 px apart; threads between the same two boxes hang 4 px more each.
- Types number and text, three tones each (`.wsf-tone-<type>-<n>`, `--wsf-tone`), taken in turn; dark uses the visual spec's colours, light deeper ones. Other types of the spec wait for outputs that declare them.
- A template's motion threads (`ws_flow_template_threads()`: from, to, setting) grow the paths drawn for them (`drawThreads()` matches `data-from`/`data-to`/`data-field`).

## Dimming, toggle, outline?
- `markThreads()` (from `markSelected()`): a box selected, threads and row items whose `data-ends` lack it get `.wsf-dim` (opacity .25; the fade only without reduced motion).
- "Data threads" (`threadsButton()`, toolbar, not in the outline view) hides them with `.wsf-threads-off` on the stage; kept per browser in localStorage `pg_wsf_threads`.
- Outline: `drawReads()` lists under each box's heading "<field> reads <box › value>", a missing one with `fl_values_missing`, a skippable one "(Empty if skipped)".

## How is a thread drawn into a field? (T2)
- In a draft every drawn dot, and every value of the selected box (`boxValues()`, unused dashed), is `.wsf-data-live`. Dragging one (`drag.kind` `thread`), or two taps on it (the captured pointer sends no dblclick), or its menu's Fork (`K.onContext`: right-click, long press) sets `state.binding` and lights the fields it may go into (`.wsf-data-target`, `data-bind`); the next tap or the drop binds, anything else or Esc lets it go.
- `bindTargets(from, token)`: the `openFields()` of every box `valueSources()` lets read it from that box, none already holding it; addresses and recipients are not in `VALUE_FIELDS`, so never lit. A condition's only while its value rule compares nothing.
- `bindValue()` writes the token at the field's end after a space (the chip "Add value" draws), refuses it over the field's maximum (`fl_thread_full`); a condition's `rule.ref`, its op back to "is" when it does not fit the type.
- Keyboard: V on a box, or the menu's "Pass to a field…": `askBind()`, a value and a field.
- Test `test_workspace_flows_canvas_thread_binds_into_the_fields_after`.

## What is left?
- The red dashed thread of a deleted box. The derived value box's own fields take threads like any box's (workspace-flows-derived-box.md). Threads in the run view: workspace-flows-run-view.md.
