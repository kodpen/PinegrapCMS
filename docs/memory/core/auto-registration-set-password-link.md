---
name: auto-registration-set-password-link
description: A form's auto-registered account gets a signed single-use set password link instead of its password; who sees it, and what $auto_registration_password carries now.
---

## Why is the password no longer written anywhere?
- `custom_form.php` (auto_registration = 1) used to keep the random password in `$_SESSION['software']['custom_form_auto_registration'][$form_id]['password']` and print it in the sender's e-mail, the administrator's copy (same request, so the session was full), watchers' HTML copies, the confirmation page and old layouts. Reported on kodpen.com, 2026-10-21. Fixed in PR #1172.
- The account now gets a random password nobody is told (`bin2hex(random_bytes(16))`); the session entry holds only `email_address` and `user_id`.

## How is the link made and checked?
- `includes/fn/auth.php`: `pg_set_password_link_issue($user_id)` writes `user.token = sha256(T)` and `user.token_timestamp = now`, the columns `forgot_password.php` uses, so `set_password.php` and the set password widget accept it unchanged. No schema change.
- `T = pg_set_password_link_token()` = HMAC-SHA256 of `set-password:<user_id>:<issued_at>` under `ENCRYPTION_KEY`. Derived, not random, so a later request (the confirmation page) redraws the same link from the user id alone; no copy of `T` is stored. No key: no link.
- `pg_set_password_link($user_id)` redraws `T`, shows the link only while `hash_equals(user.token, sha256(T))` and `pg_set_password_link_live()` (the same 24 hours as `set_password.php`). Setting the password clears the token and a forgot password request replaces it, so each link works once.
- Address: `pg_sign_in_screen_url('set password', 'set_password.php')`, else the panel's `set_password.php` (draws its own form on a GET); language prefix of the request via `pg_tr_rewrite_url()`; host `URL_SCHEME . HOSTNAME_SETTING` (`platform/hostname-in-mail-links.md`).

## Who sees the link?
- Only the person who sent the form: their plain-text copy (`pg_cf_new_account_plain_text(…, true)`), their HTML copy, and their confirmation page.
- `pg_cf_new_account_for_submitter()` is false while `pg_tr_suspend()` holds the request (the administrator's HTML copy; keep that wrapper in `custom_form.php`) and while `pg_cf_new_account_recipient(false)` marks a watcher's HTML copy. The administrator's plain text says only "A new account was created with this submission: <email>".
- Helpers in `includes/fn/custom_form.php`: `pg_cf_new_account_email()`, `_link()`, `_plain_text()`, `_html()` (system layouts of `get_form_item_view.php` and `get_custom_form_confirmation_screen_content.php`), `_password_note()`.

## What does $auto_registration_password carry now?
- Layouts in `data/backups/*/layouts/` (untouchable) and every layout an operator modified from them print it with `h()` under "Password" / "Geçici Şifre". It now holds `pg_cf_new_account_password_note()`: "Set it with this link: <url>" for the sender, the "Forgot password" sentence when there is no live link, '' for anyone else. Chosen so those layouts stay harmless without editing them.
- `includes/templates/form_item_view.php` gets `$auto_registration_set_password_url` and `$auto_registration_for_submitter` and shows a "Set your password" button.
- The designer token `__new_account_password` (widgets.php) carries the same note; a separate link token and a new designer label are not added yet.

## What is still open?
- Orders (`submit_order.php` → `$_SESSION['software']['auto_registration']['password']`, `get_order_receipt*.php`, `get_view_order_screen_content.php`, `templates/order_receipt.php`) still print the password: separate fix.
- `add_user.php` mails the random password an administrator's new account gets: out of scope.
- Tests: `tests/auto_registration_set_password_test.php`.
