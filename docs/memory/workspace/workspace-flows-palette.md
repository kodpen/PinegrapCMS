---
name: workspace-flows-palette
description: The Flows canvas palette laid out in the order a flow is built: numbered sections (start, steps, end) and the value boxes outside the order, their hints, one start box only
---

## Why sections?
- The palette listed its groups as start, values, logic (with End), people, message, system: nothing said which box goes first, which in the middle and which last, and the owner could not tell (2026-11-03). Value boxes, which no arrow joins, sat second.
- Only Start manually was refused once the flow had a start; At a time, When something happens and the other start boxes could still be added, each a second start the graph check then refused.

## How is it laid out? (workspace_flow.js)
- `PALETTE_STAGES`: `first` 1 Start (`start`), `steps` 2 Steps (`message`, `people`, `logic`, `system`), `last` 3 End (`end`), `aside` Values, no number (`values`). Each a heading with its number badge, a hint (`fl_stage_*_hint`), then its boxes; a section of one group draws no group heading, the steps section names its groups in their colours.
- `PALETTE_GROUPS` order: start, message, people, logic, system, end, values. `paletteGroupOf()` lists the End box under its own `end` group (palette only; on the canvas it keeps the logic colour, `KINDS`). `PALETTE` has End before the value boxes, so the outline's add buttons follow the same order.
- `paletteStages(groups)` keeps the sections whose groups a search left; `paletteGroups()` filters by every word as before.
- With a start box: `stageStart()` "This flow: <kind>" over the hint, the hint `fl_stage_first_taken` ("To start it another way, remove that box first."), and `startTaken(kind)` disables every start box (title `fl_one_start`), in the palette and the outline. `drawPalette()` runs again when `stageStart()` changes (a start placed or removed).
- CSS `.wsf-palette-stage`, `-stage-head`, `-stage-num` (accent on `color-mix`), `-stage-now`, `-stage-hint` (`--pg-fg-2`) in backend.src.css.

## Tests
- `test_workspace_flows_canvas_palette_groups` (Node): group and section order, items per group, search, Turkish fold, hints, `startTaken()`.
- Harness `the palette adds a box…`: the second start of another kind refused, the sections in order (30 of 30 on 2026-11-03).
