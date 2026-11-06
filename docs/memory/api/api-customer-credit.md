---
name: api-customer-credit
description: Store credit over the API: GET/POST /customers/{id}/credit and void under gift_cards:*, the account pick, credit.* events, export and erasure of a customer's cards
module: store_credit_events
---

## Which routes are there?
- `includes/api/resources/customer_credit.php`: `GET /customers/{id}/credit` (`CustomerCredit`: customer_id, account_ids, balance, cards), `POST /customers/{id}/credit` (issue, 201) and `POST /customers/{id}/credit/{card_id}/void`, both with `dry_run`. MCP tools come from the same rows.
- A card (`StoreCreditCard`) is read as My Account reads it (`pg_credit_account_cards()`: state, last day) plus account_id, issued_by, created_at; the code is never answered (a bound card pays only for its customer signed in). `GET /gift-cards/{id}` still shows the card with its redemptions.
- Cards listed: the last 50 of each linked account (`pg_credit_staff_cards()`), newest first; balance is `pg_credit_balance()` summed over the accounts.

## Why gift_cards:* and not customers:*?
- The credit is money: the staff card on `edit_user.php` asks for commerce rights (`pg_credit_staff_allowed()`), which is the gift cards scope's ceiling. The customers row rule (`api_customer_reachable_or_fail()`) applies on top, 404 outside.

## Which account gets an issued card?
- Credit belongs to a site account (`user.user_contact` = the record). `api_customer_credit_pick_account()` (pure): the only account, or `account_id` when there are several (422 without it or for someone else's); no account is 409 `customer_has_no_account`.
- Issue is kind `manual`, issued_by the application's owner, reason required (255), `last_day` Y-m-d through `pg_credit_last_day_expires()` (shared with the staff form: the card stops at the start of the next day), amount 1..100000000 minor units as the staff form. 503 `service_unavailable` before `pg_credit_ready()`.
- Void needs a reason, a card of the record's accounts (404 otherwise) with a balance; a zero balance or an order spending meanwhile is 409 `credit_not_open`. A void is not announced.

## Can DELETE /gift-cards/{id} remove a store credit card?
- No (#6783): `api_gift_cards_delete()` refuses a card with `user_id` > 0 (`api_gift_card_is_store_credit()`) with 409 `store_credit_not_deletable`, naming the void route, as the gift card screen (#6688): a delete drops the credit unaudited. Test: `tests/api_gift_card_store_credit_delete_test.php`.

## What does POST /gift-cards/{id} do to a balance?
- Writes it only over the balance it read (`api_gift_card_update_sql()`), as the screen's `balance_was` guard; a spend meanwhile is 409 `balance_changed`. A changed store credit balance gets the screen's `pg_audit('update', 'store_credit')` line (`api_gift_card_credit_audit()`). Test: `tests/api_gift_card_update_guard_test.php`.

## Which events?
- `includes/fn/store_credit_events.php`: `pg_credit_announce()` reads the card and its account's contact and calls `pg_announce()` with `pg_credit_event_data()` (pure): id, account_id, customer_id, kind, reason, amount, balance, expires_at, never the code; unbound cards are never announced.
- `credit.issued` from `pg_credit_issue()` (every kind: points, refund, staff, API); `credit.used` from `pg_order_complete_gift_cards()` per bound card redeemed (order_id, amount_used); `credit.expired` from `pg_credit_expire_card()` (expired_amount). A cancelled order giving credit back is not announced.

## What about personal data?
- Export: the privacy export already takes cards by `user_id` (code cut to its end).
- Erasure: entry `store_credit` (table `gift_cards`, found by `user_ids`, `user_id` → 0); `_pg_privacy_erase_apply_store_credit()` voids every card with a balance through `pg_credit_void()` ("Personal data erased", audit `revoke`) before clearing the account. Runs before the `user` row goes.

## Tests?
- `tests/api_customer_credit_test.php`: account pick, card presenter, last day, routes and stop order, events and payload, erasure order; `tests/privacy_erase_test.php` pins the entry.
