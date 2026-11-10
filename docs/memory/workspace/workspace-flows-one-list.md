---
name: workspace-flows-one-list
description: Scheduled work listed only in the Flows table (PR #8573): converted flows as rows for all staff, the next run in the start column; why, and what stayed untouched
---

## Why one list?
- The old scheduled actions became flows (merge of 2026-10-24, `workspace-flows-scheduled-merge.md`), but some screens kept the old split: Add › Scheduled flow beside New flow, a group of its own for converted actions under the flow table, two palette boxes that start other work. The owner found it confusing (`workflow/decisions-made-product-41.md`, 2026-11-09).
- The old actions shipped but nobody uses them yet: their useful functions (the next run, Run now) came into flows; nothing is built for the old rows, which keep running.
- Steps: one place to set up (#8394, `workspace-scheduling-one-place.md`), Run now on the canvas (#8443, `workspace-flows-run-now.md`), one list (#8573, this file).

## Who sees a converted flow in the table?
- Every staff member (`ws_can_schedule()`). A converted flow is owned by the action's creator and has channel 0 (`workspace-flows-converted-anchor.md`), so `ws_flows_list()` reads it only for its owner. The panel's `ws_flow_list` adds the rest with `ws_flows_with_converted()`: anchor `converted`, `flow_id > 0`, not binned, not already listed; access from `ws_flow_access()` (edit), unchanged.
- Merged by pure `ws_flows_rows_merge()`: each flow once, `updated_at DESC, id DESC`.
- The API's flow list (api_flows.php), the dashboard card, flow_run_cards.php, flow_draft.php and `runnable_in` (Add › Start a flow) call `ws_flows_list()` alone and are unchanged.

## How does the start column give the next run?
- For a live flow whose start kind is `time` (pure `ws_flows_next_run_ids()`), `ws_flows_next_runs()` reads the anchors once for the list: `flow_id IN (…)`, anchor hidden or converted, `status = 'active'`, `next_run_at > 0`.
- Pure `ws_flow_start_with_next()` joins it to the start words with `ws_scheduled_moment()`: "Every day 09:00 · Next: Tomorrow 09:00". It goes into the panel's `start_text`, so the search (`listMatches()`) and the phone's stacked row say it too; the API's `start_text` is unchanged.
- A paused flow, a draft, a used-up one-off and any other start get nothing added.

## What did the plan leave untouched?
- No schema and no migration step: converted rows, hidden anchors and refused rows run as before (`ws_scheduled_run()`, `ws_scheduled_execute()`).
- Scheduled messages (composer Send later, /schedule, their list) are a separate thing for any member.
- The cards' `ws_scheduled_run_now` and `ws_scheduled_get` (workspace.js) stay for the Not converted group (`workspace-flows-not-converted.md`).
- A new `ws_scheduled_execute()` call or `ws_scheduled_*` route gets its line in `workspace_flows_parity_table()` (`workspace-flows-parity-test.md`).

## Tests?
- tests/workspace_flows_list_test.php: the refused-only groups, the merge, the next run's ids and words, no converted group in workspace_flow.js. Not run in a browser or against a database.
