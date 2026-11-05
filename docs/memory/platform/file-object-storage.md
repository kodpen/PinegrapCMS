---
name: file-object-storage
description: Uploaded files in the object store: storage columns (11.0.152), the switch, the move job, get_file.php redirect, replace and delete handling, how readers and in-place doors get a path
module: file_storage
---

## Where does a row say its bytes are?
- `files.storage` ENUM('local','s3') and `files.storage_key` (11.0.152, #5037). `pg_file_storage($row)` reads 's3' only with a key. Key `files/<id>/<name>` (`pg_file_storage_key()`), kept in the row: a rename changes only the row (every rename door skips a missing local file).
- Switch: config_text `object_storage_files` ('1' move, 'back' bring home, `paused`/`back_paused`) plus a ready store; not in the connect_apps options, which `pg_os_settings_save()` writes whole. Set by the Storage card's buttons (`object-storage-migration.md`).

## How does the move job work?
- `pg_file_move_run(20, 20)` in `job.php`, direction 'to': local rows, newest id first, state in config_text `object_storage_move` (cursor, failures, rested_at); the cursor passes every row read; a finished pass rests 10 minutes.
- `pg_file_move_one()`: put, HEAD must give the size, size and mtime unchanged, then `UPDATE … storage, storage_key, size … WHERE id AND name AND storage='local'` must touch one row; only then the local file goes. A refusal deletes the object. 3 failed passes: named once in the Site Log, left local.
- Never moved (`pg_file_storage_movable()`): design and theme files, `.css`/`.js` (get_file.php rewrites `{path}` in them) and `dkim.key`.

## How does get_file.php answer a stored file?
- Its row query also reads type, size, storage, storage_key; before the upgrade that query fails and the older one runs (`upgrade-bridge-rule.md`). The missing-file 404 is skipped for a stored row.
- After every access check, before the connection closes (`includes/file_store_serve.php`, `pg_file_serve_plan()`): a local file under the name wins (a replace door wrote it); else the cached copy (`data/cache/objects/<id>.<type>`) is sent as a local file is (ranges, derivatives, no X-Sendfile); else a 302 to `pg_os_presign()` (300 s, `response-content-type` from get_file's MIME list, `response-content-disposition` inline or attachment with `filename*`), `Cache-Control: private, max-age=240`; settings that cannot sign answer 503.
- A range request follows the redirect: the store answers ranges itself. A derivative (`?w=`, `?f=`, WebP negotiation) of a stored file is made and found only while its cached copy is there (get_file.php cannot fetch: `pg_curl_run()` needs functions.php); without one the original is redirected.
- The settings are read with get_file's own query and unsealed with `decode_ssl_keys()`, moved to `includes/authentication.php` for that; signing uses `aws_sign.php` and the pure address builders of `object_storage.php`. No functions.php.

## What happens on replace and delete?
- Replace: `pg_file_storage_reconcile_run(500)` walks stored rows (cursor config_text `object_storage_reconcile`); one with a local file again goes back to 'local', its copy is dropped, the move job sends it again.
- Delete: no door was touched. `pg_file_storage_orphan_sweep()` once a day (config_text `object_storage_orphan_sweep`) lists a window of 5000 keys of `files/`, starting after the key in config_text `object_storage_orphan_sweep_after` (`pg_file_storage_sweep_cursor()`: past the window, on the last handled orphan when over 200, cleared at the end; before #PRNUM the first 5000 keys every day, so later orphans stayed), and deletes up to 200 objects whose key no row holds and that are over a day old (`pg_file_storage_orphans()`); rows that cannot be read (`pg_file_storage_referenced_keys()` null, an empty list not confirmed by a count) delete nothing. Runs while a store is set up, switch or not.

## How does a reader get a path?
- See `file-object-storage-readers.md`: `pg_file_local_copy()`, `pg_file_reader_path()`, `pg_file_make_local()`, `pg_file_present_path()`, the copy's last use.

Tests: `tests/object_storage_files_test.php`, `tests/object_storage_serve_test.php`.
