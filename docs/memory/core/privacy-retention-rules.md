---
name: privacy-retention-rules
description: Privacy centre retention rules (P6-P9): rules and bounds, config_text settings, dry run and yes, the nightly pass through the erase entries, the skips, privacy_rule audit lines and their 3-year keep
module: privacy_retention
---

## Which rules are there?
- `pg_privacy_rules_catalogue()` (`includes/fn/privacy_retention.php`): `contacts` (passive, 3 years, 1-20), `forms` (2 years, 1-20; per form by page id, 0 keeps that form), `carts` (incomplete orders, 90 days, at least 31), `orders` (years the shop types, 1-30; empty is never), `requests` (closed privacy requests, 3 years from `closed_at`, 1-20, own entry only, no person skip; `privacy-requests.md`). Also `volunteers`. Every rule is off by default. Defaults are suggestions, not legal advice.
- The pass interval: 1 to 183 days (at most six months), default 1 (`pg_privacy_rules_interval_limits()`).
- `chats` (site chats): `privacy-rule-site-chats.md`.

## Where are they kept?
- `config_text` `privacy_rules` (rules, periods, `approved_at`/`approved_by`, interval) and `privacy_rules_state` (per rule `last_run_at`, a pass under way: `started_at`, `cursor`, counts). Read directly, not through `pg_config_text()`'s cache. No schema.

## Who may change them?
- `pg_privacy_rules_may_change()`: role <= 2, who saves Site Settings (`pg_settings_reader()`); all who open the Privacy centre read them. `pg_privacy_rules_save()` and `pg_privacy_rule_approve()` refuse others.
- A rule switched on, off, or given another period (forms: other per-form periods) loses its yes (`pg_privacy_rules_from_input()`): its next run is a dry run.

## How does a rule run?
- First `pg_privacy_rule_dry_run()`: total rows, the first 200 checked (counts per erase entry, skips), five sample ids; nothing written. The yes (`pg_privacy_rule_approve()`) lets the nightly pass run it.
- `job.php`: `pg_privacy_rules_due()` (midnight to six, site time; a rule on, approved, past its interval or under way) and `pg_privacy_rules_run(5)`: 25 ids a query from the last id done, a row at a time within the budget; state saved after each rule.
- Each row goes through `pg_privacy_erase_run($scope, $apply, pg_privacy_rule_entries($key))`, the erasure's own entries and actions: contacts as the whole person (`pg_privacy_erase_scope()`); forms as the submission with its files and revisions; carts the `ship_tos` and `orders` entries (row kept, the orders entry's own field set, `pg_privacy_rule_cart_fields()`); orders `ship_tos`, `order_returns`, `shipment_labels`, `orders`.
- No rule reaches `pg_privacy_rules_never_entries()`: ERP accounts, invoices, quotes, waybills, `order_documents`.
- Carts and orders are picked only while still personal (`pg_privacy_rule_pending_where()`), so an anonymised row is not taken again.

## Who is skipped (P8)?
- In order: staff (role < 3, a `user_manage_*` = yes or a `manage_*` right; unknown role too), an open order (complete or exported within 60 days, or refund pending, failed or manual), an open return (`pg_return_open_statuses()`).
- Then a gift card or store credit balance, a booking ahead (`pg_booking_erase_cancel_where()`), and for passive contacts any activity since the cutoff (order, form, sign-in `user_online_timestamp`, contact change, consent answer, a volunteer profile). No per-contact campaign click is recorded, so none is read.

## What does the audit trail keep (P9)?
- One `pg_audit('run', 'privacy_rule', 0, [], …, <rule>)` per finished pass: rule, period, unit, checked, changed, skipped, deleted, anonymised; no person. Saves are `update`, the yes `approve`, same type.
- `pg_privacy_retention_run()` deletes with `pg_audit_retention_delete_query()`: `privacy_rule` lines and erasures (`erase` on `person`) stay at least 3 years (`record_years`) whatever `audit_log_retention_years` says. The Audit Trail card says so.

## Tests?
- `tests/privacy_retention_test.php`: bounds, input, role, each skip, entries, the cart field set, candidates, due and night, the audit entry, the keep.

Related: [[privacy-log-retention]], [[personal-data-erasure]], [[privacy-inventory]]
