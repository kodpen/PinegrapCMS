---
name: object-storage-steps
description: Steps OS1-OS7 of plans/object-storage.md: SigV4 client, backup target, file rows and move job, get_file.php redirect, Workspace files, health, close-out; claim as [plan:object-storage] OS<n>
---

## Which run side by side?
- Wave 1: OS1 alone. Wave 2: OS2 and OS3 at once (different files). Wave 3: OS4, OS5, OS6 at once after OS3. Wave 4: OS7. Question 1 before OS3, question 2 before OS2 is reviewed.
- S under 400 product lines, M about 1,000. Tests in `tests/object_storage_<topic>_test.php`, memory under `platform/`, texts through `tr_add.php`.

## Which steps are there?
- OS1 (M) Client and settings card. `free`.
  - `includes/fn/aws_sign.php`: `pg_aws_canonical_request()`, `pg_aws_string_to_sign()`, `pg_aws_signing_key()`, `pg_aws_authorization()`, `pg_aws_presign()`, tested against the AWS SigV4 test-suite vectors in `tests/fixtures/aws_sigv4/`. `includes/fn/object_storage.php`: the five calls plus multipart (16 MB parts, abort on failure) over `pg_curl_run()` with `sink` for downloads; errors as `['ok' => false, 'code', 'message']`, never thrown. Settings › Storage card (`includes/settings/`): endpoint, region, bucket, key id, secret sealed, path-style, prefix, Test. Memory: `platform/object-storage-client.md`.

- OS2 (M) Backup target. After OS1.
  - `pg_backup_cloud_types()` gains `s3`; the copy after a backup through multipart; remote prune by `pg_backup_prune_list()` over `pg_os_list()`; the restore screen (`backup_restore.php`) lists remote archives and downloads one to `data/temp` with the checksum and size caps of `platform/backup-upload-extraction.md`; ops alert `backup_copy_failed` reuses the existing failed-backup code. Tests: the key names and prune choice from fixed lists. Memory: `platform/backup-object-storage.md`.

- OS3 (L, two PRs) File rows, the move job, readers. After OS1 and question 1.
  - 3a: the schema sub-step (`install_add_column()` twice, rerun twice on a dev DB); `pg_file_storage($row)`; the move job in `job.php` (newest first, a cursor in config, N files a tick, verify by `HEAD` and size before the local byte is deleted, failures retried then named in the Site Log); `pg_file_local_copy($file_id)` fetches into `data/cache/objects/<id>` for readers that need a path (image engine, derivatives, PDF, office, editor save) with an LRU sweep in the general job. 3b: `get_file.php`: after the access checks, a stored file answers with a 302 to `pg_os_presign()` (5 minutes, `Content-Disposition` and `Content-Type` as query overrides) or streams through PHP for a range request, a changed download name or when the store is unreachable (falls back to the local copy). Delete, rename and replace doors update the store. Memory: `platform/file-object-storage.md`.

- OS4 (S) Migration tool and Disk Usage. After OS3.
  - Settings › Storage: "Move existing files" starts the job over all local rows with progress (count, bytes, failures), pause and resume; "Bring back" moves them home; Disk Usage and System Status count local and stored bytes and name the store. Memory: `platform/file-object-storage.md` grows.

- OS5 (M) Workspace and ERP attachments. After OS3 and question 1.
  - Workspace channel files and note canvases (`workspace_file_save.php`, `ws_file_preview()`) and ERP attachments follow the same row rule and copy function; previews stay in the derivative cache (`webp-same-address.md` W8). Memory: `workspace/workspace-files-object-storage.md`.

- OS6 (S) Health. After OS3.
  - Ops alert `storage_unreachable` (once a day, after N failed calls in an hour), the Test button's last result on the card, the job's failures on the System Status screen; erasure and export unchanged (bytes are deleted where they are). Memory: `platform/object-storage-health.md`.

- OS7 (S) Close-out. After OS1-OS6.
  - PR body names `[NEW] Added S3-compatible object storage for backups and uploaded files`; decisions to `platform/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
