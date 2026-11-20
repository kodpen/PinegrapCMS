---
name: connections-communication-page
description: WhatsApp's and İYS's pages on Connections (CX11d): the accounts typed there under the Communication gate; the cards show the connection; the SMS providers wait for their own step
module: connections_screen
---

## Where are the WhatsApp and İYS accounts typed?
- On `connections.php?service=whatsapp` and `?service=iys` (CX11d, the Communication group of plan `plans/connections-merges-build.md`), no longer on Site Settings › Communication.
- "Connection details" draws the card's old fields in the page's look: WhatsApp `pg_whatsapp_settings_html()` (switch, the five steps, Test Number, "Save" and "Save and send a test message"); İYS `pg_iys_settings_fields()` (switch, API User, Password, İYS Code, Brand, Environment, Mode, "E-mail and SMS", "Save").
- The writes `whatsapp_save` / `iys_save` run the cards' own `pg_whatsapp_settings_check()` / `pg_iys_settings_check()`, then `..._save()` only when nothing was marked: a changed account is asked about at Meta or İYS first, as before.
- Status has "Disconnect" (`whatsapp_remove` / `iys_remove`, the `forget` action) while an account is stored. Secrets are never drawn back.

## Who may change them?
- Who may open Site Settings › Communication (`connections_contact_may_manage()`, `pg_settings_may_open('contact')`): card `details` of `pg_connections_screen_page_cards()` with `$can['contact']`; the writes check the same.

## What do the cards show?
- `#pgsub-whatsapp-connection` and `#pgsub-iys-connection`: "WhatsApp connection" / "İYS connection", Connected when switched on and complete, "Manage in Connections" / "Connect in Connections".
- The WhatsApp card keeps "Conversations", the Workspace channel field (`pg_whatsapp_channel_check()` stays in `contact.save.php`): it is the site's own setting, not the account.
- `contact.save.php` no longer checks or writes either account. WhatsApp Messages and SMS Messages stay cards.
- Old anchors `whatsapp_app_id` and `iys_user` land on their cards (`pg_settings_legacy_anchors()`). Registry: `place` `connections.php`, `found_at` `#settings/contact/pgset-whatsapp` / `pgset-iys`; `pg_connections_count()` stays 78.
- "Where it is used": WhatsApp → the WhatsApp card and WhatsApp Messages; İYS → the İYS card (marketing consent records).

## Why did İYS's mode and switches move too?
- They are stored on the same `connect_apps` row `iys` as the account and saved by one decision; the card held nothing else.

## Why did the SMS card not move?
- Netgsm, İleti Merkezi and Twilio are three registry services behind one "Send SMS" switch and one Provider select on one card; which page holds the switch and the choice is not settled by the plans. It goes to the architect and is its own step; the SMS card stays as it is until then.

## What stayed on Communication?
- Chat (live chat, no outside service), SMS Messages and WhatsApp Messages (the site's message templates and switches), the WhatsApp Conversations field.

Tests: `tests/connections_communication_test.php`, `tests/whatsapp_test.php`, `tests/workspace_mail_phone_test.php`.
