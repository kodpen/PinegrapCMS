---
name: workspace-flows-scheduled-ways-in
description: New scheduled work starts on the flow canvas (merge plan 4b): Add › Scheduled flow in the composer, Duplicate on a card, no note toolbar entry; what each makes
---

## What does Add › Scheduled flow make?
- The composer's insert menu (staff, not in a discussion thread) calls `ws_flow_scheduled_new` → `ws_scheduled_flow_new()` (scheduled_convert.php): a draft flow named "Scheduled flow", owned by the person, and opens `workspace_flows.php?flow=`. Only where they may post.
- Graph `ws_scheduled_flow_graph()` (pure): a `schedule` start tomorrow (site date, UTC day count) 09:00, repeat `none`, then a `post` box into that channel with no text. Home: `ws_scheduled_flow_home()`, the channel when public or private and read, else none (a direct message channel is still the post box's target).
- The plan's mini window (C3 of plans/workspace-flows-channels.md, visual spec) draws this same flow small; until it is built the canvas opens.

## What does Duplicate on a card make?
- `ws_flow_scheduled_copy` → `ws_scheduled_flow_copy()`: a new draft flow "Copy of …" of the person. A converted action copies its flow's draft (`ws_flow_graph_of()`); any other is drawn by `ws_flow_graph_from_scheduled()` with its own id, so a retry of itself is the copy's own box retry. `left` sentences are shown before the canvas opens (`flowOpened()`, shared with the AI draft card). Shown on every card for staff (`CFG.scheduled`), converted ones too.

## Why no note toolbar entry?
- Answer 8 (#2395): no new scheduled actions from a note; converted ones keep their card in the note strip and their post step.
