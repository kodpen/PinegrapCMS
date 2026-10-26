---
name: workspace-flows-scheduled-merge
description: Plan (2026-10-24, slice 0 #1590, slice 1 #1707, date fix #1798, answers 1-3): scheduled actions move into Flows, no feature lost; one screen, read-only graph, boxes for every part, opt-in convert
---

## What is asked? (owner, 2026-10-24)
- Move scheduled actions ("Programlanmış işler") into Flows, no feature lost; replaces answer 8 of plans/workspace-flows.md.

## What is there today?
- includes/workspace/scheduled.php: a "when", conditions, one of 12 steps, up to 5 follow-ups on 4 outcomes, chains, tokens; runs as its creator; any staff edits or runs it. Every key and rule: plans/workspace-flows-scheduled-parity.md.
- Scheduled messages (kind `message`, any member) share the table and ?view=scheduled; staff's actions are listed in Flows (slice 1, `ws_flows_scheduled()`).
- `ws_scheduled_run()` / `ws_scheduled_due()` also drive approval expiry, read receipts, watch lines and the flow runner (#1551).
- No API route (canvas plan S1, S2).
- Flows: manual, task, end boxes; the owner edits, staff and home-channel readers run a live one (`ws_flow_access()`); no loops; runs swept after 30 days, start at depth 0; `flow_id` unread.

## What is proposed? (not approved yet)
- Actions run on `ws_scheduled_execute()` until converted; nothing retires before the parity test says a box covers it; no route is removed, driver names stay.
- Two anchors: hidden (a flow's trigger box; lists, `ws_scheduled_action()` and S1 skip it, `ws_scheduled_report()` silent) and converted (slice 5; still an action to all of them). The new filter is not `ws_scheduled_actions_only()`, which feeds event and join starts.

## Slices (one PR each)
0. #1590: tests/workspace_flows_parity_test.php maps each parity key to its slice and box, or `form` and why; tests/workspace_scheduled_test.php pins `ws_scheduled_next_run()`, `ws_scheduled_hours_hold()` (past midnight, the site's zone).
1. Done (#1707). One screen, no schema: a Scheduled actions section in Flows (today's list, `scheduledCard()`, `scheduledForm()` via `shareKit()`); staff get one Flows rail entry; ?view=scheduled keeps their Messages tab (the scheduled_message line opens it); other links, the tour step land in Flows; composer, note, @ai draft open the form; without 11.0.80 the old entry stays.
2. "Show on canvas": a pure `ws_scheduled_graph()` draws an action read-only (when, condition, step, follow-ups; starters, started actions, self-retry as chips); edits stay in the form.
3. Parity boxes (F4, F5), one per parity row: schedule, by hand or chain only, join, event triggers on the anchor; condition (no = skipped, reason kept); steps from steps.php (engine plan), every option; task without waiting; approval; outcomes; delay; retry; start a flow; depth.
4. New rules on the canvas; the form stays for keys still `form`; templates become starters, AI drafts (F8).
5. "Convert to flow" (answer 2): the row becomes a converted anchor, its id and every parity row kept. Schema (a mark for the two anchors): ask first.

## Which questions are answered? (owner, 2026-10-24: 1 no, 2 c, 3 a; 4-6 open)
1. Scheduled messages too? → no, staff's actions only; messages stay in the composer and their list.
2. End: (a) old rows forever, (b) all converted, form retired, (c) opt-in "Convert to flow" per action once every part maps, the form until boxes cover all (parity test) → c; Flows the one entry.
3. Who edits and runs a moved action: (a) any staff, as today (F7 shares first), (b) owner only → a.
4. Card, note strip, decision reply → kept where written.
5. History of a converted action: (a) kept: a flow run writes its outcome to the anchor's `ws_scheduled_runs` row, boxes go at 30 days, (b) `ws_flow_runs_sweep()` skips converted flows, (c) 30 days → a.
6. Slice 1 before F4 → yes (#1555 is in).

## Where to start?
- Date fix done (#1798). Next: slice 2; read workspace/README.md and workspace/workspace-flows-canvas.md.
