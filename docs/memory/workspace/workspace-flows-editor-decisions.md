---
name: workspace-flows-editor-decisions
description: Lasting rules of the Flows editor redrawn in 2026-11 (canvas edge to edge, card panel, add at the arrow, Try, Counts, Split, Sending hours, Leaving the flow): what stays, no schema, left out
---

## Where does the redraw come from?
- Owner (2026-11-22): the Flows editor is redrawn and widened as the two sheets show; recorded with the owner's words in `workflow/decisions-made-workspace-47.md`. The sheets stay in `flows-editor-drawing/` (`editor.html` boards A1, B1, B2, C1, D1 to D3; `threads-panel-counts.html` boards 1a to 3), each with a dark and a light picture.
- Built in FE1 to FE14 (#11083 to #11475); the parts and their files: `workspace-flows-overview.md`, "What is on the editor?".

## What did the redraw keep?
- Architect (2026-11-22): every function of the window before it stays; only its place moved. The List view, the phone forms (under 768 px the List view first, "Add a box" as a page), the run view, the read-only card and the mini window keep working, and follow the new box and thread rules where they draw them.
- The two panels of 2026-11-12 keep their jobs (`workflow/decisions-made-workspace-20.md`): adding boxes is the rail, its list and "+" on the arrows; the chosen box is a card over the canvas, there only while something is chosen. Nothing is a fixed column from 768 px up.
- One filled button on the bar, Publish (`workspace-flows-window-bar.md`).

## What may a new part not need?
- Architect (2026-11-22): no schema. Frames and leave rules are keys of the graph JSON (`ws_flows.draft`, `ws_flow_versions.graph`); counts read the run tables with their existing keys; a run that left has the status `left` in the VARCHAR column. A change that needs a column asks the architect first.
- New boxes follow `workspace-flows-adding.md`: general, never for one example (Split, `workspace-flows-split-box.md`).
- A part drawn for the editor joins the Design examples screen as a pattern ("Flow box", "Canvas tools", "Add list").

## Which choices were made for the new functions?
- Architect (2026-11-22): the trial reads what reading boxes read, sends and writes nothing, passes a Wait, gives a box waiting for a person its first outcome and offers the others; only who may try a box may try the flow (`workspace-flows-trial.md`).
- Architect (2026-11-22): "Often next" in the add list is a fixed table, never counted use (`workspace-flows-add-here.md`).
- Architect (2026-11-22): Split has two to five arms in whole percents adding up to 100; the arm taken is the step's outcome.
- Architect (2026-11-22): sending hours only on Post a message, Send an e-mail, Send an SMS, Send a WhatsApp message; off at first; the site's time zone; outside them the box waits for the next hour or does not send, as chosen (`workspace-flows-send-hours.md`).
- Architect (2026-11-22): leave rules sit on the flow, each one event naming a person or a record; only a run about a record leaves; "left" is a state of its own and the owner is not told as for a failure (`workspace-flows-leave.md`).

## What was left out?
- A vertical layout, flow variables, back arrows (`workflow/decisions-made-product-30.md`); opens and clicks of a sent e-mail on the canvas; a split's winner picked by the system. The quick setup dialog and the template gallery were not changed.

Related: docs/memory/workflow/decisions-made-workspace-47.md, docs/memory/workspace/workspace-flows-overview.md, docs/memory/workspace/workspace-flows-decisions.md
