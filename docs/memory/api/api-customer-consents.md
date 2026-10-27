---
name: api-customer-consents
description: GET /customers/{id}/consents (the consent_log rows of a customer and its accounts) and the customer.consent_changed webhook announced by pg_consent_log_record(); what is left out and why
---

## What does GET /customers/{id}/consents answer?
- Route `customers.consents`, scope `customers:read`, handler `api_customers_consents()` (`includes/api/resources/customers.php`), object `Consent` (`api_consent_schema()`): id, channel, purpose, status, source, created_at, created_at_unix.
- Rows of `consent_log` whose `contact_id` is the customer or whose `user_id` is an account with `user.user_contact` = the customer, newest first, cursor paged by row id (as the wishlist route). The latest row of a channel and purpose is the answer that stands; the route does not fold them.
- Left out: cookie notice answers (a visitor's browser, not the customer) and the row's ip and user_agent (kept on the site as evidence, not handed to applications).
- Row rule: `api_customer_reachable_or_fail()` like every customers route (`tests/api_customers_test.php` counts them). No `consent_log` table → empty list.
- The plan called it `/contacts/{id}/consents`; the API names contacts "customers", so it sits beside `/customers/{id}/wishlist`.

## When is customer.consent_changed sent?
- `pg_consent_log_record()` (`includes/fn/privacy.php`) calls `pg_announce_consent_changed()` (`includes/fn/events.php`) after every row it writes, so every writer (sign-up, account, unsubscribe, checkout, forms, double opt-in, the İYS pull) announces without its own line.
- Payload: `id` (the customer) and `consent` (id, channel, purpose, status, source, created_at). A row kept under the account alone names the account's `user_contact`.
- Quiet (`pg_consent_changed_event()` returns null, tested in `tests/customer_consents_test.php`): cookie answers, `pending` rows (a double opt-in waiting for its confirmation), source `import` (bulk path, `api-webhook-events.md`), and a row with no customer record.

Related: docs/memory/core/consent-log-writers.md, docs/memory/api/api-webhook-events.md, docs/memory/platform/iys-client.md
