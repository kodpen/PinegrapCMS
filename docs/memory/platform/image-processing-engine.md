---
name: image-processing-engine
description: Single image engine pg_process_image_file(): optimize vs resize jobs and the eight invariants (size guard, atomic write, EXIF, CMYK, GIF, memory, pixels).
---

## Where may pixels be touched, and what are the two jobs?
- `pg_process_image_file()` (`includes/image_engine.php`, loaded by functions.php and by get_file.php for derivatives) is the ONLY place that touches pixels. Never open a second compression path: if the code depends on the call site, the same file gets different results on two screens. That file may call only its own functions and PHP (get_file.php has no functions.php).
- `format` converts to `webp` or `avif` (`pg_image_format_writer()` picks Imagick, else GD; reason `no_webp`/`no_avif` when neither can).
- optimize: strips metadata, recompresses at the SAME pixel size. Default; every call without `max_dimension`.
- resize: long edge reduced to a ceiling, then compressed. Only when explicitly requested.
- Shrinking is never implied: a design image placed at exact size must survive the "optimize" button.

## What are the invariant rules?
1. Write nothing if the result is larger than the original, even when shrinking (palette PNG scaling creates edges that no longer fit the palette → smaller image, heavier file). Both buttons promise a lighter file.
2. Write to a temp file + `rename()`. Direct overwrite leaves a half image if the disk fills, and `files.name` is that file's public address.
3. Resolve EXIF orientation in pixels, not by tag. Rewriting the tag after strip only works if every reader obeys it (GD never wrote it). Symptom: "optimized, phone photo turned sideways". Both halves recompute the resize target from the upright picture; the Imagick half used getimagesize()'s sideways edges until PR #589 and squeezed a rotated phone photo (3000x4000 into 2400x1800).
4. Convert color space only when it is CMYK. Unconditional `setColorspace(RGB)` labels instead of converting; labelling sRGB as linear gives washed-out colors.
5. Never touch animated GIFs (`pg_image_is_animated_gif()`): both libraries read frame 1 and write a still, with no on-screen explanation.
6. Compute memory first (`pg_image_ensure_memory()`). GD holds two copies at 4 bytes/pixel while resizing (24 MP photo ≈ 200 MB). At a 128 MB limit that is a fatal error mid-request, i.e. a blank page on the upload path. Raise the limit only for this request, up to a 1 GB ceiling; `-1` would take down the whole box, not one upload.
7. The resize criterion is pixels, not bytes (`pg_image_can_be_resized()`): a low-quality 6000 px photo can be under 1 MB yet 6x too wide; a heavy 1200 px screenshot gains nothing from losing pixels.
8. The threshold cannot go below the target: `pg_image_settings()` clamps it, otherwise the button is offered for images that resizing would never change.

## Which screens call it, and what went wrong on the one that did not?
- Optimize and resize (`optimize_image.php`), uploads (`add_file.php`, API `files.php`), the file manager's rotate, WebP and upload, Workspace thumbnails, derivatives (`get_file.php`); since #1039 also the file screen's Rotate and "Convert to WebP" (`edit_file.php`, `write => false` for the conversion, error sentence per `reason`).
- Before #1039 `edit_file.php` called GD itself. `imagewebp()` on a palette image (every GIF, an 8-bit PNG) writes nothing and still returns true, so the conversion left an empty `.webp` and in replace mode deleted the original. A BMP turned without Imagick became JPEG bytes under its `.bmp` name; an unreadable file got the "rotated" notice. Rule: palette to truecolor before `imagewebp()`/`imageavif()`, and judge the bytes, not the return value.

Related: docs/memory/platform/image-settings-and-product-upload.md, docs/memory/platform/image-derivatives.md
