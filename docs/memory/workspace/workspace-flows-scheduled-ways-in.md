---
name: workspace-flows-scheduled-ways-in
description: Ways onto the canvas for scheduled work: New flow only (the Scheduled flow window went 2026-11-09), Duplicate on a card, Edit retrying the conversion; no New on the list, no note toolbar entry
---

## Where is a new scheduled flow made?
- On the flow canvas only: Flows › New flow, or a channel's Add › New flow, which opens the same gallery in the layer (`workspace-flow-layer.md`). It starts with an "At a time" box or a template such as `channel_message` (`workspace-scheduling-one-place.md`).
- Add › Scheduled flow's mini window (C3, #3066) went on 2026-11-09, with its route `ws_flow_scheduled_new` and the `ws_scheduled_flow_graph/fill/new/sketch()` functions. The flows it made are ordinary flows.

## What does Duplicate on a card make?
- `ws_flow_scheduled_copy` → `ws_scheduled_flow_copy()`: a new draft flow "Copy of …" of the person. A converted action copies its flow's draft (`ws_flow_graph_of()`); any other is drawn by `ws_flow_graph_from_scheduled()` with its own id, so a retry of itself is the copy's own box retry. `left` sentences are shown before the canvas opens (`flowOpened()`, shared with the AI draft card). Shown on every card for staff (`CFG.scheduled`), converted ones too.

## What does Edit on a card do? (R2, #2753 answer 1a)
- A converted row opens its flow. A row not converted yet calls `ws_flow_scheduled_edit` → `ws_scheduled_flow_edit()`: `ws_scheduled_convert()` again (a channel restored or a template fixed since the upgrade now passes), then its flow opens. Still refused: `ws_scheduled_convert_drawing()` (the creator's view, the same check the conversion makes) gives the problems, listed with Duplicate as the button; the row runs on under `ws_scheduled_execute()` until cancelled. Nothing refused means the run lock was held: "try again in a moment".
- Route: staff (`ws_can_schedule()`), row active, paused or failed, as the card's `can_edit`. The read-only drawing's Edit (`scheduledGraph()`, `showAction()` options.edit) does the same (`scheduledEdit()` in workspace.js).

## Why no New on the list?
- `scheduledList(body)` has no New since R2: a new rule is New flow. Nothing opens the old form from outside it; the form itself went in R3a.

## Why no note toolbar entry?
- Answer 8 (#2395): no new scheduled actions from a note; converted ones keep their card in the note strip and their post step.
