---
name: order-auto-registration-set-password-link
description: An account an order's auto-registration opens has no password and gets the set password link; the receipt e-mail is split so the store's BCC copy never carries the link (PR #1220).
---

## What changed for orders?
- `submit_order.php` used to keep the random password in `$_SESSION['software']['auto_registration']['password']` and print it in the receipt e-mail (plain text and HTML), on the receipt and view order screens and in old layouts. The receipt e-mail was BCC'd to `ECOMMERCE_EMAIL_ADDRESS` and to each product's `order_receipt_bcc_email_address`, so the store got the customer's password too. A staff member ordering for a customer saw it on the receipt page. Fixed in PR #1220.
- The account is created without a password (algo 3, empty hash), like the form's, so the signed-in customer's change password screen asks for no current password. `pg_set_password_link_issue()` issues the link (mechanism: `core/auto-registration-set-password-link.md`); the session keeps `email_address`, `user_id` and `ghost`.

## How is the receipt e-mail split?
- `pg_order_receipt_copies($customer, $bcc, $new_account)`: without a new account, one e-mail to the customer with the BCC, as before. With one, the customer's copy alone, then one copy per BCC address as `to` (no shared recipient list).
- Each copy is drawn with `pg_order_receipt_audience('customer'|'store')` set; it goes back to `'screen'` after the draw.
- The customer's copy of an order that opened an account is sent with `'secret' => true`, so a failed delivery keeps the link out of the activity log and the failure notice.

## Who sees the link?
- `pg_order_new_account_for_customer($audience, $ghost)`: the customer's e-mail copy (legacy receipt renderers and layouts); the receipt page of the session that ordered, unless a staff member placed the order for the customer (ghosting; that session is not the customer's); the store's copy never.
- Text: `pg_order_new_account_block($email, $url, $for_customer, $html)` in `get_order_receipt.php` (system layout) and `get_order_receipt_in_plain_text.php`; anyone but the customer reads "A new account was created with this order: <email>".
- `get_order_receipt.php` and `get_view_order_screen_content.php` pass `$auto_registration_set_password_url` and `$auto_registration_for_customer` to the layout; `templates/order_receipt.php` shows a "Set your password" button. `$auto_registration_password` carries `pg_cf_new_account_password_note()` like the form layouts, so the untouchable `data/backups` layouts stay harmless.
- Helpers live in `includes/fn/auth.php`. Tests: `tests/auto_registration_order_test.php`.

## What does not show the account yet?
- Receipt pages and e-mails built in the visual editor draw the order with the order view widget (`includes/fn/widgets.php`), which has no new-account block or token. The shipped design templates set `order_receipt_email_page_id` to such a page, so on those sites the link is issued but never shown; the customer reaches the account through "Forgot password". Not a regression: no password was shown there before.
- Closing it is a separate change: `__new_account_email` / `__new_account_set_password_url` tokens and `has_new_account` / `has_set_password_link` flags (the form item view's names, `designer/token-dictionary.md`) on the order view widget, gated by `pg_order_new_account_drawn_for_customer()`, with designer labels and `check_bindings`.