---
name: customer-value-steps
description: Plan customer-value, steps CV1 to CV4: the measured rebuild and the one summary, the Customers report screen, the group from a cell with the CSV and the API, close-out
---

## Which steps are there?
- CV1 (M) The summary and its rebuild, server only. No schema. Done in #9995 (`commerce/customer-value-summary.md`).
  - First the measurement: a whole rebuild on a seeded shop of at least 200,000 orders, its times and the summary's size written in the PR body. With a bad figure the step stops and the architect is asked for a table, with the measurement.
  - Existing files: `job.php` (one guarded call), `functions.php` (the module).
  - New: `includes/fn/customer_values.php` (what counts, the key, the runs and their place, the claim, the summary and its one write), `tests/customer_values_test.php`.
  - Tests, red first: a cancelled and a refunded order; a marketplace order left out; two guest orders with one e-mail as one customer; a rebuild in several runs giving the same summary as in one; two currencies kept apart; no name, address or e-mail in the summary.
  - Features: U1 to U7.

- CV2 (M) after CV1: the Customers report. Done in #10020 (`commerce/customer-value-report-screen.md`); owner's look: poll 111.
  - Existing files: `includes/fn/output.php` (one link in the reports menu).
  - New: the root screen `view_customer_report.php` on the report page's look (`ui/report-view.md`), its figures functions in the module.
  - Tests: each tile against a hand-counted set of orders; the cohort table; the cells' bounds at their edges; a reader without the contacts right seeing no name.
  - Pictures: the screen with data, while the first rebuild runs, with commerce data too small to say anything, a phone. To the owner; not marked ready before his look.
  - Features: U8 to U12, U15.

- CV3 (S) after CV2: the group from a cell, the CSV and the API.
  - Existing files: the report rows of the API schema; the group editor is opened with its existing rules, not changed.
  - Tests: the rules a cell hands over; the CSV refused without the contacts right; the route's scopes; `php tools/check_api_schema.php`.
  - Features: U12 (the CSV), U13, U14.
  - Under way (#PR).

- CV4 (S) close-out: every U number checked against the code with its PR; `commerce/customer-value-report.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps wait for each other?
- Each waits for the one before it.
