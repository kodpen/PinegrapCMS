---
name: workspace-flows-template-form-sort
description: Flow template form_sort (T14, PR #7505): a submitted form read, Pinegrap AI picks sales, support or spam, a task for each of the first two, spam ends, an unsorted form told
---

## What does it do?
- `form_sort` in `ws_flow_templates()` (category Site): form.submitted → Read a record (`form`, `{{trigger.record_id}}`) → Pinegrap AI box in classify mode on `{{node.read.answers}}`, outcomes Sales, Support, Spam → class_1 a high task, class_2 a normal task, class_3 an End box, `other` a notify to the person so no form is lost. No arrow on `failed`: an AI box past its hours fails the run and the owner is told.

## Why a read box before the AI box?
- The AI box fills only box and trigger values (`ws_step_fill_action()`), not the scheduled tokens such as `{{form_fields}}`, and form.submitted declares no payload outputs. Read a record gives the text answers one "name: answer" a line.

## When is it offered?
- With form.submitted in `events` and `recap_ai` in the fill (`ws_ai_ready()`): the AI box refuses to publish while Pinegrap AI is not set up.

## Who gets the tasks?
- Settings merge by type, so one person setting fills both tasks and the notify; the support task's person is changed on the canvas.

## Tests?
- `test_workspace_flow_templates_sort_incoming_forms()` in `tests/workspace_flow_templates_test.php`: graph, arrows against `ws_step_ai_arrow_outcomes()`, publish check, answers parsed to each arrow, the two gates. Not run live on a site with an AI key.
