---
name: workspace-flows-scheduled-ways-in
description: Scheduled work opens on the flow canvas (merge plan 4b, R2): Add › Scheduled flow's mini window (C3, #3066), Duplicate, Edit retrying the conversion; no New on the list, no note toolbar entry
---

## What does Add › Scheduled flow make? (C3, #3066)
- The composer's insert menu (staff, not in a discussion thread) opens a mini flow window (`scheduledFlowWindow()` in workspace.js, a drawer): `ws_flow_scheduled_new` with `sketch` → `ws_scheduled_flow_sketch()` draws the two boxes with D8b's `flowWindowDraw()` (a picked box focuses its form; nothing written), the time start's settings under it (day, time, repeat from `CFG.scheduled.repeats`, every, weekdays for weekly, until) and the message.
- Publish or Open on the canvas sends `name`, `start`, `body` (`ws_scheduled_flow_fill()`, pure) to `ws_flow_scheduled_new` → `ws_scheduled_flow_new()`: saved through `ws_flow_save()` (right to build flows), then `ws_flow_publish()` when asked (owner, the post box's channel checked as a step). A refused publish answers `published: false` and `publish_error` and keeps the draft; the window sends its `flow_id` and `draft_rev` on the next try, which changes only the person's own draft still in `draft` status, so no draft piles up. Open on the canvas goes to `workspace_flows.php?flow=`.
- Graph `ws_scheduled_flow_graph($channel_id, $today, $fill)` (pure): a `schedule` start tomorrow (site date, UTC day count) 09:00, repeat `none`, replaced by what was filled, then a `post` box into that channel. Home: `ws_scheduled_flow_home()`, the channel when public or private and read, else none (a direct message channel is still the post box's target). Only where they may post. No new store, route or schema; old scheduled actions keep their cards.
- Tests: tests/workspace_scheduled_flow_window_test.php (graph, fill, rights from the source, the sketch, the JS helpers under Node). The window was not run in a browser.

## What does Duplicate on a card make?
- `ws_flow_scheduled_copy` → `ws_scheduled_flow_copy()`: a new draft flow "Copy of …" of the person. A converted action copies its flow's draft (`ws_flow_graph_of()`); any other is drawn by `ws_flow_graph_from_scheduled()` with its own id, so a retry of itself is the copy's own box retry. `left` sentences are shown before the canvas opens (`flowOpened()`, shared with the AI draft card). Shown on every card for staff (`CFG.scheduled`), converted ones too.

## What does Edit on a card do? (R2, #2753 answer 1a)
- A converted row opens its flow. A row not converted yet calls `ws_flow_scheduled_edit` → `ws_scheduled_flow_edit()`: `ws_scheduled_convert()` again (a channel restored or a template fixed since the upgrade now passes), then its flow opens. Still refused: `ws_scheduled_convert_drawing()` (the creator's view, the same check the conversion makes) gives the problems, listed with Duplicate as the button; the row runs on under `ws_scheduled_execute()` until cancelled. Nothing refused means the run lock was held: "try again in a moment".
- Route: staff (`ws_can_schedule()`), row active, paused or failed, as the card's `can_edit`. The read-only drawing's Edit (`scheduledGraph()`, `showAction()` options.edit) does the same (`scheduledEdit()` in workspace.js).

## Why no New on the list?
- `scheduledList(body)` has no New since R2: a new rule is New flow or Add › Scheduled flow. Nothing opens the old form from outside it; the form itself goes in R3a.

## Why no note toolbar entry?
- Answer 8 (#2395): no new scheduled actions from a note; converted ones keep their card in the note strip and their post step.
