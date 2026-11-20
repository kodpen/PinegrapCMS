---
name: referral-rules
description: Referral programme rules (RF1): the signed code, the checks of a friend's order, the reward as a paid commissions row and referral store credit, the month's cap under a lock, take-back, settings
module: referral
---

## What is a referral code?
- `R` + the referrer's contact id in base 36 + `-` + ten base-36 characters of an HMAC with `ENCRYPTION_KEY` (`pg_referral_code_make()`, `pg_referral_code_contact()`); no table, none without a site key. Shape `PG_REFERRAL_CODE_PATTERN`; an affiliate code (5 characters) never has it.
- It rides the affiliate link (`?a=`): `init_tracking()` keeps it for the visit and the marketing cookie; with visitor tracking off `pg_referral_arrival()` (`get_page.php`) keeps it for the visit only. The order's `affiliate_code` carries it.
- On arrival the friend's offer (`referral_offer_id`) goes into `$_SESSION['ecommerce']['special_offer_code']` when it runs today and no offer code is there, as an affiliate's group offer does.

## Which settings does it read?
- `config_text` rows (`pg_referral_settings()`, card in RF2): `referral_on` ('1'), `referral_offer_id`, `referral_reward` (minor units; 0 keeps the programme off), `referral_reward_days` (0 = never expires), `referral_smallest` (minor units), `referral_month_cap` (0 = no limit). Off: nothing changes.

## When is a reward given?
- At completion, beside `pg_order_complete_commission()`, only when the order earned no affiliate commission: `pg_referral_order_completed()`.
- The checks are one pure function, `pg_referral_check()`, first failure wins: off, code (bad signature), affiliate (an approved affiliate holds the code), referrer (no account, an approved affiliate, or no order complete/exported), self (same account, contact, or folded e-mail: lower case, no +tag, Gmail dots), friend (an earlier complete/exported order by account, else billing e-mail), smallest, month.
- The order's value: `pg_referral_order_value()`: total + gift card discount − surcharge − everything refunded (`pg_referral_refunded_total()`: the larger of the `order_refunds` sum and `refunded_amount`). An order of 0 never counts.
- The month's room: every check and write runs under `GET_LOCK('pg_referral_<contact id>')`, so two orders at once cannot pass the cap or reward one order twice. Every referral row of the code since the month's first moment counts, a taken-back one too, so a restore never goes over the cap.
- Then `pg_credit_issue()` kind `referral` (named "Referral"), reason "Referral: order #N", notes' first line `Referral reward of order <id>` (`pg_referral_card_marker()`, how the take-back finds the card), and the row `paid`.

## When is it taken back?
- `pg_referral_take_back()`: one guarded `UPDATE … status = 'ineligible', status_before_cancel = 'paid' WHERE status = 'paid'`, so a second cancel or refund takes nothing; then the reward comes off the order's marked cards newest first (`pg_referral_take_back_plan()`, `pg_credit_reduce()`); what was spent is noted on the card and in the log.
- Cancel (a full refund included): `process_order_cancellation()` → `pg_referral_order_cancelled()`, the whole reward.
- Refund: `pg_return_refund()` and the order screen's refund → `pg_referral_order_refunded()`.
- Architect (2026-11-16, agent channel message 7257): a part refund is option (c): the value worked out again less all refunds; under `referral_smallest` as it reads then, or nothing left, the whole reward is taken back once, else nothing. A refund never gives one back. Reason: #6306's shape; one item sent back does not cost the reward, an order emptied by refunds does.
- Restore: `pg_order_restore_cancelled()` → `pg_referral_order_restored()`: row back to `paid` and a new card of the whole reward, unless what is left of the order is under the smallest order.

## What is not covered?
- A restore gives the whole reward again even when part of it had been spent before the cancel. Before the 11.0.245 step runs (`pg_referral_ready()`), nothing is written.

## Tests?
- `tests/referral_test.php`.
