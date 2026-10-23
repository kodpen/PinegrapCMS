---
name: image-color-profile
description: ICC colour profiles in the image engine: Imagick and GD keep a non-sRGB RGB profile, GD writes it into JPEG/PNG/WebP itself and refuses (color_profile) only what it cannot carry; how a profile is read
---

## Why does the engine care about colour profiles?
- Browsers show pixels without a profile as sRGB. Dropping a Display P3 (iPhone) or Adobe RGB profile makes the derivatives look pale, and Optimize made it permanent (#755, PR #1267).

## What does the Imagick half do?
- `pg_process_image_with_imagick()` reads the profile before `stripImage()` and puts it back with `setImageProfile()` only when it is not sRGB (`pg_image_icc_is_srgb()`). An sRGB profile is dropped: untagged pixels are shown as sRGB anyway, and it would add about 3 KB.
- `setImageProfile()`, not `profileImage()`: without lcms ProfileImage() only warns and attaches nothing.
- CMYK is converted to sRGB, so its profile is not kept.

## What does the GD half do?
- GD decodes without converting colours, so the source's profile still describes the pixels it writes. `pg_process_image_file()` asks `pg_image_gd_icc_plan($icc, $output)`: `none` (no profile or sRGB), `embed`, or `refuse` (reason `color_profile`, before any decode).
- Refused: an unreadable profile, a non-RGB one (grey, CMYK: GD always writes RGB), AVIF/GIF/BMP output, PNG without zlib, a JPEG profile over 255 segments.
- `embed`: after GD encodes, `pg_image_embed_icc()` writes the profile into the bytes. JPEG: APP2 `ICC_PROFILE` segments after the APPn segments. PNG: `iCCP` right after IHDR; sRGB/gAMA/cHRM dropped. WebP: a simple VP8/VP8L file gets a VP8X header (canvas and alpha read from the bitstream), an extended one the ICC flag; ICCP after VP8X, RIFF size rewritten.
- If the bytes have a shape the writer does not know (another libgd build), the result is `color_profile`, not `failed`, so no permanent `.skip`.
- Embedding is PR #1345. Before it GD refused every non-sRGB file, so a phone photo on a GD-only server got no derivative, no srcset and no Optimize.

## How is a profile found?
- `pg_image_icc_profile()`: the profile, '' for none, false for unreadable.
- JPEG: APP2 `ICC_PROFILE` chunks joined by sequence number (`pg_image_jpeg_app_segments()`).
- PNG: `iCCP` inflated with a 4 MB cap (a zlib bomb was a memory fatal on every page render); `sRGB` means sRGB. A chunk length near 2 GB ends the walk (32-bit `fseek()`).
- WebP: `ICCP` after `VP8X` with flag 0x20.
- sRGB = an RGB profile whose `desc` (v2 text or v4 `mluc`) names sRGB.

## What does each path do with a refused profile?
- Derivatives (`get_file.php ?w=&f=`): the original is sent; no `.skip` (it depends on the library). An embedded profile adds 0.5-3 KB; a derivative that is not smaller is the original as usual.
- Optimize, rotate, WebP conversion: one sentence, file untouched. Product-upload resize leaves it full size.
- Markup: `pg_img_responsive()` reads the profile only when GD writes something and builds `$refuse` (original, webp, avif) from the plan; `pg_img_profile_formats()` returns null without Imagick when the original is refused, otherwise drops each format GD writes and refuses. A P3 photo on GD-only gets srcset and WebP, no AVIF.
- Workspace channel preview (`ws_file_preview()`) falls back to the original.
