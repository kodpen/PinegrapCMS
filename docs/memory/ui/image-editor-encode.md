---
name: image-editor-encode
description: The Design Canvas's APNG and animated WebP (AN5): the message session, the formats a browser can make, deflate, changed rectangles, WebP frame chunks, loop counts
module: image_editor
---

## Which formats does the dialog offer?
- GIF everywhere; APNG where `CompressionStream` and `Response` exist; WebP where a canvas encodes WebP and `OffscreenCanvas` exists, so not Safari (`animCaps()` in the render part, `M.animFormats(caps)` in the model).
- `M.ANIM_FORMATS`: GIF up to 800, APNG and WebP up to 1080; all 15 s, 10-25 fps, default 480 and 15. Switching format keeps the edge and the rate inside the new format's limits (`M.animSwitch()`); dithering is GIF's only (`dither: true`).

## How do the messages run?
- `E.session(post)` (`Session`) answers `begin`, `sample`, `estimate`, `frame`, `end` in order for the worker and the page alike, whether a format's work is immediate (GIF) or a promise (APNG, WebP); the file's MIME comes from the encoder. After a failure it posts its one error and ignores the rest; the worker starts a new session on each `begin`.
- `E.create(opts)`: `opts.format` `apng` or `webp` gives a `TrueEncoder`, anything else the GIF `Encoder`.

## How is an APNG written?
- The samples decide RGB or RGBA. Each row goes behind the filter (none, sub, up, average, Paeth) with the smallest sum of magnitudes, then the whole frame through `CompressionStream('deflate')` (zlib, as PNG wants).
- The first frame whole (`IDAT`), each later one only its changed rectangle (`fdAT`, blend source, dispose none, so alpha stays right). A frame equal to the one before adds to its delay (`delay_num` over the fps). `acTL` (real frame count, plays, 0 forever) is written at the end.

## How is an animated WebP written?
- Each frame, or its changed rectangle with the offsets made even, goes to the browser's still WebP (`OffscreenCanvas.convertToBlob()`, quality 0.9; a test passes its own `opts.webp`).
- Only the still's `ALPH`, `VP8 ` and `VP8L` chunks go into the `ANMF`: Chrome adds an `ICCP`, and a frame holding it makes Chromium refuse the whole file.
- No blending, no disposal; durations in ms with the rounding carried (`msAt()`), a repeat merged. `VP8X` sets the animation flag and alpha when a frame has `ALPH` or `VP8L`. `ANIM`'s loop count is the plays, 0 forever (Chromium repeats plays - 1 times).

## How is it tested?
- `tests/image_editor_encode_test.php` under Node: the APNG chunk by chunk with CRCs and sequence numbers, read back by GD and `pg_image_is_animated_png()`; WebP muxed from GD's stills and parsed; the session's order; the model's formats.
- Chromium's `ImageDecoder` decoded both formats with the right frame count, pixels and alpha, in the worker and on the page (checked by hand, not in CI).
