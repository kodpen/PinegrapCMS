---
name: subscriptions-price-notice
description: Subscription price change (decided in #3311, PR #3495): price_notice event 14 days ahead, subscription_price_changed mail, applied from the first renewal on or after its date, step 11.0.132
module: subscription_renewal
---

## When is a price change told?
- `pg_subscription_price_notices_run()` runs at the end of `pg_subscription_renewals_run()` (daily job): every active or past_due row with a next charge, read in batches of 200 with the first order's line, `products.price` and the latest `price_notice` amount (`pg_subscription_price_notice_sql()`).
- New amount: `pg_subscription_price_amount()` = product price × line quantity + the line's `tax_total` scaled to the new price (the rate it was charged at). Compared with the amount last told (the latest notice, else `subscriptions.amount`): an increase and a decrease alike; the same amount is never told twice. A price set back before its date is told again (the old amount), which cancels the pending one.
- Effective date (`pg_subscription_price_effective_at()`): `next_charge_at` when at least 14 days away (`pg_subscription_price_notice_days()`), else the next date of the series after it (one period later).
- Written under the renewal's lock and checked again inside it: event kind `price_notice`, `amount` = the new charge amount (minor units, tax included), `detail` = the date `Y-m-d`. No column.
- A line sold below the product's price (a first-order discount) is told the product's price on the first run: the rule compares the product's price, as decided.

## How is it applied?
- `pg_subscription_renewal_run_row()` reads the latest notice (`$io['price_notice']`); `pg_subscription_price_due()` gives its amount once `date(now) >= detail` and while the row charges another. That amount is charged and written to `subscriptions.amount` with any outcome of the charge (a retry or Stripe's later paid report then sees the same amount).
- The renewal order's line moves with it: `pg_subscription_renewal_figures()` scales the unit price by amount / (price × quantity + tax_total) when the first line's `tax_total` is known and differs; the tax keeps the line's rate.

## Which mail?
- `pg_subscription_mail_action('price_notice')` → `subscription_price_changed`, sent by `pg_subscription_mail_send()` like the other subscription mails (no profile → no mail). Fields: the six of `pg_subscription_mail_fields()` plus `^^subscription_new_amount^^` and `^^subscription_new_date^^` (type date) from `pg_subscription_price_mail_fields()`.
- Step 11.0.132 `upgrade_2026_11_0_subscription_price_notice()` appends the action to `email_campaign_profiles.action` and `email_campaigns.action` (read first, appended at the end, null rule and default kept, skipped when present).
- The profile form offers it as a commerce action beside the other three (#3520): `action_item_id` 0, the fields row lists all eight fields (the two price ones marked "price change"), `backend.src.js` opens the row for it. The Campaign Profiles list names it.

## Where is it shown?
- `pg_subscription_price_pending()`: the latest notice whose amount is not the row's. My Account: "New amount from <date>: <amount>"; operator row page: fact "New amount"; both event lists label `price_notice` "Price change notice".

## Tested?
- `tests/subscriptions_renewal_test.php` (amount, 14 days, one period later, decrease, once, applied on/after the date, line figures, mail action and fields), `tests/subscription_mails_test.php` (step twice on an in-memory schema, label, the form offers the action and lists its eight fields, saved with `action_item_id` 0). Not run on a database.

Related: commerce/subscriptions-renewal.md, commerce/subscriptions-mails.md, plans/subscriptions-iyzico-stripe.md
