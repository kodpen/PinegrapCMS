---
name: workspace-flows-chain-box
description: The start a flow box (PR #2235, flow_runs.php): another live flow started by hand, started by a box with the owner's rights, depth 8, start key per box, {{source}}, what non-editors read
---

## What is it?
- Kind `start_flow` in `ws_flow_kinds()` (not a step, not a start), outcomes done, failed, output `run_id`. Settings `{flow_id}` (`ws_flow_chain_normalize()`); no flow is a `bad_config` problem. It is the parity rows "by hand or chain only" (`trigger`) and `{{source}}`, `{{source_result}}` of a flow. Tests `tests/workspace_flow_chain_test.php`.
- It does not wait for the run it starts: done once the run is written.

## What refuses a start?
- `ws_flow_chain_refusal()` (pure): the flow itself (flows have no loops; a self-retry is part 3d), gone, not live, a start box other than `manual` (flows that start on their own are not chained), and the run started would be the eighth deep (`depth + 1 >= WS_SCHEDULED_CHAIN_DEPTH`), as a scheduled chain stops at 8. Sentences: `ws_flow_chain_refusal_text()`.
- Publish and box try: `ws_flow_chain_check()` with the owner: `ws_flow_access()` run or edit on the flow started, then the refusal at depth 0.
- Run: `ws_flow_step_run()` case `start_flow`, owner on the staff, then `ws_flow_chain_start()`: began mark first, access and refusal again, the started flow's 500 open and 60 an hour (`ws_flow_start_refusal()`).

## What does the run started get?
- start_key `ws_flow_chain_key()` = `flow:<run>-<box>-<pass>`: a box carried out twice, or retried by hand after the run was written, finds the run (also on a 1062).
- started_by the source run's actor (the source flow's owner, so a change box asks that person), actor the started flow's owner, depth +1, start box done "Started by the flow “X”.", a Site Log line.
- Context `ws_flow_chain_context()`: trigger `chain`, the manual context of the flow started, the source run's event and newcomer carried on (`ws_step_trigger_context()`), `source` (the source flow's name) and `source_result` (`ws_flow_chain_source_result()`: the sentence of the box done whose arrow led in). Its boxes read them as `{{source}}`, `{{source_result}}`.

## Who reads what?
- The run sentence names the flow started; `ws_step_result_shown()` gives non-editors "Another flow was started." or "could not be started.".
- Canvas: `chainFields()`, `chainTargets()` (the person's flows at run or edit, not the open one) in `workspace_flow.js`.
