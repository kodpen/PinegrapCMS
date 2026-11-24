---
name: workspace-flows-canvas-tools
description: The flow canvas's tools (FE2): the add rail with group icons and the list as a card beside it; the bottom strip with Undo, Redo, zoom, Fit, Tidy, Data threads, Note, Map; no palette column
---

## What changed, and why?
- The flows editor's redraw (`workflow/decisions-made-workspace-47.md`; board A1 of `flows-editor-drawing/editor.html`) gives the canvas the editor's whole width: the left column "Add a box" and the bar's tools left for floating tools over the canvas. Built in FE2 (#11140).
- `.wsf-app` is one column, the canvas from edge to edge; the chosen box's panel is a card over it (FE3, `workspace-flows-side-panel.md`).

## What is the add rail? (drawPaletteRail() in workspace_flow.js)
- 48 px, floating 14 px in from the canvas's top left (`ui.palette` inside `.wsf-main`, not the stage): a filled "+" and one icon button per group of `RAIL_GROUPS` (Message, People and tasks, Logic, Records and tables, System, Values), each the line icon of a box of its group, in the group's colour, its name as tooltip.
- A group the person can add no box of (a member's kinds, the Workspace off) has no icon (`railGroups()`).
- "+" opens or closes the list (`workspace-flows-palette.md`) as a 264 px card beside the rail (`foldPalette()`, `state.paletteOver`, `wsf-palette-over`); a group's icon opens it with the search cleared, that group unfolded at the top and its heading focused (`openPaletteAt()`, `paletteUnfold()`).
- Esc (focus back on "+"), Close, a box added or a click on the canvas beside it closes the card. `pg_wsf_palette` in localStorage is no longer read; the canvas opens with the card closed.
- Hidden in the List view (it has its own add buttons) and for a reader; under 768 px the "Add a box" button opens the card as a page.
- Keyboard: Tab reaches the rail from the canvas's area; F6 stops on the card while it is open; the Keyboard list names the rail (`fl_key_rail`).

## What is the bottom strip? (drawStrip())
- 44 px, centred 16 px over the canvas's bottom, on the floating surface: Undo and Redo (while the draft is edited), a divider, zoom out, the percent, zoom in, Fit, a divider, Tidy with its word (while the draft may change), Data threads, Note (while the draft may change, `workspace-flows-notes-frames.md`), Map (`workspace-flows-tidy-map.md`). `pg-icon-button pg-icon-button-sm`, name as tooltip and `aria-label`; a pressed one by the quiet fill. Its group is named "Canvas tools" (`fl_strip`).
- Redrawn with the bar (`drawBar()` calls it): the buttons follow the flow shown, a run shown has no Undo. Hidden with the List view, so List has no threads toggle.
- An embedded drawing (`workspace-flow-embed.md`) has the zoom and Fit alone.

## What is known not to fit yet?
- Fit (the canvas core, `pg_graph_canvas.js`) keeps 48 px clear of the edges and knows neither the rail nor the strip: a box may be fitted under them.
- `freePlace()` steps down by whole grid steps (80 + 32 px), so a box added where another stands stays on the grid (a 72 px box made the step 104 px).

## Where is it tested and shown?
- `test_workspace_flows_canvas_add_rail_and_strip` (Node): the rail's groups and icons, a member's groups, `paletteUnfold()`; no `pg_wsf_palette`, Undo off the bar, no folded column.
- Harness scenario "the add rail floats over the canvas" (`workspace-flows-canvas-harness.md`).
- Design examples: the pattern "Canvas tools" (`pg_look_examples_canvas_tools_pattern()`).

Related: docs/memory/workspace/workspace-flows-palette.md, docs/memory/workspace/workspace-flows-window-bar.md, docs/memory/workspace/workspace-flows-undo.md
