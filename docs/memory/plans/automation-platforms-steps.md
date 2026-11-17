---
name: automation-platforms-steps
description: Plan automation-platforms, steps AU1 to AU10: the contract, newest-first lists, the subscription limit, event fields and samples in three parts, the OAuth scheme, the panel sheet and build, close-out
---

## Which steps are there?
- AU1 (S) The contract.
  - New: the list as a pure function, `tests/automation_contract_test.php`, memory `api/automation-contract.md`. No route and no behaviour changes.
  - The PR body: each candidate of A3 with its event, record route and list route, and what a candidate left out lacks.
  - Features: A1, A2, A3.

- AU2 (M) after AU1: newest first on the lists the contract names.
  - Existing files: the route rows (`includes/api/schema.php`, the modules' route files) and each list's handler. Only `/orders` has a `sort` today; `/forms/{id}/submissions` says oldest first.
  - Tests, red first: the order and the cursor of each list as a pure function; the default order unchanged.
  - Features: A4.

- AU3 (S, asked first) The subscription limit.
  - `api_webhooks_create()` (`includes/api/resources/webhooks.php`) refuses the eleventh. The number goes into one function; the architect is asked for it before the claim.
  - Features: A5.

- AU4 (M) Event fields and samples, part 1: the way and the shop's events.
  - Existing files: `api_webhook_events()` (`includes/api/outbound/webhooks.php`), `includes/api/resources/webhooks.php`, the route rows.
  - New: fields and sample beside the catalogue; `GET /webhooks/events`; `event` on `POST /webhooks/{id}/test`; the test that reads the announcing code (a literal array by the tokenizer, a payload function by its own test).
  - Events: orders, returns, marketplace orders, cart quotes, bookings, subscriptions, credits, commissions, products, stock.
  - Features: A6, A7, A8.

- AU5 (M) after AU4: part 2, the rest of the core's events (customers, users, contact groups, pages, files, forms, offers, zones, currencies, campaigns, mail, WhatsApp, experiments).

- AU6 (M) after AU4: part 3, the modules' events through their own seam: the ERP's, the Workspace's. Cut as AU6a and AU6b when over 1,000 product lines, said before the claim.
  - After AU6 the test of AU4 refuses an event without fields and a sample.

- AU7 (S) The API document names the OAuth server.
  - `api_openapi_security_schemes()` (`includes/api/openapi.php`); `php tools/check_api_schema.php` green; a test for the scheme with and without the OAuth application.
  - Features: A9.

- AU8 (S, the sheet) The panel's new parts, drawn before they are built.
  - One sheet at 1440, 1024 and 390, light and dark, the longest Turkish labels: New application with the starting point, the sheet with the three values, the Events tab with "New subscription" open and its event list searched, "Send a sample".
  - Posted in the agent channel; the architect brings it to the owner as one question.
  - Features: A10, A11, A12, A13 as drawn.

- AU9 (M) after AU8's yes, AU3 and AU4: the panel.
  - Existing files: `api_settings.php` (New application, the Events tab, the webhook card, the guides), `includes/api/resources/webhooks.php`: the checks of `api_webhooks_create()` move into one function both callers use.
  - Tests: the starting point ticks the contract's scopes; the form's refusals are the route's; a locked row shows neither the form nor the button.
  - Features: A10, A11, A12, A13.

- AU10 (S) after AU5, AU6 and AU9: the events section of both documentation pages shows fields and sample (`includes/api/console_view.php`); the lasting rules into `api/`. The plan's files stay for AX1 to AX4.

## What waits for what?
- Waiting for nothing: AU1, AU4, AU7, AU8, and AU3 once its number is answered. AU2 waits for AU1; AU5 and AU6 for AU4; AU9 for AU8's yes, AU3 and AU4; AU10 for AU5, AU6 and AU9.
- Two steps that edit the same file are not open together: AU4 to AU6 and AU9 on `includes/api/resources/webhooks.php`; AU10 and plan `api-clean-modern`'s AM12 on `includes/api/console_view.php`.
- Suggested beginning: AU1, AU4 and AU8 side by side.
