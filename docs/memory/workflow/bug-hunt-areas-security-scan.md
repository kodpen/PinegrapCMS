---
name: bug-hunt-areas-security-scan
description: The bug hunt in the Security Scan (built in #11425 to #11672, SC1 to SC9), PR #11777: the faults fixed, what was read clean and what was seen and left
---

## Which area was it?
- The Security Scan: `includes/fn/security_scan.php`, `security_scan_files.php`, `security_scan_content.php`, `security_scan_accounts.php`, `security_scan_section.php`, `includes/panel/security.php` and `security_scan_job.php`. Given by the architect to coordinator k1; hunted in #11777. Overview: `waf/security-scan-overview.md`.

## What was fixed?
- Quarantine and Restore followed a folder of the place that had turned into a symbolic link after the scan: Quarantine could move a file from outside the site (a core file through a link to `includes/`), Restore could write into another folder. Both now refuse (`pg_security_place_folder_real()`, `waf/security-scan-section.md`).
- A second Quarantine of a place planted again wrote over the first quarantined copy; the earlier copy is now kept as `<sha1>.<n>.quarantine`.
- A folder the walk could not list was walked as empty, and the finished run marked every finding under it gone: the list read clean. Such a folder is now kept in the run's `unread` and its findings stay (`waf/security-scan-file-scan.md`).
- The same for a stored-content table whose query failed: its records' findings stay (`waf/security-scan-stored-content.md`).
- Missed scripts in stored markup: `src="https&#58;//host/x.js"` (character references in an attribute value), `src="https:\\host/x.js"` or `/\host` (backslashes, read as slashes by browsers), and a tag cut at the end of a field (`<script src="//host/x.js"` with no `>`, closed by the page's markup after it).
- An ignored finding stayed ignored when what it named changed: a new host in an ignored record, another content in an ignored file. It now opens again; file findings carry the file's digest in their detail (`waf/security-scan-findings.md`).

## What was read and found clean?
- Place paths: `..`, an absolute path and a NUL are refused by `pg_security_place_path()`; the fingerprint is kind and place joined by a newline, which neither can hold.
- Quarantine is offered only for planted-file kinds, never for `file_server_config`; Restore never writes over a file at its place; both are Manager and above with the token in `api.php`; the API and MCP tool only read (`security_scan_read`).
- Escaping: the list, the place, the detail, the note and the reason go through `h()`; the toast escapes its message; the API detail of a file finding is only the server settings file's new or changed, never the digest.
- Upload rules: a PHP extension in any segment and in any case, a name that starts with a dot. Host matching covers subdomains, never a prefix or a parent (`google.com.evil.example` is unknown).
- The walk: the resume order (segment compare) matches the sorted walk; one run at a time by `GET_LOCK`; Scan now closes the session first, so no visitor or panel page waits on it.

## What was seen and left?
- An old account raised to Administrator or Designer (or a row with `user_created` 0) is not reported: the rule reads `user_created` only. Architect (2026-11-26, agent channel request 3046): a gap in the rule, to be built as one small task after #11777, with no column. A `config_text` map of staff id to role and since; the first run stores everyone as known; a raise reported for 30 days with detail `raised`. The PR that builds it writes the rule into `waf/security-scan-accounts-settings.md`.
- A failed accounts query is read as no new account, so a run closes those findings.
- A quarantined finding whose kept copy was deleted by hand stays quarantined; Restore answers that the copy is missing.
- A second extension (`x.php.jpg`) is looked for only in the upload folders, not elsewhere in the software folder.

Related: docs/memory/waf/security-scan-overview.md, docs/memory/workflow/bug-hunt.md
