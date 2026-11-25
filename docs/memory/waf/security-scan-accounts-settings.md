---
name: security-scan-accounts-settings
description: Security Scan rules on accounts and settings: new staff account, new application key, world-writable core folder, risky PHP settings, PHP security fixes; places, severities, detail
module: security_scan_accounts
---

## Where are they?
- `includes/fn/security_scan_accounts.php`; tests `tests/security_scan_accounts_test.php`. Model: `security-scan-findings.md`. Overview: `security-scan-overview.md`.
- `pg_security_accounts_scan($now)` reads the rows and the server and returns every sighting; the rules themselves are pure (`pg_security_accounts_sightings()`, `pg_security_settings_sightings($env)`).

## Which rules, places and severities?
- `account_new_admin` (high) / `account_new_designer` (medium), place `user:<id>`: role 0 or 1 with `user_created` inside the last 30 days (`PG_SECURITY_NEW_WINDOW`). `user_created` 0 (made before the column) is never new.
- `api_key_new` (medium), place `api_app:<id>`: an active `server` application made inside 30 days. Device and OAuth applications are not keys a person typed.
- `core_folder_writable` (high): the "others" write bit on the software folder (place `setting:software_folder`) or `includes`, `includes/fn`, `includes/api`, `includes/migrations`, `install`, `assets` (path places). The owner's write access is no finding (the update needs it); `data/` and uploads are not code. Skipped on Windows (no mode bits).
- `php_allow_url_include` (high), `php_display_errors` (low, not when `stderr`), places `setting:<name>`.
- `php_security_fixes` (place `setting:php_version`): `pg_php2shell_status()` as System Status reads it; missing critical, unknown medium, likely_fixed passes (`platform/system-status-php-security-fixes.md`).

## What does a finding hold?
- `detail` is JSON facts, never a sentence: `created` (and `name` for a key), `mode`, or `version`/`state`/`fixed_in`. No user name or e-mail.
- `pg_security_accounts_reason($kind, $detail)` and `pg_security_accounts_advice($kind)` give the reason and "What to do" in the reader's language ('' for another module's kind); the section escapes them (`pg_security_rule_text()`).
- A new account or key stops being seen after 30 days, so a full scan marks it gone; Ignore acknowledges it sooner.

## How does it join the scan?
- The joined run `pg_security_scan_run()` records `pg_security_accounts_scan()` last, then marks this module's unseen findings gone (`security-scan-section.md`).
