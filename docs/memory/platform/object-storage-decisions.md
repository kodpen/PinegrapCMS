---
name: object-storage-decisions
description: What binds the S3-compatible object storage for backups and uploaded files: legacy sites unchanged, same addresses, access checks in get_file.php, redirect only, the storage columns (#5037)
---

## What was built? (owner plan 2026-11-02, steps OS1-OS7)
- One client without a library (`object-storage-client.md`, `aws-sigv4-signing.md`), the backup type `s3` on it (`backup-object-storage.md`), uploaded files moved to the store by the general job (`file-object-storage.md`, `file-object-storage-readers.md`), the Storage card's move and bring-back tool (`object-storage-migration.md`), health and alerts (`object-storage-health.md`), Workspace and ERP files on the same rule (`workspace/workspace-files-object-storage.md`). Changelog: `[NEW] Added S3-compatible object storage for backups and uploaded files`.

## Which rules bind it?
- A site that configures nothing keeps every file local; the `s3` backup type keeps its settings and the three cloud accounts stay.
- A file never changes its address (`image-same-address-why.md`): links stay `get_file.php` names.
- The access checks stay in `get_file.php`: a presigned address is minted only after them, lives minutes, and is never written into a page, a mail or a feed (those keep the site address).
- An upload is always written locally first; only the general job talks to the store, so an upload never waits on the network.
- Any S3-compatible service is named by its endpoint; nothing in code names a vendor.

## Which questions were decided? (#5037)
- `files.storage` ENUM('local','s3') and `files.storage_key` (11.0.152); Workspace channel files are `files` rows and follow the same rule.
- Files are served by redirect only. A public bucket or a CDN host in front of the store is a later question for the architect, not part of this feature.
