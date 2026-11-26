---
name: designer-image-focus-drawing-frames
description: Board F5 of the image focus drawing (2026-11-25): the Focus control's phone, tablet and desktop frames stand under the whole picture, large, with their names; one frame when all three are the same
---

## Why a fifth board?
- Owner (2026-11-25), on the control as built from boards F1 and F2: "mobil tablet vs. için odak önizlemesi var ancak o kadar küçük ki çok kullanışsız. modernize edelim."
- Beside the whole picture the three frames were 34 to 104 px wide in the drawing and came out about 50 to 70 px in the panel: nobody can judge a cut at that size.
- Board F5 of `designer/designer-image-focus-drawing/image-focus.html` replaces where the frames stand and how large they are. Everything else of F1 and F2 holds (`designer-image-focus-drawing.md`). Built in #11724.

## Where do the picture and the frames stand?
- The whole picture takes the control's whole width, at most 200 px tall; a taller picture is drawn at 200 px, centred. The point is on it as before.
- Under it a small caption "On each screen", then the frames in two rows: phone and tablet share the first, desktop has the second.
- In a row the frames have one height: the largest at which the row fits the control's width, at most 120 px. With the control 288 px wide and the shapes 0.8, 4:3 and 3:1 that is 96 by 120, 160 by 120 and 288 by 96.
- The shapes are the ones the control works out today (`image-focus-control.md`, `PGImageFocus.shapes()`); only the sizes are new.

## What does a frame carry?
- Under it its icon and its name: "Phone", "Tablet", "Desktop", at 11 px.
- The frame of the screen the canvas bar shows has the ring and the bright name. A screen's own focus turns its name blue, with the chip in the head as before.
- A frame is a picture, not a button: a press on it does nothing.

## When is there one frame?
- When every screen has the same shape and the same focus (an Image with an aspect ratio and no screen's own value) the three would be the same picture. Then the caption reads "On every screen" and one frame stands there, named "Every screen" with the aspect ratio icon, at most 140 px tall and never wider than the control.
- Once a screen gets its own focus the three frames are back.

## What is drawn?
- Four columns: no focus set (the pale point, the hint line under the frames); set, the desktop ringed; the canvas at 375 with the phone's own focus, its foot line and "Same on every screen"; an Image with the aspect ratio 1:1 and its one frame.
- No board: a typed value, a bound card and a background picture keep their lines of F2 under the frames. Narrow: the same rule by the control's width; in the Options panel as a sheet on a phone the rows reach the 120 px cap and stand at the left.

## What is only an example?
- The photograph, the values 70% · 32% and 48% · 30%, the shapes.
