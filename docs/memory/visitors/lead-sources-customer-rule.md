---
name: lead-sources-customer-rule
description: Who the Lead sources report counts as a lead that became a customer, and what a cancelled or refunded first order does (architect, 2026-11-26, request 3081)
module: lead_touch
---

## When did a lead become a customer?
- Architect (2026-11-26, agent channel request 3081): a contact who bought first and sent a form later is a lead of the period, not one that became a customer (#11806).
- Built in #11806: `pg_lead_sources_read()` counts a customer row of the contact at or after the lead row (the same second counts). `GET /reports/lead-sources` reads the same figures.

## What does a cancelled or refunded first order do?
- Architect (2026-11-26, agent channel request 3081): a customer row by order whose order `pg_customer_value_order_counts()` does not count (cancelled, refunded in full) is no customer and brings no revenue; a partial refund keeps it with `pg_customer_value_order_amount()`. The row is judged at read, never deleted; the next counting order takes it over. A paid-invoice row stays as it is.
- Built in the follow-up to #11806: `pg_lead_customer_read()` (pure) judges each row; "next" is the contact's earliest counting order after the row's own by `order_date`, then id. A taken-over row counts in that order's period, with that order's amount; its touch visits stay those the row was written with.
- A lead "became a customer" when a judged customer row of the contact (any of them, a merge may leave two) is at or after the lead row.
- An undone cancel brings both back; an order row that is gone (deleted, purged) is read as written, with no revenue. Without commerce every row is read as written.
- The reader reads a row before the period only when its own order does not count and a counting order of the contact falls in the period (`pg_product_insights_counted_sql()`), so it never scans every old customer row.

Related: docs/memory/visitors/lead-sources-report.md, docs/memory/commerce/customer-value-report.md
