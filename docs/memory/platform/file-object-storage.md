---
name: file-object-storage
description: Uploaded files in the object store: files.storage/storage_key (11.0.152), the switch, the move job in job.php, pg_file_local_copy() and its cache sweep; what 3b and 3c still wire (PR OS3a)
module: file_storage
---

## Where does a file row say its bytes are?
- `files.storage` ENUM('local','s3') DEFAULT 'local' and `files.storage_key` VARCHAR(255) (11.0.152, `upgrade_2026_11_0_file_storage()`, decided in #5037). Every row is local after the upgrade.
- Read it with `pg_file_storage($row)` (`includes/fn/file_storage.php`): 's3' only with a key, anything else (a row read before the migration, an unknown value) is 'local'. `pg_file_storage_ready()` probes both columns.
- The key is `files/<id>/<name>` (`pg_file_storage_key()`), below the store's prefix, and is kept in the row: a later rename does not move the object.

## What turns it on?
- config_text `object_storage_files` = '1' plus a ready store (`pg_file_storage_on()`). Not in the connect_apps options: `pg_os_settings_save()` writes that list whole and the card's save would drop it.
- No screen sets the switch yet: until `get_file.php` serves a stored file (3b) and the readers by path are wired (3c), a moved file would be missing for them. OS4's Storage card button sets it.

## How does the move job work?
- `pg_file_move_run(20, 20)` in `job.php`, after the image derivative sweep: rows with storage 'local', newest id first, 20 files or 20 s a tick. State in config_text `object_storage_move` (JSON: `cursor`, `failures` id => count, `rested_at`); the cursor moves below every row read, moved or not, so a failing file never holds the walk; a finished pass rests 10 minutes.
- One file (`pg_file_move_one()`): `pg_os_put()` with the type's MIME, `pg_os_head()` must answer the file's size, the file's size and mtime must be unchanged, then `UPDATE … WHERE id AND name AND storage='local'` must touch one row; only then is the local file deleted. Any refusal deletes the object again and the file stays local.
- A failure is tried on the next passes; at 3 attempts (`pg_file_move_attempts()`) the job names the file once in the Site Log and leaves it. The failure list keeps its newest 500.
- Never moved (`pg_file_storage_movable()`): design and theme files (read and written by path by the designer, code editors and theme screens) and `dkim.key` (`pg_file_withheld()`).

## How does a reader get a path?
- `pg_file_local_copy($file_id)`: the local file, or for a stored file a copy in `data/cache/objects/<id>.<type>` (the type keeps the extension readers look at), fetched to a `.part` file and renamed into place, reused while its size matches the row, touched on each use.
- `pg_file_cache_sweep()` from `job.php`, once an hour (stamp `.swept`): copies unused for 7 days, `.part` files older than a day, then least recently used above 1 GB (`pg_file_cache_cap()`, choice in the pure `pg_file_cache_sweep_pick()`).
- No `GLOB_BRACE`: musl (Alpine images) has none.

## What is left for 3b and 3c?
- 3b: `get_file.php` after its access checks (302 to `pg_os_presign()`, streaming for ranges, a changed name or an unreachable store), and the delete, rename and replace doors updating the store.
- 3c: the readers by path call `pg_file_local_copy()`: image engine and derivatives (`includes/fn/image.php`), the image editor's open and save (`includes/fn/image_editor.php`, a save writes local and resets the row to 'local'), PDF and office code. About 285 `FILE_DIRECTORY_PATH` uses exist; design and theme files never move, which leaves most of them alone.

Tests: `tests/object_storage_files_test.php`.
