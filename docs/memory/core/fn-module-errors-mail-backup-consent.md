---
name: fn-module-errors-mail-backup-consent
description: The 2026.4.8 modules errors, mail_queue, backup and consent in includes/fn/: entry functions, gates, storage keys and behaviour rules.
---

## errors (central error log)
- `pg_error_install()` is installed in `init.php`. Exceptions/fatals/warnings are written as JSON lines to `data/temp/php_errors.log`; `view_log.php` displays it.
- The handler only records (`return false`) and never touches the DB. Code that installs a temporary `set_error_handler` MUST restore with `restore_error_handler()`, otherwise logging is silent for that request.

## mail_queue (outgoing mail queue)
- `mail_outbox` write/read: `pg_mail_enqueue()`, `pg_mail_queue_run()`. The queue decision is `pg_mail_job_alive()` (`job` or `mail_job` ran in the last 15 min). Backoff schedule: `pg_mail_backoff()` (the campaign job uses it too). List-Unsubscribe headers live here.
- Gate: `pg_mail_queue_ready()`. Screen `mail_queue.php`; separate cron entry `mail_job.php`.
- `email()` property `'secret' => true` (password reset link): `pg_mail_failure_body_shown()` keeps the body out of the failure log and notice; the queue stores it unchanged and re-calls `email()`.
- A permanent failure (no recipient, empty body, refused address) is given up on its first attempt, and every given-up message is told once on the bell: docs/memory/core/mail-queue-permanent-errors.md.

## backup (automatic backup)
- Weekly zip (`pg_backup_run_auto()`; a folder if ZipArchive is missing). Retention: `pg_backup_prune_list()` (only `auto_backup_*` names).
- Remote copy: FTP/FTPS, SigV4-signed S3 PUT, or via a connected account Google Drive / OneDrive / Dropbox (`includes/fn/backup_cloud.php`: 10 MiB chunked upload; retention also applies to the cloud folder).
- Type is `config.backup_remote_type` VARCHAR(8): `gdrive`/`onedrive`/`dropbox`; its settings are only `connection_id` + `folder`; purpose is `backup`.
- Settings are encrypted in `config.backup_remote_settings` (`pg_backup_remote_encode/decode()`, per-type key, order `pg_backup_remote_types()`). Gate: `pg_backup_settings_ready()`.

## consent (cookie consent)
- `pg_consent_inject()` → `pg_consent_insert($content, pg_consent_choices())` at the end of `get_page_content()`: the notice before the last `</body>`, the Consent Mode default at the top of `<head>` (excluded: email, edit mode, `IS_BOT`). Choices, Consent Mode and focus rules: docs/memory/platform/cookie-consent-notice.md.
- `pg_consent_allows($category)` / `pg_consent_setcookie()` gate optional cookies (everything is allowed while the setting is off).
- `pg_consent_hold_scripts()` holds a script as `type="text/plain" data-pg-consent`; `assets/js/pg_consent.js` revives it on consent.
- Cookie `pg_consent=1.a1.m0`: JS writes, PHP reads (`pg_consent_parse()`, `pg_consent_choices()` for this request).

Related: docs/memory/core/fn-module-map.md
