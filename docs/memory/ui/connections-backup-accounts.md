---
name: connections-backup-accounts
description: Backup accounts (CX11j1): Connect a … account for backups on Google Drive's, OneDrive's and Dropbox's pages, the return to the Backups card, the card without a Connect button
module: connections_screen, backup_cloud
---

## Where is an account for backups connected?
- On `connections.php?service=google_drive` (`onedrive`, `dropbox`), step CX11j1 of `connections-one-place.md` (#11193), no longer by a button on Site Settings › Updates and maintenance › Backups.
- In "Status", a "Backups" part (`#connections-backup`, `connections_backup_part()` in `connections.php`): "Connect a Google account for backups" (connect.php, purpose `backup`) and a link to the Backups card. The accounts themselves are Status's Account list, which reads purpose `backup`.
- Gate: card `backups` of `pg_connections_screen_page_cards()`: a manager (connect.php's right for the site's accounts) who may open Updates and maintenance (`connections_maintenance_may_manage()`, `pg_settings_may_open('maintenance')`). Nobody gains or loses a right.

## Where does the connection return to?
- The card links `pg_backup_cloud_connections_url($type)`: the page with `backup_return=1`. The page then returns to `pg_backup_cloud_connect_return($type)`, `settings_maintenance.php?backup_connect=<type>#pgset-backup`, which `pg_connect_return_path()` keeps; without it, to the page.
- The card reads `?backup_connect=` as before: connect.php's message, and the destination shown chosen with the new account, not yet saved.
- `pg_backup_cloud_types()` names each destination's `service` (gdrive google_drive, onedrive onedrive, dropbox dropbox); `pg_backup_cloud_type_of_service()` reads it back. All pure, in `includes/fn/backup_cloud.php`.

## What does the Backups card keep?
- Per destination: the "Account" select, "Folder", the "application is not set up yet" line, and "Manage in Connections" (an account is connected) or "Connect in Connections" to the page. No Connect button and no hidden connect.php form.
- FTP, S3-compatible storage and the object storage card stay: they are no registry service.

## What did not change?
- The registry rows keep `place` `#settings/maintenance/pgset-backup`: which account and folder are the card's settings. No service added, no data step, table or column.

Tests: `tests/connections_backup_accounts_test.php`, `tests/settings_backup_card_test.php`, `tests/backup_cloud_test.php`.
