---
name: backup-object-storage
description: The backup destination "S3-compatible storage" (remote type s3): its own card fields mapped onto the shared object storage client, multipart upload, the bucket's retention prune
module: backup
---

## How does a backup reach an S3-compatible bucket?
- Remote type `s3` keeps its own fields on the Backups card (`config.backup_remote_settings`, sealed with the other destinations). `pg_backup_s3_client_config()` maps them onto the shared client (`object-storage-client.md`): `access_key` to `key_id`, `secret_key` to `secret`, endpoint, region, bucket, folder (prefix), path style.
- An empty endpoint has always meant Amazon S3 in the given region; the mapping keeps that (`https://s3.<region>.amazonaws.com`), so sites set up that way send where they did. An empty region is `us-east-1`.
- `pg_backup_s3_put()` sends the archive under `<folder>/<archive name>` (the same key as before) with `pg_os_put()`: above 64 MB in 16 MB parts, aborted on failure. Before #5349's client, one signed PUT carried the whole archive, which a service's single-request limit (or a slow link inside the timeout) could refuse.
- The module's own SigV4 copy (`pg_backup_s3_signing_key()`, `..._canonical_request()`, `..._target()`, `..._error_message()` and the rest) is gone; errors are the client's messages.

## What does the bucket keep?
- After a successful copy, `pg_backup_s3_prune()` lists keys starting with `auto_backup_` under the folder, keeps only those directly in it (`pg_backup_s3_names()`: no deeper folder), and deletes what `pg_backup_prune_list()` chooses for the retention, asking `pg_backup_is_auto_name()` again before each delete. Deleted names and a failed prune go to the Site Log, as for the cloud accounts.
- A failed copy still alerts through `pg_backup_remote_record()` (`backup_remote_failed`).

## What is left?
- Listing the bucket's archives in the Backup Manager and fetching one back into `data/backups/` (OS2b in `plans/object-storage-steps.md`).
