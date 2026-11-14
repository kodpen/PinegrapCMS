---
name: mail-sms-reply
description: Reply by SMS from a Workspace conversation (WA17, #9450): 7 days after the number's last SMS, the kind reply in sms_outbox, the drawer's length and parts counter, only with a provider that receives
module: sms_reply
---

## When can an SMS conversation be answered?
- Within 7 days of the number's last SMS (`PG_SMS_REPLY_WINDOW`, from `ws_mail_conversations.last_in_at`; owner, #9286). After it the card reads "The 7 days after the customer's last SMS have passed" and offers nothing.
- Only while the site's SMS provider receives (`pg_sms_reply_provider_answers()`: a key of `pg_sms_incoming_ways()`, today Twilio, Netgsm, İleti Merkezi). Another provider: no button, no status row (`ws_mail_phone_sms_offered()` gates the payload).
- For whoever may answer in the channel (`ws_mail_can_answer()`), on the `sms` box's conversations (`ws_mail_phone_reply_target(..., 'sms')`).

## What does Reply by SMS do?
- The card's button (payload `sms_reply`, `sms_window`) opens the Reply by WhatsApp drawer with its SMS branch (`whatsappForm(message, view, h, true)` in `workspace_mail.js`): whom it goes to, the time left, the text, saved replies, Send by SMS. No files and no templates.
- Under the text: "{n} / 1000 characters, {p} SMS" and the encoding line (Standard characters, or Unicode makes every part shorter), live as the person writes.
- Actions `ws_sms_reply_form`, `ws_sms_reply` share the WhatsApp case in `includes/workspace/actions.php`; the item, card message and waiting status are written by `ws_mail_phone_reply_send(..., 'sms')` as for WhatsApp. Text: `pg_sms_text()` form, at most `PG_SMS_ANSWER_MAX` (1000, the layer's `pg_sms_max_length()`).

## How is the length counted?
- `pg_sms_parts()` (`includes/fn/sms_reply.php`): GSM-7 while every character is in the GSM 03.38 alphabet (extension characters `^{}\[~]|€` count two): 160 in one message, 153 a part. Else UCS-2 in UTF-16 units (an emoji is two): 70, then 67 a part. Turkish ğ ı ş İ Ş Ğ ç make it UCS-2.
- Its JS twin is `smsParts()` in `workspace_mail.js` (same tables); a change goes to both. It is the standard count: Netgsm sends Turkish letters with its TR encoding, which its own pricing may count differently.

## How does it leave?
- `pg_sms_enqueue_reply()` → `pg_sms_enqueue('+'.number, text, 'reply', reference, expires_at = the 7 days' end)`. `reply` is a transactional kind in `pg_sms_kinds()` (no marketing footer, no consent asked); its Sends label is "Answer to a customer". Refused on a demo site, before the upgrade, without SMS.
- The reference is the WhatsApp answer's `wsr:<item>:<previous row>`. `pg_sms_queue_run()` reads it and, on sent, failed or uncertain, calls `pg_sms_reply_outcome()`, which writes the item by `pg_whatsapp_reply_outcome()`'s rules. Uncertain reads Not sent; never sent again by itself.
- Send again: `ws_mail_phone_resend()` takes the box's transport, only inside the 7 days. Badge "Sent by SMS" (`bi-chat-dots`).

## How was it verified?
- `tests/sms_reply_test.php` (counter, window, kind, providers, card, texts, wiring); the JS twin compared with PHP on 15 texts by node. No real Twilio, Netgsm or İleti Merkezi account sent an answer.

Related: docs/memory/workspace/mail-phone-reply.md, docs/memory/platform/sms-incoming.md, docs/memory/platform/sms-outbox-queue.md
