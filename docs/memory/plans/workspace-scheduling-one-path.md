---
name: workspace-scheduling-one-path
description: Plan (owner 2026-11-09): scheduled work set up only on the flow canvas and listed only in the flow table; Run now and the next run for flows that start at a time; one start-a-flow box
---

## Why this plan?
- The old scheduled actions became flows (merge of 2026-10-24, `workspace/workspace-flows-scheduled-merge.md`). Some options still looked like the old split, and the owner found that confusing:
  - Add › Scheduled flow sat beside Add › New flow, and it could only post one message;
  - the flow list ended with a group of its own for converted actions;
  - the palette held two boxes that start another piece of work.
- Decisions: `workflow/decisions-made-product-41.md`. The old actions shipped but nobody uses them yet, so their useful functions come into flows and nothing is built for the old rows.

## Which steps are done?
- Done: step 1 #8394 (`workspace/workspace-scheduling-one-place.md`), step 2 #8443 (`workspace/workspace-flows-run-now.md`). Next: step 3.

## What holds for every step?
- No schema, no migration step: converted rows, hidden anchors and refused rows keep running as they do (`ws_scheduled_run()`, `ws_scheduled_execute()`).
- Scheduled messages (composer Send later, /schedule, their list) are not touched.
- A new `ws_scheduled_execute()` call or `ws_scheduled_*` route gets its line in `workspace_flows_parity_table()` (`workspace/workspace-flows-parity-test.md`).

## Step 1 — one place to set up
- Add › Scheduled flow goes:
  - `scheduledFlowWindow()` and `scheduledFlowShows/Start/Notes()` (workspace.js);
  - the `ws_flow_scheduled_new` route and the `ws_scheduled_flow_graph/fill/new/sketch/sketch_view()` functions;
  - the `sa_flow_new` / `sf_*` strings and tests/workspace_scheduled_flow_window_test.php.
  - Kept: `ws_scheduled_flow_home()` (Duplicate), `flowWindowDraw()` and `flowEmbedKit()` (draft cards).
- The gallery gets the template `channel_message`, "Message to a channel at a time": a time start and a post box, asking for the time, the channel and the words. The setup panel offers the flow's home as the channel when the box has none.
- The palette drops `start_action`; saved boxes keep their form and run.
- Add › Start a flow shows only where `ws_channel_open` says `flows_here`.
- Texts: the tour's Flows step no longer names scheduled actions; the Scheduled messages screen loses its "Your scheduled actions are in Flows." link.

## Step 2 — Run now for flows that start at a time (done, #8443)
- A live flow whose start box is `schedule` gets Run now on its canvas for whoever may edit it, asked first, as Run is asked.
- It starts the flow through its anchor row (hidden, or converted), by hand, as `ws_scheduled_run_now` does for a converted row. The start box says it was started by hand, and the run shows in the canvas's runs list.
- Event, join, row, mention, web and no-order starts get no Run now (owner: time only).
- Tests: the route's rights, a paused or draft flow refused, a second click finding the run.

## Step 3 — one list
- The flow table lists converted flows to every staff member; today only their owner sees them, and other staff reach them through the group. The API's flow list stays as it is.
- The "How it starts" column gives the next run of a live flow that starts at a time, read once for the list from the anchor's `next_run_at`.
- The "Converted from scheduled actions" group goes with its code and strings: `convertedRow()`, `convertedChanged()`, the `converted` part of `ws_flows_scheduled_rows()`. History before the conversion is not moved.
- "Not converted · N" stays at the end of the list.
- Memory: `workspace-flows-list.md`, `workspace-flows-scheduled-section.md`, `workspace-flows-converted-anchor.md`.

## Done when?
- Every step merged, then the plan's lasting points moved to `workspace/` and this file deleted.
