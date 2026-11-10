---
name: image-editor-render
description: The image editor's animation frames and GIF (AN3): renderFrame(), paintLayers() with a time, action base, the encoder worker, the Save animation dialog with estimate, progress, Cancel and Download
module: image_editor
---

## How is a frame drawn?
- `renderFrame(t, w, h, canvas)` (`assets/js/pg_image_editor_render.js`) answers a promise of the canvas: `paintBase()`, then `paintLayers(g, box, w, h, t)`, then the sticker part's `paintFrame()`. Without `t` paintLayers paints at rest, as the save's overlay always did.
- With a time each layer goes through `paintMoving()`: `M.motionAt()`'s transform around the box's centre, opacity, the wipe as a clip (after the transform, as the stage's clip-path), typed letters as a cut copy. A redaction never moves.
- A stroke drawn on has no path length on a canvas: `frameCutsAt()` draws its SVG node with the stage's dash (`pathLength=1`) into a picture first, which `paintMoving()` lays in its place.
- The base: an artboard's background; else the server's picture without layers on its canvas (`M.saveLayout()`: fill, then the picture at x, y) with the document's move (`M.baseAt()`, the picture only, clipped to its place).

## Where does the base come from?
- Action `base` (`pg_image_editor_action_base()`): the document with its layers emptied, the redaction masks and (without Imagick) the colour picture, drawn by `pg_image_editor_render()` on the version the browser opened (`base`); the bytes are sent as they are (payload `data` + `type` in `image_editor_api.php`). Nothing is written. Fetched once per dialog (`this.animBase`).

## How is the file encoded?
- `assets/js/pg_image_editor_encode.js`, its own bundle `pg_image_editor_encode.min.js`, its address in the config's `encoder`. A worker; where the worker is refused the same file is loaded on the page (`window.PgImageEncode`) and driven through the same messages one `setTimeout` at a time.
- Messages: `begin` (with `format`), `sample` (8 frames spread over the animation, `M.animSamples()`), `estimate` or the `frame`s (never more than 2 ahead of the encoder), `end` gives the bytes and the MIME type. One `E.session(post)` answers them for the worker and the page; APNG and WebP in `ui/image-editor-encode.md`.
- GIF: median cut to 255 colours from the samples, a transparent index 0 when a sample has see-through pixels (then each frame whole, disposal 2), else only the changed rectangle (disposal 1); Floyd-Steinberg when dithering is on; own LZW; delays in hundredths with the rounding carried and never under 2 (`delayAt()`); the NETSCAPE2.0 count is the repeats after the first play, none for one play (`loopCount()`).
- Tested under Node and read back by GD in `tests/image_editor_render_test.php`.

## What does the dialog offer?
- "Save animation" in the top bar, shown while a layer or the base moves (`animMoves()`), only where the editor saves into Files (`replace`, `copy`, `new`); not with `save.url` or a callback.
- Format (those this browser can make, `ui/image-editor-encode.md`), longest edge (240-800, 1080 for APNG and WebP, never past the picture's, `M.animEdges()`), frame rate 10-25, repeat (forever, once, three times, the document's own), dithering (GIF only), the estimate (header plus an average sample frame encoded whole times the frames, so it errs high), Download.
- A device with `navigator.deviceMemory` 4 or less starts at 240. An animation longer than the format's 15 s is refused with a note, not cut.
- Working: the file name, a bar, the steps (picture from the server, frames n / count, encoding; a video with sound adds the mix and the sound's encoding, `ui/image-editor-mix.md`), Cancel (stops the worker; nothing is written). At the end the browser downloads the file and a toast says so.
- Save to Files and the upload limit: `image-editor-animation-save.md`; MP4 and WebM: `image-editor-video-export.md`.
