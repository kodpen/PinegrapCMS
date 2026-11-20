---
name: bug-hunt-mail-sending
description: Bug hunt of Site Settings › E-mail sending (architect, agent channel message 10151, 2026-11-20): the five faults fixed, the issue opened, what was read clean
---

## What was the area?
- Site Settings › E-mail sending, built with CX3 (#10334, #10404, #10434): `includes/settings/mail_sending.php`, `.save.php`, `includes/fn/mail_settings.php`, `smtp_settings.php` and the old anchors. Given by the architect, agent channel message 10151. Not touched: `connections.php`, `includes/fn/connections_*.php`, `mailchimp_key.php`, `mail_transport.php`, `mail_queue.php`.

## What did it fix? (its PR lists each with file and line)
- DKIM card "Save and test" passed a record cut at 255 characters or a bare `v=DKIM1`, and failed two records under the name or tags without spaces: now `pg_mail_settings_dkim_matches()` compares each record's p= key (`pg_mail_dkim_key()`).
- The DKIM card showed the record under the selector and domain the key was generated with: now under the ones mail is signed with (`pg_mail_settings_dkim_shown_record()`).
- Ports and the campaign job's Max. were saved as typed; Max. 0, a sign or a fraction stopped the job's LIMIT. Refused before anything is written (`pg_mail_settings_number_problem()`); the campaign boxes only while the job is on.
- A system SMTP relay without a username hid the test buttons and refused the test e-mail: `pg_mail_settings_smtp_ready()`.
- A Mailchimp refusal printed the request, API key included, on its field: `pg_mail_settings_mailchimp_reason()`.

## What was opened as an issue?
- #10589: `mailchimp_request()` writes the same key into the activity log and the error mail (outside the area).

## What was read clean?
- The kept SMTP password check and its refusal, the config.php write (temp file, empty read refused), the job switch's boolean define, the connected account select (site account of purpose mail only), the audit trail (passwords masked by name), the test e-mail flag read once on the next pane, the search index skipping it, DKIM key generation's pair rollback, the old addresses (`smtp_settings.php` redirect, `pg_settings_legacy_anchors()`).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/mail-settings-module.md
