---
name: prototype-sketch-tool
description: The Prototype view's Sketch tool (SK2): Sketch and New page, frames k:<n>, boxes with snapping (snapRect) and guides, chips and kinds, typed headings, keys, undo, where it is kept
---

## What is it?
- "Sketch" (a switch, `ui.sketchBtn`) and "New page" (`ui.sketchNew`) lead the tools zone of the canvas bar's Prototype mode (designer/designer-canvas-bar-prototype.md). New page puts an empty sketch frame right of everything on the map, selects it, zooms to it as Enter does (head and both edges in sight; Esc or "All" goes back to the map) and turns the switch on (#11884; before, a frame taller than the view was centred with its head hidden).
- A sketch frame is a box of the graph canvas core with id `k:<n>` and `kind: 'sketch'`, added after the pages and chips by `_sdSketchMount()` (called by `_sdProtoDraw()` and after each sketch change). It has no ports: no arrow goes to it (`canConnect`). Fit, zoom, Enter's zoom to a frame and moving the frame by its head work as for a page.
- Code: the "Sketch" section of style_designer.src.js (`_sdSketch*`), styles `.sd-sketch-*` in style_designer.css, tests `tests/designer_sketch_test.php`.

## How are boxes drawn?
- Only while the switch is on (off: a press on the body moves the frame). The view's adapter hands a press inside `.sd-sketch-body` to `_sdSketchPress()` first: on a handle `skresize`, on a box `skmove`, on the empty body `skdraw`; `move`, `release` and `cancel` go to the sketch for kinds starting with `sk`.
- Units are the frame's: 480 wide, a body at least 640 high that grows 80 under the lowest box. A box is at least 24 by 24; a smaller drawing is dropped.
- `_sdSketchRect()` lines a box up with `PGGraphCanvas.snapRect()` (ui/graph-canvas-core-module.md): edges and middles of the frame and the other boxes within 6 screen pixels, else the 8-unit grid; Alt held is free. A drawn box's pressed corner lines up the same way, not only the dragged one (#11884: boxes drawn side by side started at 17, 170, 324). Guides (`.sd-sketch-guide`) show while it moves. A box stays inside the frame's width and below its top.

## What does a box carry?
- A chip (`.sd-sketch-chip`) naming its kind; its menu lists "Read from the sketch" and the kinds `header`, `intro`, `features`, `cards`, `text`, `picture`, `gallery`, `form`, `cta`, `footer`, `catalog`, `blog`, `booking` (`SD_SKETCH_KINDS`). `kind: ''` means not chosen: the reader (SK1) decides; until it runs the chip says "Not read yet" in amber. A chosen kind is the user's and binds.
- A heading (`text`, one line, at most 80 characters): a letter typed on a selected box starts it, Enter, F2 or a double-click edits it; Enter or leaving the input keeps it, Esc drops the edit.

## Which keys?
- On a selected box: Delete or Backspace takes it out, the arrows move it 8 (Shift 32), Esc selects its frame. `+`, `-`, `=`, `_`, `0` and Space stay the map's keys.
- On a selected sketch frame: Delete takes the page out.
- While the switch is on, Ctrl+Z and Ctrl+Y undo and redo the sketch (`G.historyOf()`, arrow nudges fold into one step), not the open tab; with the switch off they undo the tab as before (designer/prototype-view-rules.md K4).

## Where is a sketch kept?
- In this browser only, `localStorage` `pg_proto_sketch_<style id>`: `{ n, pages: [{ id, name, x, y, boxes: [{ id, x, y, w, h, kind, text }] }] }`, read through `_sdSketchClean()` (at most 50 pages and 200 boxes a page, unknown kinds dropped). A built page also keeps it in its draft's root (`props._sketch`, `designer/sketch-build.md`); no table or column was added.

## How is it checked?
- The pure parts (`snapRect()`, `_sdSketchClean()`, `_sdSketchRect()`, `_sdSketchNearSide()`) are tested in Node. The drags, touch, pen, the chip menu and every key run in Chromium by hand (`tests/js/sketch_feel.js`, `sketch-feel.md`).
