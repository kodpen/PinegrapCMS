---
name: store-credit-screens
description: Store credit on the customer's screen (edit_user.php, 2026.11.0): the Store Credit card, who sees it, issue (amount, reason, last day), void with a reason, what is logged
module: store_credit_staff
---

## Where do staff see a customer's store credit?
- `edit_user.php`, the Store Credit card (`#store_credit`) under the main form (it is its own form; forms cannot nest): the balance (`pg_credit_balance()`), the last 50 cards bound to the user newest first (`pg_credit_staff_cards()`: kind, reason, amount, balance, last day, Open / Used / Expired by `pg_credit_staff_card_state()`, a link to `edit_gift_card.php`), and the issue form. Drawn only when `pg_credit_ready()`.
- Who: the person who may open this user (the screen's role check) and has commerce rights, `pg_credit_staff_allowed()` (role < 3 or `manage_ecommerce`, as the gift card screens). Without them the card is not drawn and a post is refused.

## How is credit issued?
- Post `pg_credit_action=issue` → `pg_credit_staff_post()`: `pg_credit_staff_issue_input()` (pure) reads the amount like `pg_parse_amount()` into minor units with `round()` (above 0, at most 1,000,000.00), the optional last day as `Y-m-d` (the card stops at the start of the next day, so `expiration_date` is that day; today or later), a reason (required, 255 characters). Then `pg_credit_issue()` kind `manual`, issued by the staff member (audit `create`), one activity line.

## How is a card voided?
- Each open card has a reason box and Void: post `pg_credit_action=void` with `credit_card_id`; the card must be this user's (the id comes from the page) and a reason is required; `pg_credit_void()` writes the balance 0 over the one read (an order spending it meanwhile makes the void fail with "may have been used meanwhile"), a note line and the audit `revoke`; one activity line.
- The answer is a notice or an error of the `edit_user` liveform, back at `#store_credit`.

## What is left for the next steps?
- My Account (built-in and designer widget), the `credit_issued` / `credit_expiring` mails and the expiry job: `plans/store-credit-steps-2.md` (SC5b, SC5c).

## Tests?
- `tests/store_credit_staff_test.php`: amount, last day (end of the day, today valid, impossible dates), reason, card state, rights.
