---
name: api-subscriptions-list
description: GET /subscriptions and /subscriptions/{id} (customers:read): every store-run subscription with its customer, the filters, updated_since on subscriptions.updated_at, the user-role row rule
---

## What does GET /subscriptions answer?
- Routes `subscriptions.list` and `subscriptions.get` (`includes/api/resources/subscriptions.php`), scope `customers:read`, the `Subscription` object of `GET /customers/{id}/subscriptions` with three fields more, on both routes: `customer_id` (the account's `user_contact`, null when the account is no contact), `customer_name` and `customer_email` from the first order (the account's e-mail when the order has none). The columns come from `api_customer_subscriptions_select_sql()` (`resources/customers.php`), which now joins `orders` and `user`; never `card_ref`, `remote_id` or `driver`.
- `customer_name`, `customer_email` and `card_last4` are order billing and payment data: `api_subscription_present($row, $order_data)` gives them only when `api_subscription_order_data_shown(api_current_scopes())`, i.e. the key holds `orders:read` beside `customers:read` (the effective scopes already carry the owner's e-commerce ceiling); else null, `customer_id` stays. Before this a `customers:read` key (a contacts-only owner too) read them although the subscriptions screen is an e-commerce screen; the same shape as the event reservations fix (#7197).
- Filters: `status` (the stored statuses, whitelisted, not escaped), `customer_id`, `product_id`, `order_id`, `updated_since`. Cursor on (`updated_at`, id) ascending, like `GET /commissions`, so a sync walks forward without repeats. `updated_at` moves on every writer: the create, a status move, a renewal outcome, a card change in the renewal. Legacy gateway profiles are not listed; no table → empty list.
- The moves stay on the customer routes (`api-customer-subscriptions.md`).

## Which rows does the user role (3) reach?
- `api_subscription_owner_filter($owner)`: '' below role 3, else `pg_contact_owner_sql($owner['id'], 'u.user_contact')`: the subscriptions of the customers in the owner's contact groups; an account that is no contact is outside; `GET /subscriptions/{id}` answers 404 outside, as the customers routes do.

## Tested?
- `tests/api_subscriptions_test.php`: the row rule, the filters, the customer fields and their nullability, the select, the routes. Not run against a database.
