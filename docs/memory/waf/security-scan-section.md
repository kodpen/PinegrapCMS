---
name: security-scan-section
description: Security Scan row of Site Settings › Firewall and HTTPS: the joined run and its state row, the findings list, Quarantine and Restore (data/quarantine), Ignore with a note, Scan now through api.php
module: security_scan_section
---

## Where is it?
- `includes/fn/security_scan_section.php` (pure above "Disk and database"); the card in `includes/settings/firewall.php` (`pgset-scan`), its row line `pg_firewall_fold_scan()`; the actions `includes/panel/security.php`; the client at the end of `backend.src.js`. Tests `tests/security_scan_section_test.php`.
- The row sits after Firewall (`pg_firewall_fold_order()`: headers, waf, scan, limits, …). Line: open findings and the last scan, "Not scanned yet", "Scanning now"; pill the worst open severity, "Nothing open" after a clean scan; a high or critical open finding opens the row.

## How does one run go?
- `pg_security_scan_run($budget)`: files (`pg_security_files_run()`), then stored content (`pg_security_content_pass()` with the cursor kept in the state), then accounts and settings (`pg_security_accounts_scan()`, unseen ones closed to gone). One run at a time by `GET_LOCK('pg_security_scan')`; a second caller answers busy.
- State: `config_text` row `security_scan` (`pg_security_scan_state()`): started, phase, content cursor, files and records so far, last_finished, last_files, last_records. The file scan keeps its own row `security_scan_files`.
- `security_scan_job.php` (daily, heavy lane) and Scan now both call it. Scan now (`api.php` `security_scan_step`, 8 seconds a request) asks again until done, every 3 s while busy; a run finished there calls `pg_cron_ran('security_scan_job')`.

## What does the list show?
- Open findings worst and newest first, then quarantined, then ignored (dimmed); gone ones are not listed; at most 300. Each row: severity tile and pill, the reason as title, the place, the detail (a content finding's hosts and fields; a server settings file's new or changed), "What to do", when it was found, and the actions.
- Reason and advice: `pg_security_rule_text($kind, $detail)` joins `pg_security_file_reason()` / `pg_security_file_advice()`, `pg_security_content_rule_text()` and `pg_security_accounts_reason()` / `_advice()`; the test fails for a kind with no text.

## Which actions does a row offer?
- `pg_security_section_actions()`: open → Quarantine (file kinds only) and Ignore; quarantined → Restore; ignored → Reopen. `api.php` `security_scan_act` (Manager and above, token) answers with the list drawn again.
- Quarantine only for `file_php_unknown`, `file_upload_executable`, `file_picture_php`, `file_shell_mark` (`pg_security_quarantinable()`); never `file_server_config`, as moving the root `.htaccess` could take the site down. It asks first (the only danger dialog).
- Quarantine moves the file to `data/quarantine/<sha1(place)>.quarantine` (an extension nothing runs; the folder gets `Require all denied` and is skipped by the walk) and marks every open quarantinable finding of that place quarantined. Restore moves it back only when nothing is at the place again, and the place's quarantined findings become open.
- Ignore first shows a note field ("Why is it ignored?", 255 characters, `pg_security_note_clean()`); Return in it sends the note, not the settings form. Each action writes a Site Log line.

Related: `security-scan-api.md` (the API read), `security-scan-findings.md`, `security-scan-file-scan.md`, `plans/security-scan.md`.
