---
name: settings-mail-sending-rows
description: E-mail sending's folded rows (EM2): How e-mail leaves with a sentence a kind of mail, the ways sheet of system e-mail and what its choice saves, each row's line and pill, Send a test's result
module: mail_sending_rows
---

## Why folded rows?
- Owner (2026-11-21, poll 211): the drawing of the E-mail category, board A (rows) and B (the ways sheet), `plans/mail-settings-one-design.md`. The scan of 2026-11-21 found the sending way drawn twice (Sending service and System cards, each with a "Send through" select) and cut off; EM2 draws it once.
- Built from `pg_settings_fold()` (`settings-fold-rows.md`); the lines are `includes/fn/mail_sending_rows.php`. Order `pg_mail_sending_fold_order()`: leaves, domain, campaign, mailchimp, test (row ids `pg-fold-mail-<key>`).

## What does How e-mail leaves say?
- A value row a kind of mail (Design examples' Value rows): "System e-mail" and "Campaigns", each one sentence of what happens today (`pg_mail_way_read()` → `pg_mail_way_now()`), in `email()`'s order: the account chosen for the kind, the sending service switched on, the kind's own server, PHP mail(), else "Cannot leave". Campaigns set to "The same way as system e-mail" say "Leaves the same way as system e-mail, through X." (`core/mail-campaign-way.md`).
- The head: "System e-mail through X · campaigns the same way" (or "· campaigns through Y"); pill Ready, Not set up (no `pg_mail_settings_ready()`, as the tab's pill), Sign-in needed (an account to connect again).
- System e-mail's Change opens the drawer `pgset-mail-way`; Campaigns' Change opens `pgset-mail-campaign-way` (EM3).

## What is the ways sheet?
- `pg_settings_drawer('pgset-mail-way')`: tile, "How should system e-mail leave?", "Order, form, password and notice mail."; four choice cards (`pg_mail_way_cards()`, radio `system_mail_way`), then only the chosen way's fields.
- `pgset-mail-campaign-way` (EM3): "How should campaigns leave?", "Newsletters and other bulk sends."; five cards (`pg_mail_way_cards(…, 'campaign')`, radio `campaign_mail_way`), "The same way as system e-mail" first. Accounts in `campaign_mail_connection` (`pg_mail_way_account_select(…, 'campaign_mail_connection')`, ids `setting_campaign_mail_connection[_microsoft]`); A sending service names system e-mail's service, no fields; Your own mail server holds the four `campaign_smtp_*` fields. Starts on `pg_mail_campaign_way_chosen()`.
- Google or Microsoft: that provider's accounts in `system_mail_connection` (the other provider's select disabled, so one is posted), Connect a/another … account, or the Connections link while the application is not set up. A sending service: `pg_mail_transport_settings_fields()` (services only). Your own mail server: the four `system_smtp_*` fields.
- It sits outside the form: every field has `form=` the settings form; the drawer's fields use the drawer recipe (`pg-input`, `pg-field-label`, `pg-field-help`). Save fill and Cancel quiet in its foot; a save reopens it, as every settings drawer.
- It starts on the account's provider, else the service switched on (even incomplete, with its warning), else the server (`pg_mail_way_chosen()`).

## What does a choice save?
- `pg_mail_way_post()` before anything else: an account way keeps the service as it is (campaigns without an account may use it) and needs an account of its provider; a sending service clears the account; the mail server clears the account and switches the service off, as a service would take system e-mail before the server. So choosing the server can change how campaigns leave; the Campaigns sentence says so after the save.

## What do the other rows say?
- Sending domain: "domain · DMARC to check" with "n to check" amber, Good, or Could not check. Campaign mail: "Scheduled sending on · at most n a run" / off. MailChimp: "Sync on · list x" / off. None asks for attention, so How e-mail leaves opens first.
- Send a test: "The test goes to <address>.", the last result as one line with its pill (Sent / Failed and the reason), "Save and send a test". Line "Last test 16:20 · accepted" (the date when not today) or "No test sent yet".

Related: docs/memory/ui/settings-mail-sending.md, docs/memory/plans/mail-settings-one-steps.md
