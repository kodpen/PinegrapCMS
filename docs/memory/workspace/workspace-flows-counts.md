---
name: workspace-flows-counts
description: The counts of a flow's boxes (ws_flow_counts(), FE11a): runs waiting now, passes done and failed in the days, one grouped read, rights as the runs list, panel action, API route
---

## What does ws_flow_counts() answer?
- `ws_flow_counts($viewer, $flow, $days, $keep = null)` in `includes/workspace/flow_counts.php` answers `ok`, `error`, `days`, `since` (Unix time counted from), `boxes`, `left` (runs a leave rule ended since `since`, narrowed to the same runs; `workspace-flows-leave.md`) and `partial`.
- `boxes` is one line per box id with a count: `node`, `waiting` (runs still going, running or waiting, whose step at the box is waiting now; a run counts once even when a loop passed the box twice), `passed` (steps of the box done since `since`), `failed` (steps failed since `since`). Boxes with nothing are left out; ids of every version the runs followed come back.
- `days` (`ws_flow_counts_days()`): 7 when none is sent, whole days from 1 to the days ended runs are kept (`WS_FLOW_RUNS_KEEP`, 30).

## How is it read?
- One grouped read (`ws_flow_counts_sql()`): `ws_flow_runs` by `idx_flow` (the flow's runs still going or finished since the moment), joined with `ws_flow_run_steps` on `run_id` (`uq_node` leads with it), `GROUP BY s.node_id`.
- Nothing is written.

## Who sees which runs?
- As the runs list (`ws_flow_runs_list()`): the flow's owner and an edit share count every run in the one read.
- Anybody else who reaches the flow: the newest `WS_FLOW_COUNTS_SCAN` (2000) runs in the window are looked at one by one with `ws_flow_run_access()`, and the grouped read is narrowed to those they read (`r.id IN (…)`). Past 2000, `partial` is true and the older runs are left out.
- Over the API the application Claude works through also passes `ws_api_flow_run_reachable()` (channels where Claude may be asked) for a flow not its owner's own, as on the runs list.

## How are the runs behind a count listed?
- `ws_flow_runs_list()` takes `node`, `state` (`waiting`, `passed`, `failed`, `ws_flow_counts_states()`) and `days`: `ws_flow_runs_node_where()` adds an `EXISTS` over the box's steps (waiting also keeps the run running or waiting; passed and failed are bounded by the days).
- A box id that is not a token of at most 32 characters, or another state, narrows nothing.

## Where is it reached?
- Panel action `ws_flow_counts` (`flow_id`, `days`), in the panel's action list and `ws_flow_actions_without_workspace()`; it answers `counts`. Panel action `ws_flow_runs_list` passes `node`, `state` and `days`.
- API: `GET /workspace/flows/{id}/counts` (`workspace.flow_runs.counts`, `workspace:read`, an MCP tool, `WorkspaceFlowCounts` / `WorkspaceFlowBoxCount`); `GET /workspace/flows/{id}/runs` takes `node`, `state`, `days`.
- The canvas draws them as pills over boxes: [[workspace-flows-counts-canvas]]; the panel's runs list of a count also carries `at` and `about` there.

## Where is it tested?
- `tests/workspace_flow_counts_test.php`: days, the grouped read, the fold, the runs list narrowing, the rights from the source, the action and the route. `tests/automation_flows_api_test.php` counts 28 flow routes.
