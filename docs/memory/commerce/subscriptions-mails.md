---
name: subscriptions-mails
description: Subscription e-mails (decided in #3288): renewed / failed / cancelled campaign profiles, which event mails which, once per event, the fields, the signed card update page, step 11.0.129
module: subscription_renewal
---

## Which event sends which mail?
- `pg_subscription_mail_action()`: `renewed` → `subscription_renewed`; `renewal_failed` and `confirm_needed` (Stripe `authentication_required`) → `subscription_failed`; a failure that ends the ladder, `status_cancelled`, and `status_ended` not after a cancel → `subscription_cancelled`. `price_notice` → `subscription_price_changed` (`subscriptions-price-notice.md`). Pending, unclear and other moves send nothing.
- Sent by `pg_subscription_mail_send()` through `create_auto_email_campaigns()`, like `order_delivered`: every enabled profile of the action makes an automatic campaign to the first order's billing address (contact opt-out for commercial profiles as usual). No profile → no mail.
- Called from `pg_subscription_renewal_run_row()` (also the refused path) and `pg_subscription_renewal_confirmed()` through `$io['mail']`, after the lock is let go, and from `pg_subscription_set_status()`. A throw is logged, never stops the renewal.

## How is it once per event?
- Only an event that was written mails: the caller passes the event id, 0 → nothing. A webhook delivered twice is refused by `uk_subscription_event_remote` (`INSERT IGNORE` → 0), and a cron run moves the row before the event, so a second run writes no second event.

## Which fields does a profile get?
- `pg_subscription_mail_fields()`, always all six (empty when unknown, so `[[...||...]]` takes its else part): `^^subscription_product^^` (the first order line's name), `^^subscription_amount^^` (site number format + currency code), `^^subscription_next_date^^` (type date, `%%format%%` works; empty while held), `^^subscription_card_url^^` (failed only), `^^subscription_confirm_url^^` (confirm_needed only: the event's link), `^^order_number^^` (the renewal order).
- The profile form lists them under the action (`pg_email_campaign_profile_subscription_fields()`, `#subscription_fields_row`); the four actions (with `subscription_price_changed`, #3520) are commerce actions (after the action, `action_item_id` 0); the list also names the two price-change fields. The two links are in `pg_mail_merge_address_hrefs()`'s defaults, so the Link dialog's `http://` comes off.

## What is the card update link?
- `subscription_card.php?s=<id>&t=<time>&k=<hmac>`: `pg_subscription_card_token()` = HMAC-SHA256 of `subscription-card:<id>:<t>` with `ENCRYPTION_KEY` (no key → no link). Open 30 days (`pg_subscription_card_link_ttl()`, past the 1/3/7 ladder), for an active, past_due or paused row (`pg_subscription_card_link_state()`).
- GET shows a button only (mail scanners open links); the POST with the form token calls the driver's `update_card` and redirects (303) to its https page. No sign-in: the link is the customer's; it only opens the PSP's page for that customer.

## Why the step 11.0.129?
- `create_auto_email_campaigns()` copies the profile's action into `email_campaigns.action`; 11.0.128 widened only `email_campaign_profiles.action`, so a campaign would be stored with action `''` (the 11.0.20 → 11.0.42 trap). `upgrade_2026_11_0_subscription_campaigns()` reads the enum, appends the missing values at the end, keeps the null rule and default, skips when all are there. A fresh install runs the steps, so it gets them the same way.

## Tested?
- `tests/subscription_mails_test.php` (step run twice on an in-memory schema, the action copied, form, link fields), `tests/subscriptions_renewal_test.php` (mapping, fields, link state, one mail per event). Not run on a dev database or with a live service.

Related: commerce/subscriptions-renewal.md, commerce/shipment-mail-fields.md, plans/subscriptions-iyzico-stripe.md
