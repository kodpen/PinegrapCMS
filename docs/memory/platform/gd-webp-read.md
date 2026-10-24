---
name: gd-webp-read
description: Reading WebP with GD: bundled GD before PHP 8.2 ends the request on an animated or cut-short WebP, imagecreatefromstring() reads WebP from 7.3; pg_image_webp_gd_readable(), pg_image_from_string()
---

## Why can a WebP end the request?
- PHP's bundled GD before 8.2 reports a WebP it cannot decode with `zend_error(E_ERROR)` (`ext/gd/libgd/gd_webp.c`: "gd-webp cannot get webp info", "gd-webp cannot allocate temporary buffer"). `@` does not stop it; on PHP 7 it even hides the message (exit 255, blank page). From 8.2 it is a warning and false. A build on the system libgd (`GD_BUNDLED` 0) warns on every version.
- GD cannot decode an animated WebP, one cut short (a copy or transfer that did not finish) or one with damaged image data.
- Encoding is the same there: `imagewebp()` on a palette image is a fatal error ("Paletter image not supported by webp"), hence the engine's palette-to-truecolor rule (image-processing-engine.md).

## What did it break, and what checks it now?
- Before #?, on PHP 7.3–8.1 (bundled GD): the Edit file screen of an animated or cut-short WebP and the order screens with such a contact picture were a blank page (`get_dominant_area_color()`), and so was the account menu's profile picture save when sent such a WebP (the browser normally sends a JPEG it drew). On 7.1–8.1: Optimize, an upload or a derivative of a cut-short WebP (`pg_image_gd_read()`), and every request for the panel icon when its file is such a WebP and the server has no Imagick (`manifest_icon.php`).
- `pg_image_webp_gd_readable($bytes)` (`includes/image_engine.php`) is asked first when `PHP_VERSION_ID < 80200`, in `pg_image_gd_read()`, `pg_image_from_string()` and `pg_app_icon_render()`: the RIFF size within the bytes, every chunk within the RIFF, a `VP8 ` key frame with its start code and first partition inside the chunk, or the `VP8L` signature; a VP8X with the animation flag, ANIM or ANMF is refused. Refused bytes give false: the grey fallback colour, the picture kept as it came, a `failed` engine report, the shipped icon.
- Not told: a whole file whose image data is damaged (a lossless stream, the first partition) still ends the request before 8.2; only decoding finds it. A derivative tries such a file once (`.try`, image-derivatives.md).

## Why pg_image_from_string()?
- `imagecreatefromstring()` reads WebP only from PHP 7.3. Before it a WebP gave the grey accent colour, and a WebP sent as the profile picture was kept as it came: not squared, not shrunk, its metadata kept.
- `pg_image_from_string($binary, $path = '')` (`includes/fn/image.php`): the check above, `imagecreatefromstring()`, and on 7.1–7.2 `imagecreatefromwebp()` on `$path` (the file the bytes came from) or a temporary copy that is deleted. Callers: `get_dominant_area_color()`, `pg_contact_photo_store()`. A new GD read of bytes that may be WebP uses it.

## How is it tested?
- `tests/image_webp_read_test.php`: the check (lossy, alpha, a lossless sample, cut short, animated, frame tag), `pg_image_from_string()` with and without a path, the accent colour, `pg_image_gd_read()` and the engine report, the doors. Before the change the run ends with the fatal error on PHP 7.1–8.1, and on 7.1 a still WebP's colour came out grey; with it, green on 7.1–8.2.

Related: docs/memory/platform/image-files-left-alone.md, docs/memory/platform/image-processing-engine.md
