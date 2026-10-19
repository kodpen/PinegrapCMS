---
name: image-derivatives
description: Responsive image derivatives (PR #589) made by get_file.php on request (?w=&f=): fixed widths, WebP/AVIF, data/cache/img naming and signature, skip markers, lock, which files get none
---

## How is a derivative asked for?
- On the file's own address: `/photo.jpg?w=960`, `?w=960&f=webp`, `?f=avif`. `pg_image_derivative_request()` reads only `w` (1–5 digits) and `f` (`webp`/`avif`); anything else serves the original as before.
- Widths are fixed (`pg_image_derivative_widths()`: 480, 960, 1440, 1920). A requested width rounds UP to the next step, never above the source width. Any number lands on an existing file, so a crawler cannot fill the disk with one copy per pixel.
- AVIF falls back to WebP, WebP to the source format, when nothing on the server can write it (`pg_image_format_writer()`: Imagick `queryFormats()`, else GD `imagewebp`/`imageavif`).
- Width counts the displayed picture: a JPEG with EXIF orientation 5–8 has its edges swapped (`pg_image_oriented_size()`), and the engine's longest-edge ceiling is worked out from the width (`pg_image_derivative_long_edge()`).

## Where is it kept and when is it replaced?
- `dirname(FILE_DIRECTORY_PATH)/cache/img/<file id>/<width>-<signature>.<ext>` (data/cache/img by default). Beside the file directory, not the software directory: two sites sharing one software directory must not read each other's file id. data/ is refused over HTTP.
- Signature = md5 of recipe `1`, source mtime, size and quality. Optimize, rotate, replace give the file a new mtime, so the old derivative stops matching; the next one made for that width and format prunes the old one (`pg_image_derivative_prune()`).
- Quality is fixed for now (`pg_image_derivative_quality()`: AVIF 50, WebP 80, source format 82); it is part of the signature, so a later setting replaces derivatives on its own.

## What does get_file.php send when there is no derivative?
- `original` (normal caching): not a raster type, already small enough, the derivative would be as heavy or heavier (palette PNG), the engine refused (animated GIF, too large, unreadable), or an earlier attempt died part way (`<name>.try` left behind: one try per picture, else every request would 500). The last three are remembered with an empty `<name>.skip` so the bytes are not decoded again. A missing library is not remembered (server state, can change).
- `busy` (`Cache-Control: no-store`): another request holds `<name>.lock` (flock, non-blocking) or the folder cannot be written. Checked before decoding, so an unwritable folder costs no decode per request.
- Generation runs after the session and DB connection are closed, with `ignore_user_abort` and a 120 s time limit. Derivatives are sent with `readfile()`, never X-Sendfile (XSendFilePath may not cover data/cache).

## Which files get none?
- ERP documents, workspace files and form/comment attachments: personal data, no copies in the cache. Access checks run before for the original and hold for derivatives.

## Why is the engine outside functions.php?
- `includes/image_engine.php` holds `pg_process_image_file()`, its GD/Imagick halves, `pg_ini_bytes()` and the derivative functions. functions.php requires it at the top; get_file.php requires it only for `?w=`/`?f=`, defining `PG_GET_FILE`. Contract: those functions call only each other and PHP (no db, no lang); settings stay in `includes/fn/image.php`.
- `get_file.php` also serves `.avif` uploads as `image/avif` now (they downloaded before).

## What is still open?
- `pg_img_tag()`, srcset/sizes, lazy loading, settings, purge, retention sweep, deleting a file's folder on delete: `docs/memory/plans/image-delivery-pipeline.md`.
