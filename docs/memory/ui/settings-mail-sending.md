---
name: settings-mail-sending
description: Site Settings › E-mail › E-mail sending tab (#10404, CX3c #10434, EM1, EM2, EM3): its folded rows, the save writing config.php then the config row, the test e-mail, old anchors
---

## What is the tab?
- Owner (2026-11-17, poll 148, message 9374): SMTP Settings becomes "E-posta gönderimi" (E-mail sending), for a manager as before (`workflow/decisions-made-product-76.md`). Since EM1 it is the first tab of the E-mail category (`settings-email-tabs.md`), drawn by `includes/settings/mail_sending.php`, saved by `.save.php` through `email.save.php`; `settings_mail_sending.php` opens the tab.
- Since EM2 the tab is folded rows (`settings-mail-sending-rows.md`): How e-mail leaves `pgset-mail-system`, Sending domain `pgset-mail-domain` (SPF, DKIM with the site's key, DMARC; since EM3 the one place of DKIM), Campaign mail `pgset-mail-campaign`, MailChimp `pgset-mailchimp`, Send a test `pgset-mail-test`. Tests: `tests/smtp_settings_layout_test.php`, `tests/settings_mail_sending_fold_test.php`.
- The Sending service and System cards went into How e-mail leaves' ways sheet; `pgset-mail-transport` and `mail_transport_card` land on `pgset-mail-system` (`pg_settings_legacy_anchors()`; the card keeps `id="mail_transport_card"`).
- Since EM3 Campaign mail holds the job switch, "Max. a run" (folded while the job is off), Opt-In Label and Plain Text Footer (CX3c #10434: the texts apply whether the job runs or not). The campaigns' account and server went into their ways sheet (`core/mail-campaign-way.md`). Same field names, columns and texts.

## What does its save write?
- First the way the ways sheet posted (`pg_mail_way_post()`, `settings-mail-sending-rows.md`); a Google or Microsoft way without an account of that provider marks `system_mail_way` and writes nothing.
- Then the MailChimp checks, at Mailchimp too (key, list, store; a missing store is created), as Communication did; a refusal marks its field and writes nothing.
- Then config.php defines and the sending service's account through `pg_mail_settings_save()` (`core/mail-settings-module.md`). A refusal marks its field and writes nothing; the dialog answers it as a refused save.
- Only after that save the config row: `opt_in_label`, `plain_text_email_campaign_footer` and the seven `mailchimp*` columns, one `UPDATE config`; then the campaigns' way, when one was chosen (config_text `campaign_mail_way`).
- The buttons post their name: "Save and generate a DKIM key" (in Sending domain's DKIM row) runs after a successful save with the saved values; "Save and send a test" (only in Send a test since EM2) sets `$_SESSION['smtp_settings_send_test']`. The DKIM "Save and test" went with EM3: the DKIM row judges the published record on every view.

## Where is the test e-mail sent?
- To the site's address (`EMAIL_ADDRESS`), said in the Send a test row. The pane sends it (`pg_mail_settings_send_test()`) when drawn again after the save: the posting request's SMTP constants still hold the old settings. Never while the search index draws the categories (`$_GET['search']`).
- The result is kept in config_text `mail_test_last` (time, sent or not, the reason); the row's line and pill and its one result line read it.
- The dialog's save reads the pane again; `send()` in `backend.src.js` keeps that read's warnings and errors at the top of the pane and adds its notices to the "saved" toast.

## What happened to the old screen?
- `smtp_settings.php` redirects a manager to `settings_mail_sending.php`; the browser keeps an old `#mail_domain_card` across it. `pg_settings_legacy_anchors()` maps it, and the dialog's `fromUrl()` opens the tab for a `smtp_settings.php[#…]` link.
- Communication's old anchors are in the same map: `pgset-campaigns` → `email/pgset-mail-campaign`, `pgset-mailchimp` → `email/pgset-mailchimp`; the DKIM card's `pgset-mail-dkim` → `email/pgset-mail-domain`. My Campaigns' "Campaign settings" and the registry's Mailchimp row link the new cards.
- The registry's mail rows (the five services, Gmail, Microsoft 365 mail) name `#settings/email/pgset-mail-system`; Messages › Sends' warning opens it too.
- The Connect buttons' hidden forms return to `settings_mail_sending.php#pgset-mail-system`.

Related: docs/memory/ui/look-settings-mail-sending.md, docs/memory/ui/settings-mail-sending-rows.md
