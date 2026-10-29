---
name: workspace-flows-scheduled-section
description: Staff's Scheduled actions as a section of the Flows screen (?view=scheduled): the entry under the flow list, scheduledList(), Show on canvas
---

## Where are they?
- `ws_flows_scheduled($viewer)` (staff; `CFG.flows.scheduled`): an entry under the flow list opens a section in place of the canvas (`?view=scheduled`), drawn by `scheduledList(body, bar)` of workspace.js through `PGWsKit`; the Workspace list then has Scheduled messages, not Scheduled actions, workspace.php's `?view=scheduled` shows their scheduled messages only (`&part=messages` names that tab without flow tables). Parity: `workspace-flows-parity-test.md`.
- "Show on canvas" on a card there: `ws_scheduled_get` answers `graph` (pure `ws_scheduled_graph()` of the staff card: when, conditions, step, follow-ups by outcome; chips: starters, started, self-retry); `showAction()` draws it read-only on a scrolling stage; Edit opens the form.
