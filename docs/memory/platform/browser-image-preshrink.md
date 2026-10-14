---
name: browser-image-preshrink
description: Browser-side pre-shrink of images over server upload limits: when, EXIF orientation check, quality-before-pixels order, shrinkTo/shrinkQuality, blob name.
---

## When does the browser shrink an image?
- Only for a file the server would reject. A file that fits is untouched: the server re-encodes from the original bytes with Lanczos, better than any canvas result. Principle: a bad copy that arrives beats a good copy that does not.

## What is the real danger (orientation)?
- Canvas has no metadata. If the browser decodes without applying the `Orientation` tag, a sideways image goes up and the tag the server's fix would read no longer exists.
- The `ImageBitmapOptions` member cannot be feature-detected, so ask the browser directly: a 2×1 JPEG tagged `Orientation=6` comes back 1×2 from a conforming browser (`orientationApplied()`, run once, cached).
- On a non-conforming browser, do not request JPEG shrinking. PNG/WebP carry no orientation.

## In what order is it encoded?
- Quality first, then pixels: 2400 px/q0.60 reads better than 1400 px/q0.85. PNG has no quality knob; only pixels move.
- If no combination fits, the file is NOT sent and is reported by name.
- `shrinkTo` / `shrinkQuality` are the SAME numbers the server will use (`pg_pb_upload_limits()`); two different ceilings on two sides of the wire for the same photo is an unexplainable difference. If the feature is off, `shrinkTo` is zero.

## What is the Blob name pitfall?
- A canvas Blob has no name: use `form.append('file[]', blob, name)`. Without the third argument the server stores it as `blob`.

Related: docs/memory/platform/upload-limits-and-empty-post.md
