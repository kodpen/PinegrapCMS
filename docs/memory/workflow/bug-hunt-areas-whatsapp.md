---
name: bug-hunt-areas-whatsapp
description: The bug hunt in WhatsApp messages (WA1–WA10, architect request 3055, 2026-11-26): what was fixed, what was read clean, what is left
---

## Which area was it?
- WhatsApp messages, WA1–WA10 (#9067 to #9414): `includes/fn/whatsapp.php`, `whatsapp_webhook.php` (fn and root), `whatsapp_messages.php`, `whatsapp_reply.php`, `whatsapp_offer.php`, `whatsapp_forms.php`, `whatsapp_media.php`, the driver `includes/whatsapp/cloud_api.php`; not the template designer's look. Named by the architect in the agent channel (2026-11-26, request 3055).

## What was fixed?
- At the send, a row queued as utility whose template Meta moved to marketing since, or holding a single-use offer key, went to a number without marketing consent: the run computed the gate but `pg_whatsapp_queue_refusal()` only looked at the row's own category. It now takes the gate (`$marketing`) (`platform/whatsapp-outbox-queue.md`).
- A customer's STOP, İPTAL, RET… by WhatsApp wrote no marketing refusal, against owner #9286: a new inbox message that is a stop word alone now calls `pg_sms_stop_refusal()` (`platform/whatsapp-webhook-events.md`).
- Communication › Sends, Retry put a failed row back whose single-use key had been switched off at the failure: the customer got a dead code. Such a row is no longer retried; the screen says why (`platform/whatsapp-outbox-queue.md`).
- `pg_whatsapp_queue_run()` sent from a demonstration site (rows a copy of a live site brought): it now stops there.

## What was read and found clean?
- Webhook: signature on the raw body with `hash_equals`, verify token compared in constant time and the challenge echoed only as a short token, events read only for the stored phone number id, statuses forward-only, inbox `INSERT IGNORE` on the wamid, 500 events a post, demo writes nothing.
- Media: the file address comes from Meta's own answer (https only), not the event; media ids are a narrow pattern; size capped.
- Queue: atomic claim on `status = 'queued'`, a stuck `sending` row turned `uncertain` and never resent, Meta's codes decide a retry. Free text and files only before the 24 hours end (the row expires at the window's end). Enqueue: consent before a key is made, duplicate reference returns before a key, a failed insert disables the key.
- Secrets: never in a log line, run row, notice or the Sends list; numbers through `pg_sms_number()` in every sender.

## What is left?
- A delivery `failed` that arrives by webhook after the row was `sent` leaves its offer key on and the cart-recovery touch counted; harmless while nobody knows the code.
- Meta's `user_preferences` webhook (a user stopped marketing in WhatsApp) is not read; Meta itself blocks the sends (131050).
