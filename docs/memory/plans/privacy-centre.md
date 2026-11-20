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
- No inventory: nothing lists which tables hold which personal data, for whom and why. The registry of outside services (`api/connections-registry.md`, 78 rows) names each service but not its country or the data it receives.
- A person's request comes by e-mail; nothing records it or counts its 30 days. The checkout KVKK notice and consent box exist (`commerce/checkout-kvkk-notice.md`).
- An xlsx writer (`includes/fn/xlsx_write.php`) and the export's PDF summary exist.

## What binds it?
- Every retention rule is opt-in and off by default; a first run shows what it would remove and waits for a person's yes.
- One engine: removing or anonymising goes through `pg_privacy_erase()`'s table list and actions, never a second list. Invoices, ERP records, order legal documents and records with money still owed (open order or return, gift card or store credit balance) are never removed by a rule.
- The inventory is code, not typed text: a catalogue beside `pg_privacy_erase_tables()` and fields on the registry rows, with tests that fail when either side gains a table or service the other lacks.
- Every rule change, run and request step is an audit line; the panel says once that the defaults are no legal advice.
- No legal period is written into code as a fact: a suggested default the shop can change, empty meaning never.

## What does the architect decide?
- Schema outside the ERP, the owner's: a `privacy_requests` table for the request box, and where destruction records live (a table of their own, or audit lines kept at least 3 years apart from `audit_log_retention_years`).
- The public request form: a new root file with the form captcha and an e-mail link that proves the address, or only My Account plus requests typed by staff from an e-mail?
- Incomplete carts: blank their personal fields after the period (the row stays for the cart recovery figures), or delete the row?
- Where the screen sits: a Privacy centre under Contacts beside the erase screen (manager and up), or Site Settings › Visitors and privacy?

## What is built?
- Features: `privacy-centre-features.md` (P1 to P12). Steps: `privacy-centre-steps.md`. Claim as `[plan:privacy-centre] <step> — <topic>`.

## What is left out?
- Filing with VERBİS itself, a cookie scanner, a DPIA wizard, contracts with processors; each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the Privacy centre is seen on dev after KV2.
