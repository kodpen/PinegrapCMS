---
name: object-storage-migration
description: The Storage card's Uploaded files tool: move existing files, pause, resume, bring back; the switch's modes; Disk Usage and System Info counting local and stored bytes
module: file_storage
---

## What does the Storage card offer for files?
- An "Uploaded files" block, only with the storage columns and a saved, switched-on store: files and bytes on the disk and in the store (`pg_file_storage_usage()`, one GROUP BY on `files.storage`), what the job is doing, the failures of the current direction (being retried / left after three attempts), and the buttons of `pg_file_storage_actions()`.
- Buttons post `object_storage_files_action` with the card; `maintenance.save.php` acts after the settings are saved and only while the store is ready, else it says what is missing.

## What values does the switch take?
- config_text `object_storage_files`, read by `pg_file_storage_mode()`: '1' move to the store (the value OS3 used), 'paused', 'back' bring home, 'back_paused'; anything else off. `pg_file_storage_mode_after()` gives the value after a button; pause/resume keep the direction and do nothing when they do not apply.
- `pg_file_storage_running($direction)`: mode, columns and `pg_os_ready()`. `pg_file_storage_on()` is `running('to')`; while bringing back, new uploads stay local.

## What do Move and Bring back reset?
- Each starts a fresh walk at once: its direction's state (`object_storage_move` or `object_storage_back`, `pg_file_move_state_key()`) is written empty, so the cursor, the 10-minute rest and the failures go and files given up earlier are tried again. Pause and resume keep the state.

## How does a file come back?
- `pg_file_move_run()` walks stored rows newest first with `pg_file_back_one()`: fetch to `<name>.<pid>.part` in the file directory, rename, then `UPDATE … storage='local', storage_key='', size` where still 's3'; the cached copy goes. A local file already under the name is kept as the bytes.
- The object is not deleted there: the daily orphan sweep takes it (unreferenced, over a day old), so a presigned address given a moment ago still answers. The sweep needs the store still set up.
- 3 failed passes: named once in the Site Log, the row stays stored. Design, theme, `.css`/`.js` never moved, so the back walk has no movable check.

## What counts as disk usage?
- `pg_files_disk_usage()` (Explorer footer, System Info) counts only local rows plus file versions. System Info adds "Object Storage (Files): {bytes} in {store}" when any file is stored; the System Status tile lists both places (`object-storage-health.md`).

Tests: `tests/object_storage_migration_test.php`.
