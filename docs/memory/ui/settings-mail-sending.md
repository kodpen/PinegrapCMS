---
name: settings-mail-sending
description: Site Settings › E-mail sending (#10404): SMTP Settings' five cards, smtp_settings.php a redirect, a save writing config.php, the test e-mail sent by the pane read after the save
---

## What is the category?
- Owner (2026-11-17, poll 148, message 9374): SMTP Settings becomes the "E-posta gönderimi" (E-mail sending) category, between Communication and Mailboxes, for a manager as before (`workflow/decisions-made-product-76.md`). Key `mail_sending`, `includes/settings/mail_sending.php`, `.save.php`, `settings_mail_sending.php`.
- Cards in the old screen's order: Sending service `pgset-mail-transport`, System `pgset-mail-system`, Campaign `pgset-mail-campaign`, DKIM TXT Record `pgset-mail-dkim`, Sending domain `pgset-mail-domain` (read only). Test: `tests/smtp_settings_layout_test.php`.

## What does its save write?
- config.php defines and the sending service's account through `pg_mail_settings_save()` (`core/mail-settings-module.md`), never a config row column. A refusal marks its field and writes nothing; the dialog answers it as a refused save.
- The buttons post their name: "Save and generate a DKIM key" and "Save and test" run after a successful save with the saved values; "Save and send a test" sets `$_SESSION['smtp_settings_send_test']`.

## Where is the test e-mail sent?
- The cards send it (`pg_mail_settings_send_test()`) when they are drawn again after the save: the posting request's SMTP constants still hold the old settings. Never while the search index draws the categories (`$_GET['search']`).
- The dialog's save reads the pane again; `send()` in `backend.src.js` keeps that read's warnings and errors at the top of the pane and adds its notices to the "saved" toast. Before, the toast's `notice('')` wiped what the read said.

## What happened to the old screen?
- `smtp_settings.php` redirects a manager to `settings_mail_sending.php`; the browser keeps an old `#mail_domain_card` across it, and the cards keep `mail_transport_card` and `mail_domain_card`. `pg_settings_legacy_anchors()` maps both, and the dialog's `fromUrl()` opens the category for a `smtp_settings.php[#…]` link.
- SMTP Settings left System tools › Connections and its search words; the registry's mail rows name the Sending service card (API services) or the System card (Gmail, Microsoft 365 mail, where the account is chosen). The alert, Messages › Channels and Sends, the campaign screens and the Cron Jobs card link the category.
- The Connect buttons' hidden forms return to `settings_mail_sending.php#pgset-mail-system`.

## What did the move change in the markup?
- The buttons are `pg-button pg-button-quiet pg-button-sm` with Bootstrap icons, the Connect buttons and the Sending domain card's Copy too (`ui/look-new-parts.md`); the copy listener binds once per page (`window.pgMailCopyRecordBound`).
