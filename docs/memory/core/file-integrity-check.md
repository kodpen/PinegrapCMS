---
name: file-integrity-check
description: The hourly file-integrity check: which reference counts (dev machine's own or the GitHub tag), scope and ignored names, line-ending-proof digests, state file, what cleanup keeps, result codes.
---

## Which reference does the check use?
- `check_directory_file_integrity()` (`includes/fn/system_status.php`) compares, at most once an hour, the files directly in the software directory and under `pg_integrity_scope_directories()` (only `includes`, in scope since 2026.4.4) with a reference.
- On the development machine: `data/temp/hash_reference.json`, written by `_software_create_hash.php` (dev machine only, not in the repository, never shipped). Its `_generated` stamp (host, version, time) names this host and this version, so here it is the truth: SHA-256, first byte for byte, then with CRLF read as LF. GitHub is not asked.
- Everywhere else: the release tag's tree on GitHub (`file-integrity-github-reference.md`). Sites on 2026.4.4 and earlier still use the kodpen.com copy described there.
- The dev reference is built from the working tree (leftovers, unpushed work), so it is never uploaded anywhere; a release needs no generated reference.

## What is in scope?
- Decided in one place: `pg_integrity_scope_directories()`, `pg_integrity_ignored_files()` (`error_log`, `.htaccess`, `.user.ini`, `.DS_Store`, `Thumbs.db`) and `pg_integrity_in_scope()`. The generator reads the same functions; never write the list a second time. `data/` and `install/` are out on purpose (`file-placement-rules.md`).
- What counts as a file in a reference is decided by SHAPE (`pg_integrity_reference_files()`: a value of 64 hex digits), never by a name list: old copies carried bookkeeping keys (`last_local_check`) that a name list missed and reported as missing files. A reference with no file entries after filtering is corrupt.

## Why are the digests line-ending independent?
- `pg_integrity_hash_file()` reads every CRLF as LF. The dev clone has `core.autocrlf=true`, the package is LF, an FTP client in ASCII mode converts again: a reference hashed with `hash_file()` was healthy on the Windows dev machine while every Linux site on 2026.4.4 reported 764 of 1916 files as tampered. Do not go back to `hash_file()` in the generator.

## Where does the check keep its state, and what may cleanup delete?
- `data/temp/hash_reference_state.json`: last run, last result, last GitHub attempt. It is a cache and `clean_up.php` sweeps it. A changed reference (regenerated, fetched, replaced) resets the cached result through its stamp. The state used to live inside the reference itself.
- `clean_up.php` and `pg_purge_caches()` never delete `data/temp/hash_reference.json`: on the dev machine it is the authoritative reference, and losing it turns every local difference into "tampered".

## What does it answer?
- `success`; `missing_or_tampered_files` (red, the files are logged); `unable_to_fetch_reference` (yellow: no tag for this version yet, or GitHub unreachable; that is not evidence of tampering, never turn it red); `unable_to_resolve_directory`; `version_not_defined`. With `ENVIRONMENT` set to `development` the widget does not run the check.

Related: docs/memory/core/file-integrity-github-reference.md
