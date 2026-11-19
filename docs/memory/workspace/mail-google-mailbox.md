---
name: mail-google-mailbox
description: A Gmail or Google Workspace mailbox (S5d, #10431): IMAP with an app password, the steps in the box form, the Google pages checked, what OAuth reading would take
---

## How does a Google mailbox connect today?
- Over IMAP with an app password: the `gmail` preset of `ws_mail_providers()` (imap.gmail.com 993, smtp.gmail.com 465, checking server mx.google.com). Google refuses the account's own password.
- The Connections registry's Google entry covers sending only (purpose `mail`); purpose `mail_read` (`includes/connect/purposes/mail_read.php`) has scopes for Microsoft alone. So a Google account connected in Settings › Connections sends the site's mail and reads no mailbox. The owner connected one there on 2026-11-19 thinking it was the inbox.

## What does the form say?
- `ws_mail_google_steps()`: a block between Mail service and User name, shown while Gmail or Google Workspace is chosen (`data-ws-mail-when="provider=gmail"`; the script now also listens to `provider`).
- Steps: the mailbox's own Google account; 2-Step Verification (myaccount.google.com/security); App passwords (myaccount.google.com/apppasswords), a name, a 16-character password; keep the service (it fills empty servers on save); full address as user, the app password without spaces, Connected account None; Save, then Test connection.
- Notes: IMAP needs nothing in Gmail; when app passwords are not offered; a Google password change revokes them; a Connections account only sends.

## Which Google pages were checked (2026-11-19)?
- support.google.com/accounts/answer/185833: app passwords need 2-Step Verification; not offered with security-key-only 2SV, Advanced Protection or a work, school or organisation account; revoked when the Google password changes.
- support.google.com/mail/answer/7126229: since January 2025 IMAP access is always on and the Enable IMAP choice is gone.
- The redirect addresses, APIs and "Google hasn't verified this app" notes the architect gave apply only to an OAuth connection, which the mailbox flow does not have: not written.

## What would Google OAuth reading take?
- A `google` entry in `mail_read`'s `scopes` and `imap_scopes` (`https://mail.google.com/`, the scope Gmail IMAP's XOAUTH2 takes), a Connect button for Google beside Microsoft's, and the preset choosing the connected account; sign-in already speaks XOAUTH2 (`ws_mail_box_login()`). Answers through the account need a Gmail send path for purpose `mail_read`.
- `https://mail.google.com/` is a restricted scope: public use needs Google's verification and a security assessment; in Testing the access lapses after 7 days. Not built: the architect decides (asked by K1, 2026-11-19).

Related: docs/memory/workspace/mail-microsoft.md, docs/memory/ui/look-settings-mailboxes.md
