---
name: prototype-sketch-keys
description: The Sketch tool's keys and Add a box (B, More, command palette), Alt+arrows sizing a box, the undo, the links panel's sentence on a sketch page (SX1, #11907)
---

## How is a box made without a pointer?
- "Add a box" (#11907, `_sdSketchAddBox()`): B on a selected sketch page, the first row of the page's "More" menu, a command palette row while a sketch page is selected. The box is as wide as the page, 96 units high (`SD_SKETCH.addH`), 16 under the lowest box (`addGap`), at the top of an empty page (`_sdSketchAddRect()`); it is selected and focused, the switch goes on, one undo step. Why: no box could be made without a pointer (UR3).

## Which keys?
- On a selected box: Delete or Backspace takes it out, the arrows move it 8 (Shift 32), Alt and an arrow move its right or bottom edge 8 (Shift and Alt 32; `_sdSketchSize()`: never under 24 by 24, never past the page's width; #11907), Esc selects its frame. B and Alt+arrows are in the editor's shortcuts help ("Sketch"). `+`, `-`, `=`, `_`, `0` and Space stay the map's keys.
- On a selected sketch frame: Delete takes the page out, B adds a box. A letter on a selected box still starts its heading.
- The links panel with a sketch page selected says "A sketch page has no links until it is built." (`_sdProtoListDraw()`).
- While the switch is on, Ctrl+Z and Ctrl+Y undo and redo the sketch (`G.historyOf()`, arrow nudges and Alt+arrow sizes fold into one step), not the open tab; with the switch off they undo the tab as before (designer/prototype-view-rules.md K4).

Related: docs/memory/designer/prototype-sketch-tool.md, docs/memory/designer/designer-sketch-tool-drawing.md
