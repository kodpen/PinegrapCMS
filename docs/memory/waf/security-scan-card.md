---
name: security-scan-card
description: Security Scan part of the new dashboard Firewall card (last scan, open findings by severity, Scan now) and the daily ops alert security_finding for a critical or high finding
module: security_scan_section
---

## Where is it on the dashboard?
- The new dashboard's Firewall card (`includes/dashboard/v2/cards/firewall.php`, `pg_dashboard_v2_firewall_scan()`) carries a `scan` part under "Most blocked"; drawn by `scanPart()` in `assets/js/pg_dash_cards_security.js`, styles `.pg-dash-scan*` in `pg_dash.src.css`. The old dashboard's widgets 21/22 are untouched (owner rule: the old dashboard stays as it is).
- No `scan` part before the upgrade made `security_finding` (`pg_security_finding_ready()`).

## What does it show?
- Title "Security Scan", then a line: "Not scanned yet", "Last scan {relative time}", or the running scan's progress (`pg_security_scan_progress()`).
- One pill per severity with open findings, worst first (`pg_security_card_pills()`, the section's tones); "Nothing open" only after a finished scan.
- Counts come from a `GROUP BY severity` (`pg_security_open_counts()`), not the section's list, which stops at 300.
- Buttons: "Scan now" and "See the findings" (`settings_firewall.php#pgset-scan`), both quiet and small.

## How does Scan now run from the card?
- The same `api.php` action as the section, `security_scan_step` with the page's `software_token`; it asks again until done, every 3 s while busy. The card is staff only (role < 3), the action Manager and above: the same people.
- While it runs, a refresh of the card keeps the button disabled with the last line (module state `scan`); when done the card is loaded again (`pgDash.reload()`). A failure puts the message in the line, in the bad tone.

## When is the operations alert sent?
- Code `security_finding` (error, opens `settings_firewall.php#pgset-scan`), checked by `pg_ops_alerts_check_security()` in the ops pass.
- Sent while an open finding of critical or high severity exists (`pg_security_alert_text()`; medium and low never alert), once per 24 h by the code's usual rule; it repeats daily until the finding is quarantined, ignored or no longer seen.

Tests: `tests/security_scan_section_test.php`. Related: `security-scan-section.md`, `platform/ops-alerts.md`.
