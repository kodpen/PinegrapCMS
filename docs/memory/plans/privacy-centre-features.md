---
name: privacy-centre-features
description: Plan privacy-centre, features P1 to P12: the inventory and its transfers, the retention rules with their dry run and audit-line destruction records, the request box, API
---

## What is the inventory?
- P1 A catalogue in code: each table that holds personal data, its categories (identity, contact, address, order and payment, IP and cookie, location, signature, uploads), whose data it is (customer, visitor, applicant, staff), purpose, suggested legal ground and its retention (a rule of P6 or "until erased").
- P2 Live counts: how many people and records each line covers, read on the screen.
- P3 Transfers: each registry service switched on, with its country, the data it receives and why; services abroad marked.
- P4 Download as Excel with the VERBİS columns (category, purpose, legal ground, data subject group, recipients, retention, transfer abroad, measures) and as PDF.
- P5 Kept live: tests fail when `pg_privacy_erase_tables()` names a table the catalogue lacks, or a registry row has no country and data.

## What are the retention rules?
- P6 Per kind, each off until switched on, the period changeable:
  - a passive contact (no order, form, sign-in or campaign click for 3 years): erased;
  - form submissions after 2 years, per form when a form says otherwise;
  - an incomplete cart's personal fields, contact and user links blanked after 90 days (at least 31), the row kept;
  - orders anonymised after the years the shop types (empty: never);
  - closed requests 3 years after closing.
- P6b Switching a rule, its period and the first run's yes: only the role that may save Site Settings; a manager reads the rules.
- P7 A rule's first run is a dry run: counts and a sample. After the yes, a nightly pass in slices, at the interval the shop picks, never longer than 6 months.
- P8 Skipped, whatever the rule: invoices and ERP records, order legal documents, a person with an open order or return, a gift card or store credit balance, a booking ahead, a user with a panel role.
- P9 A destruction record per run and rule: one `audit_log` line (`entity_type = 'privacy_rule'`, counts in `after_json`, no person), kept at least 3 years whatever `audit_log_retention_years` says. The PDF lists them with the erase lines and says once that older backups still hold the data.

## What is the request box?
- P10 Kinds: information, correction, erasure, objection, other. My Account and staff-typed requests always. The public `privacy_request.php` is off by default (404 until opened in the Privacy centre): form captcha, the same "check your e-mail" answer whether the address is known, `waf_rate_exceeded()` 5 an address an hour, also with the firewall off.
- P10b The proof link as `confirm_subscription.php`: signed, stateless, GET shows a button, POST confirms; 48 hours. The 30 days start at the proof; an unproven request is not shown and is swept after 7 days.
- P11 The panel list: kind, person, received, due (30 days), who handles it, status, result. Bell and e-mail at 7 and 2 days left. "Prepare the answer" runs the export (information) or the erase dry run (erasure); the answer goes by e-mail and is kept with the request. Each step is an `audit_log` line (`entity_type = 'privacy_request'`).

## Who can read it from outside?
- P12 `GET /privacy/inventory` and `GET /privacy/requests` with a request's steps; MCP tools by the route rule. Writes stay on the panel, where a person confirms them.
