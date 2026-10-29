---
name: workspace-flows-look
description: Plan (owner, 2026-10-29): bring the Flows editor and list to the owner's drawing; box colours, icons, arrows, a palette column, a flow list table, no Scheduled actions section
---

## What is asked?
- The screens do not look like the owner's drawing of 2026-10-27 (owner, 2026-10-29). Threads (plans/workspace-flows-data-threads.md) and run flags (C1) match; the rest below never became a step. The numbers: workspace-flows-visual-spec.md (dark theme; the light theme takes darker tones of the same hues that keep 4.5:1 on its ground).

## Which steps? (one PR each, in this order)
- V1 boxes and arrows: a group colour per kind, 1.5 px border, selection ring, grey arrows. Done (PR #3118, `workspace/workspace-flows-box-look.md`).

- V2 icons: the spec's 16 px, 1.5 px stroke line icons as inline SVG in place of the Bootstrap icons of `KINDS` and `PALETTE`, on boxes, palette, outline and the mini window; kinds the spec does not name get one in the same style. Done (PR #3158, `workspace/workspace-flows-box-look.md`).

- V3 flow list: `workspace_flows.php` without `?flow=` shows the list full width as a table: Flow, How it starts (kind icon and its sentence, "Every day 09:00", "Stock below 5"), State (Live · vN, Draft, Paused), Last run (its flag), Home channel; search; filter chips with counts (All, Time, By hand, Event, Join, Draft) in place of the start select (#2391); New flow. `ws_flow_list` (includes/workspace/actions.php) adds the start sentence and the channel's name; a row opens the flow; "Flows ›" in the editor's toolbar leads back.

- V4 palette column (after V3): in the editor the left column holds the palette, not the flow list, and nothing floats over the canvas: Start from a template (while the flow is empty), a search field, groups with V1's coloured headings in the order start, values, logic, people and tasks, message, system; the start group says "This flow: <kind>" once it has a start. Click and drag as today (`drawPalette()`, `paletteButtons()`); a phone keeps the outline's add buttons.

- V5 no Scheduled actions section (after V3; channels plan: scheduled work has no menu of its own): the entry under the list and `showScheduled()` go, `?view=scheduled` opens the list; converted rows (`ws_flow_converted()`) form a last group, "Converted from scheduled actions · N", each with its when and step, a converted mark and Open flow. Before it starts, its session asks the architect where a row left unconverted (cancelled, or a graph `ws_flow_problems()` refused) and a converted row's Run now and History go.

- V6 the data row no wider than its box (after V1, before or beside V2; `drawData()` only): today the field chips (76 px) and output dots (84 px) sit in one row half over the box's bottom edge, so "Ask for approval" (four outputs) spreads its row under the neighbouring boxes and its threads cross other boxes (owner's screenshot, 2026-10-29). The output dots spread along the box's own bottom edge, labels shortened with the full name on hover and focus, unused outputs shown only while the box is selected, the reading box's field chips kept within its width, threads routed so they do not pass under other boxes where avoidable. Done (PR #3134, `workspace/workspace-flows-thread-drawing.md`).

## Where to start?
- V3. Each step updates workspace/workspace-flows-canvas.md. The drawing's other boards (run view, channel, templates, AI draft, the parts map) were not compared with the screens: compare them before this plan closes.
