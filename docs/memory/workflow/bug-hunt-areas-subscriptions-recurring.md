---
name: bug-hunt-areas-subscriptions-recurring
description: The bug hunt of subscriptions and recurring orders (area 33, 2026-11-24): renewals, retries, pause and cancel dates, price changes, card tokens, mails; what it fixed, what was read clean
---

## Which area was hunted?
- Area 33, store-run subscriptions: `includes/fn/subscriptions.php`, `subscription_renewal.php`, `subscriptions_account.php`, `subscriptions_operator.php`, `subscription_card.php`, `subscription_action.php`, the Stripe and iyzico `charge_renewal` / `update_card` / `subscription_cancel`, the checkout hook in `submit_order.php` and the payment callback.
- Looked at: a renewal charged twice (two cron runs, a retry, a webhook), the 1/3/7 ladder and its end, pause, resume and cancel dates, a price change mid-period, the card token, the mails, amounts in minor units.
- Rules checked against: `commerce/subscriptions*.md`, `workflow/decisions-made-product-5.md` and `-6.md`.
