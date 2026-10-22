---
name: image-files-left-alone
description: Images the engine leaves alone: animated (PR #1234), unreadable Exif orientation (PR #1242), non-sRGB ICC profile under GD (PR #1267); how each is told, what each path does
---

## Which images does pg_process_image_file() leave alone?
- Animated images, reason `animated` (`pg_image_is_animated()` in `includes/image_engine.php`). GD and Imagick read one frame and write a still back, so a derivative, an Optimize or a product upload would end the animation for good.
- A JPEG with an Exif block whose orientation cannot be read, reason `orientation_unreadable`, GD half only (Imagick reads the tag itself). Which way up a browser shows it is not known, and the written copy has no tag.
- A colour profile other than sRGB, reason `color_profile`, GD half only (`pg_image_has_foreign_color_profile()`): GD writes no profile, and pixels without theirs are shown as sRGB (a Display P3 or Adobe RGB photo turns pale). Imagick keeps the ICC profile through `stripImage()` (taken before, put back with `profileImage()`), except for CMYK, which is converted to sRGB.
- Other refusals (too large, missing, no library) are in docs/memory/platform/image-processing-engine.md.

## How is an animated file told apart?
- By its bytes, never its name or extension (an APNG named `.jpg` still counts).
- GIF: two or more image descriptors (`pg_image_is_animated_gif()`).
- PNG: an `acTL` chunk before the first `IDAT` (`pg_image_is_animated_png()`). Only chunk headers are read; data is skipped with `fseek()`. An `acTL` after `IDAT` is a still PNG to every reader.
- WebP: the animation bit (0x02) of the `VP8X` flags byte (`pg_image_is_animated_webp()`).

## How is a JPEG's orientation read?
- `pg_image_jpeg_orientation()`: 1–8, 1 = none, 0 = unreadable. The exif extension when it returns `Orientation` or read IFD0; else Imagick (ping); else `pg_image_jpeg_exif_orientation()` walks the marker segments to the `Exif\0\0` APP1 and reads tag 0x0112 of IFD0 (`pg_image_exif_tiff_orientation()`: II/MM, SHORT or LONG).
- 0 only for a broken block (unknown byte order, IFD0 or entries outside it, cut short, odd type). A value outside 1–8 counts as 1, as browsers do.
- Without exif the GD half used to skip the turn: every phone photo's derivatives and Optimize came out on their side (#754; exif is off by default on Windows PHP and absent in the official Docker image).

## How is a colour profile found?
- `pg_image_icc_profile()`: bytes, '' for none, false for unreadable (counts as foreign). JPEG APP2 `ICC_PROFILE` chunks joined by sequence number (`pg_image_jpeg_app_segments()`, shared with the Exif reader); PNG `iCCP` (zlib; an `sRGB` chunk means sRGB); WebP `ICCP` after `VP8X` (flag 0x20).
- sRGB = RGB profile whose `desc` (v2 text or v4 `mluc`) names sRGB (`pg_image_icc_is_srgb()`). Anything else is foreign: refused, never wrong.

## What happens to such a file on each path?
- Derivatives (`get_file.php ?w=&f=`): the original is sent. Animated: an empty `.skip` remembers it. Unreadable orientation: `pg_image_oriented_size()` gives 0×0, no attempt. Foreign profile: `.skip`, as animated.
- Optimize, rotate, WebP conversion (`optimize_image.php`, file manager, `edit_file.php`): one sentence per reason, file untouched.
- Product and API uploads: the file is stored byte for byte.
- Markup: `pg_img_responsive()` returns null: no `srcset` or `<source>` (foreign profile: only without Imagick).

## What went wrong before?
- Until PR #1234 only GIFs were checked (#753): an APNG's derivatives were one frame, the `<picture>` sent WebP readers to that still, and Optimize or a product upload wrote one frame over the file.
- Until PR #1267 derivatives and Optimize dropped every ICC profile (#755): an iPhone P3 photo's JPEG and WebP derivatives were pale beside the original.
- Not covered: the image editor's Replace still checks only GIFs (`image_editor_save.php`).
