---
name: connections-sms-providers-page
description: Netgsm's, İleti Merkezi's and Twilio's pages on Connections (CX11d2): each account typed there under the Communication gate; the SMS card keeps the switch, the Provider select and the connection
module: connections_screen, sms
---

## Where is an SMS provider's account typed?
- On `connections.php?service=netgsm`, `?service=iletimerkezi`, `?service=twilio` (CX11d2 of `plans/connections-merges-build.md`, architect 2026-11-20). No single "SMS" page.
- "Connection details": `pg_sms_settings_page_fields()`: the provider's help line, its two credentials and sender (`pg_sms_settings_account_fields()`, page look), Twilio's "Address for customers' messages" (`pg_sms_webhook_field()`), Test Number, "Save" and "Save and try".
- Write `sms_save` (provider = `connections_return`): `pg_sms_account_check()` then `pg_sms_account_save()` only when nothing was marked. "Disconnect" in Status (`sms_remove`, `pg_sms_account_forget()`) deletes that provider's row while one is stored.

## What do Save and Save and try do?
- `pg_sms_account_decide()` (pure, tested): only a change is written; a changed account is asked about at the provider (`pg_sms_senders()`), whether SMS is on or off. The stored `enabled` is kept: the page never switches SMS on or off.
- "Save and try" also asks about an unchanged account ("… accepted the account and the sender …") and, with a Test Number, sends a test through this provider even when it is not the one sending (`pg_sms_send(…, $provider)`, `pg_sms_settings_test(…, $provider)`), within the 10 tests an hour.

## What does the SMS card show now?
- `pg_sms_settings_fields()`: "Send SMS" switch, "Provider" select, per provider a `#pgsub-sms-<key>-connection` block ("Netgsm connection", Connected / Not connected, "Manage in Connections" / "Connect in Connections"), the Workspace Channel field, the queue line. No account field and no test message.
- Connected = `pg_sms_app_complete()` (user, a readable password, sender). `pg_sms_settings_script()` shows the select's block and keeps the switch disabled while that provider is not connected and the switch is off; one that is on can always be turned off.
- `contact.save.php`: `pg_sms_switch_check()` / `pg_sms_switch_save()`. `pg_sms_switch_decide()` (pure): on with a provider not connected is marked on `sms_enabled`; a change turns the chosen provider on and every other one off (UPDATE only); an unchanged switch writes nothing, so a running site keeps sending. No account is deleted here.

## Who may change them, and where did the old links go?
- Who may open Site Settings › Communication (`connections_contact_may_manage()`): card `details` of `pg_connections_screen_page_cards()` with `$can['contact']`; the write checks the same.
- Registry rows: `place` `connections.php`, `found_at` `#settings/contact/pgset-sms`; `pg_connections_count()` stays 78. Old anchors `sms_<key>_user` land on the SMS card (`pg_settings_legacy_anchors()`). No data step, table or column: the accounts stay in `connect_apps` `sms_<key>`.
- "Where it is used": the SMS card and SMS Messages, Connected only for the provider sending.

Tests: `tests/sms_test.php`, `tests/connections_communication_test.php`, `tests/sms_inbox_test.php`.
