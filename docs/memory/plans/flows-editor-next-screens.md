---
name: flows-editor-next-screens
description: Plan flows-editor-next, the editor in words (board A1): the bar, the add rail, the bottom strip, the map, a box as a card, the chosen box's card over the canvas, what opens with nothing chosen
---

## How is the editor laid out? (board A1)
- The bar (52 px) and under it the canvas from edge to edge; no column on either side at 768 px and up. Under 768 px today's forms stay (List view first, "Add a box" as a page).
- The bar, left to right: the back arrow, the flow's name with a chevron, the status pill, the save state; in the middle Canvas / List; at the right "No problems" or the problems' count, Counts, Flow runs, Try, Publish, the menu. Undo, Redo and Data threads leave the bar for the bottom strip.
- One filled button, Publish, as today (`workspace/workspace-flows-window-bar.md`); who cannot publish keeps today's rule for Run.

## What opens with nothing chosen?
- No panel. The name's chevron opens a small window under it with what the empty panel held: Start, Boxes and arrows, Last flow run, Publishing, the live/draft hint, the home channel field, the Keyboard list folded.
- "No problems" / "Problems: N" opens the problems and warnings as a list under the button, each with "Go to the box"; Esc closes; the focus rules of `showProblems()` stay.

## How is a box added from the rail?
- A 48 px rail floats at the canvas's top left (14 px in): a filled "+" and six icon buttons, one per group (Messages, People, Logic, Records and tables, System, Values), each with its name as tooltip.
- "+" opens today's list (search, sections, Start with "Change") as a 264 px card beside the rail; a group's icon opens it scrolled to and unfolding that group. Click or drag adds as today; Esc, a box added or a click on the canvas closes it.
- The rail replaces both the open column and the folded rail of numbers; `pg_wsf_palette` in localStorage is no longer read.

## What is on the bottom strip and the map?
- One strip, 44 px, centred 16 px over the canvas's bottom: Undo, Redo; zoom out, the percent, zoom in, Fit; "Tidy" with its word, Data threads, Note, Map. Icon buttons with tooltips; pressed ones by the quiet fill.
- The map: a 172 by 104 px card at the bottom left, the whole flow as small blocks and the part on screen framed; a drag on it moves the canvas. Shown when the flow has 12 boxes or more, or when Map is pressed; the choice is kept per browser.

## How does a box look?
- A card 196 by 72 px on the panel's card surface with the hairline border and the card shadow, radius 13 px.
- At its left a 30 px tile in the group's colour with the kind's line icon; beside it three lines: the kind (10 px capitals, the group's colour), the title (13.5 px, 600), one line of what it does (12 px, the second ink). Each cut with three dots, the whole text in the tooltip.
- The out port is an 8 px dot in the group's colour on the right edge; the in port shows on pointing. Chosen: the ring of today (`--pgc-ring`). A problem box keeps its red line, a run view box its run state's line, both on the border.
- A value box keeps a dashed border. The group colours and their contrast test stay (`workspace/workspace-flows-box-look.md`).
- An arrow is 1.75 px in today's grey with its filled head; an arrow of a box with several outcomes carries the outcome's name in a small pill on it.

## How is the chosen box's panel shown?
- A card 320 px wide over the canvas's right edge, 14 px in from the top, right and bottom, on the floating surface; the canvas stays draggable beside it. It opens when a box, an arrow, a thread or a run is chosen and closes with Close, Esc or a click on the empty canvas.
- Its head, tabs (Settings, Connections, Try) and foot are today's (`workspace/workspace-flows-side-panel.md`); its fields follow `flows-editor-next-screens-2.md`.
- A chosen box that would sit under the card is brought into the free part of the canvas.

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-screens-2.md, docs/memory/plans/flows-editor-next-steps.md
