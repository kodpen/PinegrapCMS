---
name: workspace-flows-box-try
description: Trying one box of a flow's draft without running it (F4e, PR #2024, ws_flow_box_try()): answers per kind, run values, rights, panel action, read-only API route, canvas button
---

## What does it do?
- `ws_flow_box_try($viewer, $flow, {node_id, run_id})` in `includes/workspace/steps.php` answers in words what one box of the **draft** would do: `said`, `refusal` (why it would fail), `outcome` (the arrow it would leave on), `texts`, `empty`, `next`. It never sends, writes or calls (no `ws_scheduled_do()`, step row or log line; a test reads its source).
- Step box: `ws_step_check()` with the owner, then `ws_step_try_texts()` (pure: `ws_step_fill_action()` plus `ws_step_fill_missing()`, the `{{node.*}}` a run gives no value for), `said` = `ws_scheduled_action_text()` of the filled action; a notify gets `Flow “name”: ` as the run adds it.
- With a chosen run the texts the run fills are filled too: `ws_step_try_scheduled()` applies `ws_scheduled_fill()` to the keys `ws_step_scheduled_texts()` names per kind, with the run's context built as `ws_step_run()` builds it: viewer = owner, row = `ws_step_action_row()` with `ws_step_run_anchor()`, plus `ws_step_trigger_context()`. So `{{record}}`, `{{event}}`, `{{channel}}` … read what started that run; a token left empty joins `empty`. `{{date}}`, `{{time}}`, `{{run}}` and `{{count:*}}` are filled as a run now would (counts with the owner's rights). Without a run they all stay raw; node values carry U+2060 after `{`, never read as tokens.
- Condition: `ws_flow_condition_check()`, then `ws_scheduled_rules_hold()` now (last day for a count), yes/no with the run's sentence. Start box: `ws_flow_anchor_check()`, the rule in words and up to three next times (`ws_flow_anchor_next_times()`, pure, none for an event or join). Manual, task (texts filled as the run fills them, `ws_flow_task_texts()`, #2919), end: a sentence.
- Value and loop boxes (#4103, #3982; before "It would fail: … cannot run yet."), never a `refusal`: `ws_flow_value_try()` gives constants as `texts` (`key: value`) and a derived box's worked-out value; `ws_flow_loop_try()` reads a For each list (`ws_flow_loop_items()`: N items, why not, or no run/value) and says what an End box gathers.

## Whose values and rights?
- Values: the settled boxes of a run the person chose (`ws_flow_run_for()`, same flow, else 404 `run_id`), built by `ws_flow_loop_try_values()`; without one every `{{node.*}}` is empty and listed in `empty`.
- A box inside a loop is tried at its last pass in that run, as the run view shows it (#4256): pass 0 and that pass plus `index`, `item`, fields of `items[pass-1]` (`ws_flow_loop_pass_values()`, shared with `ws_step_run_values()`). Before, item values read empty, the fallback applied and `{{node.X.index}}` was “Left empty” (#4147). Other boxes read every settled box, later rows win.
- Only the owner tries a box (`ws_flow_share_manager()`, 403, as publish): the checks and sentences use the owner's rights. Why: an edit-share colleague could read the owner's private task title or channel count back (#2024).
- The API refuses the application an assistant works through (`ws_api_is_assistant()`, 403): `'mcp' => false` alone let its REST key read records outside its channels. A `run_id` must also pass `ws_api_flow_run_reachable()`.

## Where is it offered?
- Panel action `ws_flow_box_try` (`includes/workspace/actions.php`), answer `{try: …}`. It needs its line in the ws_flow list of `includes/panel/actions.php`.
- API `GET /workspace/flows/{id}/boxes/{node}/try?run_id=` (`workspace.flows.box_try`, `workspace:read`, `'mcp' => false`), answer `WorkspaceFlowBoxTry` (`ws_api_flow_box_try_present()`).
- Canvas: `tryField()` and `drawTry()` in `workspace_flow.js`, under the box settings when `state.flow.manage`: a run list loaded on first focus; "Try this box" saves the draft (`flush()`) first.
- Tests: `workspace_flow_steps_test.php` (`…_try_*`), `workspace_flow_values_test.php`, `workspace_flow_loops_test.php` (`…_box_try*`).
