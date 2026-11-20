---
name: privacy-centre
description: Plan (owner 2026-11-20, poll 167): KVKK centre: a live personal data inventory with transfers abroad, opt-in retention and destruction rules, a request box with the 30-day clock; KV1 to KV7
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 167, message 10254): "Evet, plana girsin": the inventory, the retention and destruction rules and the request box, all three. Periods and legal grounds are suggested defaults, not legal advice; the shop changes them.

## What is there today?
- One person at a time: `privacy_export.php` (`core/personal-data-export.md`) and `privacy_erase.php` with its table list `pg_privacy_erase_tables()` (`core/personal-data-erasure.md`), manager and up, from Contacts.
- Retention only for `audit_log` and `consent_log` (`core/privacy-log-retention.md`, `includes/fn/privacy_retention.php`, a daily pass in slices). Contacts, form submissions and orders: "Not built ... opt-in and off by default".
- No inventory. The registry of outside services (`api/connections-registry.md`, 78 rows) names no country or data received.
- A request comes by e-mail; nothing counts its 30 days. The checkout KVKK notice exists (`commerce/checkout-kvkk-notice.md`).
- An xlsx writer (`includes/fn/xlsx_write.php`) and the export's PDF exist.

## What binds it?
- Every retention rule is opt-in and off by default; a first run shows what it would remove and waits for a person's yes.
- One engine: removing or anonymising goes through `pg_privacy_erase()`'s table list and actions, never a second list. Invoices, ERP records, order legal documents and records with money still owed (open order or return, gift card or store credit balance) are never removed by a rule.
- The inventory is code, not typed text: a catalogue beside `pg_privacy_erase_tables()` and fields on the registry rows, with tests that fail when either side gains a table or service the other lacks.
- Every rule change, run and request step is an audit line; the panel says once that the defaults are no legal advice.
- No legal period is written into code as a fact: a suggested default the shop can change, empty meaning never.

## What did the architect decide? (2026-11-20, agent channel message 10557)
- `privacy_requests` table: the owner's, poll 177 (message 10555); KV4 waits for it; the proposed columns are in KV4.
- No table for destruction records: one `audit_log` line per rule run (`entity_type = 'privacy_rule'`, counts in `after_json`, no person), beside the erase's `pg_audit('erase', 'person', …)`. `pg_privacy_retention_run()` keeps both kinds at least 3 years, whatever `audit_log_retention_years` says; the Audit trail card says so.
- Request form: both. My Account and staff-typed requests always; the public `privacy_request.php` is off by default (404 until opened in the Privacy centre), so an update opens no new public endpoint on any site.
- Incomplete carts: personal fields blanked, contact and user links too; the row stays (cart recovery counts open carts from `orders`). Through the `orders` entry's anonymise action, no second action; the period at least 31 days.
- Screen: Contacts › "Privacy centre" beside the erase screen, manager and up (`pg_privacy_export_allowed()`'s rule); Site Settings › Visitors and privacy only links to it. Switching a rule, its period and the first run's yes: only the role that may save Site Settings; a manager reads the rules.
- Also: staff (a panel role) is never removed by a rule; erased data stays in older backups, said once on the screen and the PDF; a rule for closed requests (P6).
- Form, carts and screen: the architect's reading; the owner may turn them.

## What is built?
- Features: `privacy-centre-features.md` (P1 to P12). Steps: `privacy-centre-steps.md`. Claim as `[plan:privacy-centre] <step> — <topic>`.

## What is left out?
- Filing with VERBİS itself, a cookie scanner, a DPIA wizard, contracts with processors; each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the Privacy centre is seen on dev after KV2.
