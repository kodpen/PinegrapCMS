---
name: sms-incoming
description: A customer's SMS into a channel (WA16a, step of plans/whatsapp-channel): Twilio's webhook sms_webhook.php, the SMS settings' Channel field, the conversation by number, stop words to the consent log
module: sms_inbox
---

## Which providers receive?
- `pg_sms_incoming_ways()` (`includes/fn/sms_inbox.php`): Twilio by webhook. Netgsm and İleti Merkezi by the general job's poll are WA16b; until then they show no field and receive nothing. Facts: `plans/whatsapp-channel-incoming-sms.md`.

## How does Twilio's message arrive?
- `sms_webhook.php?provider=twilio` (root, no session), set as the number's "A message comes in" webhook (HTTP POST). Shown read-only under "Address for customers' messages" in the SMS settings (`pg_sms_webhook_url()`).
- `X-Twilio-Signature` checked with the auth token over the configured address and the sorted fields (`pg_sms_twilio_signature()`); the configured address is used, not the request's, so a proxy's scheme does not break it. Wrong: 401 and a WAF offence. 120 posts a minute an address (`sms_webhook` counter). Answered with an empty TwiML `<Response>`, so Twilio sends nothing back.
- In the WAF's machine endpoints (`waf_is_api_request()`, `waf_country_api_scripts()`).

## Where does it go?
- The SMS box: a `ws_mail_boxes` row with transport `sms` (step 11.0.199), written by the SMS settings' Channel field (`sms_channel`, drawn inside the Twilio block, saved after the provider: `pg_sms_channel_check()` / `_save()`); name `SMS <number>` from the provider's sender, settings `{"app":"sms","provider":…}`. No channel: kept, switched off.
- `ws_mail_phone_ingest()` as WhatsApp's (`workspace/mail-phone-conversations.md`): the hash is SHA-256 of `sms:<MessageSid>`, the contact the first one whose mobile is the number, the card's badge "SMS" (`bi-chat-dots`), the customer line "An SMS from …". Nothing is kept with the Workspace off, without a channel, or on a demo site: the SMS lives only as a conversation's item (owner, #9286).

## What does a stop word do?
- The whole message alone (STOP, STOPALL, UNSUBSCRIBE, CANCEL, END, QUIT, IPTAL, RET, DUR; any case, Turkish letters folded, a closing dot or ! ignored): `pg_sms_stop_refusal()` writes an `sms`/`marketing`/`refused` consent row with source `message` for each contact of the number; the İYS push sends it on. A number no contact has records nothing (the log needs a person). The conversation still opens.

## How was it verified?
- `tests/sms_inbox_test.php` (providers, stop words, signature, message, box values, field, wiring), `waf_subjects_test.php`. No Twilio account: no real post was received.
