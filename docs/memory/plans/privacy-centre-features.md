---
name: privacy-centre-features
description: Plan privacy-centre, features P1 to P12: the inventory and its transfers, the retention rules with their dry run and destruction records, the request box, API
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
  - an incomplete cart's personal fields after 90 days;
  - orders anonymised after the years the shop types (empty: never).
- P7 A rule's first run is a dry run: counts and a sample. After the yes, a nightly pass in slices, at the interval the shop picks, never longer than 6 months.
- P8 Skipped, whatever the rule: invoices and ERP records, order legal documents, a person with an open order or return, a gift card or store credit balance, a booking ahead.
- P9 A destruction record per run and rule: what, how many, deleted or anonymised, when, which rule set by whom; kept 3 years; downloadable as PDF.

## What is the request box?
- P10 A request form (information, correction, erasure, objection, other) with the form captcha; the address proven by an e-mail link before the request counts; a signed-in person's account is known.
- P11 The panel list: kind, person, received, due (30 days), who handles it, status, result. Bell and e-mail at 7 and 2 days left. "Prepare the answer" runs the export (information) or the erase dry run (erasure); the answer goes by e-mail and is kept with the request.

## Who can read it from outside?
- P12 `GET /privacy/inventory` and `GET /privacy/requests` with a request's steps; MCP tools by the route rule. Writes stay on the panel, where a person confirms them.
