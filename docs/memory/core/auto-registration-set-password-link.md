---
name: auto-registration-set-password-link
description: Accounts a form auto-registers or an administrator creates (add user, import) get a single-use set password link, not the password; who sees it, what $auto_registration_password holds.
---

## Why is the password no longer written anywhere?
- `custom_form.php` (auto_registration = 1) used to keep the random password in the session and print it in the sender's e-mail, the administrator's copy, watchers' HTML copies, the confirmation page and old layouts. Reported on kodpen.com, 2026-10-21. Fixed in PR #1172.
- The account is created without a password (`create_member_user()` without `'password'`: algo 3, empty hash, which `pg_password_verify()` rejects). The change password screen and widget then ask for no current password. `set_password.php` writes algo 2. The session entry holds only `email_address` and `user_id`.

## How is the link made and checked?
- `includes/fn/auth.php`: `pg_set_password_link_issue($user_id)` writes `user.token = sha256(T)` and `user.token_timestamp`, the columns `forgot_password.php` uses, so `set_password.php` and the set password widget accept it. No schema change.
- `T = pg_set_password_link_token()` = HMAC-SHA256 of `set-password:<user_id>:<issued_at>` under `ENCRYPTION_KEY`, so a later request (the confirmation page) redraws the link from the user id; `T` is not stored. No key: no link.
- `pg_set_password_link($user_id)` shows it only while `hash_equals(user.token, sha256(T))` and `pg_set_password_link_live()` (24 hours, as `set_password.php`). Setting the password clears the token, a forgot password request replaces it: each link works once.
- Address: `pg_sign_in_screen_url('set password', 'set_password.php')`, else the panel's `set_password.php`; language prefix via `pg_tr_rewrite_url()`; host `URL_SCHEME . HOSTNAME_SETTING`.

## Who sees a form's link?
- Only the sender: their plain-text copy (`pg_cf_new_account_plain_text(…, true)`), HTML copy and confirmation page. Their copy is sent `'secret' => true`, so a failed delivery keeps the link out of the activity log and the delivery-failed notice (`pg_mail_failure_body_shown()`).
- `pg_cf_new_account_for_submitter()` is false while `pg_tr_suspend()` holds the request (the administrator's HTML copy; keep that wrapper in `custom_form.php`) and while `pg_cf_new_account_recipient(false)` marks a watcher's copy. The administrator's plain text says only "A new account was created with this submission: <email>".
- Helpers in `includes/fn/custom_form.php`: `pg_cf_new_account_email()`, `_link()`, `_plain_text()`, `_html()`, `_password_note()`.

## What does $auto_registration_password carry now?
- Layouts in `data/backups/*/layouts/` (untouchable) and their copies print it with `h()` under "Password". It holds `pg_cf_new_account_password_note()`: "Set it with this link: <url>" for the sender, the "Forgot password" sentence without a live link, '' for anyone else.
- `includes/templates/form_item_view.php` gets `$auto_registration_set_password_url` and `$auto_registration_for_submitter` and shows a "Set your password" button. The designer token `__new_account_password` carries the same note.

## What does an administrator's "New Account" e-mail carry?
- `add_user.php` and `import_users.php` ("Notify User") used to mail the random password; `add_user.php` repeated it in the notice. PR #1217.
- Now `pg_new_user_email_body()` writes the address, the link (or the "Forgot password" sentence without `ENCRYPTION_KEY`) and the Login lines; sent `'secret' => true`. A notified user's notice shows no password; without notify the administrator still sees it, to hand it over.

## What is still open?
- Orders (`submit_order.php`, order receipts) still print the password: #1213 / #1220.
- `reset_password.php` mails the password an administrator resets and repeats it with `h($random_password)` in the notice: separate fix.
- Tests: `tests/auto_registration_set_password_test.php`, `tests/add_user_set_password_test.php`.