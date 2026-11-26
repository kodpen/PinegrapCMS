---
name: sketch-feel
description: The Sketch tool's feel (SK8): what keeps a drag at 60 frames a second on a hundred boxes, touch near a handle, the selected box on top, how it is measured (tests/js/sketch_feel.js) and the numbers
module: designer
---

## What keeps a drag smooth?
- The guides of a moving box are placed and hidden through their style (`_sdSketchGuides()`), never added and taken out on a pointer move: each insert restyled the whole editor (about 10 ms at 4x slowdown), several a move.
- A press marks the selection in place (`_sdSketchSelDraw()` from `_sdSketchPick()`): no frame is built again, as no part reads which box is selected.
- A tap draws nothing again. A drag let go, a drawn box (the ghost becomes the box) and an arrow nudge (`_sdSketchChanged(true)`, `_sdSketchPlace()`) keep the box where it is, kept and a history step at once; the frames are drawn again once the hand rests (`_sdSketchMountSoon()`, `SD_SKETCH.rest` 300 ms), never while a drag runs (`_sdProto.sk.drag`). A full `_sdSketchMount()` drops the waiting one.
- So the reader's chips, the head's count and "Build again" follow 300 ms after a change.

## What changed for touch and pen?
- A touch on the selected box within 16 screen px (`SD_SKETCH.touch`) of a handle takes that handle (`_sdSketchNearSide()`, at most a quarter of the box, so its middle moves it): Chrome's touch adjustment hands a press near a handle to the focusable box. Pen and mouse hit the handles themselves.
- The selected box lies over the others (`z-index: 1`) with an opaque ground: its handles were under the boxes drawn after it, and their words showed through it.
- Pinch with two fingers moves no box (the core cancels the drag).

## Light and dark?
- The designer's chrome is dark under both panel schemes (`style_designer.css` `:root`), so the Sketch looks the same in both; checked in light and dark.

## How is it measured?
- `node tests/js/sketch_feel.js perf` on `tools/setup_sandbox.sh` with the built bundle (`php tools/build_js.php`, then `git checkout` it): a sketch of 100 boxes, Chromium at 4x CPU slowdown, frames by `requestAnimationFrame`, script time per pointer event, style time from `Performance.getMetrics`. `keys` drives touch, pen and every key of `prototype-sketch-tool.md` (27 checks); `--theme=light`.

## What were the numbers? (#11749, three runs each)

| 4x slowdown, 100 boxes | before | after |
|---|---|---|
| move a box | 25-30 fps, style 3.7-4.5 s a drag | 56-59 fps, 0.2 s |
| resize | 29-38 fps | 59-60 fps |
| draw a box | 34-37 fps | 59-60 fps |
| let go (a drag or a tap) | 78-194 ms | 0-7 ms |
| press on a box | 81-135 ms | 21-35 ms |
| held arrow key | 9-11 fps, 111-117 ms a key | 48-56 fps, 4.2-4.8 ms |

- Without slowdown both were 60 fps. The first press on a page not yet selected stays about 80-100 ms: selecting the frame on the map.
- Before, a touch on a handle covered by the next box, and a touch beside a handle, did not resize.
