---
name: customer-value-report
description: Customers Report (customer value: repeat purchase, value per customer, cohorts, customer groups) as built: its parts, the decisions that bind it, what was left out, each feature U1-U15 with its PR
module: customer_values
---

## What is it, and where is each part?
- A Customers Report of repeat purchase, value per customer, cohorts and customer groups (owner, 2026-11-16, agent channel poll 82). Built in #9995, #10020, #10297; closed out 2026-11-19.
- The summary and its rebuild in the general job: `customer-value-summary.md`. The screen `view_customer_report.php`: `customer-value-report-screen.md`. The group from a cell, the CSV, `GET /reports/customer-value`: `customer-value-groups-csv-api.md`.

## Which decisions bind it?
- Architect (2026-11-16): no table; the general job works the figures out from the orders into one summary kept as a settings text, rebuilt whole, never repaired in place; the owner had asked to grow what exists instead of adding a table (agent channel message 6553, poll 84).
- Architect (2026-11-16): a run is bounded in time and goes on where it stopped; the summary carries the moment it was worked out, holds no name, address or e-mail (an order number stays, the name is read when shown), and keeps currencies apart.
- Architect (2026-11-16): a table is asked for only with a bad measurement; #9995 measured 1.0 s and 4.1 KB at 200,000 orders, so none is needed.
- Architect (2026-11-16): read-only, no write on the order path; the refund and cancel rule lives in one function (`pg_customer_value_order_counts()`) that every figure uses.
- Architect (2026-11-16): the customer's key is the folded billing e-mail, else the contact; nothing stores it.
- Architect (2026-11-16): group names over the cells, bounds stated beside each name, every cell exactly one name: New, one order, the last within 90 days; Returning, two to three orders, the last within 180 days; Loyal, four and more, the last within 180 days; At risk, the last 181 to 365 days ago, or one order 91 to 180 days ago; Lost, over 365 days (`pg_customer_value_cell_group()`).
- Owner: the screen's look (poll 111).

## What was left out?
- A list of every customer with their figures; groups by moving fifths and a rule "the customer's group is" (their bounds move at each rebuild, so people would join groups again and flows and campaign mails would repeat); a predicted value; profit; repeat purchase per product. Each needs a later yes.

## Which features are built, with which PR?
- U1 counting rule (complete or exported, not cancelled, total less refunds, other-unit marketplace orders apart), U2 key, U3 one rule for every figure, U4 one summary as a settings text, U5 rebuild in bounded runs and one write, U7 no person in the summary: #9995.
- U6 progress before the first rebuild, "As of" after it: #9995 (pass record) and #10020 (screen).
- U8 tiles, U9 months chart and table, U10 cohorts, U11 groups with fixed bounds, U15 access (`validate_ecommerce_report_access()`, commerce on, Reports menu): #10020.
- U12 top customers of all time and of the last 12 months, names only with the contacts right: #10020; the CSV: #10297; the 12-month list counts only that period's orders: #10357.
- U13 "Make a contact group" from a cell, existing rules, the size note: #10297. U14 `GET /reports/customer-value` under `orders:read`, names with `customers:read`: #10297.
- Every U number was checked against the code at close-out; none is open.
