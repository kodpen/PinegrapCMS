---
name: workspace-flows-change-box
description: The change box of a flow (F4b3, PR #1957): the scheduled change step as a box, its settings, rights, what it writes, and why its record and values reach only the flow's editors
---

## What is it?
- Kind `change` in `ws_step_kinds()` (type change): a scheduled action's change step, checked by `ws_scheduled_action_input()` (kinds of `ws_change_types()` but `ws_scheduled_change_skipped()`, update or delete, `ws_change_allowed()`, the record must exist, each field through `ws_change_value()`) and written by `ws_scheduled_change()` → `ws_change_write()`, never a writer by name.
- Settings (`ws_step_config_normalize()`): record, action (update unless delete), id, fields (up to 30 name => value; a value is a number, yes/no, a text, or a set of texts for a submission's answers; a delete keeps none). `ws_step_config_problems()`: kind, record, and a field for an update.
- Canvas form `changeFields()` in `workspace_flow.js`: the kinds the builder may change come from `ws_scheduled_js_config()['changes']` as `steps.changes` of `ws_flow_js_config()`; the record is typed as its number (no picker yet); money is shown as a decimal and kept in minor units.

## Whose rights, which channel?
- The owner's, at publish and again as the box runs (`ws_step_run()`), like every step box.
- As it runs, also the starter's (F4f, PR #2070): `ws_step_starter_refusal()` checks the change again with `ws_change_viewer_for_id($run['started_by'])`; a starter who may not make it, no longer exists or has left the team (`member` empty) fails the box with "The person who started the run may not make this change.". A run whose starter is its owner (`actor_id`), as every anchor start, is unchanged; other box kinds keep the owner's rights alone. Why: a run share let a colleague have a change made with the owner's rights, a person's role included. Try-a-box (`ws_flow_box_try()`) is the owner's alone and does not ask it.
- The step array gets `channel_id` 0: `ws_scheduled_change()` posts its decision under a scheduled action's card in that action's channel; a box has neither, so no decision message is posted. Its Site Log line still reads "scheduled action “<flow name>”" (the source is `scheduled`); a flow's own wording needs a change in scheduled.php.

## Who reads what?
- A change box names a record the reader may not open and values that may be a customer's data. `ws_flow_graph_present($graph, $edit)` gives a reader without edit the kind, the action and the field names only (id 0, values ''), on the screen, the API and a run's graph, as it masks a hook's address (`workspace-flows-steps.md`).
- In a run, `ws_step_result_shown()` (through `ws_api_flow_run_step_present($step, $graph, $edit)`) replaces a change box's sentence for a non-editor with "The change was made." or "The change could not be made.". The owner's end notice and `{{node.ID.text}}` keep the whole sentence.
- Tests: `tests/workspace_flow_steps_test.php` (`…_change_config`, `…_change_is_kept_back`), `tests/workspace_flow_change_starter_test.php`. The parity table already maps the change step and its kinds to this box.
