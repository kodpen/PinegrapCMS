---
name: order-completion-account
description: pg_order_auto_register() (A3b, #3035): an order's auto-registration shared by the checkout and the payment message; the receipt names the account its completion hands it, not the session's
module: order_completion
---

## What does it do?
- `pg_order_auto_register($email, $contact_id)` in `includes/fn/order_completion.php`: the account whose `user_email` is the billing e-mail (`existing`: the order is linked, nothing is created), else a new one without a password (algo 3) with a set password link issued and its contact in the registration contact group (`new`). A blocked address gets none and a log line; the order still goes through. Returns type, user id, username, and `account` (`email_address`, `user_id`) for the receipt.
- What it does is `pg_order_auto_register_action()` (pure): `existing`, `blocked`, `new` or `''` (no address).
- Session-free: no sign-in, no device cookie, no `$_SESSION` (`tests/order_completion_account_test.php`).

## Who calls it?
- `submit_order()` when the page's switch is on and the visitor is a guest or a staff member ordering for the customer. Around it the checkout keeps the session's part: for a new account it keeps the account (with `ghost`) in `$_SESSION['software']['auto_registration']` for the receipt page and, when not ghosting, signs the visitor in (`pg_session_sign_in()`) and binds the device cookie.
- `pg_order_complete_from_payment()` when the order is a guest's and the recorded page's switch is on (#2984): links the existing account or opens one; nobody is signed in. Before #3035 it only linked an existing account.
- Both pass the result as `$context['new_account']` (the account array, `array()` for none) and `$context['auto_registration']` (the type) to `pg_order_complete()`.

## How does the receipt get the link?
- `pg_order_complete_receipt()` sets `pg_order_receipt_new_account($account)` around each copy it draws and clears it after; `pg_order_new_account_email()`, `pg_order_new_account_link()` and `pg_order_new_account_drawn_for_customer()` read that entry, and only when none is set the session's (the receipt page after the redirect). The customer's copy carries the set password link on both paths; the store's copy names the account only.
- Before #3035 the renderers read only the session, so a receipt e-mailed by the payment message could not carry the link.

## What is not covered?
- A visitor's checkout and the payment message completing the same order at the same moment can both run auto-registration before the lock; the second finds the account by e-mail unless both insert at once. Not run against a database here.

Related: commerce/order-completion-from-payment.md, commerce/order-completion-before-lock.md, workflow/decisions-made-product-2.md
