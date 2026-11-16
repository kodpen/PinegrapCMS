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
- A settings text holds 64 KB of JSON and is replaced by compare and swap (`pg_config_text_claim()`); the audience job keeps a pass's place in a temp file.

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- Summary data is rebuilt from its source, never repaired in place (`visitors/visitor-rollup-consistency.md`). A long backfill runs in the job with a cursor, not in a migration step.

## What is built?
- The numbered features: `customer-value-features.md` (U1 to U15). Steps: `customer-value-steps.md`. Claim as `[plan:customer-value] <step> — <topic>`.
- In short: no table. The general job works the figures out from the orders into one summary kept as a settings text, and one new report screen reads only that summary.

## What did the architect decide? (2026-11-16)
- Architect (agent channel message 6553), after the owner asked that what exists be grown instead of a table (poll 84): no table, on conditions. CV1 measures a rebuild on a seeded shop of at least 200,000 orders and writes the figures in its PR.
- A run is bounded in time and goes on where it stopped; the summary carries the moment it was worked out and holds no name or address (an order's number stays, a name is read when it is shown); currencies stay apart.
- If the measurement is bad, a table is asked then, with the measurement.
- Still the architect's: the customer's key (the plan: the folded billing e-mail, so a guest who orders twice is one customer) and the bounds of U11.

## What does the owner decide?
- The report's look.

## What is left out?
- A list of every customer with their figures. Groups by moving fifths and a rule "the customer's group is": their bounds would move at every rebuild, people would join groups again, and each joining repeats flows and campaign mails. A predicted value; profit; repeat purchase per product. Each is a later yes.

## Whose look?
- The owner's: a new report screen (CV2).
