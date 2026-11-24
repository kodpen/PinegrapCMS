---
name: security-scan
description: Plan: Security Scan, a rule-based scan of files, stored content, accounts and settings joined to the firewall's screen and card, with quarantine, a manual run, an API and MCP read; SC1 to SC9
priority: 6
---

## Why is this a plan?
- Owner (2026-11-24, agent channel messages 12839 and 12847): a protection beside the WAF that finds PHP2Shell-like files and older security faults by itself, without AI, and that a connected assistant can read; joined to the WAF and bot blocking, no screen of its own (`workflow/decisions-made-product-102.md`).
- Today only release files are compared (`core/file-integrity-check.md`): an added file, the upload folders and `data/` are not looked at.
- Not an antivirus: no signature feed. The rules are what this software knows about itself.

## Which rules does every step keep?
- The scan only reads. It never deletes, moves or blocks by itself; quarantine and ignore are a person's clicks. No request of a visitor or crawler is slowed or refused by it: it runs in the scheduled task inside a time budget and resumes (`platform/scheduled-job-resume.md`).
- No AI, no outside service, no library. A finding is a rule's name, a place and a reason in words.
- One screen: a "Security Scan" section in Site Settings › Firewall and HTTPS; the dashboard's Firewall card shows the last scan and a "Scan now"; no menu row.
- Table `security_finding` (`workflow/decisions-made-schema-51.md`); the last run in one `config_text` row.

## What does each step build? (`[plan:security-scan] SC<n> — <topic>`)
- SC1 (S) done (#11425, `waf/security-scan-findings.md`): the table by a migration step, the pure finding model (kind, place, fingerprint, severity, state), tests.
- SC2 (M) File scan done (#11451, `waf/security-scan-file-scan.md`): a PHP file not in the release reference; an executable name in the upload folders (`.php`, `.phtml`, `.phar`, a double extension), PHP tags inside a picture; a changed or new `.htaccess` / `.user.ini`; known shell marks (`eval(base64_decode(`, `assert($_`) only in files outside the reference. After SC1.
- SC3 (M) The section: findings by severity with place, reason and "What to do"; Quarantine (moved to a folder that cannot run, with Restore), Ignore with a note, "Scan now" with its progress, the last run. After SC2.
- SC4 (S) The Firewall card: last scan, open findings by severity, "Scan now"; the ops alert `security_finding`, once a day (`platform/ops-alerts.md`). After SC3.
- SC5 (M) done (#11456, `waf/security-scan-stored-content.md`) Stored content: a script, hidden iframe or redirect from a host the site never used, in pages, regions, styles and settings. After SC1.
- SC6 (M) done (#11457, `waf/security-scan-accounts-settings.md`) Accounts and settings: a new Administrator or Designer account, a new API key, a writable core folder, risky PHP settings, the PHP security fixes check joined in. After SC1.
- SC7 (S) API read route and one MCP tool ("is the site at risk?"): findings and last run, read only. After SC3.
- SC8 (M) The test round, below. After SC2 to SC7.
- SC9 (S) Close-out: memory in `waf/`, this file deleted, the `[NEW]` line named.

## What does the test round try? (SC8)
- A clean install and an upgraded site report nothing: every design template, a backup restore, the update's replace, object storage, uploads of every kept type, a custom form's files.
- Each rule fires on its planted case and on nothing else; a quarantine and its restore leave the site as before.
- A scan during a crawl: page times, the sitemap and robots answers unchanged; a scan cut by the time limit resumes; 50,000 files inside the budget.
- Every false alarm found becomes a rule fix or a named exception before the plan closes.

## How is each drawn?
- Look A from the Design examples screen: gathered rows with a severity pill, quiet small actions, danger only on Quarantine's confirm; the card as the Firewall card's rows.
