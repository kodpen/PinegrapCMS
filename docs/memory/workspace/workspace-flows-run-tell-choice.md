---
name: workspace-flows-run-tell-choice
description: The start box's "Tell me" choice (every run / only failures / never) decides whether a run's end writes or updates the owner's flow_finished line, alone and in a chain (#3526 step 2, PR #3566, #3371)
---

## Where is the choice kept? (#3526 step 2, PR #3566)
- `config.tell` of the start box (every kind with `trigger` in `ws_flow_kinds()`): `every` | `failures` | `never`. `ws_flow_graph_normalize()` keeps `failures`/`never` and drops `every` and anything unknown (`ws_flow_tell_normalize()`), so a graph saved before the choice, or set to every run, is unchanged byte for byte (templates, API, stored versions). `ws_flow_tell_of($graph)` reads it, `every` without a start box or key.
- No schema; the API flow object carries it inside the box's `config` (typed `object`, no key list to update). Webhook run events and the runs list do not change.
- Canvas: `tellField()` in `workspace_flow.js`, a select under every start box's settings (`fl_tell*` keys of `ws_flow_js_strings()`); choosing every run deletes the key.

## Which version's choice counts?
- The run's own: `ws_flow_run_graph($run)` (the published version it used), never the draft. Only the owner publishes, so a member's edit never changes what the owner is told.

## Who is told what?
- `ws_flow_run_tell_wanted($own, $root, $state, $folded)` (pure, `flow_run_chain_notice.php`):
  - own `never` → never told, even a failure; in a chain it stays out of the line and out of its count (`ws_flow_chain_notice_count()` skips runs whose version says never).
  - any other failure → told: alone, or the chain line turns to the failure line (`ws_flow_chain_notice_plan()`).
  - success → told only if the line's choice is `every`: its own alone (or as the root), the root's when it folds into the root's line. A root set to `failures` therefore has a chain line only once something in it failed.
- Asked in `ws_flow_run_tell_chain()` after the fold check, and in `ws_flow_run_tell_owner()` for a run told alone (another owner's chained flow: its own choice).
- Default every run for every start kind: running sites change nothing until the owner chooses (an "only failures" default for time/event starts was refused, `workflow/decisions-made-workspace-4.md`).
- Tests `tests/workspace_flow_run_tell_choice_test.php`, `workspace_flows_canvas_test.php` (`…_start_box_tell_choice`).
