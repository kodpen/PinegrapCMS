---
name: image-derivative-upkeep
description: Upkeep of the image derivative cache: the daily sweep and its cursor, the purge that sets the folder aside, the storage line, and the warm-up pass that makes own-address WebP copies ahead of visitors
module: image
---

## How is the cache kept clean?
- General job, once a day, 10 s a tick until through (`pg_image_derivative_sweep_run()`, cursor in `sweep.json`): a folder whose file id is no longer in `files` goes whole, a derivative or `.skip` of an old signature goes, a `.lock`/`.tmp*` older than an hour goes. No delete hooks: reading `files` covers every door that deletes a file. Nothing goes for being old: a current derivative is the cache working (PR #602).
- Clear caches (`pg_purge_caches()`) renames the folder to `img.old-<time>-<pid>` (instant, `formats.json` included, so formats are tested again), deletes what it can in 2 s; the job deletes the rest.
- System Status › Storage: `images` in `pg_storage_usage()` (6 h cache), labelled with the formats this server writes.

## What does the warm-up make, and when?
- Warm-up (general job, after the sweep, 10 s a tick, `pg_image_warmup_run()`): while bits 1 and 4 of `config.image_derivative_formats` are on (WebP at the own address) and the server writes WebP, the WebP copy at the own width of each JPEG/PNG row (no attachment, `erp-`, `ws-` or workspace folder), largest first, through `pg_image_derivative()`. Cursor (size, id) in `warmup.json`; at least one picture a tick; all slots taken stops the tick without moving the cursor; a finished pass starts again after a day. Tests: `tests/image_encode_warmup_test.php`.
