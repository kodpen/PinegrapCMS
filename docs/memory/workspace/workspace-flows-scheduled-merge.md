---
name: workspace-flows-scheduled-merge
description: Scheduled actions moved into Flows (owner, 2026-10-24): what was decided, the two anchors, what stays a scheduled action, and the PRs of each slice
---

## What was asked and decided? (owner, 2026-10-24; answers 1 no, 3 a; 2 b, 7, 8 on 2026-10-27)
- Scheduled actions ("Programlanmış işler") move into Flows with no feature lost; it replaced answer 8 of plans/workspace-flows.md.
- 1. Scheduled messages stay out: staff's actions only; messages keep the composer and their list (?view=scheduled, kind `message`, any member).
- 2. b: every part a box, all actions converted in the 2026.11.0 upgrade, the form retired; Flows is the one entry.
- 3. Any staff edits and runs a moved action, as before.
- 4. Card, note strip and decision reply stay where they were written.
- 5. History stays: a flow run writes its outcome to the anchor's `ws_scheduled_runs` row; boxes go at 30 days.
- 7. The composer insert menu's entry is "Scheduled flow", a mini window with that channel as home (C3 of plans/workspace-flows-channels.md, #2394).
- 8. No new scheduled action from a note (#2395); converted ones keep their card and post step.
- The `/workspace/scheduled-actions` API routes were removed before 2026.11.0 (owner, 2026-10-27; #2107).

## How does a row relate to its flow?
- Two anchors: hidden (a flow's trigger box; lists, `ws_scheduled_action()` and S1 skip it, `ws_scheduled_report()` silent: `workspace-flows-anchors.md`) and converted (still an action to all of them: `workspace-flows-converted-anchor.md`). The hidden filter is not `ws_scheduled_actions_only()`, which feeds event and join starts.
- Driver names stay: `ws_scheduled_run()` / `ws_scheduled_due()` also drive approval expiry, read receipts, watch lines and the flow runner; an unconverted row runs on `ws_scheduled_execute()`.

## Which PRs did it?
- 0 #1590 parity test (`workspace-flows-parity-test.md`); 1 #1707 section in Flows; 2 #1810 Show on canvas; 3 boxes (#2205, #2235, #2249, #2275); 4 ways in (4a #2292, 4b #2730, 4c #2306 #2323: `workspace-flows-scheduled-ways-in.md`); 5 conversion (5a #2558, 5b #2606; schema #2396).
- Form retired: R1 #2769, R1b #2787, R2 #2803, R3a #2823, R3b #2905, R4 #2970 (`workspace-flows-form-retirement.md`).
