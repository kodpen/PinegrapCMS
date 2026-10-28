---
name: workspace-flows-value-picker
description: "Add value" in a Flows box's side panel (data-passing D6): the text boxes with value chips, what the list offers, "empty if skipped", how a chip is kept and edited
---

## Which fields have it?
- The texts a run fills (`texts` of `ws_step_kinds()`): post body, e-mail subject and body, notify text, task_open description, webhook text, digest subject and intro, report title, check-in question, approval text. `valueField()` in workspace_flow.js; it replaced the `fl_values_help` hint (now `fl_values_hint`). Titles (the box label) and the waiting task box (it fills no values at run time) keep a plain input.

## What does the list offer?
- `valueGroups(node)`: what started the run first (`trigger_outputs` of `ws_flow_js_config()`: the start kind's, an event box's by its event), then every box before the box along the arrows in `orderedNodes()` order with text, outcome, status and its `outputs` (a web check's by mode, a report's counts as `ws_step_outputs()` keys them). A start that gives nothing (manual, schedule) is left out; so are later boxes and the box itself.
- A box that may not have run is marked "Empty if skipped" (decision 3a of #2414): `sureBefore()` is a port of `ws_flow_sure_before()`, so the mark matches the `skippable_reference` warning. Test `test_workspace_flows_canvas_value_picker_offers_the_boxes_before`.

## How is a chip kept?
- The field is a `contenteditable` div (`valueInput()`); a value is a `span.wsf-chip` (`contenteditable=false`) with `data-token` = `{{node.<id>.<field>}}` or `{{trigger.<field>}}`, reading "Box name › Field". `valuesText()` saves the text with the tokens; `<br>` is "\n", a trailing one only shows the last line break. Single-line fields never take a line break.
- Saved, pasted and typed tokens are drawn as chips; one no box before gives keeps its token in a red chip (`.wsf-chip-bad`), a skippable one dashed in the warning colour.
- Enter inserts a `<br>` (`insertLineBreak`), paste is plain text, drop and Ctrl+B/I/U are refused; Backspace/Delete beside a chip removes it (Chrome does not delete a trailing non-editable span). Over the field's maximum the last text is drawn again.
- The list opens under "Add value" (`aria-expanded`); Esc closes it; a pick goes where the caret was, else at the end.
