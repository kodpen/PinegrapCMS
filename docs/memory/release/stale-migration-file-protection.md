---
name: stale-migration-file-protection
description: Why a stale migration file survived a zip upload (0555 folders) and the protection layers: permission scan, update screen, status card, repair, extraction.
---

## Why did an old migration file survive an upload, leaving a half schema?
- Cause was folder permissions, not OPcache. On the server `includes/migrations` (and other `includes/` subfolders) were 0555, files inside 0666, root / `data` / `install` / `assets` 0777.
- A closed folder cannot get new files; extractors that write a temp file and rename cannot replace an existing file either, so the file stays as it was. Beta-channel overwrite of an existing `2026.4.4.php` with a longer version is routine.
- Incident: a stale 26-step `2026.4.4.php` stayed after the zip was opened 3-5 times; the runner ran its short list, wrote `config.version = 2026.4.4`, new code asked for `api_webhooks` / `waf_rate_limit_api`, dashboard and settings returned 500.
- A layer cross-checking the schema against the version list was tried and removed (hurts maintainability, needs a second list every version). The remaining protection is on the file-system side.

## What are the protection layers?
- Write-permission scan: `pg_write_permission_scan()` (`includes/fn/system_status.php`): every folder of the software dir, every file outside `data/`; once per request, 10 min in the status cache.
- `software_update.php` scans before download; lists closed folders, **Güncelle** disabled, admin gets the button "Dosya izinlerini ayarla" (`api.php` `write_permissions_repair`) -> repair -> page reloads -> normal flow.
- System status card "Yazma İzinleri" (weight 12, `server`): red + path/mode list in the panel if a folder is closed, yellow if only a file is; the **Onar** button hits the same endpoint.
- Repair `pg_write_permission_repair()`: folder 0777, file 0666 (two users apply updates: panel = web server, FTP/file manager = hosting account). `chmod` works only for the owner; entries owned by someone else are reported by name.
- Extraction `pg_extract_archive()`: before extracting, unwritable targets get `chmod`/`unlink`; if any remain or the folder is unwritable, it stops with nothing extracted; after extraction it verifies size + CRC-32 (`stale`).
- Schema-behind must not 500: `get_system_status_checks()` probes `api_webhooks` with `SHOW TABLES LIKE` instead of querying; `settings.php` writes `waf_rate_limit_api` behind a `waf_table_has_column()` gate.

## What are the symptoms and the first move?
- "I unzipped, the file stayed old": check the Yazma İzinleri card first, for a closed (0555) folder.
- "Site looks current but table X is missing": pull `config.version` back to the previous released version and re-run (steps are idempotent), after making sure `includes/migrations/<version>.php` is the package's copy.

Related: docs/memory/release/schema-probe-rules.md
