---
name: customer-value
description: Plan (owner 2026-11-16, agent channel poll 82): a Customers report of repeat purchase, value per customer, cohorts and customer groups, read from a summary the general job rebuilds; steps CV1 to CV4
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): lifetime value, repeat purchase rate, RFM groups, cohorts.

## What is there today, and what is reused?
- An order carries its contact, its user and the billing e-mail; a guest's order can make a new contact each time, and nothing matches by e-mail. `total` and `refunded_amount` are in the base currency; a marketplace order keeps its own unit.
- The Order Report can be summarized by Billing Email and has ready reports (`pg_ready_reports()`, `commerce/order-report-ready-strip.md`); who may open commerce reports is `validate_ecommerce_report_access()`. A report page has one look (`ui/report-view.md`).
- "An order" means three things today: contact group rules count everything that is not a cart, cancelled ones too; the "stops ordering" flow start counts complete and exported; the Order Report leaves cancelled out.
- Contact groups filled by rules already know the last order's age, the number of orders and their total (`core/audiences-rules.md`); the win-back flow start finds customers who stopped ordering.
- No single place sees an order completed, cancelled and refunded: the refund amount has three writers and one of them announces nothing.

## What binds it?
- Architect (2026-11-16, agent channel message 6460): it only reads and adds no write to the order path; heavy figures come from a summary the general job fills page by page, as rule-filled groups are recomputed (`core/audiences-recompute.md`); currencies are never mixed; the cancel and refund rule sits in one function.
- Summary data is rebuilt from its source, never repaired in place (`visitors/visitor-rollup-consistency.md`). A long backfill runs in the job with a cursor, not in a migration step.

## What is built?
- The numbered features: `customer-value-features.md` (U1 to U15). Steps: `customer-value-steps.md`. Claim as `[plan:customer-value] <step> — <topic>`.
- In short: two summary tables the general job rebuilds from the orders, and one new report screen that reads only them.

## What does the architect decide?
- The customer's key: the folded billing e-mail (the plan; a guest who orders twice is one customer) against the contact. The bounds of U11. The tables' columns, in CV1's draft PR.

## What does the owner decide?
- The two tables (asked with the set's other tables). The report's look.

## What is left out?
- Groups by moving fifths and a rule "the customer's group is": their bounds would move at every rebuild, people would join groups again, and each joining repeats flows and campaign mails. A predicted value; profit; repeat purchase per product. Each is a later yes.

## Whose look?
- The owner's: a new report screen (CV2).
