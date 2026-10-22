---
name: update-package-extraction
description: How update packages must be downloaded and extracted (pg_extract_archive, pg_looks_like_zip) so a partial install is never reported as success.
---

## Why did updates silently leave missing files?
- `extractTo()` returns a bool and the old code never checked it. Extraction stops at the first entry it cannot write (locked file, permission, full disk, `open_basedir`), everything after is never created, yet the screen said "update complete". A half-finished install then needs manual repair.

## What does `pg_extract_archive()` (`includes/fn/update.php`) prove, in order?
1. Opens with `ZipArchive::CHECKCONS`: a corrupt/partial package is rejected before touching a single file, never half-applied.
2. The `extractTo()` return value is checked.
3. EVERY entry inside the archive is looked up on disk. This step catches the real partial extraction; (2) can return `true` on some platforms even when entries were skipped.

## What download-side silent failures were closed?
- HTTP status was never checked (a 403/404 body was written as a `.zip`); a short transfer did not return `false` so it counted as success; the `fopen(...,'x')` return and the `fwrite()` byte count were ignored.

## What must a package download check?
- Before processing a downloaded package verify HTTP 200, the expected byte count and the `PK` signature (`pg_looks_like_zip()`). Always extract via `pg_extract_archive()`.
- `download_assistant.php` must run standalone, so it carries its own copy of the same logic.

Related: docs/memory/platform/update-channel-stable-beta.md
