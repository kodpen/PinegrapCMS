---
name: customer-value-steps
description: Plan customer-value, steps CV1 to CV4: the summary tables and the job's rebuild, the Customers report screen, the group from a cell with the CSV and the API, close-out
---

## Which steps are there?
- CV1 (M) The summary and its rebuild, server and data only. After the owner's yes on tables.
  - A migration step adds the two tables, listed in `get_tables()`; the draft PR's body carries their columns for the architect. The first fill is the job's, not the step's.
  - Existing files: `job.php` (one guarded call), `functions.php` (the module), `includes/fn/contacts.php` (`pg_contact_holders()`) and `includes/fn/privacy.php` (`pg_privacy_erase_tables()`) for the contact column.
  - New: `includes/fn/customer_values.php` (what counts, the key, a customer's row worked out from their orders, the month rows, the pages, the cursor kept as a settings text, the claim, the weekly rebuild), `tests/customer_values_test.php`.
  - Tests, red first: a cancelled and a refunded order; a marketplace order left out; two guest orders with one e-mail as one customer; a rebuild giving the same rows twice; an erased contact gone after it; `tests/contact_holders_test.php` green.
  - Features: U1 to U7.

- CV2 (M) after CV1: the Customers report.
  - Existing files: `includes/fn/output.php` (one link in the reports menu).
  - New: the root screen `view_customer_report.php` on the report page's look (`ui/report-view.md`), its figures functions in the module.
  - Tests: each tile against a hand-counted set of orders; the cohort table; the cells' bounds at their edges; a reader without the contacts right seeing no name.
  - Pictures: the screen with data, while the first build runs, with commerce data too small to say anything, a phone. To the owner; not marked ready before his look.
  - Features: U8 to U12, U15.

- CV3 (S) after CV2: the group from a cell, the CSV and the API.
  - Existing files: the report rows of the API schema; the group editor is opened with its existing rules, not changed.
  - Tests: the rules a cell hands over; the CSV refused without the contacts right; the route's scopes; `php tools/check_api_schema.php`.
  - Features: U12 (the CSV), U13, U14.

- CV4 (S) close-out: every U number checked against the code with its PR; `commerce/customer-value-report.md`; this plan's three files deleted and its line in `plans/more-features.md` removed.

## Which steps wait for each other?
- Each waits for the one before it.
