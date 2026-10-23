---
name: image-color-profile
description: ICC colour profiles in the image engine (PR #1267): Imagick keeps a non-sRGB profile, GD refuses such a file (color_profile); how a profile is read, what each path does
---

## Why does the engine care about colour profiles?
- Browsers show pixels without a profile as sRGB. `stripImage()` dropped the ICC profile and GD never writes one, so a Display P3 (iPhone) or Adobe RGB photo looked right as the original and pale in its JPEG and WebP derivatives, and Optimize removed the profile for good (#755, fixed in PR #1267).

## What does the Imagick half do?
- `pg_process_image_with_imagick()` reads the profile (`getImageProfiles('icc', true)`) before `stripImage()` and puts it back with `setImageProfile()` only when it is not sRGB (`pg_image_icc_is_srgb()`). An sRGB profile is dropped as before: untagged pixels are shown as sRGB anyway, and it would add about 3 KB to every result.
- `setImageProfile()`, not `profileImage()`: on an ImageMagick built without lcms, ProfileImage() only warns and attaches nothing.
- CMYK is excluded: it is converted to sRGB, and its profile would no longer describe the pixels.

## What does the GD half do?
- When GD would write the file and `pg_image_has_foreign_color_profile()` is true, `pg_process_image_file()` stops with reason `color_profile` before any decode. For a conversion the writer is chosen per format (`pg_image_format_writer()`): with Imagick loaded, GD may still be the one writing AVIF or WebP.

## How is a profile found?
- `pg_image_icc_profile()` reads the file's bytes: the profile, '' for none, false for unreadable (counts as foreign).
- JPEG: APP2 `ICC_PROFILE` chunks joined by sequence number (`pg_image_jpeg_app_segments()`, shared with the Exif reader).
- PNG: `iCCP` is inflated with a 4 MB output cap; a larger stream is unreadable. Without the cap a 200 KB file carrying 200 MB of zeros was a memory fatal (`@` does not stop it) on every page render on a GD-only server. An `sRGB` chunk means sRGB. A chunk length near 2 GB ends the walk instead of overflowing `fseek()` on 32-bit PHP.
- WebP: `ICCP` after a `VP8X` header with flag 0x20.
- sRGB = an RGB profile whose `desc` (v2 text or v4 `mluc`) names sRGB. Anything else is foreign: refused, never shown wrong.

## What does each path do with a foreign profile under GD?
- Derivatives (`get_file.php ?w=&f=`): the original is sent, and no `.skip` is written: the refusal depends on the library, which can change while the file does not.
- Optimize, rotate, WebP conversion (`optimize_image.php`, file manager, `edit_file.php`): one sentence, file untouched. The product-upload resize leaves it at full size.
- Markup: without Imagick `pg_img_responsive()` writes no `srcset` or `<source>`; with Imagick a format only GD writes is left out (`pg_img_profile_formats()`), since all its candidates would be the full-size original.
- Workspace channel preview (`ws_file_preview()`) falls back to the original.
