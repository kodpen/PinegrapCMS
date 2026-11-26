---
name: customer-rhythm-report
description: Customer rhythm as read: Customers Report's Rhythm part (stages, came back after a reminder, list by stage and CSV, products), the contact card's Order rhythm card, the settings form, who sees names
module: customer_rhythm_report
---

## Where is it?
- `view_customer_report.php?part=rhythm`: the toolbar's Value / Rhythm switch, shown once `pg_customer_rhythm_ready()`. Same gate as the report (`validate_ecommerce_report_access()`, commerce on). Built by `pg_customer_rhythm_report_html()`.
- Before the first pass: how far the pass is, or that it comes after the next job run. "As of" is the summary's `at`.

## What does the Rhythm part show?
- Stages: customers and expected 12-month value per stage, read from the table (`pg_customer_rhythm_stage_totals()`, GROUP BY `stage`), so they match the list; stage 0 ("Not yet known") only when there are any. A stage's pill filters the list.
- Came back after a reminder (H13): per reminder month, newest first, 12 months: reminders, came back, return rate, value; the held-back group's columns when the share is on or a month has one (`pg_customer_rhythm_return_rows()`).
- The list: list table driver, list `customer_rhythm` (`pg_customer_rhythm_list_source()`), filtered by `?stage=`, sorted by expected value by default; keyset paging by `customer_rhythm.id`.
- Products with a rhythm: the 50 with most repeat purchases from the summary's `products`, days per unit, pairs, customers, own or from its group.

## Who sees names?
- Only a reader for whom `validate_contacts_access($user, true)` holds: the source then joins `contacts` (by `contact_id`) and `orders` (by `last_order_id`); the name is the contact's, else the billing name, else the company (`pg_customer_rhythm_name()`), searchable. Others get no name column and no join.
- "Export customers" (`?csv=rhythm&stage=`) only for name readers, 403 otherwise, as the top customers CSV. Columns `pg_customer_rhythm_csv_head()`; dates Y-m-d, value in major units; read in batches of 1,000 by id.

## How is "came back" counted? (H13)
- The nightly pass: an existing row with `reminded_at` and no `came_back_order_id` gets the first own (not marketplace) order placed after the reminder and within 14 days (`pg_customer_rhythm_came_back()`). Written by `pg_customer_rhythm_came_back_query()` (only where still 0) before the tell step, so a new reminder in the same pass clears it afterwards.
- Counted in the reminder's month (UTC 'YYYY-MM', as `reminders`) into the summary's `returns`: [came back, value, held back came back, held back value], 24 months (`pg_customer_rhythm_returns_merge()`). The last two weeks' reminders are still open.

## What does the contact card show? (H15)
- `edit_contact.php`, card "Order rhythm" under Journey (`pg_customer_rhythm_contact_card()`): for role < 3 or a commerce or commerce reports right, after the first pass. The row is the contact's e-mail key, else its contact key, else the latest row naming the contact.
- Stage, days since the last order, own interval (the store's median until a second order on another day: two orders under a day apart make no gap, fixed by the customer rhythm hunt PR), next due day, chance, expected 12 months, and the products due now, worked out live from that key's counting orders (at most 1,000) with the summary's product rhythms, today's stock and the lead days.

## Where are the settings? (H15)
- Card "Rhythm settings" at the end of the Rhythm part, Manager and above (`pg_customer_rhythm_may_change()`); posts `rhythm_settings` to the report. Writes config_text `customer_rhythm_settings` (`pg_customer_rhythm_settings_encode()`): `on`, `brake_days` 1-90, `lead_days` 0-30, `share`. An unchecked switch is off.

## Tests?
- `tests/customer_rhythm_report_test.php`.

Related: docs/memory/commerce/customer-rhythm-events.md, docs/memory/commerce/customer-rhythm-store.md, docs/memory/commerce/customer-value-report-screen.md, docs/memory/api/api-customer-rhythm-report.md, docs/memory/commerce/customer-rhythm-group-rule.md
