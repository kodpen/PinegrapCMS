---
name: privacy-centre
description: Start file of Contacts › Privacy centre (KVKK): inventory and transfers, Excel/PDF, Retention rules, request box with 30 days, public request form, download link, privacy API; which file holds what
module: privacy_centre
---

## What is the Privacy centre?
- Contacts › "Privacy centre" (`privacy_centre.php`, manager and up), beside Personal data export and Personal data erasure. Two views: "Inventory" and "Requests". Site Settings › Visitors and privacy only links to it.
- Built from the owner's yes of 2026-11-20 (`workflow/decisions-made-product-87.md`) in #10738 to #11020. What binds it: `privacy-centre-decisions.md`.

## Which part is where? (P numbers of the plan)
- (P1, P2, P3, P5) The catalogue beside the erase table list, live counts, transfers from the registry rows' country and data, the tests that keep both sides whole: `privacy-inventory.md` (#10738).
- (P4) The Inventory view, Download Excel with the VERBİS columns, Download PDF with the destruction records: `privacy-centre-screen.md` (#10789).
- (P6 to P9) The five retention rules, dry run and yes, the nightly pass through the erasure's entries, the skips, the `privacy_rule` audit lines kept 3 years: `privacy-retention-rules.md` (#10791). The Retention rules card on Inventory: `privacy-rules-card.md` (#11020).
- (P11) The `privacy_requests` table, the 30 days, reminders at 7 and 2 days, the 7-day sweep, the Requests view and its drawer: `privacy-requests.md` (#10851). The Answer tab ("Prepare the answer"): `privacy-request-answer.md` (#10894). The signed 7-day download link of an information answer: `privacy-request-export-link.md` (#10944).
- (P10, P10b) My Account's request, the public `privacy_request.php` (off by default), the 48-hour proof link, the rate limit: `privacy-request-form.md` (#10921).
- (P12) `GET /privacy/inventory`, `/privacy/requests`, `/privacy/requests/{id}` and their MCP tools: `api/api-privacy-routes.md` (#10897).
- `audit_log` and `consent_log` keep periods (older than the centre): `privacy-log-retention.md`. One person at a time: `personal-data-export.md`, `personal-data-erasure.md`.

## Where does the code differ from the plan's words?
- (P6) A passive contact is one with no order, form, sign-in, contact change or consent answer in the period: no click on a campaign is recorded per contact, so none is read (`privacy-retention-rules.md`).
- (P10) The rate limit also counts 20 sends an hour per sender network address beside 5 per address asked about.
- (P6b) Site Settings is saved by role <= 2, the same roles that open the screen (`validate_area_access($user, 'manager')`), so whoever opens the Privacy centre may change the rules; the read-only card is a guard that a stricter Site Settings rule would use.
- (P12) The detail route `/privacy/requests/{id}` with a request's steps was added to the two list routes.

## How is a new part added?
- A new table holding personal data: a line in `pg_privacy_inventory()` and an entry in `pg_privacy_erase_tables()` in the same PR; `tests/privacy_inventory_test.php` fails otherwise. Its purpose and labels get Turkish keys (`tr_add.php`).
- A new outside service: `country` and `data` on its registry row (`api/connections-registry.md`).
- A new retention rule: a row in `pg_privacy_rules_catalogue()` naming erase entries, never a second removal list; off by default; the card draws it.
- The plan (features P1 to P12, steps KV1 to KV7) was deleted at its close-out; git history keeps it (`git log --diff-filter=D --name-only -- docs/memory/plans`).

Related: [[privacy-centre-decisions]], [[privacy-inventory]], [[privacy-requests]], [[privacy-retention-rules]]
