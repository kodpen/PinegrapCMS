---
name: workspace-flows-scheduled-merge
description: Plan (2026-10-24, not started, answers open): scheduled actions move into Flows with no feature lost; one screen first, then a read-only graph, boxes for every part, opt-in convert
---

## What is asked? (owner, 2026-10-24)
- Move the scheduled actions ("Programlanmış işler") into the Flows section without losing any feature; replaces answer 8 of plans/workspace-flows.md.

## What is there today?
- includes/workspace/scheduled.php: one "when" (at with repeats, trigger, join, event), conditions (record, count, workday, weekdays, hours; a skip says why), one of 12 steps (`ws_scheduled_do()`), 5 follow-ups (done, failed, skipped, always), chains (delay, depth 8, 60 an hour, self-retry), tokens (`ws_scheduled_fill()`). Runs as its creator; any staff member edits or runs any action.
- Around it: ?view=scheduled, a channel card non-staff see too, the note strip, Run now, Pause, Duplicate, History, Cancel, the form (`scheduledForm()`: 18 templates, AI sentence, next-runs preview), @ai drafts, news-only reports, inbox lines, the decision under the card. Runs are never swept.
- Scheduled messages (kind `message`, any member) share the table, screen and rail slot.
- `ws_scheduled_run()` / `ws_scheduled_due()` also drive approval expiry, read receipts, watch lines and the flow runner (#1551).
- No API route for actions (S1, S2 of plans/workspace-canvas-workflow-tables.md); no engine test (`ws_scheduled_next_run()`, `_rules_hold()`, `_execute()`).
- Flows: manual, task, end boxes; owner-only (`ws_flow_access()`); no loops; runs kept 30 days; `ws_scheduled_actions.flow_id` unread.

## What is proposed? (not approved yet)
- Flows is the one home. Actions keep running on `ws_scheduled_execute()` until each is converted; nothing retires before the parity test says a box covers it.
- Guard: tests/workspace_flows_parity_test.php (pure) maps every feature key (when kinds, `ws_scheduled_repeats()`, condition kinds, `_operators()`, `_metrics()`, step types, `_outcomes()`, `_webhook_formats()`, `_placeholders()`, `ws_events_catalog()`, card buttons, limits) to its box and slice, or `form` with a reason; a new key without an entry fails it.
- Messages stay in the composer and their tab; ?view=scheduled, inbox links and the tour keep working.
- Anchors get their own list filter (`ws_scheduled_actions_only()` also feeds event and join starts).
- No route is removed; driver names stay.

## Slices (one PR each)
0. Tests: the parity test; `ws_scheduled_next_run()` (month end, 29 February, every N weeks on days); hours past midnight.
1. One screen, no schema: a Scheduled actions section in the Flows list with `scheduledCard()`, `scheduledForm()` lent by `shareKit()`; staff get one Flows rail entry; ?view=scheduled and inbox links lead there; without step 11.0.80 the old entry stays.
2. "Show on canvas": a pure `ws_scheduled_graph()` draws an action read-only (when, condition, step, follow-ups by outcome); edits stay in the form.
3. Parity boxes (F4, F5): schedule, join, event triggers on the anchor; condition (no = skipped); the other steps from steps.php; task without waiting, with its channel and tokens; approval; failed and always outcomes; delay; retry; start a flow; reports, card, decision reply kept.
4. New rules on the canvas; the form stays for keys still `form`; templates become starters, AI drafts (F8).
5. Opt-in "Convert to flow" once an action's keys all map: the row becomes the anchor (id, card, history kept). Schema: ask first.

## Open questions (recommendation)
1. Scheduled messages too? → no, staff's actions only.
2. End: (a) old rows forever, (b) all converted, form retired, (c) opt-in convert, form until parity → c.
3. Who edits a moved action: (a) any staff, as today (F7 shares first), (b) owner only → a.
4. Card, note strip, decision reply → kept where written.
5. History of a converted action: (a) forever, (b) 30 days → a.
6. Slice 1 before F4 → yes, after #1555 merges.

## Where to start?
- Slice 0, then 1. Read workspace/README.md.
