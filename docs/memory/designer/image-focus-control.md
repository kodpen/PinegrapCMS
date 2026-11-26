---
name: image-focus-control
description: The Focus control (IF2): where it shows in an Image's and a background picture's options, what it draws and writes per screen, the part 'focus' and its host, what is not built yet
module: image_focus
---

## Where does "Focus" show?
- Content › Image, under "Object Fit", in place of "Object Pos", while the Image has a picture (`_sdFocusTakes(n, 'image')`).
- Style › Color and background › Bg Image, in place of "Bg Pos", while the background picture is cut: size "Cover", or "No Repeat" at its own size (default or Auto). A pattern or no picture keeps the list. Several selected elements keep both lists.
- No bar button. "Set focus" in the selected element's toolbar drags the picture on the canvas (`image-focus-mode.md`); while it runs, this control's point follows.

## What does it draw?
- Head: "Focus", the value read out (`70% · 32%`, aria-live), then "Reset" (removes every screen's value) or, for a screen's own value, a chip "Phone" / "Tablet".
- The whole picture across the control's width, at most 200 px tall (a taller one at 200 px, centred), with one point (`role="slider"`); sizes from `PGImageFocus.whole()`.
- Under it "On each screen" and the cut (`background-size: cover` at each screen's value) as frames in two rows: Phone and Tablet, then Desktop (board F5, ON1 #11724). A row's frames have one height, the largest fitting the control's width (8 px gap), at most 120 px: at 288 px 96x120, 160x120, 288x120 (`PGImageFocus.sizes()`, width measured per draw, else 288).
- Under each frame its icon and name (11 px); the canvas bar's screen has the ring and the bright name, a screen's own value an accent name. No button.
- One frame when every screen has the same shape and the same point (an Image with an Aspect Ratio and no screen's own value): caption "On every screen", one frame "Every screen" with the aspect-ratio icon, at most 140 px tall, never wider than the control. A screen's own value brings the three back.
- Shapes: an Image's Aspect Ratio on every frame; else the screen shown takes the shape measured on the canvas, the others 0.8, 4:3, 2.4 (`PGImageFocus.shapes()`; the drawing's 3:1 desktop was an example).
- Foot: none set, "Drag the point onto what must stay in view."; typed, "Typed before as …"; a screen's own, "This focus is the phone's own. …" and "Same on every screen"; in a loop area, that the focus is every record's.
- The picture is the canvas's (`currentSrc`, computed background): a bound one shows its sample.

## What does it write?
- A press puts the point there, a drag moves it: the canvas element's position and the frames follow live (per animation frame, no tree change); release writes once, one undo step.
- Arrows move it 1 %, Home to the middle; written once keys rest 300 ms.
- The screen is the canvas bar's width (`_sdFocusCanvasScreen()`, full = desktop): a move at 375 writes `objectPositionPhone` (`image-focus-value.md`). A per screen value adds `pg-focus` to User Styles; a background gets the `pg-focus` class while it has one.
- A typed value is kept until the point is moved (`PGImageFocus.typed()`).

## How is it loaded and tested?
- Part `focus`: `assets/js/style_designer_focus.min.js` = `pg_image_focus.js` (window.PGImageFocus(F), pure, over the core twins) + `style_designer_focus.js`. The core keeps `_sdFocusTakes/Place/Host/Bind()`; the place is `<div class="sd-fc" data-sd-focus="image|background">`. A closed Bg Image group loads the part on first open.
- `tests/designer_image_focus_control_test.php`: views per screen, own values, typed, keys, pointer, Reset, Same on every screen, shapes, frame sizes and the one-frame case, the whole picture's size, wiring. Not unit-tested: drawing and dragging in a browser.
- Measured (#11576): core +4.3 KB; part 9 KB, loaded only when a control shows.

Related: docs/memory/designer/designer-image-focus-drawing-frames.md, docs/memory/designer/image-focus-value.md, docs/memory/designer/designer-image-focus-drawing.md, docs/memory/designer/designer-parts-on-demand.md
