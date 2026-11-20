---
name: referral-commission-readers
description: Every reader and writer of commissions and what each does with a referral's row: Commissions screen totals (affiliates only), Edit Commission read-only, GET /commissions kind
module: commission_list
---

## What is a referral's row?
- A `commissions` row whose `affiliate_code` is a referral code (`commerce/referral-rules.md`), `order_id` the friend's order, `amount` the reward in minor units, `status` `paid` when the credit is issued, `ineligible` once taken back; `recurring_commission_profile_id` 0. `pg_referral_commission_kind()` tells it from an affiliate's row by the code's shape; `pg_referral_row_sql()` is its SQL twin. No new column.

## Which places write commissions?
- `pg_order_complete_commission()`: only for an approved affiliate's code; the referral's call (`pg_referral_order_completed()`) sits beside it and runs only when the order earned no affiliate commission.
- `pg_order_cancel_commissions()` / `pg_order_restore_commissions()`: touch only `pending`/`payable` rows, so never a referral's (`paid`). The referral's own cancel, refund and restore calls move it (`referral-rules.md`).
- `update_recurring_commissions()`: recurring profiles only.
- `pg_commission_status_save()` (Edit Commission and `POST /commissions/{id}`): returns false for a referral's row, so `commission.status_changed` is never announced for one.

## What do the screens and the API show?
- Commissions screen (`view_commissions.php`): lists the row with a "Referral" mark beside its code (`pg_commission_list_referral_badge()`); the name column is empty. The status totals (Pending, Payable, Ineligible, Paid) count affiliates' rows only.
- Edit Commission: a referral's row shows its status as text and one sentence ("its order sets the status"), no Save button; a posted save is refused.
- `GET /commissions`: field `kind` (`affiliate` or `referral`), read-only, after `affiliate_code`; `POST /commissions/{id}` answers a validation error on `status` for a referral's row.
- My Account's Affiliate part: reads rows by an approved affiliate's own code only; a referrer is never an affiliate, so referral rows never show there.
- `generate_commission_reference_code()`: the row takes a reference code like any commission.
- Table lists only: `includes/fn/innodb.php`, `install/index.php` `get_tables()`, the API scopes and console, the device switch's read scope, help links.

## What do personal data export and erasure do?
- Neither reads `commissions`. The export lists the person's store credit cards (`gift_cards.user_id`), so a referrer sees each reward card with its `Referral` kind; the friend's order is exported as an order.
- Erasure anonymises the friend's order and the referrer's store credit cards and contact; the row keeps only the code, the order id and the amount, as an affiliate's commission does.

## What did the architect decide? (2026-11-16)
- Architect (2026-11-16, agent channel message 7257): the Commissions screen's status totals count affiliates' rows only. A reward is store credit, not money paid out to an affiliate; mixed into Paid it would misstate what was paid. The rows stay listed with the "Referral" mark; their sums are the Referrals report's (RF4).
- Architect (2026-11-16, agent channel message 7257): `GET /commissions` gets a read-only field `kind` from the same one function from RF1 on, so an API reader can tell the rows apart from the first day.
- Architect (2026-11-16, agent channel message 7257): Edit Commission shows a referral's row read-only with one sentence saying the order sets it, instead of a form that refuses on save.
- Part refunds: `referral-rules.md` (same message).

## What carries the code besides commissions?
- The visit (`$_SESSION['software']['affiliate_code']`, the marketing cookie) and `orders.affiliate_code` through `pg_order_submit_affiliate_code()`: stored and shown as text; not commission readers.
