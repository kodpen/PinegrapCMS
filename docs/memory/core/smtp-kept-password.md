---
name: smtp-kept-password
description: SMTP Settings: an empty password box keeps the saved SMTP password only for the same host and port; a new server drops it and a save that would sign in there is refused
module: mail_transport
---

## When does an empty password box keep the saved SMTP password?
- Only when the host (trimmed, without case) and the port are what the password was saved with; an empty port counts as 587. The username may change. `pg_smtp_password_kept()` in `includes/fn/mail_transport.php` decides, for the system and the campaign server alike; `smtp_settings_save()` in `smtp_settings.php` calls it before anything is written.
- Otherwise the saved password is removed from `config.php`. When the posted server still has a host and a username (it would sign in), the whole save is refused with "The SMTP server or its port changed. Enter the password again."; nothing is written, the sending service card included.

## Why?
- The password is never drawn back into the form, so a manager who can open SMTP Settings cannot read it. Before this, changing the host and pressing "Save and send a test" sent the saved password to the new server in SMTP AUTH. A port change counts because 465 is the only port with TLS before the greeting.

Related: docs/memory/platform/backup-remote-stored-secret.md (the same rule for the FTP backup), docs/memory/core/mail-transports.md
