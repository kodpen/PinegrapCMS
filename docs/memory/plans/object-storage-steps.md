---
name: object-storage-steps
description: Steps OS1-OS7 of plans/object-storage.md: SigV4 client, backup target, file rows and move job, get_file.php redirect, Workspace files, health, close-out; claim as [plan:object-storage] OS<n>
---

## Which run side by side?
- Wave 1: OS1 alone. Wave 2: OS2 and OS3 at once (different files). Wave 3: OS4, OS5, OS6 at once after OS3 (3a, 3b, 3c). Wave 4: OS7. Question 1 before OS3, question 2 before OS2 is reviewed.
- S under 400 product lines, M about 1,000. Tests in `tests/object_storage_<topic>_test.php`, memory under `platform/`, texts through `tr_add.php`.

## Which steps are there?
- OS1 (M) Client and settings card. Done (#5349).
  - `includes/fn/aws_sign.php` (#5348, `platform/aws-sigv4-signing.md`) with a `payload_hash` option (#5349). `includes/fn/object_storage.php`: the five calls plus multipart (16 MB parts, abort on failure) over `pg_curl_run()` with `sink` for downloads; errors as `['ok' => false, 'code', 'message']`, never thrown. Settings › Storage card (`includes/settings/`): endpoint, region, bucket, key id, secret sealed, path-style, prefix, Test. Memory: `platform/object-storage-client.md`.

- OS2 (M, two PRs) Backup target. Done (#5604, #5641).
  - 2a (done, #5604): the backup type `s3` sends through `pg_os_put()` with its own card fields; its SigV4 copy goes; remote prune by `pg_backup_prune_list()` over `pg_os_list()` (`platform/backup-object-storage.md`). 2b (done, #5641): the Backup Manager (`backups.php`) lists the bucket's archives and fetches one through `data/temp` into `data/backups/` with the checksum and size caps of `platform/backup-upload-extraction.md`; a failed copy keeps alerting `backup_remote_failed`. Tests: the key names and prune choice from fixed lists. Memory: `platform/backup-object-storage.md`.

- OS3 (L, three PRs) File rows, the move job, readers. After OS1 and question 1.
  - 3a: done (#5623). Schema 11.0.152, `pg_file_storage()`, the move job in `job.php`, `pg_file_local_copy()` and the cache sweep, behind the switch config_text `object_storage_files` that no screen sets yet (`platform/file-object-storage.md`).
  - 3b: done (#5803). `get_file.php` redirects to `pg_os_presign()` after the access checks, a local file or cached copy is sent as before, settings that cannot sign answer 503; replaced files go back to local (`pg_file_storage_reconcile_run()`), deleted files' objects go in a daily orphan sweep instead of one change per delete door; `.css`/`.js` never move.
  - 3c: done (#5904). Readers through `pg_file_local_copy()` / `pg_file_reader_path()`, in-place doors through `pg_file_make_local()`, passes through `pg_file_present_path()` (`platform/file-object-storage.md`). The switch may now be offered (OS4).

- OS4 (S) Migration tool and Disk Usage. Done (#5993, `platform/object-storage-migration.md`).
  - Settings › Storage: "Move existing files" starts the job over all local rows with progress (count, bytes, failures), pause and resume; "Bring back" moves them home; Disk Usage and System Status count local and stored bytes and name the store. Memory: `platform/file-object-storage.md` grows.

- OS5 (M) Workspace and ERP attachments. Done (#PR, `workspace/workspace-files-object-storage.md`).
  - Workspace channel files and note canvases (`workspace_file_save.php`, `ws_file_preview()`) and ERP attachments follow the same row rule and copy function; previews stay in the derivative cache (`api/api-file-endpoints.md`). Memory: `workspace/workspace-files-object-storage.md`.

- OS6 (S) Health. Done (#5933).
  - Ops alert `storage_unreachable` (once a day, after N failed calls in an hour), the Test button's last result on the card, the job's failures on the System Status screen; erasure and export unchanged (bytes are deleted where they are). Memory: `platform/object-storage-health.md`.

- OS7 (S) Close-out. After OS1-OS6.
  - PR body names `[NEW] Added S3-compatible object storage for backups and uploaded files`; decisions to `platform/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
