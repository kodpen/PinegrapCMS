---
name: whatsapp-outbox-queue
description: WhatsApp queue (whatsapp_outbox, step 11.0.196, WA2): pg_whatsapp_enqueue() and its refusals, the general job's pass on the SMS queue's rules, consent asked again at the send, erasure
module: whatsapp
---

## How does a WhatsApp message go out?
- A request calls `pg_whatsapp_enqueue($to, $template, $options)` (`includes/fn/whatsapp.php`): no call to Meta. `$template`: name, language, category (MARKETING, UTILITY, AUTHENTICATION, as Meta has it when queued), components (the filled parameters), id. Options: kind (what queued it, `[a-z0-9_]{1,16}`, required), reference, send_after, expires_at, contact_id. Returns `ok, row, duplicate, error`.
- Refused: a number `pg_sms_number()` cannot read, a bad name or language, an unknown category, components over 64 KB as JSON, a marketing template or a message carrying an offer code to a number without SMS marketing consent (`pg_sms_marketing_permission()`, owner #9087), an offer code that cannot be made (`whatsapp-offer-codes.md`), a demo site, the table missing, WhatsApp not set up (`pg_whatsapp_ready()`).
- Reference, send_after and expires_at as in `sms-outbox-queue.md`: UNIQUE reference, a second enqueue returns the first row with `duplicate`.

## How does the general job send it?
- `job.php` loads the module and runs `pg_whatsapp_queue_run(10, 15)` after the SMS pass; an empty table costs one read. No Run now and no settings test through it: the card's test message stays immediate (`whatsapp-connection.md`).
- A Workspace answer is a row of its own: kind `reply`, category `SERVICE`, a text payload sent as free text (`workspace/mail-phone-reply.md`).
- At the send `pg_whatsapp_queue_refusal()` fails the row for good when WhatsApp is off or incomplete (no burst after switching on again), a marketing template's consent is gone (marketing as queued, as its template is now, or holding a key), or the payload is unreadable. Then `pg_whatsapp_cloud_send()`.
- The outcome is `pg_sms_queue_outcome()` itself: sent (the wamid into `wamid`), queued again only when Meta's code allows (`pg_whatsapp_cloud_code_retries()`) or nothing reached Meta, `uncertain` never sent again, expired without an attempt. Meta's code goes into `error_code`. A row stuck in `sending` 15 minutes becomes `uncertain`.
- Failed and uncertain rows get an activity-log line. Sent rows are swept after 7 days, failed and uncertain ones 30 days after queueing.

## What does the table hold?
- `sms_outbox`'s columns without body and provider, plus contact_id, template_id, template_name, template_language, category, payload (MEDIUMTEXT JSON), key_code_id, wamid (indexed), delivery, delivered_at, read_at, error_code. Approved by the owner as WA0 proposed (#9085, `workflow/decisions-made-schema-17.md`). In `get_tables()`.
- `delivery`, `delivered_at`, `read_at` are written by the webhook's statuses by wamid (`whatsapp-webhook-events.md`); `key_code_id` holds the key made for the row's offer code (`whatsapp-offer-codes.md`).

## Who is it erased and merged with?
- A contact holder (`pg_contact_holders()`: merge move, erase find); `pg_privacy_erase_tables()` deletes rows by recipient (the person's phones) or contact_id, listed as "WhatsApp messages".

## Where is it shown?
- Not yet. Communication › Sends keeps branch 3 for it (`messages-area-sends.md`); a screen of the area is built only under the architect's decision of #9295 (drawing, look A, screenshots approved by the owner), so the WhatsApp branch is a step of its own.

## How was it verified?
- `tests/whatsapp_queue_test.php` (row, refusals, refusal at send, outcome, wiring), `privacy_erase_test.php`. No database and no Meta call in the cloud session: the step, the enqueue and the run were not run against MySQL.

Related: docs/memory/platform/sms-outbox-queue.md, docs/memory/platform/whatsapp-connection.md, docs/memory/plans/whatsapp-channel-storage.md
