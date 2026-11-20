---
name: mail-settings-module
description: includes/fn/mail_settings.php: what E-mail sending saves and runs (config.php defines, kept password, DKIM, test e-mail), moved out of the screen (CX3a)
module: mail_settings
---

## What is in the module?
- `pg_mail_settings_save($post, $liveform)`: the kept-password check (`smtp-kept-password.md`), then the sending service (`mail-transports.md`), then the fourteen defines of `pg_mail_settings_keys()` written to config.php in one place; a refusal marks its field and writes nothing. Audit label `smtp`. Returns the saved values by lower-case name, or false.
- `pg_mail_settings_dkim_generate()` and `_dkim_test()` take that answer, so they use the selector and domain just saved; `pg_mail_settings_send_test()` sends when the cards are drawn after the save, whose constants hold the saved settings, and clears its session flag first.
- `pg_mail_settings_ready()`, `_providers()`, `_connection_field()` and `_connect_buttons()` draw what the System card needs; the Connect buttons submit hidden forms `pgset_mail_connect_<provider>`.

## What does the save refuse and the card check?
- A port outside 1 to 65535 and a campaign Max. that is not a whole number of 1 or more (email_campaign_job.php puts it into its LIMIT); the campaign boxes only while the job is on, because they are folded away otherwise. `pg_mail_settings_number_problem()`, before anything is written.
- The test buttons and the test e-mail need a way to send: a system SMTP host with no username or with its password (`pg_mail_settings_smtp_ready()`, as `email()` signs in), a connected account or a sending service.
- DKIM "Save and test" passes when one TXT record under the saved selector carries the generated p= key whole (`pg_mail_settings_dkim_matches()`); the card shows the key under the selector and domain mail is signed with (blank: `pinegrap`, the site address's domain).
- A Mailchimp refusal shows Mailchimp's reason with the key masked, never the request (`pg_mail_settings_mailchimp_reason()`).

## Why a module?
- Owner (2026-11-17, agent channel poll 148, message 9374): SMTP Settings becomes the "E-posta gönderimi" (E-mail sending) category of Site Settings. CX3a (#10334) moved the logic out of `smtp_settings.php`; the category (#10404, `ui/settings-mail-sending.md`) calls it from its save and its cards, and logs and says "saved" as every category does.
- The DKIM notice no longer carries a textarea: it points at the record the DKIM TXT Record card shows, which a dialog's text notice could not hold.

Tests: `tests/mail_settings_save_test.php` (the save, the buttons, the account select), `tests/dkim_key_test.php`, `tests/mail_settings_dkim_test.php`, `_numbers_test.php`, `_ready_test.php`, `_mailchimp_reason_test.php`.
