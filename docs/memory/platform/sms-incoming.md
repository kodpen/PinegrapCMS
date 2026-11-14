---
name: sms-incoming
description: A customer's SMS into a channel (WA16a #9412, WA16b): Twilio's webhook sms_webhook.php, Netgsm and İleti Merkezi asked by the general job, the Channel field, stop words to the consent log
module: sms_inbox
---

## Which providers receive?
- `pg_sms_incoming_ways()` (`includes/fn/sms_inbox.php`): Twilio by webhook; Netgsm and İleti Merkezi by poll (WA16b). Facts: `plans/whatsapp-channel-incoming-sms.md`. Each block draws the Channel field; a polled one, no address.

## How are Netgsm and İleti Merkezi asked?
- `pg_sms_inbox_job()` in `job.php` after the SMS queue: only while the site's provider is a polled one, the Workspace on, the `sms` box on and its settings naming that provider; never on a demo site. Every 120 s (`PG_SMS_INBOX_EVERY`), reaching two days back (`PG_SMS_INBOX_REACH`); what was kept is a duplicate. The last ask is noted in the `connect_apps` row `sms_inbox` (checked, ok, logged); a failure logs one activity line an hour.
- Netgsm: `GET /sms/rest/v2/inbox?startdate=&stopdate=` (`ddMMyyyy`, Basic auth); code 40 is an empty inbox. Dates always sent: without them a message is handed out once, and a failed run would lose it. No message id: the hash of sender, time and text stands for it (`pg_sms_netgsm_inbox_parse()`).
- İleti Merkezi: `POST /v1/get-inbox/json`, `inbox.filter` start/end `Y-m-d H:i:s` (10 days at most), 1000 a page, five pages at most; id `iletimerkezi-<id>`; its `action` UNSUBSCRIBE counts as a stop word (`stop`).
- Read from `@netgsm/sms` 1.1.11 and İleti Merkezi's API page (2026-11-14); never called with a real account.

## How does Twilio's message arrive?
- `sms_webhook.php?provider=twilio` (root, no session), set as the number's "A message comes in" webhook (HTTP POST). Shown read-only under "Address for customers' messages" in the SMS settings (`pg_sms_webhook_url()`).
- `X-Twilio-Signature` checked with the auth token over the configured address and the sorted fields (`pg_sms_twilio_signature()`); the configured address is used, not the request's, so a proxy's scheme does not break it. Wrong: 401 and a WAF offence. 120 posts a minute an address (`sms_webhook` counter). Answered with an empty TwiML `<Response>`, so Twilio sends nothing back.
- In the WAF's machine endpoints (`waf_is_api_request()`, `waf_country_api_scripts()`).

## Where does it go?
- The SMS box: a `ws_mail_boxes` row with transport `sms` (step 11.0.199), written by the SMS settings' Channel field (`sms_channel`, drawn inside the Twilio block, saved after the provider: `pg_sms_channel_check()` / `_save()`); name `SMS <number>` from the provider's sender, settings `{"app":"sms","provider":…}`. No channel: kept, switched off.
- `ws_mail_phone_ingest()` as WhatsApp's (`workspace/mail-phone-conversations.md`): the hash is SHA-256 of `sms:<MessageSid>`, the contact the first one whose mobile is the number, the card's badge "SMS" (`bi-chat-dots`), the customer line "An SMS from …". Nothing is kept with the Workspace off, without a channel, or on a demo site: the SMS lives only as a conversation's item (owner, #9286).

## What does a stop word do?
- The whole message alone (STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT, IPTAL, RET, DUR; any case, Turkish letters folded, a closing dot or ! ignored): `pg_sms_stop_refusal()` writes an `sms`/`marketing`/`refused` consent row with source `message` for each contact of the number; the İYS push sends it on. A number no contact has records nothing (the log needs a person).
- The conversation still opens. The refusal is written after the ingest and not for a duplicate, so a message the provider sends again is refused once.

## How was it verified?
- `tests/sms_inbox_test.php` (providers, stop words, signature, message, box values, field, poll window, both inbox requests and answers, wiring), `waf_subjects_test.php`. On the sandbox (MySQL): a message stored as a conversation, "İptal" from a contact's number wrote one refusal, its retry none; the settings field and the card shot. No Twilio account: no real signed post was received.
