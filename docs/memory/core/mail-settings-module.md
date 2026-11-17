---
name: mail-settings-module
description: includes/fn/mail_settings.php: what SMTP Settings saves and runs (config.php defines, kept password, DKIM, test e-mail), moved out of the screen (CX3a)
module: mail_settings
---

## What is in the module?
- `pg_mail_settings_save($post, $liveform)`: the kept-password check (`smtp-kept-password.md`), then the sending service (`mail-transports.md`), then the fourteen defines of `pg_mail_settings_keys()` written to config.php in one place; a refusal marks its field and writes nothing. Audit label `smtp`. Returns the saved values by lower-case name, or false.
- `pg_mail_settings_dkim_generate()` and `_dkim_test()` take that answer, so they use the selector and domain just saved; `pg_mail_settings_send_test()` sends on the next view, whose constants hold the saved settings, and clears its session flag first.
- `pg_mail_settings_ready()`, `_providers()`, `_connection_field()` and `_connect_buttons()` draw what the System card needs; the Connect buttons submit hidden forms `pgset_mail_connect_<provider>`.

## Why a module?
- Owner (2026-11-17, agent channel poll 148, message 9374): SMTP Settings becomes the "E-posta gönderimi" (E-mail sending) category of Site Settings. CX3a moved the logic out of `smtp_settings.php` with no change to what the screen does, so the category reuses it; the screen keeps its log line and "saved" notice.
- The DKIM notice no longer carries a textarea: it points at the record the DKIM TXT Record card shows, which a dialog's text notice could not hold.

Tests: `tests/mail_settings_save_test.php`, `tests/dkim_key_test.php`.
