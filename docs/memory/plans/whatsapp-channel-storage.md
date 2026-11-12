---
name: whatsapp-channel-storage
description: WA0's answers for the WhatsApp channel plan (2026-11-12): queue, templates and customer messages in three tables of their own, SMS marketing consent as the gate, per-recipient keys as single-use key codes, the account in connect_apps
---

## Who settled these, and can they change?
- Step WA0 chose them on 2026-11-12, under the owner's rule of that day that a step decides such questions itself. They hold unless the owner changes them before WA2 writes the migration. Facts: `platform/whatsapp-cloud-api-facts.md`, `-webhooks.md`.

## Where do queued messages live?
- A table of their own, `whatsapp_outbox`, in `sms_outbox`'s shape and rules (`platform/sms-outbox-queue.md`): status queued, sending, sent, failed, uncertain; attempts, send_after, expires_at, claimed_at, sent_at, last_error, kind, recipient (E.164 digits), reference (UNIQUE).
- Added: contact_id, template_id, template_name, template_language, category (as Meta had it when queued), payload (the filled components, JSON), key_code_id, wamid (indexed), delivery (sent, delivered, read, failed from the webhook), delivered_at, read_at, error_code.
- Not `sms_outbox` with a channel: every released SMS query would need a filter, the body is JSON, and `order:438:received` belongs to both channels. The messages area's Sends list reads both tables.

## Where do template drafts and the account's templates live?
- One table, `whatsapp_templates`: a draft is a row without Meta's id (status `DRAFT`); "Send for approval" gives it the id and Meta's state; the refresh (WA3) and the webhook update rows by Meta's id, and remove one Meta deleted unless it is a draft.
- Columns: meta_id (UNIQUE), name, language (UNIQUE together), category, status, reason, parameter_format, components (JSON as Meta reads it), samples (values, the header's file), offer settings (WA10), quality, synced_timestamp, created and updated user and time.
- Not `connect_apps` options: up to 250 templates, looked up at every send.

## Where is a customer's message kept?
- `whatsapp_inbox`: wamid (UNIQUE: Meta's retries arrive twice), received_at, from_number (E.164, empty for a user known only by username), from_user_id (BSUID), profile_name, contact_id (matched by number on arrival), type, body (text, caption or button title), media_id, media_mime, context_wamid (which of ours it answers), read_user_id, read_at.
- The contact's screen lists them (WA4); the bell gets one notice per message. Removed with the contact's erasure, and after two years.

## Which consent lets a marketing template go?
- The number's SMS marketing consent, as `pg_sms_marketing_permission()` reads it (`platform/sms-marketing-kind.md`): a `granted` sms:marketing row and no refusal there or in İYS (`MESAJ` is İYS's type for messages to a mobile number). Asked again at the send.
- Category is Meta's, read at the send: a template filed as utility that Meta approved or moved as marketing is gated as marketing.
- From WA10 the SMS box reads "SMS and WhatsApp" while WhatsApp is set up, so later answers name the channel. WhatsApp's own stop (131050, `user_preferences`) is never sent again.
- Not a box of its own: no stored contact would have it.

## How is a key for one recipient made?
- At enqueue: a `key_codes` row, code 10 characters of `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (copy-code limit 20, no 0/O/1/I), checked against key codes and offers' codes; offer_code the template's offer, single_use 1, report `offer_code`, expiration the template's validity in days from the day it is queued, notes naming the template and number.
- Stored in the row's payload, so a retry sends the same code; a row that ends failed disables its key. A shared code is the offer's own code.

## Where is the account, and how does the card look?
- `connect_apps` row `whatsapp`, no schema: built in WA1 (`platform/whatsapp-connection.md`).
- Drawn in look A: `plans/whatsapp-channel/settings-card.html`, pictures beside it.

Related: docs/memory/plans/whatsapp-channel.md, docs/memory/plans/whatsapp-channel-steps.md
