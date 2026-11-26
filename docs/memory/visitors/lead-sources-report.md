---
name: lead-sources-report
description: First and last touch of a lead or customer (LJ5): the contact_journey rows kind 3 and 4, the four save paths, once per contact, and the Lead sources report under Visitors with its Excel
module: lead_touch
---

## When does a contact become a lead or a customer?
- Lead (`contact_journey.kind` 3, `PG_LEAD_JOURNEY_LEAD`): its first complete form or its first booking. Customer (kind 4, `PG_LEAD_JOURNEY_CUSTOMER`): its first complete or exported order or its first paid sales invoice (`pg_lead_touch_act_kind()`).
- `pg_lead_became()` checks and writes under the contact's lock for the kind (`pg_lead_became_lock_name()`, `busy` after 5 s, #11806).
- `pg_lead_became($act, $contact_id, $ref_id)` writes the row only when `pg_lead_touch_verdict()` says `mark`. Refused in this order: `not_an_act`, `no_contact`, `panel_user` (the contact's user has panel work), `already` (a row of that kind exists), `not_first` (an earlier complete form, a booking requested, confirmed, no-show or done (`pg_lead_touch_booking_statuses()`; a hold or a cancelled one is none, #11806), a complete or exported order or a paid invoice, other than this one), `no_visit`.
- The row: `at` = now, `ref_id` = the form, booking, order or invoice, `detail` = the act, `first_visit_id` and `last_visit_id` = the earliest and latest `lead_visits` of the contact started at or before now (`pg_lead_touch_pick()`, same second: the higher id is later). No tied visit, no row: only marketing-cookie browsers count.
- The journey card shows no row for kinds 3 and 4 (`pg_lead_journey_row_kind()` gives null): the form or order is its row.

## Where is it called?
- `custom_form.php` and `submit_order.php` right after `pg_lead_tie()`, so the visits tied back are the touches.
- A booking: `pg_booking_confirm()` with source `site` or `link`, through `pg_lead_became_booking()` (the account's `user_contact`; a guest booking has no contact). Panel and API bookings make no lead.
- A paid invoice: `erp_invoice_refresh_paid()` (`includes/erp/settlement.php`), where `erp.invoice.paid` is announced, through `pg_lead_became_invoice()` (sales invoices only, the account's `erp_accounts.contact_id`).

## What does the Lead sources report show?
- `lead_sources.php`, Visitors menu after "Create Visitor Report" while `pg_lead_ready()`; gate `validate_visitors_access()`. Help row `visitors#lead-sources`.
- Tools row: month stepper, "Last 12 months", the period, "First touch" / "Last touch", "Excel" quiet at the right. Figures: Leads, Customers (note: the share), Revenue. "Group by" Channel / Campaign / Landing page above the table frame. Head line: the figures count only visitors who accepted marketing cookies.
- Rows by the touch visit's channel (the visitor reports' names), campaign ("No campaign") or landing page; a trimmed visit is its own group (`gone`), "Not known" under every grouping, Campaign too (`pg_lead_sources_group()`, most leads first, #11806). Each contact's earliest row of a kind counts once.
- "Became customers" = the period's leads whose contact has a customer row at or after the lead row (the same second counts), as a share of the leads; "–" with no lead. The API reads the same figures.
- A customer row is judged at read (`pg_lead_customer_read()`): a cancelled or fully refunded first order is no customer and no revenue; the next counting order takes the row over, in its own period. Details: `lead-sources-customer-rule.md`.
- Revenue only with commerce on and the order reports right: the customer's counting order total less refunds (`pg_customer_value_order_amount()`, minor units); a customer by paid invoice adds none (its currency may differ from the shop's).
- Excel: `pg_xlsx_write_plain()`, the table as shown, revenue as a money cell.

## What is not done here?
- `GET /reports/lead-sources` (LJ6, #11315): `api/api-lead-journey.md`. A contact merge moves both contacts' rows to the survivor, so it may hold two of a kind; the report counts the earliest (#11806).

Tests: `tests/lead_touch_test.php`. Related: docs/memory/visitors/lead-journey-view.md
