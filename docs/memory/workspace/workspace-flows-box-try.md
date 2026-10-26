---
name: workspace-flows-box-try
description: Trying one box of a flow's draft without running it (F4e, PR #2024, ws_flow_box_try()): answers per kind, run values, rights, panel action, read-only API route, canvas button
---

## What does it do?
- `ws_flow_box_try($viewer, $flow, {node_id, run_id})` in `includes/workspace/steps.php` answers in words what one box of the **draft** would do: `said`, `refusal` (why it would fail), `outcome` (the arrow it would leave on), `texts`, `empty`, `next`. It never sends, writes or calls: no `ws_scheduled_do()`, no step row, no log line (the test reads its source for that).
- Step box: `ws_step_check()` with the owner, then `ws_step_try_texts()` (pure: `ws_step_fill_action()` plus `ws_step_fill_missing()`, the `{{node.*}}` a run gives no value for), `said` = `ws_scheduled_action_text()`; a notify gets `Flow “name”: ` as the run adds it. `{{date}}` and the rest stay unfilled, as `ws_scheduled_preview()` leaves them.
- Condition: `ws_flow_condition_check()`, then `ws_scheduled_rules_hold()` now (last day for a count), yes/no with the run's sentence. Start box: `ws_flow_anchor_check()`, the rule in words and up to three next times (`ws_flow_anchor_next_times()`, pure, none for an event or join). Manual, task (title filled), end: a sentence.

## Whose values and rights?
- Values: the settled boxes of a run the person chose (`ws_flow_run_for()`, same flow, else 404 `run_id`); without one every `{{node.*}}` is empty and listed in `empty`.
- Only the owner tries a box (`ws_flow_share_manager()`, 403, as publish): the checks and sentences use the owner's rights. Why: the first version let an edit-share colleague try one, and a condition on the owner's private task or a count on their private channel read its title, count or name back (merge review of #2024).
- The API refuses the application an assistant works through (`ws_api_is_assistant()`, 403): Claude's routine calls REST with its key, so `'mcp' => false` alone let it read records outside its channels. A `run_id` must also pass `ws_api_flow_run_reachable()`.

## Where is it offered?
- Panel action `ws_flow_box_try` (`includes/workspace/actions.php`), answer `{try: …}`. It needs its line in the ws_flow list of `includes/panel/actions.php`, or the panel refuses it.
- API `GET /workspace/flows/{id}/boxes/{node}/try?run_id=` (`workspace.flows.box_try`, `workspace:read`, `'mcp' => false`: it reads counts and records with the owner's rights), answer `WorkspaceFlowBoxTry` (`ws_api_flow_box_try_present()`).
- Canvas: `tryField()` and `drawTry()` in `workspace_flow.js`, under the box settings when `state.flow.manage`: a run list loaded on first focus, "Try this box" saves the draft first (`flush()`), then asks.
- Tests: `tests/workspace_flow_steps_test.php` (`…_try_texts`, `…_try_anchor_times`, `…_try_writes_nothing`, `…_try_is_the_owners`, `…_try_refuses_assistants`).
