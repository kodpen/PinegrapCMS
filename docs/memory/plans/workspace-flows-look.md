---
name: workspace-flows-look
description: Plan (owner, 2026-10-29): bring the Flows editor and list to the owner's drawing; box colours, icons, arrows, a palette column, a flow list table, no Scheduled actions section
---

## What is asked?
- The screens do not look like the owner's drawing of 2026-10-27 (owner, 2026-10-29). Threads (plans/workspace-flows-data-threads.md) and run flags (C1) match; the rest below never became a step. The numbers: workspace-flows-visual-spec.md (dark theme; the light theme takes darker tones of the same hues that keep 4.5:1 on its ground).

## Which steps? (one PR each, in this order)
- V1 boxes and arrows: a group colour per kind, 1.5 px border, selection ring, grey arrows. Done (PR #3118, `workspace/workspace-flows-box-look.md`).

- V2 icons: the spec's 16 px, 1.5 px stroke line icons as inline SVG in place of the Bootstrap icons of `KINDS` and `PALETTE`, on boxes, palette, outline and the mini window; kinds the spec does not name get one in the same style. Done (PR #3158, `workspace/workspace-flows-box-look.md`).

- V3 flow list: `workspace_flows.php` without `?flow=` shows the flows as a full-width table (Flow, How it starts, State, Last run, Home channel) with search and filter chips. Done (PR #3172, `workspace/workspace-flows-list.md`).

- V4 palette column (after V3): in the editor the left column holds the palette, not the flow list, and nothing floats over the canvas: Start from a template (while the flow is empty), a search field, groups with V1's coloured headings in the order start, values, logic, people and tasks, message, system; the start group says "This flow: <kind>" once it has a start. Click and drag as today (`drawPalette()`, `paletteButtons()`); a phone keeps the outline's add buttons. Done (PR #3199, `workspace/workspace-flows-canvas.md`).

- V5 no Scheduled actions section: `?view=scheduled` opens the list; converted and refused rows form its last groups (#3173, `workflow/decisions-made-workspace-3.md`). Done (PR #3222, `workspace/workspace-flows-scheduled-section.md`).

- V6 the data row no wider than its box (`drawData()`): output dots along the box's own bottom edge, labels shortened, threads routed around other boxes. Done (PR #3134, `workspace/workspace-flows-thread-drawing.md`).

- V7a mini flow window to the spec (AI draft card and Add › Scheduled flow; visual spec 'Mini window and templates?'): boxes 128 × 60 px, control arrows 2 px with a filled head from the right edge, data threads 1 px from the output dot (sag 10 px + 0.05 × span, the type's tones, 3 px apart) sent by the server with their output type, each box's F4e dry run as its third line (a waiting new draft or a scheduled sketch with the asker's rights on an in-memory flow; none for a waiting patch; an opened draft where `can_try`). Done (PR #3393, `workspace/workspace-flow-draft-card-window.md`).

- V7b "Edit here" in the mini window (#3444): a waiting card opens the draft first (`ws_flow_draft_open()`), then the box's name and texts save through `ws_flow_save()` with `draft_rev`; the proposal is never edited. Done (PR #3493, `workspace/workspace-flow-draft-card-window.md`).

## Where to start?
- V1-V7b are done. Next: compare the drawing's other boards (run view, channel, templates, AI draft, the parts map) with the screens before this plan closes; each finding becomes a step here, else the plan closes.
