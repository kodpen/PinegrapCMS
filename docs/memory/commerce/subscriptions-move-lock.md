---
name: subscriptions-move-lock
description: Why a pause, resume or cancel of a store-run subscription (My Account, operator screen, API) waits for the renewal job's row lock, and what it answers while a charge runs
module: subscriptions_account
---

## Which lock does a move take?
- `pg_subscription_store_move()` (`includes/fn/subscriptions_account.php`), the one path of the customer's, the operator's and the API's moves, takes `GET_LOCK('pg_subscription_renewal_<id>', 10)` through `$io['lock']` before `set_status`, and lets it go after the card is forgotten (`try`/`finally`). It is the lock `pg_subscription_renewal_run_row()` and `pg_subscription_renewal_confirmed()` hold around a charge.
- No lock within 10 seconds: the move is not written and answers as a move lost to a concurrent one (My Account and the operator: "refused"; API: 409 `subscription_move_refused`).

## What went wrong without it?
- The renewal reads the due row inside its lock, then calls `charge_renewal` at the payment service. A pause or cancel written during that call changed the status, so the renewal's save (`WHERE status = ? AND next_charge_at = ?`) was lost: the money was taken and the order written, but `next_charge_at` stayed on the date just paid.
- Paused, then resumed: the row was due at once and charged a second time for the same period. Cancelled: the customer was charged after cancelling while the card was being forgotten at the service, and the row ended at once instead of running to the paid date.
- After the lock, `set_status` reads the row the renewal left (next date moved, or past_due after a failure), so the move's status check sees the outcome.

## Tested?
- `tests/subscriptions_account_test.php`: the lock is taken before the status write and let go after the card call; a lock not obtained writes nothing. Not run against a database.

Related: commerce/subscriptions-account.md, commerce/subscriptions-renewal.md, commerce/subscriptions-operator-screen.md, api/api-customer-subscriptions.md
