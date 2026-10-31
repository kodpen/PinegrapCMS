---
name: admin-new-account-email-link
description: E-mails an administrator triggers for an account (add user, import users, reset password) carry a single-use set password link, never a password (PR #1217, #4093)
---

## What did the e-mails carry before?
- `add_user.php` and `import_users.php` ("Notify User") mailed the random password; `add_user.php` repeated it in the panel notice.
- `reset_password.php` ("Reset & Send Password") mailed a new 10-letter password without `'secret'`, so a failed send wrote it to the Site Log (`log`, readable by managers) and the delivery-failed notice; the panel notice showed it and said "e-mailed" without checking the send (#4064).

## What do they carry now?
- New account: `pg_new_user_email_body()` writes the address, the link (or the "Forgot password" sentence without `ENCRYPTION_KEY`) and the Login lines; sent `'secret' => true`. A notified user's notice shows no password; without notify the administrator still sees it, to hand it over.
- Reset (#4093): the old password is replaced by a 32-letter random one nobody is told (it only locks the old one out), remembered sessions are revoked, then `pg_set_password_link_issue()` / `pg_set_password_link()` issue the link. `pg_reset_password_email_body()` builds the same shape of body; sent `'secret' => true`. The notice is `pg_reset_password_notice($url, $sent)`: link sent, sent without a link ("Forgot password"), or the send failed. The button and its confirmation read "Reset Password & Send Link".
- Who may reset whom is unchanged: manager gate, CSRF token, not one's own account, a non-administrator only below their own role.
- The link is the same single-use HMAC link a form's auto-registration issues: `core/auto-registration-set-password-link.md`.

## Where are the tests?
- `tests/add_user_set_password_test.php`, `tests/user_reset_password_link_test.php`.
