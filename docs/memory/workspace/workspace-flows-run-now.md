---
name: workspace-flows-run-now
description: Run now on the canvas of a live flow that starts at a time (PR #8443): who sees it, how it starts the flow through its anchor by hand, the token that makes a second click find the run, what is refused
---

## What is it?
- A live flow whose live version starts with the "At a time" box (`schedule`, start column `time`) shows Run now in place of Run on its canvas (`runButton()` in workspace_flow.js, icon lightning). Plan step 2 of `plans/workspace-scheduling-one-path.md`; owner decision `workflow/decisions-made-product-41.md` (Run now moves to the flow's own screen, time starts only).
- Event, join, row, mention, web and no-order starts get none (owner: time only); they wait for what they wait for. A flow started by hand keeps Run.

## Who sees it?
- Whoever may change the flow (`ws_flow_access()` edit: the owner, an edit share, any staff on a converted flow). A run share, a view share and a home channel's reader get no button; before this step they saw Run, which the server refused ("not started by hand").
- The server says it: `ws_flow_detail()` gives `run_now` (`ws_flow_run_now_offered()`), read only for a live flow that starts at a time and that the person edits, so no other flow costs a read.

## What does it refuse?
- `ws_flow_run_now_refusal()` (pure): `access` (not edit), `not_live` (paused, draft, never published, binned), `not_time` (any other start or none), `over` (the anchor is not active or failed: a paused anchor, or a flow that starts once and has run).

## How does it start the flow?
- Asked first (`fl_run_now_ask`, Start the run), as Run is; no customer picker. Panel action `ws_flow_run_now` (flow_id, start_token) → `ws_flow_run_now()` → `ws_scheduled_execute(anchor, true, {start_key})`: by hand, through the hidden or converted anchor, as a converted card's Run now does. The run is the owner's; its start box says "Started by hand."; it shows in the canvas's runs list, and the answer opens it (`showRun()`).
- The anchor goes on as after a run on its time: a repeating rule keeps its next time, a one-off is done (the ask says so) and the answer's `run_now` false hides the button. A converted flow's run is written back to its action's run, with news (`workspace-flows-converted-anchor.md`).
- Not started (the owner left the staff, open-run or hourly limits): the refusal is the anchor run's sentence (`ws_scheduled_run_summary()`).

## Why does a second click find the run?
- The canvas's token (`startToken()`, kept until answered) goes as `start_token`; `ws_flow_start_key($token)` is handed through `ws_scheduled_execute()`'s context to `ws_flow_anchor_start()`, which uses it in place of `sched:<run id>`. `ws_flow_run_now()` asks `ws_flow_run_for_token()` before the refusal (a run started before a share was lowered is still answered) and again after the start (another request holding the anchor's lock started it).

## Tests?
- tests/workspace_flow_run_now_test.php (rights, paused/draft refused, time only, anchor states, the route, order of checks, the second click, the canvas button and its request); tests/workspace_flow_anchors_test.php (the start key); the parity line `ws_scheduled_execute($anchor['id']) in ... ws_flow_run_now`.
- Not run in a browser or against a database.
