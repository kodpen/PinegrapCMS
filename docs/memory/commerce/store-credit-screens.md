---
name: store-credit-screens
description: Store credit screens (2026.11.0): the staff card on edit_user.php (issue, void, rights) and the customer's My Account section (built-in templates, designer widget section store_credit)
module: store_credit_staff, store_credit_account
---

## Where do staff see a customer's store credit?
- `edit_user.php`, the Store Credit card (`#store_credit`) under the main form (it is its own form; forms cannot nest): the balance (`pg_credit_balance()`), the last 50 cards bound to the user newest first (`pg_credit_staff_cards()`: kind, reason, amount, balance, last day, Open / Voided / Used / Expired by `pg_credit_staff_card_state()`, a link to `edit_gift_card.php`), and the issue form. Drawn only when `pg_credit_ready()`.
- Money on it: `pg_credit_staff_money()` (`pg_money_text()`, then `h()`); until #8391 it read `&#8378;0,00` (`core/currency-symbol-as-text.md`).
- Who: the person who may open this user (the screen's role check) and has commerce rights, `pg_credit_staff_allowed()` (role < 3 or `manage_ecommerce`, as the gift card screens). Without them the card is not drawn and a post is refused.

## How is credit issued?
- Post `pg_credit_action=issue` → `pg_credit_staff_post()`: `pg_credit_staff_issue_input()` (pure) reads the amount like `pg_parse_amount()` into minor units with `round()` (above 0, at most 1,000,000.00), the optional last day as `Y-m-d` (the card stops at the start of the next day, so `expiration_date` is that day; today or later), a reason (required, 255 characters). Then `pg_credit_issue()` kind `manual`, issued by the staff member (audit `create`), one activity line.

## How is a card voided?
- Each open card has a reason box and Void: post `pg_credit_action=void` with `credit_card_id`; the card must be this user's (the id comes from the page) and a reason is required; `pg_credit_void()` writes the balance 0 over the one read (an order spending it meanwhile makes the void fail with "may have been used meanwhile"), a note line and the audit `revoke`; one activity line.
- The answer, the kept values, when Void asks and the Voided state: `staff-store-credit-card.md`.

## What does the customer see on My Account?
- `pg_credit_account_view($user_id, $view_order_url, $now)` (`includes/fn/store_credit_account.php`): the balance, the cards bound to them (`pg_credit_staff_cards()`, newest first, 50) with source (`pg_credit_kinds()`), reason, amount, balance, last day (the earlier of `expires_at` and a staff `expiration_date`), Open / Used / Expired, and the orders that spent each card (`applied_gift_cards` with an amount, the customer's own complete, exported or cancelled orders, linked to the view order page); and the points conversion form (`pg_credit_account_convert()`, `store-credit-points.md`). Money and dates are plain text: escape them.
- Shown only when there is a card or points enough to convert (`pg_credit_account_shown()`). The reason is shown to the customer; the staff issue form says so.
- Built-in `my_account.php` and `my_account_system.php`: a "Store credit" section before the order history (`$store_credit`); the conversion form moved there from the Account block. `$reward_points_credit` is still passed for custom layouts written before.
- Designer my_account widget: section `store_credit` (shop sites) by `pg_credit_account_section_html()` (Bootstrap 5.3.8) and flag `has_store_credit`; the JS starter has its heading and section after My Bookings. A conversion posts to `convert_reward_points.php` and comes back to the page; the widget's `my_account` Messages node prints the notice.

## What about mails and expiry?
- `store-credit-mails.md`: `credit_issued` / `credit_expiring` and the job that closes ended cards.

## Tests?
- `tests/store_credit_staff_test.php`: amount, last day (end of the day, today valid, impossible dates), reason, card state, rights, money.
- `tests/store_credit_account_test.php`: uses grouped by card, the card list (state, last day, staff date), when the section shows.
