---
name: admin-new-account-email-link
description: The "New Account" e-mail an administrator sends from add user or import users carries a single-use set password link, never the random password (PR #1217)
---

## What did the e-mail carry before?
- `add_user.php` and `import_users.php` ("Notify User") mailed the random password; `add_user.php` repeated it in the panel notice.

## What does it carry now?
- Now `pg_new_user_email_body()` writes the address, the link (or the "Forgot password" sentence without `ENCRYPTION_KEY`) and the Login lines; sent `'secret' => true`. A notified user's notice shows no password; without notify the administrator still sees it, to hand it over.
- The link is the same single-use HMAC link a form's auto-registration issues: `core/auto-registration-set-password-link.md`.

## What is still open?
- `reset_password.php` mails the password an administrator resets and repeats it in the notice: separate fix.
- Test: `tests/add_user_set_password_test.php`.
