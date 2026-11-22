---
name: walk-in-queue-notices
description: A walk-in queue number's three notices (WQ3): taken, ahead, called; each once by its bit; WhatsApp, SMS or e-mail; kind queue as a service message; the texts and their places
module: walk_in_queue_tickets
---

## Which notices go?
- `pg_waiting_notify()`: taken (with the link), ahead (when no more than the place's "Tell when N are ahead"; with the link), called (the desk's name; sent by the desk step, WQ4). Each once: its bit (1, 2, 4 in `notices`) is claimed by one conditional UPDATE first. A number taken with N or fewer ahead gets the ahead bit at once, no second message.
- `pg_waiting_notify_ahead($place, $service)` runs after a number leaves; the desk step (WQ4) is to call it on Call next.
- A phone: WhatsApp when the place's WhatsApp is on and the event `queue_taken` / `queue_ahead` / `queue_called` is set on the WhatsApp Messages card, else SMS (`pg_sms_enqueue()`, kind `queue`, reference `queue:<id>:<kind>`, 2 hours to expire). An e-mail only when no phone is given. Texts: the place's own or the standard one; `{number}`, `{desk}`, `{link}`, `{place}`, `{ahead}` filled.
- Service messages (owner, poll 207): kind `queue` is not commercial in `pg_sms_kinds()`, no marketing consent is asked; the queue events refuse a MARKETING template (`service` in `pg_whatsapp_message_events()`, a log line, then SMS) and expire in 2 hours.

## How was it checked?
- `tests/walk_in_queue_tickets_test.php` (texts, channels, bits, the kind and the WhatsApp events). No database, SMS provider or Meta account in the cloud session: no notice was sent.

Related: docs/memory/commerce/walk-in-queue-number-page.md, docs/memory/platform/whatsapp-order-messages.md, docs/memory/platform/sms-outbox-queue.md
