---
name: gift-card-code-in-site-log
description: Site Log lines name a gift card by its last four characters (pg_gift_card_log_code()), never the full spendable code.
module: core
---

## Why is the code masked in the Site Log?
- A gift card code is its money: whoever reads it can spend the balance. The Site Log is read by more than the gift card screens: the `site_log:read` API scope (an app with no gift card scope), the dashboard activity feed and the user screen's Recent activity.
- So a log line names the card as `pg_gift_card_log_code()` gives it: `****-****-****-WXYZ` (`****` for a code of four characters or fewer). The full code stays on the gift card's own screen and in the creation notice.

## Which lines use it?
- `add_gift_card.php` (created), `edit_gift_card.php` (deleted, modified).
- Still writing the full `output_gift_card_code()`: `includes/fn/order_completion.php` (card created by an order, auto campaign line), `includes/api/resources/gift_cards.php` (API create, update, delete), `includes/fn/gift_card_list_edit.php` (quick edit). Move them to the masker when those files are free.

## What is it like?
- Store credit's audit label already masks the same way (`pg_credit_audit_label()`, `store_credit.php`).
- Test: `tests/gift_card_log_code_test.php`.
