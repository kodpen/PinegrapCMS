---
name: mail-settings-one
description: Plan (owner 2026-11-21): one E-mail category with Sending and Mailboxes tabs, the e-mail settings' contradictions fixed, one account for sending and reading, Sign in with Google when adding a box
priority: 8
---

## What is asked?
- Owner (2026-11-21, in the R&D agent's window): "eposta gönderme ve eposta kutuları ekrankları karışık durumda. google oauth ile hızlı gmail gelen kutumu ekleyecek bir seçenek göremedim."
- "eposta ayar menüsü tek olabilir. içinde 2 ye tablar ile ayrılır"; "eposta kutularını kaldırma ekleme ekranları çok modern olmalı"; a new part not on the Design examples screen is asked of the architect as a design with a picture.
- The Mailboxes tab's own content is `plans/mailboxes-tidy.md` (MT1 to MT4); this plan does not redraw it.

## What contradicts today? (read on development, 2026-11-21)
- (C1) Two categories, E-mail sending (`mail_sending`) and Mailboxes (`mailboxes`), each opening with a line that sends the reader to the other.
- (C2) One Google or Microsoft account is connected twice: for sending on E-mail sending's System card (purpose `mail`, `gmail.send` / `Mail.Send`), for mailboxes on Connections (purpose `mail_read`, `https://mail.google.com/`). System's select lists only `mail` accounts (`includes/settings/mail_sending.php:44`), so a box's Gmail account is not offered for sending.
- (C3) Three layers decide how the site's mail leaves (System's connected account, else the Sending service's API, else System's server); the order is told only in a paragraph of the Sending service card.
- (C4) Campaign keeps its own server boxes (host, port, user, password, Max.); the screen does not say whether the Sending service carries campaigns. Unverified: the step reads `email_campaign_job.php`.
- (C5) DKIM twice: the DKIM TXT Record card (selector, domain, generate, record) and the Sending domain card's DKIM row with its record to copy.
- (C6) MailChimp's key, list and sync, and the campaign texts (Opt-In Label, Plain Text Footer), sit in E-mail sending though they are not about sending; other services' keys live on Connections.
- (C7) A Gmail box: Add a mailbox, choose Gmail, "Connect in Connections" in another tab, come back, choose the account, Test connection. No sign-in in the add flow itself.
- (C8) Addresses the site's mail uses are spread: Support E-mail Address (General), Registration and Membership E-mail Address (Security), Commerce, BCC and Affiliate Program E-mail Address (Commerce). What each is used for is unverified.

## What is asked of the architect?
- The category's key and the rights of each tab (today a manager opens E-mail sending; Mailboxes has its own gate).
- (C2) One account for both: a box's account (its scope also sends) offered for sending; a sending account gains reading by a second consent on the same account.
- (C4) Whether campaigns follow system mail by default, the campaign server only when "Send campaigns another way" is chosen.
- (C6) MailChimp to Connections' Mailchimp page; the campaign texts to the Campaign mail row.
- (C8) Whether those addresses gather in the Sending tab, or stay with their features.
- One-click Gmail with no Google application of the site's own needs the `connect.pinegrap.com` broker and Google's verification of Kodpen's application, with a security assessment for the restricted `https://mail.google.com/` scope (`workspace/mail-google.md`): an open owner decision of `plans/reverse-oauth-leftovers.md`.
- The parts the Design examples screen lacks, as a design with a picture: `mail-settings-one-screens.md`.

## What binds it?
- Field names, ids and what a save stores do not change; old category addresses and anchors keep opening the right tab (`pg_settings_legacy_anchors()`).
- `mail_settings.php` and `includes/settings/mailboxes*.php` are entered by one PR at a time; mailboxes-tidy's steps go first there.

Related: docs/memory/plans/mail-settings-one-steps.md, docs/memory/plans/mail-settings-one-screens.md, docs/memory/plans/mailboxes-tidy.md
