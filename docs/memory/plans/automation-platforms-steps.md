---
name: automation-platforms-steps
description: Plan automation-platforms, steps AU1 to AU7: the contract and its test, newest-first lists, the subscription limit of 50, event fields and samples in three parts, the OAuth scheme
---

## Which steps are there?
- AU1 (S) The contract. Done, PR #10127.
  - New: the list as a pure function, `tests/automation_contract_test.php`, memory `api/automation-contract.md`. No route and no behaviour changes.
  - The test also refuses a row whose route is marked `moves_money` (`api/api-plugin-scopes.md`), so the starting point of A10 never ticks such a scope.
  - The PR body: each candidate of A3 with its event, record route and list route, and what a candidate left out lacks.
  - Features: A1, A2, A3.

- AU2 (M) after AU1: newest first on the lists the contract names.
  - Existing files: the route rows (`includes/api/schema.php`, the modules' route files) and each list's handler. Only `/orders` has a `sort` today; `/forms/{id}/submissions` says oldest first.
  - Tests, red first: the order and the cursor of each list as a pure function; the default order unchanged.
  - Features: A4.

- AU3 (S) The subscription limit: 50 for each application (architect 2026-11-17, agent channel message 7594, a technical bound). Done: PR #10128.
  - `api_webhooks_create()` (`includes/api/resources/webhooks.php`) refuses the eleventh today. The number goes into one function and the 409's text takes it from there.
  - The PR body says how many rows one run of the dispatcher sends, so that 50 subscribers of one event are known to drain.
  - Features: A5.

- AU4 (M) Event fields and samples, part 1: the way and the shop's events. Done, PR #10149.
  - Existing files: `api_webhook_events()` (`includes/api/outbound/webhooks.php`), `includes/api/resources/webhooks.php`, the route rows.
  - New: fields and sample beside the catalogue; `GET /webhooks/events`; `event` on `POST /webhooks/{id}/test`; the test that reads the announcing code (a literal array by the tokenizer, a payload function by its own test).
  - A test that the router reads `/webhooks/events` as that route and not as `/webhooks/{id}`.
  - Events: orders, returns, marketplace orders, cart quotes, bookings, subscriptions, credits, commissions, products, stock.
  - Features: A6, A7, A8.

- AU5 (M) after AU4: part 2, the rest of the core's events (customers, users, contact groups, pages, files, forms, offers, zones, currencies, campaigns, mail, WhatsApp, experiments). Done, PR #10170.

- AU6 (M) after AU4: part 3, the modules' events through their own seam: the ERP's, the Workspace's. Cut as AU6a and AU6b when over 1,000 product lines, said before the claim. Done, PR #10184.
  - After AU6 the test of AU4 refuses an event without fields and a sample.

- AU7 (S) The API document names the OAuth server. Done, PR #10129.
  - `api_openapi_security_schemes()` (`includes/api/openapi.php`); `php tools/check_api_schema.php` green; a test for the scheme with and without the OAuth application.
  - Features: A9.

## Where are the other steps?
- The panel's steps AU8 to AU10 and what waits for what: `automation-platforms-steps-2.md`. The apps, AX1 to AX4: `automation-platforms-steps-3.md`.
