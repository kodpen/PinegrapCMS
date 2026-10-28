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
0. #1590: tests/workspace_flows_parity_test.php maps each parity key to its slice and box, or `form` and why.
1. Done (#1707). A Scheduled actions section in Flows (list, cards, form); one Flows rail entry for staff; composer, note, @ai draft open the form.
2. Done (#1810). "Show on canvas": `ws_scheduled_graph()`, `showAction()` draws it read-only.
3. Done. Parity boxes (F4, F5): 3a #2205 trigger values; 3b #2235 start a flow box; 3c #2249 arrows on always; 3d #2275 self-retry (workspace/workspace-flows-*.md).
4. New rules on the canvas; the form stays for keys still `form`; templates are starters (flow gallery, G1). Parts:
   - 4a. Done (#2292). Scheduled actions section: New flow in the toolbar, the list's New hidden (`{ add: false }`); cards keep the form.
   - 4b. The other ways in (composer insert menu, note toolbar, Duplicate): open the canvas instead of the form. 7 and 8 answered: may start.
   - 4c. Done: AI drafts on the canvas (F8): 4c1 #2306 `ws_flow_graph_from_scheduled()`; 4c2 #2323 a draft card opens as a new flow.
5. Conversion (answer 2): in the 2026.11.0 upgrade every row becomes a converted anchor, its id and every parity row kept. Schema approved (#2396).
   - 5a #2558, 5b #2606 done (workspace-flows-converted-anchor.md). Form retires after 4b.

## Which questions are answered? (owner, 2026-10-24: 1 no, 2 c, 3 a; 7 and 8 on 2026-10-27)
1. Scheduled messages too? → no, staff's actions only; messages stay in the composer and their list.
2. End → b (owner, 2026-10-27, was c opt-in per action): every part a box, all converted in the 2026.11.0 upgrade, form retired; Flows the one entry.
3. Who edits and runs a moved action → any staff, as today.
4. Card, note strip, decision reply → kept where written.
5. History of a converted action → kept: a flow run writes its outcome to the anchor's `ws_scheduled_runs` row, boxes go at 30 days.
6. Slice 1 before F4 → yes (#1555 is in).
7. Composer insert menu "Scheduled action" in a channel → C3 of workspace-flows-channels.md (#2394): the entry becomes "Scheduled flow", a mini window with that channel as home; built once, in C3's shape.
8. A scheduled action kept in a note → b (#2395): no new ones from a note; the toolbar entry goes, converted ones keep their card and post step.

## Where to start?
- Slices 1-3, 4a, 4c and 5 done. Next: 4b, then retire the form; read workspace/README.md.
