---
name: image-focus-control
description: The Focus control (IF2): where it shows in an Image's and a background picture's options, what it draws and writes per screen, the part 'focus' and its host, what is not built yet
module: image_focus
---

## Where does "Focus" show?
- Content › Image, under "Object Fit", in place of the "Object Pos" list, while the Image has a picture (`_sdFocusTakes(n, 'image')`). No picture: the list as before.
- Style › Color and background › Bg Image, in place of "Bg Pos", while the background picture is cut: size "Cover", or "No Repeat" at its own size (default or Auto). A pattern or no picture keeps the list. Several selected elements keep both lists.
- No bar button; the toolbar's "Set focus" is IF3 (`plans/designer-image-focus.md`).

## What does it draw?
- Head: "Focus", the value read out (`70% · 32%`, aria-live), then "Reset" (removes every screen's value) or, for a screen's own value, a chip "Phone" / "Tablet".
- The whole picture with one point (`role="slider"`), and the cut on a phone, a tablet and a desktop as small frames (`background-size: cover` at each screen's value). The frame of the screen the canvas bar shows carries the ring; a screen's own value turns its icon to the accent.
- Shapes: an Image's Aspect Ratio on every frame; else the screen shown takes the shape measured on the canvas, the others 0.8 (phone), 4:3 (tablet), 2.4 (desktop) (`PGImageFocus.shapes()`).
- Foot: none set, the hint "Drag the point onto what must stay in view."; a typed value, "Typed before as …"; a screen's own, "This focus is the phone's own. Wider screens keep …" and "Same on every screen"; inside a loop area, the info line that the focus is every record's.
- The picture is the one the canvas draws (`currentSrc`, the computed background), so a bound picture shows its sample.

## What does it write?
- A press puts the point there, a drag moves it: the canvas element's `object-position` / `background-position` and the frames follow live (one frame per animation frame, no tree change); release writes once, one undo step.
- Arrows move it by 1 %, Home to the middle; written once the keys rest 300 ms, focus kept on the point.
- The screen is the canvas bar's chosen width (`_sdFocusCanvasScreen()`, full width = desktop), so a move at 375 writes `objectPositionPhone` (`image-focus-value.md`). A per screen value adds `pg-focus` to User Styles (`_sdEnsureBlockStyles`); a background also gets the `pg-focus` class while it has one.
- A typed value is kept until the point is moved (`PGImageFocus.typed()`).

## How is it loaded and tested?
- Part `focus`: `assets/js/style_designer_focus.min.js` = `pg_image_focus.js` (window.PGImageFocus(F), pure, over the core twins) + `style_designer_focus.js`. The core keeps `_sdFocusTakes/Place/Host/Bind()`; the place is `<div class="sd-fc" data-sd-focus="image|background">`. A closed Bg Image group loads the part on first open.
- `tests/designer_image_focus_control_test.php`: views per screen, own values, typed, keys, pointer, Reset, Same on every screen, shapes, wiring. Not unit-tested: drawing and dragging in a browser.
- Measured (PR of IF2): core `style_designer.min.js` +4.3 KB; part 9 KB loaded only when a control is shown; Node edit-cycle timing on the fixture unchanged within spread.

Related: docs/memory/designer/image-focus-value.md, docs/memory/designer/designer-image-focus-drawing.md, docs/memory/designer/designer-parts-on-demand.md
