---
name: workspace-flows-scheduled-merge
description: Plan (2026-10-24, not started, answers open): scheduled actions move into Flows with no feature lost; one screen first, then a read-only graph, boxes for every part, opt-in convert
---

## What is asked? (owner, 2026-10-24)
- Move the scheduled actions ("Programlanmış işler") into the Flows section without losing any feature; replaces answer 8 of plans/workspace-flows.md.

## What is there today?
- includes/workspace/scheduled.php: a "when", conditions, one of 12 steps, up to 5 follow-ups on 4 outcomes, chains, tokens; runs as its creator; any staff member edits or runs any action. Every key and rule: plans/workspace-flows-scheduled-parity.md.
- Scheduled messages (kind `message`, any member) share the table, ?view=scheduled (a tab for staff), the rail slot.
- `ws_scheduled_run()` / `ws_scheduled_due()` also drive approval expiry, read receipts, watch lines and the flow runner (#1551).
- No API route (S1, S2 of plans/workspace-canvas-workflow-tables.md); no engine test.
- Flows: manual, task, end boxes; the owner edits while staff, home-channel readers run a live one (`ws_flow_access()`); no loops; runs swept after 30 days (`ws_flow_runs_sweep()`); runs start at depth 0; `flow_id` unread.

## What is proposed? (not approved yet)
- Flows is the one home (messages stay where they are). Actions keep running on `ws_scheduled_execute()` until each is converted; nothing retires before the parity test says a box covers it.
- Guard: tests/workspace_flows_parity_test.php maps every parity row to its box and slice, or `form` with a reason; a new key without an entry fails it.
- Two anchors: hidden (a flow's trigger box; lists, `ws_scheduled_action()` and S1 skip it, `ws_scheduled_report()` silent) and converted (slice 5; still an action to all of them). The new filter is not `ws_scheduled_actions_only()`, which feeds event and join starts.
- No route is removed; driver names stay.

## Slices (one PR each)
0. Tests: the parity test; `ws_scheduled_next_run()` (month end, 29 February, every N weeks on days; workdays calls `ws_day_off()`); the hours check of `ws_scheduled_rules_hold()` as a pure helper given the clock (past midnight).
1. One screen, no schema: a Scheduled actions section in the Flows list (the list as today, `scheduledCard()`, `scheduledForm()` via `shareKit()`); staff get one Flows rail entry; ?view=scheduled keeps their Messages tab; inbox links and the tour step land on the section; composer, note, @ai draft still open the form; without step 11.0.80 the old entry stays.
2. "Show on canvas": a pure `ws_scheduled_graph()` draws an action read-only (when, condition, step, follow-ups by outcome; starters, started actions, self-retry as chips); edits stay in the form.
3. Parity boxes (F4, F5), one per parity row: schedule, by hand or chain only, join, event triggers on the anchor; condition (no = skipped, reason kept); steps from steps.php (proposed, engine plan), every option; task without waiting; approval; skipped, failed, always outcomes; delay; retry; start a flow; depth through flow runs and their events.
4. New rules on the canvas; the form stays for keys still `form`; templates become starters, AI drafts (F8).
5. Opt-in "Convert to flow" once an action's rows all map: the row becomes a converted anchor, its id and every parity row kept. Schema (a mark for the two anchors): ask first.

## Open questions (recommendation)
1. Scheduled messages too? → no, staff's actions only.
2. End: (a) old rows forever, (b) all converted, form retired, (c) opt-in convert, form until parity → c.
3. Who edits a moved action: (a) any staff, as today (F7 shares first), (b) owner only → a.
4. Card, note strip, decision reply → kept where written.
5. History of a converted action: (a) kept: a flow run writes its outcome into the anchor's `ws_scheduled_runs` row, boxes go at 30 days, (b) `ws_flow_runs_sweep()` skips converted flows, (c) 30 days → a.
6. Slice 1 before F4 → yes, after #1555 merges.

## Where to start?
- Slice 0, then 1. Read workspace/README.md.
