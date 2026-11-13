---
name: workspace-flows-arrow-names
description: Flow canvas: an arrow's outcome name on a pill at its middle (edgeNamed(), edgePill(), FW5 #9251), which arrows carry one, the rose warning mark of a box with a problem
---

## Which arrows carry their outcome's name?
- `edgeNamed(from, on)` in workspace_flow.js: every arrow of a box with more than one outcome (Done / Failed, Yes / No, Approved / Rejected / Expired, an AI box's classes), and any arrow on always ("However it ended"), each, other, cancelled or failed.
- An arrow of a box with one outcome (a post, an e-mail, a start) carries none.
- Always keeps its name: it was drawn before the flow window plan (`workspace-flows-arrows-always.md`) and a control is moved, never dropped, though the plan's map had always unnamed.

## How is the name drawn?
- `edgePill()`: an SVG `rect.pgc-edge-pill` (18 px high, `rx` 9, its width the text's measured length + 14, else 6.2 px a letter when the canvas is hidden) under `text.pgc-edge-label` at the arrow's middle (`path.mx`, `path.my` of `canvas.edge()`), both inside the arrow's group, so a click on the pill chooses the arrow.
- The pill is the canvas's background with the panel's border colour; a chosen arrow's pill takes the accent, an arrow the server names in a problem (`wsf-bad`) the danger colour (backend.src.css, beside `.pgc-edge-label`).
- The scheduled-action window's small drawing (`.pgc-edge-label` beside the box it leads to) is not a pill and stays as it was.

## And the warning mark?
- A box with a problem (`markProblems()` gives it `wsf-bad`) shows `.wsf-node-warn`, a `bi-exclamation-triangle-fill` in the danger (rose) colour at the right of its head; the box's tooltip lists its problems, then its warnings. A box with warnings only has no mark: the right panel lists them (`workspace-flows-empty-panel.md`). A run's boxes are never marked.

## Tests
- Harness (`workspace-flows-canvas-harness.md`): the outcome scenario and the problems scenario draw the pill and the mark (picture 12 with `--shots`).
