---
name: file-integrity-check
description: How the hourly file-integrity check and hash_reference.json work (2026.4.4 and earlier model): generator, _generated stamp, state file, release regeneration.
---

## How does the integrity check work?
- `_software_create_hash.php` (dev machine only, not shipped; `https://dev.pinegrap.com/_software_create_hash.php`) writes SHA-256 digests of the files at the `pinegrap/` root and of the subtrees in `hashed_subdirectories()` to `pinegrap/data/temp/hash_reference.json`.
- `check_directory_file_integrity()` (`includes/fn/system_status.php`) compares them hourly. REGENERATE the reference before releasing a version, otherwise every changed file looks "tampered". The `includes/` tree is in scope since 2026.4.4.

## How are the two kinds of reference treated?
- The generator stamps `_generated` (host, version, time). If the stamp's host and version match this install, the reference is this machine's truth: compared with the files, NEVER compared with or replaced by the kodpen.com copy. Without this the dev machine could never be silenced (each regenerated reference was overwritten in the same request by the kodpen.com copy, and newly listed files came back as "extra").
- A reference stamped with another host (shipped with a customer-site package) or unstamped (downloaded) is compared every 12 hours with `pinegrap_hash_referance[VERSION].json` on kodpen.com and REPLACED by it (the copy catches a tampered local reference).

## What else is in the rules?
- The check's own ledger (last result, last run, last look at kodpen.com) is in `data/temp/hash_reference_state.json`, NOT inside the reference (it used to be; a reference uploaded to kodpen.com then carried `last_local_check` as a "missing file"). The generator deletes the state file on every run.
- What counts as a file in the reference is decided by SHAPE (`pg_integrity_reference_files()`): a key whose value is 64 hex digits is a file, the rest is ignored, in local and kodpen.com copies alike. A name list only covers the names of its writing day and old copies circulate for years. A reference with no file entries after filtering is corrupt.
- `clean_up.php` skips `hash_reference.json` when sweeping `data/temp`: it is not a cache; deleting it swaps the authoritative reference for the remote copy and every local diff reads as tampering. Same rule as `pg_purge_caches()`. The neighbouring `hash_reference_state.json` is a cache and IS swept.
- Publishing: the generated file is uploaded to kodpen.com as-is as `pinegrap_hash_referance[VERSION].json`; nothing else is written into it.

Related: docs/memory/core/file-integrity-github-reference.md
