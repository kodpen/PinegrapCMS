---
name: security-scan-api
description: GET /security-scan and its MCP tool security_scan_read: is the site at risk, the findings and the last run of the Security Scan, read only under system:read
---

## Where is it?
- Route `security_scan.read`, `GET /security-scan`, scope `system:read`, in `api_schema()` (`includes/api/schema.php`) beside `system.status`. Handler, answer and schema: `includes/api/resources/security_scan.php`; declared answer `SecurityScanReport`. Tests `tests/security_scan_api_test.php`.
- The MCP tool `security_scan_read` comes from the route row itself (`api/api-mcp-server.md`): no tool code of its own.

## What does it answer?
- `at_risk`: an open finding is high or critical. `worst`: the worst open severity key, null when nothing is open. `open`: open findings by severity (critical, high, medium, low); `quarantined`, `ignored`: their counts.
- `scanned`: a first run has finished. `ready`: the findings table exists (`pg_security_finding_ready()`); before the update every count is 0.
- `scan`: `running`, `phase`, `progress` (the section's words), `started_at`, `last_finished_at`, `last_files`, `last_records`, from `pg_security_scan_stored_state()`.
- `findings`: the section's rows (`pg_security_section_rows()`, at most 300, gone ones left out) in its order, each with keys to branch on (`kind`, `severity`, `state`), `place`, `reason` and `what_to_do` in the site language (`pg_security_rule_text()`), `detail`, `note`, `quarantinable`, the three moments.
- `?state=open|quarantined|ignored` narrows `findings` only; the counts and the risk always cover every row.

## Why read only?
- Quarantine, Ignore and Scan now stay a person's clicks in the panel (`security-scan-overview.md`): a leaked key or an assistant must not move files or start the heavy run. The settings screens keep no endpoint (`api/api-settings-no-endpoint.md`); this read was asked for by the owner (`workflow/decisions-made-product-102.md`).

Related: `security-scan-section.md`, `security-scan-findings.md`.
