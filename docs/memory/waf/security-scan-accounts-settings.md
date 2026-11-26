---
name: security-scan-accounts-settings
description: Security Scan rules on accounts and settings: new staff account, new application key, world-writable core folder, risky PHP settings, PHP security fixes; places, severities, detail
module: security_scan_accounts
---

## Where are they?
- `includes/fn/security_scan_accounts.php`; tests `tests/security_scan_accounts_test.php`. Model: `security-scan-findings.md`. Overview: `security-scan-overview.md`.
- `pg_security_accounts_scan($now)` reads the rows and the server and returns every sighting; the rules themselves are pure (`pg_security_accounts_sightings()`, `pg_security_settings_sightings($env)`).

## Which rules, places and severities?
- `account_new_admin` (high) / `account_new_designer` (medium), place `user:<id>`: role 0 or 1 with `user_created` inside the last 30 days (`PG_SECURITY_NEW_WINDOW`), or raised to the role inside them. `user_created` 0 (made before the column) is never new by creation.
- Architect (2026-11-26, agent channel request 3046): an old account raised to Administrator or Designer is a new staff account to the reader, so the scan reports it (#11801). Symptom fixed: the rule read `user_created` only, so a role 2/3 account raised, or a row inserted with `user_created` 0, was never reported.
- How: `config_text` row `security_scan_staff_roles` (`PG_SECURITY_STAFF_ROLES_STATE`), a JSON object user id => `role`, `since`, for role 0 and 1 only; ids, roles and times, no name or address. `pg_security_staff_roles_track($users, $stored, $now)` (pure) gives the sightings and the next map; `pg_security_accounts_scan()` reads and saves the row in the joined run's accounts step.
- Map rules: no row yet stores every account with since 0 (the first scan after the update reports nobody by this rule); a new id, or Designer now Administrator, gets since = now; same role or Administrator lowered to Designer keeps since; an id no longer 0 or 1 is dropped, so lowered then raised again reopens.
- A since inside 30 days is a sighting of the same kind and place, detail `raised`; when `user_created` inside 30 days already gives the account's sighting, nothing is added.
- `api_key_new` (medium), place `api_app:<id>`: an active `server` application made inside 30 days. Device and OAuth applications are not keys a person typed.
- `core_folder_writable` (high): the "others" write bit on the software folder (place `setting:software_folder`) or `includes`, `includes/fn`, `includes/api`, `includes/migrations`, `install`, `assets` (path places). The owner's write access is no finding (the update needs it); `data/` and uploads are not code. Skipped on Windows (no mode bits).
- `php_allow_url_include` (high), `php_display_errors` (low, not when `stderr`), places `setting:<name>`.
- `php_security_fixes` (place `setting:php_version`): `pg_php2shell_status()` as System Status reads it; missing critical, unknown medium, likely_fixed passes (`platform/system-status-php-security-fixes.md`).

## What does a finding hold?
- `detail` is JSON facts, never a sentence: `created` (and `name` for a key), `raised` (the time the scan first saw the role), `mode`, or `version`/`state`/`fixed_in`. No user name or e-mail.
- `pg_security_accounts_reason($kind, $detail)` and `pg_security_accounts_advice($kind, $detail)` (a `raised` account gets its own reason and advice: lower the role) give the reason and "What to do" in the reader's language ('' for another module's kind); the section escapes them (`pg_security_rule_text()`).
- A new or raised account or a key stops being seen after 30 days, so a full scan marks it gone; Ignore acknowledges it sooner.

## How does it join the scan?
- The joined run `pg_security_scan_run()` records `pg_security_accounts_scan()` last, then marks this module's unseen findings gone (`security-scan-section.md`).
