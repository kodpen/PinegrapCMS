---
name: mail-campaign-way
description: How campaign e-mail leaves: the layers email() reads, the campaigns' ways sheet and its stored way (config_text campaign_mail_way), why a site that never chose keeps today's order
---

## Which layers carry a campaign today?
- `email()` with `type` `campaign` (`includes/fn/email_campaign_send.php`, Send now and the campaign job) reads, in order: the account chosen for campaigns (`CAMPAIGN_MAIL_CONNECTION`, a `connect_accounts` row of purpose `mail`), the sending service switched on (`pg_mail_transport()`, shared with system e-mail), the campaign server (`CAMPAIGN_SMTP_*`), PHP `mail()`.
- It never reads system e-mail's account or server: a site with only a system server sends campaigns through `mail()`. Since EM2 (#11045) the Campaigns line of How e-mail leaves says so.

## What does the campaigns' ways sheet store?
- config_text `campaign_mail_way` (no schema): `system`, `google`, `microsoft`, `service` or `server`; missing on a site that never chose. Read by `pg_mail_campaign_way()` (`includes/fn/mail.php`), cached per request.
- `pg_mail_kind_route()` (pure) turns it into the kind whose account and server `email()` reads and whether a service may take the message: `system` reads system e-mail's account, service and server; `server` reads the campaign server and skips the service; the rest and nothing keep the order above (the account or service way is in the defines already).
- `pg_mail_way_read('campaign')` follows the same route, so the Campaigns sentence is what `email()` does.

## Why is nothing switched by itself?
- Architect (2026-11-21, agent channel message 11313, plan mail-settings-one C4): "The same way as system e-mail" is a way the administrator chooses, never automatic, for a Google account's daily limit; a running site's campaigns keep leaving as before.
- So the sheet posts the way it started on as `campaign_mail_way_was`; a site with nothing stored that saves that same way stores nothing (`pg_mail_campaign_way_post()`). Only a changed choice is kept.

## What does a choice save?
- `pg_mail_campaign_way_post()` runs after system e-mail's `pg_mail_way_post()`. An account way needs an account of its provider; the others clear `campaign_mail_connection`. `service` needs a service left on for system e-mail (the posted `mail_transport`), as campaigns have no service of their own; a refusal marks `campaign_mail_way`.
- The stored way is written after config.php and the config row (`mail_sending.save.php`). The campaign server's four fields are posted whichever way is chosen, as the save writes each define it knows.
- The campaign port is checked when the way is `server`, or with no way posted while the job is on (`pg_mail_settings_save()`).

Tests: `tests/settings_mail_sending_fold_test.php` (route, start, post, sentence).

Related: docs/memory/ui/settings-mail-sending-rows.md, docs/memory/core/mail-transports.md
