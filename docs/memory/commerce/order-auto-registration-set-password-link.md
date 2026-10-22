---
name: order-auto-registration-set-password-link
description: An account an order's auto-registration opens gets the set password link instead of its password; the receipt e-mail is split so the store's BCC copy never carries the link.
---

## What changed for orders?
- `submit_order.php` used to keep the random password in `$_SESSION['software']['auto_registration']['password']` and print it in the receipt e-mail (plain text and HTML), on the receipt and view order screens and in old layouts. The receipt e-mail was BCC'd to `ECOMMERCE_EMAIL_ADDRESS` and to each product's `order_receipt_bcc_email_address`, so the store got the customer's password too. Fixed in PR #1220.
- The account now gets a random password nobody is told, `pg_set_password_link_issue()` issues the link (mechanism: `core/auto-registration-set-password-link.md`), and the session keeps `email_address`, `user_id` and `ghost`.

## How is the receipt e-mail split?
- `pg_order_receipt_copies($customer, $bcc, $new_account)`: without a new account, one e-mail to the customer with the BCC, as before. With one, the customer's copy alone, then one copy per BCC address as `to` (no shared recipient list).
- Each copy is drawn with `pg_order_receipt_audience('customer'|'store')` set; it goes back to `'screen'` after the draw.

## Who sees the link?
- `pg_order_new_account_for_customer($audience, $ghost)`: the customer's e-mail always; the receipt page of the session that ordered, unless a staff member placed the order for the customer (ghosting; that session is not the customer's); the store's copy never.
- Text: `pg_order_new_account_block($email, $url, $for_customer, $html)` in `get_order_receipt.php` (system layout) and `get_order_receipt_in_plain_text.php`; anyone but the customer reads "A new account was created with this order: <email>".
- `get_order_receipt.php` and `get_view_order_screen_content.php` pass `$auto_registration_set_password_url` and `$auto_registration_for_customer` to the layout; `templates/order_receipt.php` shows a "Set your password" button. `$auto_registration_password` carries `pg_cf_new_account_password_note()` like the form layouts, so the untouchable `data/backups` layouts stay harmless.
- Helpers live in `includes/fn/auth.php`. Tests: `tests/auto_registration_order_test.php`.
