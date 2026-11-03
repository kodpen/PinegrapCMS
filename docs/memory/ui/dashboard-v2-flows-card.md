---
name: dashboard-v2-flows-card
description: New dashboard Flows card (cards/flows.php, renderer in pg_dash_cards_work.js): tiles, the run going on now as a path of boxes, last runs with Approve and Try again through the Workspace actions
---

## Where is it?
- `includes/dashboard/v2/cards/flows.php`; renderer `pgDash.renderers.flows` in `assets/js/pg_dash_cards_work.js`; styles in the "Work cards: Flows" section of `pg_dash.src.css`. Tests: `tests/dashboard_v2_flows_test.php`. Gate `workspace` (`pg_dashboard_workspace_rights()`); a site without the flow tables (`ws_flows_ready()`) gets an empty line.

## What does it read, and who sees what?
- Flows from `ws_flows_list($rights)` and runs only where `ws_flow_run_access()` answers, as the Flows screen reads them: a run of a channel the person does not read is never shown.
- Tiles: Live (note: paused), Today (runs started since midnight; note: failed, bad tone), Approval (open runs whose approval box waits on a request where the person's own `ws_approval_people.decision` is still pending).
- Now: the newest open run (running or waiting); `pg_dashboard_v2_flow_path()` walks from the start box (`ws_flow_trigger_node()`) along the arrow the run took, else the first one drawn, stops at a loop, and keeps five boxes starting at the one before the first box not done. States: done (done, skipped), waiting (ready, running, waiting), failed, to do (no step row); each state is in hidden words too.
- Last runs: the three newest runs the person reaches. Failed: "Failed at <box>"; Waiting with an approval asked of the person: "Waiting for your approval".

## How do the buttons work?
- Approve posts `ws_approval_decide` (`approval_id`, `decision=approved`); Try again posts `ws_flow_step_mend` (`run_id`, `node_id`, `how=retry`), offered only to the flow's owner (`ws_flow_share_manager()`), as on the canvas. Both are the Workspace's own panel actions, which check channel access, the owner and a second click again; after success the card is drawn anew (`pgDash.reload`), a refusal is shown in the row's note.
