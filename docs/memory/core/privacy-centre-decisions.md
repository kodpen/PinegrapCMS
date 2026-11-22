---
name: privacy-centre-decisions
description: What binds the Privacy centre (KVKK): opt-in rules off by default, one removal engine, what no rule removes, staff never removed, public request form off, answer by link, who changes rules
module: privacy_retention
---

## What did the owner decide?
- Owner (2026-11-20, agent channel poll 167, message 10254): a live inventory with the services abroad, opt-in retention and destruction rules, and a request box counting the 30 days, all three (`workflow/decisions-made-product-87.md`). Periods and legal grounds are suggested defaults, not legal advice; the shop changes them.
- Owner (2026-11-20, poll 177, message 10555): one `privacy_requests` table (`workflow/decisions-made-schema-36.md`).
- Owner (2026-11-21, `workflow/decisions-made-product-95.md`): the exported data reaches the person by a signed download link valid 7 days, never an attachment (`privacy-request-export-link.md`).

## What binds every rule and step?
- Every retention rule is opt-in and off by default; its first run is a dry run that waits for a person's yes, and a changed rule loses its yes.
- One engine: removing or anonymising goes through `pg_privacy_erase()`'s table list and actions, never a second list.
- Never removed by a rule: invoices, ERP records, order legal documents, records with money still owed (open order or return, gift card or store credit balance), a booking ahead, a user with a panel role.
- The inventory is code, not typed text: tests fail when the erase list or a registry row gains something the other side lacks.
- No legal period is written into code as a fact: a suggested default the shop can change, empty meaning never.
- Every rule change, run and request step is an `audit_log` line; the screen says once that defaults are no legal advice and that erased data stays in older backups.

## What did the architect decide?
- Architect (2026-11-20, agent channel message 10557): no table for destruction records; one `audit_log` line per rule run (`entity_type = 'privacy_rule'`, counts in `after_json`, no person), kept at least 3 years with the erasure lines whatever `audit_log_retention_years` says.
- Architect (2026-11-20, message 10557): My Account and staff-typed requests always; the public `privacy_request.php` is off by default (404 until opened in the Privacy centre), so an update opens no new public endpoint on any site.
- Architect (2026-11-20, message 10557): an incomplete cart keeps its row (cart recovery counts open carts from `orders`); its personal fields, contact and user links are blanked through the `orders` entry's anonymise action, after at least 31 days.
- Architect (2026-11-20, message 10557): the screen sits in Contacts, manager and up (`pg_privacy_export_allowed()`'s rule); switching a rule, its period and the yes belong to the role that may save Site Settings (`pg_privacy_rules_may_change()`, role <= 2); another role sees them read only.
- Architect (2026-11-21, message 11219): the privacy API is read only under its own `privacy:read` scope; writes stay on the panel where a person confirms them (`api/api-privacy-routes.md`).

## What was left out?
- Filing with VERBİS itself, a cookie scanner, a DPIA wizard, contracts with processors: each a later yes.

Related: [[privacy-centre]], [[privacy-retention-rules]], [[privacy-requests]]
