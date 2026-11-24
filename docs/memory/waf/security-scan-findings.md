---
name: security-scan-findings
description: Security Scan findings - the security_finding table (11.0.277), the pure finding model, fingerprint, severities, states and which moves a scan or a person may make.
module: security_scan
---

## Where is it?
- Table `security_finding`, step `upgrade_2026_11_0_security_finding()` (11.0.277), listed in `get_tables()`; columns as `workflow/decisions-made-schema-51.md` agreed. Readiness probe: `pg_security_finding_ready()`.
- Model: `includes/fn/security_scan.php`, pure but the probe; tests `tests/security_finding_test.php`. The file scan that writes findings: `security-scan-file-scan.md`. Plan: `plans/security-scan.md`.

## What is a finding?
- One row per rule and place. `kind` is the rule's name (`^[a-z][a-z0-9_]{0,39}$`, `pg_security_kind_valid()`).
- `place` is a file path relative to the software folder (`pg_security_place_path()`: forward slashes, no leading `./` or `/`, `..` or over 500 characters gives '') or a record as `<type>:<id>` (`pg_security_place_record()`, `page:12`, `user:3`; an account is its user id only).
- `fingerprint` = `sha1(kind . "\n" . place)`, unique: a later scan updates the row instead of adding one.
- A scan builds a sighting with `pg_security_sighting()` (null when not a finding) and merges it with `pg_security_finding_merge($stored_or_null, $sighting, $now)`.

## Which values do severity and state take?
- Severity 1 low, 2 medium, 3 high, 4 critical (higher is worse, so `idx_state (state, severity)` lists the worst first). Keys: `pg_security_severity_key()`.
- State 0 open, 1 quarantined, 2 ignored, 3 gone (`PG_SECURITY_STATE_*`, `pg_security_state_key()`).

## Which moves may a scan make?
- Found and new: open. Found again: last_seen, severity and detail updated; gone and quarantined become open again (a quarantined file back at its place came back), ignored stays ignored; note, acted_by and acted_at are kept.
- Not seen by a full scan (`pg_security_state_unseen()`): open and ignored become gone; quarantined stays quarantined, because the quarantine moved the file away.
- A gone row is removed 90 days after its last_seen (`pg_security_finding_expired()`).

## Which moves may a person make?
- `pg_security_state_after($state, $action)`: Quarantine and Ignore from open, Restore from quarantined, Reopen from ignored; anything else null. The scan never deletes, moves or blocks by itself.
- The Ignore note is one line, no control characters, at most 255 characters (`pg_security_note_clean()`).
