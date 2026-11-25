---
name: security-scan-overview
description: Security Scan as a whole: rule-based self-scan in Firewall and HTTPS settings and the Firewall card; binding rules, parts, which file holds what, how to add a rule
module: security_scan, security_scan_files, security_scan_content, security_scan_accounts, security_scan_section
---

## What is it?
- The software scans itself without AI, an outside service or a library: a finding is a rule's name, a place and a reason in words. Built in #11425 to #11672.
- Not an antivirus: no signature feed, no database of outside marks. The rules are what this software knows about itself (the release reference, its own folders, its own records).
- It finds PHP2Shell-like files and older faults the release file check (`core/file-integrity-check.md`) misses: an added file, the upload folders, `data/`, stored markup, new staff accounts and keys, risky PHP settings.

## Which decisions bind it?
- Owner (2026-11-24): a protection beside the firewall that a connected assistant can read, joined to the firewall and bot blocking, a manual run and the existing security card, and tested so it never harms SEO or use; no screen of its own (`workflow/decisions-made-product-102.md`).
- Owner (2026-11-24): table `security_finding`, the last run in a `config_text` row, no column (`workflow/decisions-made-schema-51.md`).
- The scan only reads: it never deletes, moves or blocks by itself. Quarantine and Ignore are a person's clicks in the panel; neither the API nor MCP may make them (`security-scan-api.md`).
- No visitor or crawler request is slowed or refused by it: it runs in the scheduled task (heavy lane) or Scan now inside a time budget and resumes (`platform/scheduled-job-resume.md`).
- One place on screen: the "Security Scan" row of Site Settings › Firewall and HTTPS and the dashboard's Firewall card with "Scan now"; no menu row. Look A from the Design examples screen: severity pill, quiet small actions, danger only on Quarantine's confirm.
- A false alarm becomes a rule fix or a named exception with a test, never a silenced rule (`security-scan-test-round.md`).

## Which file holds what?
- `security-scan-findings.md`: the table, the pure model (kind, place, fingerprint, severity, state), which moves a scan or a person may make. Module `security_scan`.
- `security-scan-file-scan.md`: the file rules, the release reference, the resumable walk, the server-config baseline, `security_scan_job.php`. Module `security_scan_files`.
- `security-scan-retired-files.md`: `file_php_retired` from the Cleanup tool's shared list.
- `security-scan-stored-content.md`: script, hidden frame or redirect from an unknown host in pages, regions, styles and settings. Module `security_scan_content`.
- `security-scan-accounts-settings.md`: new Administrator or Designer account, new API key, writable core folder, PHP settings, PHP security fixes. Module `security_scan_accounts`.
- `security-scan-section.md`: the joined run `pg_security_scan_run()`, its state row, the findings list, Quarantine and Restore, Ignore, Scan now. Module `security_scan_section`.
- `security-scan-card.md`: the Firewall card part and the daily ops alert `security_finding`.
- `security-scan-api.md`: `GET /security-scan` and the MCP tool `security_scan_read`, read only.
- `security-scan-test-round.md`: what the round test proves, its exceptions, what stays unverified.

## How is a rule added?
- A kind name (`^[a-z][a-z0-9_]{0,39}$`) with a severity, built through `pg_security_sighting()` in the module of its area, run by that area's pass inside `pg_security_scan_run()`; the reason and "What to do" through `lang()`.
- A test in the area's test file, and a case in `tests/security_scan_round_test.php` showing a clean install and every design template give no finding.
- The rule goes into its area's topic file above, not here.
