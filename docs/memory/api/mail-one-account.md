---
name: mail-one-account
description: One Google or Microsoft account sends the site's e-mail and reads mailboxes (EM4): "Connected for a mailbox", Allow reading mail, Disconnect naming both uses
---

## Which accounts may send the site's e-mail?
- A site account (user 0) holding purpose `mail` or `mail_read` (`pg_mail_account_sends()`, `includes/fn/mail_accounts.php`): `mail_read` carries the send permission too (Gmail's `https://mail.google.com/`, Microsoft's Graph `Mail.Send`), so no new consent is asked.
- How e-mail leaves lists them (`pg_mail_sending_accounts()`); an account that only reads mailboxes is marked "Connected for a mailbox" (`pg_mail_account_mark()`). The save (`pg_mail_settings_save()`), the way's check and `pg_mail_connection()` in `email()` take both.
- Architect (2026-11-21, agent channel message 11313, plan mail-settings-one C2): one `connect_accounts` row per provider account with the purposes it was granted; a box's account is offered for sending as it is.
