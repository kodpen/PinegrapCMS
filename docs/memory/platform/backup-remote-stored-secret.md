---
name: backup-remote-stored-secret
description: Settings › Backups: an empty FTP password box keeps the stored password only for the same host, port and TLS; the S3 secret key is kept as is, since SigV4 never sends it
module: backup
---

## When does an empty password box keep the stored FTP password?
- Only when the host (without case), the port and TLS are what the password was stored with; TLS may be turned on, not off. The user and the folder may change. `pg_backup_ftp_password_kept()` in `includes/fn/backup.php` decides, `includes/settings/maintenance.save.php` calls it.
- Otherwise the stored password is dropped; with FTP as the destination the save is refused with "The FTP server or its encryption changed. Enter the password again." A destination without a stored password (anonymous FTP) is not refused.

## Why?
- The password is never drawn back into the form, so a manager who can open the settings cannot read it. Before this, changing the host and pressing "Save and test the connection" sent the stored password to the new server at FTP sign-in, in clear text without TLS.

## Why is the S3 secret key kept when the endpoint changes?
- Signature Version 4 sends only the access key and an HMAC, never the secret, and the signature names the host it was made for, so a new endpoint learns nothing it could reuse at the old one.

Related: docs/memory/platform/backup-object-storage.md
