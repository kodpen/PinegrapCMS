---
name: flows-editor-next
description: Plan (owner 2026-11-22): the Flows editor redrawn and widened: canvas edge to edge, add at the arrow, several boxes, a whole-flow trial, counts, Split, sending hours, leaving
priority: 6
---

## What is asked?
- Owner (2026-11-22, in the architect's window), with a picture of the editor: "Mimar akışlar sayfasında nasıl mantıklı bir geliştirme yapabiliriz?"; shown three proposals: "çiz önermelerini"; shown them drawn: "daha da modernize etsek ve özellik olarak da genişletip, kullanımı kolaylaştırsak?"; shown the second sheet: "görseller ve detaylı planla projeye ekle".
- Recorded in `workflow/decisions-made-workspace-47.md`.

## Where are the drawings?
- `workspace/flows-editor-drawing/editor.html` (boards A1, B1, B2, C1, D1 to D3) and `threads-panel-counts.html` (boards 1a, 1b, 2a to 2c, 3), each with its `-dark.jpg` and `-light.jpg`.
- The second sheet's boards 1a to 3 are drawn with today's bordered boxes; they are built on the card boxes of `editor.html`. Where a drawing and these words differ, the drawing wins; what neither says is asked of the architect before it is built.

## What is wrong today? (development c01d764a)
- Two fixed columns leave the canvas a third of the screen; with nothing chosen the right one shows the flow's facts.
- Every data thread is drawn all the time, along the dots' row and through their names; unused values stand as unnamed rings (`workspace-flows-thread-drawing.md`).
- A box's panel repeats one hint under every value field and carries paragraphs in a narrow column; the e-mail's text shows `^^name^^` typed while its subject takes chips.
- A box is added only from the left list; nothing is added between two boxes in place. Canvas notes, auto-layout and multi-select were never built (`workspace-flows-canvas.md`), though the store keeps `notes`.
- One box is tried at a time, with a past run's values; nothing walks a draft from start to end.
- Runs are read one at a time; nothing says how many wait at a box or where they fail.
- No box splits runs by share, no sending box keeps to hours, and a run leaves early only through a Condition after every Wait.

## What does the plan settle itself?
- Every function of today's window stays; what moves is said in `flows-editor-next-screens.md`. The List view, the phone forms, the run view, the read-only card and the mini window keep working; where they draw boxes or threads they follow the new rules.
- No schema: frames and leave rules are keys of the graph JSON (`ws_flows.draft`, `ws_flow_versions.graph`); counts read the run tables with their keys of 11.0.80; a run that left gets the status `left` (VARCHAR). A step that finds it needs a column stops and asks.
- New boxes follow `workspace/workspace-flows-adding.md`: general, never for one example.
- The parts join the Design examples screen as patterns in the step that first draws them ("Flow box", "Canvas tools", "Add list").

## What is left out?
- A vertical layout, flow variables, back arrows (`workflow/decisions-made-product-30.md` stands).
- Opens and clicks of a sent e-mail on the canvas; a split's "winner" picked by the system.
- The quick setup dialog and the template gallery: unchanged.

## What binds it?
- `workflow/decisions-made-workspace-47.md`; `workspace/workspace-flows-decisions.md`; `workflow/decisions-made-workspace-20.md` (the flow window of 2026-11-12, which this plan redraws by the owner's word); `ui/screen-composition-rules.md`.

Related: docs/memory/plans/flows-editor-next-screens.md, docs/memory/plans/flows-editor-next-screens-2.md, docs/memory/plans/flows-editor-next-screens-3.md, docs/memory/plans/flows-editor-next-screens-4.md, docs/memory/plans/flows-editor-next-steps.md, docs/memory/plans/flows-editor-next-steps-2.md, docs/memory/plans/flows-editor-next-steps-3.md, docs/memory/workspace/workspace-flows-overview.md
