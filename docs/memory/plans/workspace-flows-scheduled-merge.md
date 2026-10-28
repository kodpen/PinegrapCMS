---
name: workspace-flows-scheduled-merge
description: Plan (2026-10-24): scheduled actions move into Flows, no feature lost; one screen, read-only graph, boxes, all converted
---

## What is asked? (owner, 2026-10-24)
- Move scheduled actions ("Programlanmış işler") into Flows, no feature lost; replaces answer 8 of plans/workspace-flows.md.

## What is there today?
- includes/workspace/scheduled.php: a "when", conditions, one of 12 steps, up to 5 follow-ups on 4 outcomes, chains, tokens; runs as its creator; any staff edits or runs it. Every key and rule: plans/workspace-flows-scheduled-parity.md.
- Scheduled messages (kind `message`, any member) share the table and ?view=scheduled; staff's actions are listed in Flows (slice 1, `ws_flows_scheduled()`).
- `ws_scheduled_run()` / `ws_scheduled_due()` also drive approval expiry, read receipts, watch lines and the flow runner.
- Flows: the owner edits, staff and home-channel readers run a live one (`ws_flow_access()`); no loops; runs swept after 30 days.

## What is proposed? (not approved yet)
- Actions run on `ws_scheduled_execute()` until converted; nothing retires before the parity test says a box covers it; driver names stay. The `/workspace/scheduled-actions` routes were removed before 2026.11.0 (owner, 2026-10-27; #2107).
- Two anchors: hidden (a flow's trigger box; lists, `ws_scheduled_action()` and S1 skip it, `ws_scheduled_report()` silent) and converted (slice 5; still an action to all of them). The new filter is not `ws_scheduled_actions_only()`, which feeds event and join starts.

## Slices (one PR each)
- Done: 0 #1590 parity test; 1 #1707 section in Flows; 2 #1810 Show on canvas; 3 boxes (#2205, #2235, #2249, #2275); 4 canvas ways in (4a #2292, 4b #2730, 4c #2306 #2323); 5 conversion (5a #2558, 5b #2606; schema #2396). workspace/workspace-flows-*.md.
- Retire the form (map and sizes: workspace/workspace-flows-form-retirement.md), in order:
  - R1. Done (#2769). Welcome and the five event starters in the flow gallery.
  - R1b. Done (#2787). forms, orders, chain with flow semantics (#2753 answer 2a): counts of the last day, chain a manual start.
  - R2. Done (#2803). Doors closed: Edit of an unconverted row retries `ws_scheduled_convert()`: canvas, or its problems and Duplicate (#2753 answer 1a); `scheduledGraph()` Edit, the list's New, `showAction()` edit (workspace.js, workspace_flow.js, scheduled_convert.php).
  - R3a. Done (#2823). `scheduledForm()`, `saSentenceBox()`, `saTemplates()`, `saFollowRow()`, workspace_events.js `whenBox()`/`templates()`; parity readers `templates`, `entry_points`, the form's `request` keys.
  - R3b. Done (#PRNUM). `saActionEditor()` and the helpers only it used, the two `scheduledEditor()`.
  - R4. Routes save/preview/targets/draft, `ws_scheduled_save()`, `ws_scheduled_draft_saved()`, form config keys, strings, styles; memory. After R3b.

## Which questions are answered? (owner: 1 no, 3 a on 2026-10-24; 2 b, 7 and 8 on 2026-10-27)
1. Scheduled messages too? → no, staff's actions only; messages stay in the composer and their list.
2. End → b (was c, opt-in per action): every part a box, all converted in the 2026.11.0 upgrade, form retired; Flows the one entry.
3. Who edits and runs a moved action → any staff, as today.
4. Card, note strip, decision reply → kept where written.
5. History of a converted action → kept: a flow run writes its outcome to the anchor's `ws_scheduled_runs` row, boxes go at 30 days.
6. Slice 1 before F4 → yes (#1555 is in).
7. Composer insert menu "Scheduled action" in a channel → C3 of workspace-flows-channels.md (#2394): the entry becomes "Scheduled flow", a mini window with that channel as home; built once, in C3's shape.
8. A scheduled action kept in a note → b (#2395): no new ones from a note; the toolbar entry goes, converted ones keep their card and post step.

## Where to start?
- Next: R4 (after R3b). Routes save/preview/targets/draft, `ws_scheduled_save()`, `ws_scheduled_draft_saved()`, form config keys, strings, styles; read workspace/README.md and workspace/workspace-flows-form-retirement.md (its R4 list).
