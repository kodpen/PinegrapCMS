---
name: auto-registration-set-password-link
description: Accounts a form auto-registers or an administrator creates (add user, import) get a single-use set password link, not the password; who sees it, what $auto_registration_password holds.
---

## Why is the password no longer written anywhere?
- `custom_form.php` (auto_registration = 1) used to keep the random password in the session and print it in every e-mail copy, the confirmation page and old layouts. Fixed in PR #1172.
- The account is created without a password (`create_member_user()` without `'password'`: algo 3, empty hash, rejected by `pg_password_verify()`). Change password then asks for no current password. `set_password.php` writes algo 2. The session entry holds only `email_address` and `user_id`.

## How is the link made and checked?
- `includes/fn/auth.php`: `pg_set_password_link_issue($user_id)` writes `user.token = sha256(T)` and `user.token_timestamp` (as `forgot_password.php`), so `set_password.php` and its widget accept it. No schema change.
- `T = pg_set_password_link_token()` = HMAC-SHA256 of `set-password:<user_id>:<issued_at>` under `ENCRYPTION_KEY`. Derived, so a later request redraws the same link; `T` is not stored. No key: no link.
- `pg_set_password_link($user_id)` shows the link only while `hash_equals(user.token, sha256(T))` and `pg_set_password_link_live()` (the same 24 hours as `set_password.php`). Setting the password clears the token and a forgot password request replaces it, so each link works once.
- Address: `pg_sign_in_screen_url('set password', 'set_password.php')`, else the panel's; language via `pg_tr_rewrite_url()`; host `URL_SCHEME . HOSTNAME_SETTING` (`platform/hostname-in-mail-links.md`).

## Who sees the link?
- Only the form's sender: their plain-text copy (`pg_cf_new_account_plain_text(…, true)`), HTML copy and confirmation page.
- The sender's copy is sent with `'secret' => true` while an account was opened, so a failed delivery keeps the link out of the activity log and the failure notice (`pg_mail_failure_body_shown()`).
- `pg_cf_new_account_for_submitter()` is false while `pg_tr_suspend()` holds the request (the administrator's HTML copy; keep that wrapper in `custom_form.php`) and while `pg_cf_new_account_recipient(false)` marks a watcher's HTML copy. The administrator's plain text says only "A new account was created with this submission: <email>".
- Helpers in `includes/fn/custom_form.php`: `pg_cf_new_account_email()`, `_link()`, `_plain_text()`, `_html()` (system layouts of the form item view and the confirmation screen), `_password_note()`.
- Orders: the same; the customer's receipt copy is secret, the store gets its own copy without the link (`commerce/order-auto-registration-set-password-link.md`, #1220).

## What does $auto_registration_password carry now?
- Layouts in `data/backups/*/layouts/` (untouchable) and their copies print it with `h()` under "Password" / "Geçici Şifre". It holds `pg_cf_new_account_password_note()`: "Set it with this link: <url>" for the sender, the "Forgot password" sentence when there is no live link, '' for anyone else, so those layouts stay harmless unedited.
- `includes/templates/form_item_view.php` gets `$auto_registration_set_password_url` and `$auto_registration_for_submitter` and shows a "Set your password" button.
- Designer (form item view, `widgets.php`): `__new_account_password` carries the same note (kept for saved trees); `__new_account_set_password_url` is the bare link for an href, flag `has_set_password_link` (off for anyone but the sender and once the link is used or expired). Shipped templates: a "Set your password" button, then the "Forgot password" sentence (#1348).

## What does an administrator's "New Account" e-mail carry?
- A set password link, never the password (`add_user.php`, `import_users.php`): `core/admin-new-account-email-link.md` (#1217).

## What is still open?
- `reset_password.php` still mails the password an administrator resets and repeats it in the notice: separate fix.
- Tests: `tests/auto_registration_set_password_test.php`.
