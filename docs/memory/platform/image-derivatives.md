---
name: image-derivatives
description: Image derivatives (PR #589) made by get_file.php for ?w=&f=: fixed widths, WebP/AVIF tested once per server, data/cache/img name and signature, skip and lock files, files that get none
---

## How is a derivative asked for?
- On the file's own address: `/photo.jpg?w=960`, `?w=960&f=webp`, `?f=avif`. `pg_image_derivative_request()` reads only `w` (1–5 digits) and `f` (`webp`/`avif`); anything else serves the original as before. Made on the first such request, never in the upload path.
- Widths are fixed (480, 960, 1440, 1920). A requested width rounds UP to the next step, never above the source width, so any number lands on an existing file and a crawler cannot fill the disk.
- Width counts the displayed picture: EXIF orientation 5–8 swaps the edges (`pg_image_oriented_size()`); the engine's longest-edge ceiling comes from the width (`pg_image_derivative_long_edge()`).

## Which formats does a server make?
- `pg_image_derivative_formats()`: each format a library claims (`pg_image_format_writer()`: Imagick `queryFormats()`, else GD) is test-encoded once on a 16 px PNG and kept in `data/cache/img/formats.json` (key: PHP/GD/ImageMagick versions + claims; re-tested after a week). Why: Ubuntu's ImageMagick lists AVIF but cannot write it without `libheif-plugin-aomenc`; trusted, every `?f=avif` sent the full-size original.
- AVIF falls back to WebP, WebP to the source format. A format the operator switched off counts as not written: docs/memory/platform/image-delivery-settings.md.

## Where is it kept and when is it replaced?
- `dirname(FILE_DIRECTORY_PATH)/cache/img/<file id>/<width>-<signature>.<ext>`: beside the file directory, so two sites sharing one software directory never read each other's file id. data/ is refused over HTTP.
- Signature = md5 of recipe `1`, source mtime, size and quality. Optimize, rotate, replace or a new quality give a new name; the next derivative of that width and format prunes the old one.

## What does get_file.php send when there is no derivative?
- `original` (normal caching): not a raster type, already small enough, as heavy as the original or heavier (palette PNG), the engine refused (animated, too large, unreadable), or an earlier attempt died part way (`<name>.try` left: one try, else every request would 500). The last three leave an empty `<name>.skip`. A missing library is not remembered.
- `busy` (`Cache-Control: no-store`): another request holds `<name>.lock` (non-blocking flock) or the folder cannot be written (checked before decoding).
- Made after the session and DB connection are closed, with `ignore_user_abort` and a 120 s limit; sent with `readfile()`, never X-Sendfile.

## Which files get none?
- ERP documents, workspace files and form/comment attachments (personal data). Access checks for the original hold for its derivatives.

## Why is the engine outside functions.php?
- `includes/image_engine.php` holds `pg_process_image_file()`, its GD/Imagick halves, `pg_ini_bytes()` and the derivative functions; functions.php requires it, get_file.php only for `?w=`/`?f=` (defines `PG_GET_FILE`). Its functions call only each other and PHP; settings stay in `includes/fn/image.php`.

## How is the cache kept clean?
- General job, once a day, 10 s a tick until through (`pg_image_derivative_sweep_run()`, cursor in `sweep.json`): a folder whose file id is no longer in `files` goes whole, a derivative or `.skip` of an old signature goes, a `.lock`/`.tmp*` older than an hour goes. No delete hooks: reading `files` covers every door that deletes a file. Nothing goes for being old: a current derivative is the cache working (PR #602).
- Clear caches (`pg_purge_caches()`) renames the folder to `img.old-<time>-<pid>` (instant, `formats.json` included, so formats are tested again), deletes what it can in 2 s; the job deletes the rest.
- System Status › Storage: `images` in `pg_storage_usage()` (6 h cache), labelled with the formats this server writes.

