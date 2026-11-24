---
name: workspace-flows-value-picker
description: "Add value" in a Flows box's side panel (data-passing D6, FE5): the button inside the field, what the list offers (Recipient, Always), "empty if skipped", how a chip is kept and edited
---

## Which fields have it?
- The texts a run fills (`texts` of `ws_step_kinds()`): post body, e-mail subject and body, notify text, task_open and task box description, webhook text, digest subject and intro, report title, check-in question, approval text. `valueField()` in workspace_flow.js. Titles (the box label) keep a plain input.
- No hint under the field (FE5): the list's first line says what a value is (`fl_values_what`). Before, `valuesHint()` repeated a paragraph under every value field.
- "Add value" is a 24 px icon button (`bi-braces`, `.wsf-values-add`) at the field's bottom right, inside `.wsf-values-box`; the text keeps clear of it.

## What does the list offer?
- In order: the first line, "Recipient" (an e-mail box: `workspace-flows-panel-fields.md`), the groups of the boxes before it, "Always". A field with `takes` (the webhook's address takes numbers) gets neither Recipient nor Always.
- "Always" (`alwaysWords()`): `{{date}}` and `{{time}}` in `WORD_KINDS` (the keys of `ws_step_scheduled_texts()` plus `task`, whose texts the run fills), with `{{weekday}}` in a derived value box; none elsewhere. A pick types the word as text. Only there: a derived value box once offered them, and `{{date}}` reached a table cell as typed. Test `tests/workspace_flow_values_hint_test.php`.
- `valueGroups(node)`: what started the run first (`trigger_outputs` of `ws_flow_js_config()`: the start kind's, an event box's by its event), then every box before the box along the arrows in `orderedNodes()` order with text, outcome, status and its `outputs` (a web check's by mode, a report's counts as `ws_step_outputs()` keys them). A start that gives nothing (manual, schedule) is left out; so are later boxes and the box itself.
- An empty list says why (`valuesNone()`, PR #8384): with boxes or a start before it, it names them ("What comes before this box gives no value here: Manual start"); only with nothing before it does it ask to connect it (`fl_values_none`). It used to ask a box behind a manual start to be connected.
- A box that may not have run is marked "Empty if skipped" (decision 3a of #2414): `sureBefore()` is a port of `ws_flow_sure_before()`, so the mark matches the `skippable_reference` warning. Test `test_workspace_flows_canvas_value_picker_offers_the_boxes_before`.

## How is a chip kept?
- The field is a `contenteditable` div (`valueInput()`) with `display: block` on `.wsf-values`: Bootstrap 6's `.form-control` is a flex row, which stacked text letter by letter and stretched chips to the field's height (PR #4275, #3977); a value is a `span.wsf-chip` (`contenteditable=false`) with `data-token` = `{{node.<id>.<field>}}` or `{{trigger.<field>}}`, reading "Box name › Field". `valuesText()` saves the text with the tokens; `<br>` is "\n", a trailing one only shows the last line break. Single-line fields never take a line break.
- A value typed whole becomes its chip at once (`typedChips()` in `valueInput()`, through the pure `valueParts()`), the caret after it.
- Saved, pasted and typed tokens are drawn as chips; one no box before gives keeps its token in a red chip (`.wsf-chip-bad`), a skippable one dashed in the warning colour. Clicking a chip sets its "When empty, write" fallback, kept in the token (`workspace-flows-empty-fallback.md`); a skippable chip with one is no longer dashed.
- Enter inserts a `<br>` (`insertLineBreak`), paste is plain text, drop and Ctrl+B/I/U are refused; Backspace/Delete beside a chip removes it (Chrome does not delete a trailing non-editable span). Over the field's maximum the last text is drawn again.
- The list opens under "Add value" (`aria-expanded`); Esc closes it; a pick goes where the caret was, else at the end.
