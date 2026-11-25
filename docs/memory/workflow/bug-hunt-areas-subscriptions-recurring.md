---
name: bug-hunt-areas-subscriptions-recurring
description: The bug hunt of subscriptions and recurring orders (area 33, 2026-11-24): renewals, retries, pause and cancel dates, price changes, card tokens, mails; what it fixed, what was read clean
---

## Which area was hunted?
- Area 33, store-run subscriptions: `includes/fn/subscriptions.php`, `subscription_renewal.php`, `subscriptions_account.php`, `subscriptions_operator.php`, `subscription_card.php`, `subscription_action.php`, the Stripe and iyzico `charge_renewal` / `update_card` / `subscription_cancel`, the checkout hook in `submit_order.php` and the payment callback.
- Looked at: a renewal charged twice (two cron runs, a retry, a webhook), the 1/3/7 ladder and its end, pause, resume and cancel dates, a price change mid-period, the card token, the mails, amounts in minor units.
- Rules checked against: `commerce/subscriptions*.md`, `workflow/decisions-made-product-5.md` and `-6.md`.

## What did the hunt's PR fix? (#11422)
- No subscription when Stripe's message completed the order before the visitor's return: now made from the message's card or from the late return, under one lock (`commerce/subscriptions-completion-paths.md`).
- A renewal payment confirmed after the row was cancelled was dropped silently: now `unrecorded`, logged and alerted (same file).

## Which issues did it open?
- #11426: a row resumed after its charge date is charged at once and again a few days later (the series date). Needs a product decision on what resume does to a passed date.
- #11427: a row held for a confirmation or a pending charge never ends when no outcome comes. Needs a decision on how long it waits.

## What was read clean?
- Double charge: the per-row `GET_LOCK` in the run, the moves and the message; the row read again inside it; the conditional save; Stripe's idempotency key; iyzico's curl errors answered unclear, never failed.
- The 1/3/7 ladder and its end, Number of Payments, the series dates (month end, DST), a later-start line, the price notice's 14 days and its amount after a retry, the renewal order's figures in minor units, the mail and webhook fields, the signed card link.

## What was left, unverified?
- Cancelling an iyzico subscription deletes its card token; `card_shared` looks only at the same order. If iyzico gives one card wallet per buyer e-mail across orders, cancelling one order's subscription would break another's renewals. Not checked without an iyzico sandbox.
- A renewal save that fails on a database error after a paid charge leaves the row due; no fix without a failing case.

## What was not run?
- No database, no Stripe or iyzico sandbox.
