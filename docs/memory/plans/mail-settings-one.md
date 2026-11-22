---
name: mail-settings-one
description: Plan (owner 2026-11-21): one E-mail category with Sending and Mailboxes tabs, the e-mail settings' contradictions fixed, one account for sending and reading, Sign in with Google when adding a box
priority: 8
---

## What is asked?
- Owner (2026-11-21, in the R&D agent's window): "eposta gönderme ve eposta kutuları ekrankları karışık durumda. google oauth ile hızlı gmail gelen kutumu ekleyecek bir seçenek göremedim."
- "eposta ayar menüsü tek olabilir. içinde 2 ye tablar ile ayrılır"; "eposta kutularını kaldırma ekleme ekranları çok modern olmalı".
- The Mailboxes tab's own content was tidied by mailboxes-tidy (MT1 to MT4, `ui/look-settings-mailboxes.md`); this plan does not redraw it.

## What contradicts today? (development, 2026-11-21)
- (C1) Two categories, E-mail sending (`mail_sending`) and Mailboxes (`mailboxes`), each sending the reader to the other.
- (C2) One Google or Microsoft account is connected twice: for sending (purpose `mail`) and for mailboxes (`mail_read`). System's select lists only `mail` accounts (`includes/settings/mail_sending.php:44`).
- (C3) Three layers decide how the site's mail leaves (System's account, else the Sending service, else System's server), told only in a paragraph.
- (C4) A campaign with no campaign server leaves through PHP mail(), not System's way (`includes/fn/mail.php` near 2162); the screen says neither.
- (C5) DKIM twice: the DKIM TXT Record card and the Sending domain card's DKIM row.
- (C6) MailChimp's key, list and sync and the campaign texts sit in E-mail sending; other keys are on Connections.
- (C7) A Gmail box needs "Connect in Connections" in another tab; no sign-in in Add a mailbox.
- (C8) Addresses the mail uses are spread over General, Security and Commerce; Support E-mail Address is the From of system mail.

## What did the architect decide? (2026-11-21, agent channel message 11313)
- Key `email`, label "E-mail", tabs `sending` and `mailboxes` kept in the address. Each tab keeps today's gate; the category shows when the user may open one tab, and with one right there is no tab control.
- (C2) One `connect_accounts` row per provider account, with the purposes it was granted. A box's account is offered for sending as it is, no new consent. A sending account chosen for a box asks the wider consent on the same account (its address as login hint, granted scopes kept) and updates that row, never a second. A site that only sends keeps the narrow scope.
- (C2) Removing a box never disconnects an account sending uses; Connections' Disconnect says both uses stop.
- (C4) Nothing switches by itself on a running site: the Campaigns line says what happens today. "The same way as system e-mail" is a way the administrator chooses, stored in `config_text` (no schema); never automatic, for a Google account's daily limit.
- (C6) MailChimp's key, list and sync to Connections' Mailchimp page, the old anchors opening it; Opt-In Label and Plain Text Footer to the Campaign mail row.
- (C8) The addresses stay with their features: no copy in Sending, no next plan; the settings search finds them.

## What did the owner answer? (`workflow/decisions-made-panel-14.md`)
- Owner (2026-11-21): the design, boards A to E of `plans/mail-settings-one/email-settings.jpg`: "Evet, böyle yapılsın" (poll 211). Its parts enter the Design examples screen as DX2b (`plans/design-examples-patterns-mail.md`, message 11316).
- Owner (2026-11-21): no `connect.pinegrap.com` broker or Google verification now (poll 212, "Şimdi değil"). Sign in with Google runs on the site's own Google application; not set up, the two "not set up" cards.

## What binds it?
- Field names, ids and what a save stores do not change; old addresses and anchors open the right tab (`pg_settings_legacy_anchors()`).
- `mail_settings.php` and `includes/settings/mailboxes*.php` take one PR at a time.

Related: docs/memory/plans/mail-settings-one-steps.md, docs/memory/plans/mail-settings-one-screens.md
