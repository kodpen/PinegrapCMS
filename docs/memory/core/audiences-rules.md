---
name: audiences-rules
description: Contact groups filled by rules: the 11.0.155 columns, the condition catalogue, the one rule shape, the pure SQL builder pg_audience_sql() and what each condition family reads
module: audiences
---

## Which columns hold it? (11.0.155, decided in #5033)
- `contact_groups.kind` ENUM('static','dynamic') default `static`, `rules` TEXT NULL (JSON), `computed_at` and `compute_cursor` INT UNSIGNED (the recompute's state).
- `contacts_contact_groups_xref.source` ENUM('hand','form','product','rule') default `hand`, INDEX `idx_group_source (contact_group_id, source)`. Every row written before the step is `hand`; a recompute removes only `rule` rows.
- The decision named `users_contact_groups_xref`; that table is which staff may use a group (`user_id`), so the column went on the contact membership table the decision meant.
- Step `upgrade_2026_11_0_contact_group_rules()`; the xref is in `install_heavy_tables()` and its column is added in place where the server can. Readers ask `pg_audience_ready()` first.

## What is a rule set?
- `{match: 'all'|'any', conditions: [...]}`; a condition is `{key, op, value[, field][, days]}` or, one level deep only, a nested `{match, conditions}`.
- `pg_audience_rules_clean($rules)` (array or JSON) gives that one shape: unknown keys, operators, contact fields, consents and subscription states dropped; ids and numbers whole and in bounds; days 1-3650; text trimmed to 100 characters; at most 20 conditions a level; an emptied nested set dropped. Cleaning a clean set changes nothing.
- `pg_audience_conditions()` is the catalogue (label, family, operators, value kind, table read).

## How is it turned into SQL?
- `pg_audience_sql($rules, $now)` is pure: `array('sql' => 'SELECT contacts.id FROM contacts WHERE …', 'params' => …)` for `db_q()`. Each condition is one predicate on `contacts.id`: a column test or `IN` / `NOT IN` a sub-query; `all` joins with AND, `any` with OR. Every value is a `?`; a contact field is a name from the list, never the given text. No condition selects nobody (`WHERE 1 = 0`).
- `pg_audience_count($rules)` runs a COUNT over it; null before the upgrade or on a failed query.

## What does each family read?
- Contact: country and city match the business or home column; lead source and the listed fields take is, is not, contains (`pg_list_like_pattern()`), empty.
- Consent: `email_opt_in` is `contacts.opt_in = 1` with an address; `consent` (`email|sms|call:marketing`, `contract:kvkk|preinfo|contract`) is the latest non-pending `consent_log` row of the contact being `granted`. A rule only selects; sends still check consent and İYS per contact (`platform/sms-and-iys-rules.md`).
- Membership: `member_id` set; active, expired and "ends within n days" read `expiration_date` against the day of `$now` (UTC), as the contact list views.
- Orders count only `status <> 'incomplete'`. "At most n" and "exactly 0" are NOT IN the contacts above n, so a contact with no order counts; "at least 0" is everybody; total is `SUM(orders.total)` in minor units. "Left a cart": an incomplete order with lines changed within the days. Product and product group through `order_items` and `products_groups_xref`.
- Returns: `order_returns.requested_at` through the order. Subscriptions: `subscriptions.status` through `user.user_contact`.
- Forms: `forms.complete = 1` by page; a form answer is `form_data` of one field, equal or containing.
- Visits: only through the order a visit made (`visitors.order_id`), always within a window on the indexed `start_timestamp` (landing page and UTM take a required `days`), never a per-contact scan (`visitors/high-volume-counter-rules.md`).
- Groups: in or not in another group's xref rows (any source).

## Tests?
- `tests/audiences_rules_test.php`: every key and operator compiles, nesting, nobody for no condition, hostile text only in parameters, bounds, the step.

Related: docs/memory/core/contact-holders.md, docs/memory/core/consent-log-writers.md
