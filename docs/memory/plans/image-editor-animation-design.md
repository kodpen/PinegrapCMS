---
name: image-editor-animation-design
description: Design of plans/image-editor-animation.md: the motion fields of the document and a layer, the presets, M.motionAt(), the preview on the stage, the frame renderer and the still base the server draws
---

## What does the document hold?
- `doc.motion`: `{ duration, loop, base }`. Duration in ms (1,000-60,000, default 5,000); loop 0 for forever or a count; base an optional move of the picture under the layers (slow zoom in or out, pan) over the whole duration. Absent on a still document.
- A layer's `anim`: `{ in, loop, out }`, each absent or `{ kind, ms, delay, ease, dir }`. `in` starts at its delay, `out` ends at the end, `loop` runs from the end of `in` to the start of `out`.
- In and out kinds: fade, slide, rise, pop, zoom, wipe, spin, draw (the stroke drawn along its path: shapes, pen, path, arrow), type (a text letter by letter). Loop kinds: pulse, float, spin, blink, wiggle, swing. `dir` left, right, up or down where the kind moves; ease linear, in, out, in-out, back, bounce.
- Scenes (AN7): `doc.scenes`, each `{ id, name, layers, background, duration, transition }`; the open scene's layers are `doc.layers` while it is open, so the tools need no change.

## How is a moment computed?
- `M.motionAt(layer, t, end)` in a `MOTION` section of the model gives `{ opacity, x, y, scale, rot, clip, draw, chars }`: offsets in rotated-space units, `clip` a side and fraction (wipe), `draw` the stroke's drawn fraction, `chars` the letters shown. In, loop and out compose; no `anim` or a null `t` gives the rest state.
- Pure and tested under Node (`tests/image_editor_motion_test.php`): each kind at its start, middle and end, every ease, the compose order, a loop alone, `chars` rounding.

## How does the stage show it?
- `this.time` is the playhead while playing or scrubbing, else null: editing always sees every layer at rest, and the editor never plays by itself.
- `renderLayers()` (layers part) puts each layer's node in a `<g>`; the motion part sets that group's transform (around the layer box's centre, in rotated space), opacity and clip path from `motionAt()` on each frame. Only a layer whose state cuts its drawing (`draw` a dash offset, `chars` a cut of the text) is drawn again; the text, draw and vector parts read that state.
- requestAnimationFrame moves the playhead; nothing else in the view is redrawn.

## How is a frame drawn?
- `renderFrame(t, w, h)` on a canvas: the base, then `paintLayers(g, box, w, h, t)`, each type's `paint(g, l, state)` inside the same transform, clip and opacity. The save's overlay is this call at rest.
- The base comes from the server once per export: action `base` runs `pg_image_editor_render()` with the document minus its layers, the redaction masks included (E10b), and streams the bytes; nothing is written. An artboard's base is its background, drawn here.
- `layersReady()` first; frames are drawn on the main thread (text needs the page's fonts), a few per tick behind a progress bar with Cancel, and passed to the encoder worker as transferred `ImageData` buffers.
- Offered where the editor saves into Files (replace, copy, new); a caller with `save.url` or a callback gets no animation export.
