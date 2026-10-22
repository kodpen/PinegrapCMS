---
name: image-files-left-alone
description: Images the engine refuses to process and why: animated GIF/APNG/WebP (reason animated, PR #1234); how each is told from the file's bytes, and what the screens, derivatives and markup do with them
---

## Which images does pg_process_image_file() leave alone?
- Animated images, reason `animated` (`pg_image_is_animated()` in `includes/image_engine.php`). GD and Imagick read one frame and write a still back, so a derivative, an Optimize or a product upload would end the animation for good.
- Other refusals (too large, missing, no library) are in docs/memory/platform/image-processing-engine.md.

## How is an animated file told apart?
- By its bytes, never its name or extension (an APNG named `.jpg` still counts).
- GIF: two or more image descriptors (`pg_image_is_animated_gif()`).
- PNG: an `acTL` chunk before the first `IDAT` (`pg_image_is_animated_png()`). Only the 8-byte chunk headers are read; each chunk's data is skipped with `fseek()`, so a large text chunk costs nothing. An `acTL` after `IDAT` is a still PNG to every reader.
- WebP: the animation bit (0x02) of the `VP8X` flags byte (`pg_image_is_animated_webp()`); a VP8X with only the alpha bit is a still.

## What happens to an animated file on each path?
- Derivatives (`get_file.php ?w=&f=`): the original is sent and an empty `.skip` remembers it (docs/memory/platform/image-derivatives.md).
- Optimize, the file manager's buttons, `edit_file.php`: "is an animated image ... left alone" sentence, file untouched.
- Product and API uploads: the file is stored byte for byte.
- Markup: `pg_img_responsive()` returns null, so the Image element and widget images get no `srcset` or `<source>` (a browser that reads WebP would pick a still frame).

## What went wrong before?
- Until PR #1234 only GIFs were checked (#753): an APNG's derivatives were one frame, the `<picture>` sent WebP readers to that still, and Optimize or a product upload wrote one frame over the file.
- Not covered: the image editor's Replace still checks only GIFs (`image_editor_save.php`).
