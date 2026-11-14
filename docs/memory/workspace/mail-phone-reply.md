---
name: mail-phone-reply
description: Reply by WhatsApp from a Workspace conversation (WA14a, #9405): free text inside the customer's 24 hours, queued in whatsapp_outbox as kind reply, its outcome on the item, Send again, the drawer
module: whatsapp_reply
---

## When can a WhatsApp conversation be answered?
- Only inside the 24 hours after the customer's last message (`PG_WHATSAPP_WINDOW`, from `ws_mail_conversations.last_in_at`): WhatsApp takes free text only then. After it the card reads "The 24 hours … have passed" and offers nothing; approved templates there are WA14b.
- For whoever may answer in the conversation's channel (`ws_mail_can_answer()`), with WhatsApp set up (`pg_whatsapp_ready()`), on the WhatsApp box's conversations only (`ws_mail_phone_reply_target()`).

## What does Reply by WhatsApp do?
- The card's button (payload `whatsapp_reply`, `whatsapp_window` seconds left) opens a drawer: whom it goes to, the time left ("WhatsApp takes a written answer for 5 h 30 min more."), the text (at most 4096 characters, plain), saved replies, Send by WhatsApp. No files yet (WA14b).
- `ws_mail_phone_reply_send()`: a `ws_mail_items` row direction out (`to_list` the number, `send_state` queued, `user_id` the writer), its message under the card, the conversation set waiting (`first_reply_at` once), then `ws_mail_phone_dispatch()`.

## How does it leave?
- `pg_whatsapp_enqueue_reply()` (`includes/fn/whatsapp_reply.php`): a `whatsapp_outbox` row kind `reply`, category `SERVICE`, no template, payload `{"type":"text","text":…}`, reference `wsr:<item>:<previous row>`, `expires_at` the window's end (a row not sent by then expires rather than reaching Meta, which refuses with 131047). Refused on a demo site, before the upgrade, while WhatsApp is off.
- `pg_whatsapp_queue_run()` sends a text payload with `pg_whatsapp_cloud_send_text()` (`type: text`, `preview_url` false) and every other row as a template.
- After each send of a `reply` row, `pg_whatsapp_reply_outcome()` writes the outcome on the item by the e-mail answer's rules (`ws_mail_send_outcome_change()`): sent, or Not sent with the reason and `mail_unsent` to the writer. An uncertain send reads Not sent with the queue's reason; it is never sent again by itself.

## Send again and the badge?
- `ws_mail_resend()` hands a WhatsApp item to `ws_mail_phone_resend()`: only while the window is open, one press counts, the new row's reference names the previous row. A refusal says its own reason (error `phone`).
- `ws_mail_send_badge_html()` reads "Sent by WhatsApp" for a phone item.

## Status and holder?
- A WhatsApp card has no `mail_reply`; the screen draws the reply row when `whatsapp_reply` is set and the status buttons send `mail_reply || whatsapp_reply`, so WA13's WhatsApp cards gained their status and holder controls here.

## How was it verified?
- `tests/whatsapp_reply_test.php` (window, row, reference, Meta's text request, offer, badge, wiring). On a sandbox with a stand-in account (no Meta): the drawer and a queued answer; the send to Meta itself was not reached.

Related: docs/memory/workspace/mail-phone-conversations.md, docs/memory/platform/whatsapp-outbox-queue.md, docs/memory/workspace/mail-tables-sending.md
