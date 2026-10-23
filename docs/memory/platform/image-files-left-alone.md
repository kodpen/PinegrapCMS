---
name: image-files-left-alone
description: Images the engine refuses to process: animated (PR #1234), unreadable Exif orientation (PR #1242), ICC profile GD cannot carry (PR #1267, #1345); how each is told and handled
---

## Which images does pg_process_image_file() leave alone?
- Animated images, reason `animated` (`pg_image_is_animated()` in `includes/image_engine.php`). GD and Imagick read one frame and write a still back, so a derivative, an Optimize or a product upload would end the animation for good.
- A JPEG with an Exif block whose orientation cannot be read, reason `orientation_unreadable`, GD half only (Imagick reads the tag itself). Which way up a browser shows it is not known, and the written copy has no tag.
- A colour profile GD cannot carry (unreadable, not RGB, or AVIF output), reason `color_profile`, GD half only; GD writes an RGB one back into JPEG, PNG and WebP: docs/memory/platform/image-color-profile.md.
- Other refusals (too large, missing, no library) are in docs/memory/platform/image-processing-engine.md.

## How is an animated file told apart?
- By its bytes, never its name or extension (an APNG named `.jpg` still counts).
- GIF: two or more Graphic Control Extension blocks (`00 21 F9 04`) (`pg_image_is_animated_gif()`).
- PNG: an `acTL` chunk before the first `IDAT` (`pg_image_is_animated_png()`). Only the 8-byte chunk headers are read; each chunk's data is skipped with `fseek()`, so a large text chunk costs nothing. An `acTL` after `IDAT` is a still PNG to every reader. A chunk length near 2 GB ends the search instead of overflowing the `fseek()` offset on 32-bit PHP (a `TypeError` there, a 500 on upload, Optimize, `?w=` and page render).
- WebP: the animation bit (0x02) of the `VP8X` flags byte (`pg_image_is_animated_webp()`); a VP8X with only the alpha bit is a still.

## How is a JPEG's orientation read?
- `pg_image_jpeg_orientation()`: 1–8, 1 = none, 0 = unreadable. The exif extension when it returns `Orientation` or read IFD0; else `pg_image_jpeg_exif_orientation()` walks the marker segments to the `Exif\0\0` APP1 and reads tag 0x0112 of IFD0 (`pg_image_exif_tiff_orientation()`: II/MM, SHORT or LONG); Imagick (ping) only when those bytes say 0 or exif is missing.
- Why: with exif and Imagick both loaded, a JPEG without Exif would otherwise cost an Imagick ping on every page render.
- 0 only for a broken block (unknown byte order, IFD0 or entries outside it, a TIFF header cut short, odd type). An empty Exif APP1 (`Exif\0\0` alone) and a value outside 1–8 count as 1, as browsers do.
- Without exif the GD half used to skip the turn: every phone photo's derivatives and Optimize came out on their side (#754, fixed in PR #1242; exif is off by default on Windows PHP and absent in the upstream php Docker images; Pinegrap's own docker/ image installs it).

## What happens to such a file on each path?
- Derivatives (`get_file.php ?w=&f=`): the original is sent. Animated: an empty `.skip` remembers it (docs/memory/platform/image-derivatives.md). Unreadable orientation: `pg_image_oriented_size()` gives 0×0, so no attempt is made.
- Optimize, the file manager's buttons, `edit_file.php`: one sentence per reason ("is an animated image ... left alone" and the orientation one), file untouched.
- Product and API uploads: the file is stored byte for byte.
- Image editor Replace (`image_editor_save.php`): refused with a JSON message for any animated file (`pg_image_is_animated()`, bytes not name); Save as new file still works. The footer hides Replace only for `.gif` names, so an APNG or animated WebP learns it on save (PR #1340).
- Markup: `pg_img_responsive()` returns null, so the Image element and widget images get no `srcset` or `<source>` (a browser that reads WebP would pick a still frame).

## What went wrong before?
- Until PR #1234 only GIFs were checked (#753): an APNG's derivatives were one frame, the `<picture>` sent WebP readers to that still, and Optimize or a product upload wrote one frame over the file.
- Until PR #1340 the image editor's Replace checked only `.gif` names and wrote one frame over an APNG or animated WebP.
