---
name: update-beta-builds
description: Beta builds of one version over the Kodpen API (PR #3755): data/update_build.json, when a same-version build is offered (#3204), the re-run after replace, backup/rollback and integrity ref
---

## Where is the running beta build recorded?
- `data/update_build.json`: `{version, build, git_ref}` of the verified manifest the last update installed (`includes/fn/update.php`). `pg_update_build_parse()` counts it only when `version === pg_code_version()` and `build` is a build id; otherwise build '' and no `git_ref`. A bad `git_ref` is dropped, the build kept. `pg_update_build_read()`, `pg_update_build_write()` (temp file + rename), `pg_update_build_delete()`. No schema: the id is a file.
- Why a file: a beta build carries its release's version number, so `config.version` cannot tell two builds apart.

## When is a same-version build offered? (Q1, #3204)
- Only on the beta channel and only when the file names a beta build of the running version: `pg_vendor_release_check()` and `pg_vendor_update_site()` pass `pg_update_build_read()['build']`. A beta site without the file (stable install, 2026.4.x updated) gets betas again from the next version. Repair installs the pointer's build, named on screen.

## What happens after replace?
- `git_ref` comes from the signed manifest: the download step stores `pg_vendor_update_pin_manifest()` as the session pin.
- The replace step reads the installed build before extraction, then `pg_vendor_update_record_build()`: for another build of the running version (`pg_vendor_update_rerun()`) `config.version` goes back to the previous `versions.php` entry (`pg_upgrade_previous_version()`), read back to check; a failure is an error naming Repair Software. Then the file is written for a beta pin with a build (`pg_update_build_for_pin()`), deleted otherwise. The upgrade screen then reruns the version's (idempotent) steps.

## Backup, rollback, integrity?
- Backup `manifest.json` carries `build` and `git_ref`; rollback restores the file or deletes it (`update-backup-rollback.md`).
- The integrity check tries the file's `git_ref` first on beta, then `v<version>`, `v<version>-beta`; cache keyed by ref (`core/file-integrity-github-reference.md`).

## Tests? What is unverified?
- `tests/update_build_test.php` (parse, write/read/delete, pin record, previous entry, Q1 matrix, tag order), `update_backup_test.php`, `vendor_update_steps_test.php`. The live path waits for a signed vendor beta release (P8).

Related: docs/memory/platform/vendor-api-update.md
