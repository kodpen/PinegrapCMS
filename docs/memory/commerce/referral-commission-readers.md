---
name: referral-commission-readers
description: Every reader and writer of the commissions table, and what each does with a referral's row (a commission row under a referral code, status paid, reward given as store credit)
---

## What is a referral's row?
- A `commissions` row whose `affiliate_code` is a referral code (the referrer's contact and a signature, never a 5-character affiliate code), `order_id` the friend's order, `amount` the reward in minor units, `status` `paid` when the credit is issued; `recurring_commission_profile_id` 0. One function tells it from an affiliate's row by the code; no new column.

## Which places write commissions?
- `pg_order_complete_commission()` (`includes/fn/order_completion.php`): only for an approved affiliate's code (`pg_order_affiliate_rate()` is null for any other code), so it never writes a referral's row; the referral's call sits beside it.
- `pg_order_cancel_commissions()` / `pg_order_restore_commissions()` (`includes/fn/ecommerce.php`): touch only `pending`/`payable` rows. A referral's row is `paid`, so they leave it alone; the referral's own cancel and restore calls set it `ineligible` and back, with the credit.
- `update_recurring_commissions()`: writes from recurring profiles only; a referral has none.
- `pg_commission_status_save()` (`includes/fn/commission_list.php`): the one hand path, used by Edit Commission and `POST /commissions/{id}`; it refuses a referral's row, so `commission.status_changed` is never announced for one.

## Which places read them, and what do they show?
- Commissions screen (`view_commissions.php`, list source `pg_commission_list_source()`): lists the row; the affiliate join finds no contact, so the name column is empty; the row gets a "Referral" mark. The status totals sum every row, so a reward counts in Paid.
- Edit Commission (`edit_commission.php`): opens the row; its status cannot be saved.
- External API `GET /commissions` (`includes/api/resources/commissions.php`): returns the row as it is, `affiliate_code` being the referral code; `POST` is refused through the save above.
- My Account's Affiliate part (`get_my_account.php`, `templates/my_account*.php`): reads rows by the approved affiliate's own code only; a referrer is never an affiliate, so referral rows never show there.
- `generate_commission_reference_code()` (`includes/fn/forms.php`): the row takes a reference code like any commission.
- Table lists only: `includes/fn/innodb.php`, `install/index.php` `get_tables()`, the API scopes and console, the device switch's read scope, help links.
- Webhook `commission.status_changed`: never for a referral (above).

## What do personal data export and erasure do?
- Neither reads `commissions` today. The export lists the person's store credit cards (`gift_cards.user_id`), so a referrer sees each reward card with its `Referral` kind; the friend's order is exported as an order.
- Erasure anonymises the friend's order and the referrer's store credit cards and contact; the row keeps only the code, the order id and the amount, as an affiliate's commission does.

## What carries the code besides commissions?
- The visit (`$_SESSION['software']['affiliate_code']`, the marketing cookie) and `orders.affiliate_code` through `pg_order_submit_affiliate_code()`: stored as text and shown as text on the order screens and lists; these are not commission readers and take a referral code as they take any code.
