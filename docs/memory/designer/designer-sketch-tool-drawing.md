---
name: designer-sketch-tool-drawing
description: Drawing of the Sketch tool (DD4, 2026-11-26): switch, new page, a box drawn and named, the chip's kinds, every key; what SX1 (#11907) added for the keyboard and links panel
---

## Where is it?
- `docs/memory/designer/designer-sketch-tool-drawing/`: `tool.html`, `drawing.css` (the Sketch sheets' styles and this sheet's own), `tool-dark.jpg`, `tool-light.jpg`. Boards T1 to T6.
- The tool was built before its sheet (SK2, #11497) and then used in the use round (UR3, #11884). The sheet draws it as built (`prototype-sketch-tool.md`, `sketch-feel.md`) and marks "new" what the fix step SX1 added (#11907). The later steps' sheets are in `designer-sketch-drawing.md`.
- The Prototype view is the designer's own dark frame in both pictures.

## Which board shows what?
- T1 Sketch on, a new sketch page, the links panel. T2 a box drawn, selected, its heading typed. T3 the chip's menu and its thirteen kinds. T4 by the keyboard alone. T5 narrow, 390 px. T6 every key and press of the tool.

## What did the fix step SX1 add? (#11907)
- Architect (2026-11-26), answering the two questions the UR3 worker left in #11884.
- An empty sketch page says "Drag to draw a box, or press B" in its own body; the line leaves with the first box.
- B on a selected sketch page while Sketch is on adds a box as wide as the page, 96 units high, 16 under the lowest box (at the top of an empty page), and selects it. The same act is "Add a box", the first row of the page's "More" menu, and a row of the command palette. On a selected box a letter still starts its heading.
- Alt with an arrow moves a selected box's right or bottom edge by 8 units, Shift and Alt by 32; never under 24 by 24, never past the page's width.
- Each of these is one step of the sketch's undo; the new keys are listed in the editor's shortcuts help.
- The links panel with a sketch page selected says "A sketch page has no links until it is built."
- Why: no box could be made without a pointer, and the panel asked to select a page while one was selected.

## What stays?
- Everything not marked "new" is the built tool and is not changed by the step: the lining up, the chip and its kinds, the heading's keys, touch, the undo.
- Labels go through `php tools/tr_add.php`; a test for the place and size of the added box and for the size's limits.

Related: docs/memory/designer/prototype-sketch-tool.md, docs/memory/designer/designer-sketch-drawing.md
