---
name: image-editor-animation-output
description: Outputs of plans/image-editor-animation.md: the encoder worker, GIF, APNG, WebP, MP4, WebM, PDF and SVG with their limits, the save into Files, re-editing an animation, scenes and the Present mode
---

## Where are files encoded?
- In one worker, `assets/js/pg_image_editor_encode.js` (its own entry in `pg_js_bundles()`, same origin, which the built-in CSP allows; where a site's own policy refuses a worker, the same code runs on the page in slices). Pure byte work tested under Node: each output read back by PHP (GD, `pg_image_is_animated()`) or parsed box by box.
- GIF89a: one palette of up to 255 colours from sampled frames (median cut), optional Floyd-Steinberg dithering, a transparent index on a transparent background, only the changed rectangle of each later frame, own LZW, the NETSCAPE2.0 loop count. Delays in hundredths with the rounding carried over (browsers slow anything under 2).
- APNG: each frame's PNG from the canvas re-chunked into `acTL`, `fcTL` and `fdAT`; changed rectangles.
- Animated WebP: the canvas's WebP frames in a `VP8X`, `ANIM`, `ANMF` RIFF; offered only where the browser encodes WebP (not Safari).
- Video: WebCodecs `VideoEncoder`, H.264 into an own MP4 muxer (index first) or VP9 into an own WebM muxer; else MediaRecorder on `canvas.captureStream()` in real time (WebM or MP4, whichever the browser has); else no video. Silent (open question 2).
- PDF: a page per scene, each a JPEG (`DCTDecode`) at the scene's size, from an own small writer.
- Animated SVG (E19's content): the server writes one `<style>` of `@keyframes` from the preset names and numbers, never the browser's CSS, inside `prefers-reduced-motion: no-preference`; no SMIL, no script.

## Which limits apply?
- GIF: longest edge up to 800 (default 480), 15 s, 10-25 fps. APNG and WebP: up to 1080, 15 s. Video: up to 1920, even sides, 24 or 30 fps, 60 s.
- Every output fits the server's upload limit (`pg_upload_limits()`, sent at open): the dialog estimates the size from a few encoded frames and offers a smaller size or rate before it starts. A phone (`navigator.deviceMemory` 4 or less) starts at half the default size.

## How is it saved?
- The Save animation dialog: format, size, frame rate, loop, the estimate; Download keeps it on the device, Save puts it in Files.
- Action `animation`: the file as a binary body (not base64 in JSON), its type told by its bytes (GIF, APNG, animated WebP, MP4 `ftyp`, WebM EBML, PDF), never its name; a new file beside the source or in the artboard's folder; its version keeps the document, which names its source file.
- Opening that file later finds the document and edits it; frames come from the source again. Replace is allowed for an animation the editor made, in the same format; any other animated file still opens as a still copy, as today.
- `.webm` joins the MIME map; `get_file.php` already answers ranges, so a player can seek.

## How do scenes and Present work?
- A scene strip under the stage: thumbnails, add, duplicate, delete, drag to reorder, a duration each; transitions fade, slide, zoom, wipe. The timeline and every export play the scenes back to back.
- Present (AN8): full screen, one scene at a time with its motion; a click, Space, the arrow keys or a swipe moves on; Escape leaves.
