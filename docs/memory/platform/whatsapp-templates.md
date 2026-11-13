---
name: whatsapp-templates
description: WhatsApp templates (whatsapp_templates, step 11.0.197, WA3): read from Meta page by page, the refresh plan, the job every six hours and after a card save, the readers
module: whatsapp
---

## Where are the templates kept?
- `whatsapp_templates` (approved as WA0 proposed, #9085): one row per name and language (`uq_name_language`; name and language in ASCII so the key stays under 767 bytes), `meta_id` UNIQUE and NULL for a draft, category, status (DRAFT for a draft, else Meta's), reason (a rejection's code, NONE read as empty), parameter_format, components (JSON as Meta reads it), samples and offer (WA8-WA10), quality, synced_timestamp, created and updated user and time. In `get_tables()`.

## How are they read from Meta?
- `pg_whatsapp_cloud_templates()` (driver): `GET /<account id>/message_templates` with the fields above, 100 a page, following `paging.cursors.after` while `paging.next` is there, at most 10 pages; more is an error, never a partial list.
- `pg_whatsapp_cloud_templates_parse()` skips entries without a numeric id, a valid name or a language; an answer without `data` is an error, not an empty account.

## What does a refresh write?
- `pg_whatsapp_templates_refresh()`: refused before the upgrade, on a demo site and while WhatsApp is not set up. A failed read writes nothing.
- `pg_whatsapp_templates_plan()` (pure): match by Meta's id, else by name and language (a draft sent for approval, or a template made again under the same name), else add; a row with an id Meta no longer lists is removed; a draft never. Deletes run before inserts.
- Each refresh is noted in its own `connect_apps` row `whatsapp_templates` (checked, ok, error), not in the account's options: the card's save writes the account whole, and a refresh running then would write the old account back.

## When does it run?
- `pg_whatsapp_templates_job()` in `job.php` after the queue pass: while WhatsApp is ready, when never read, six hours after the last read (`PG_WHATSAPP_TEMPLATES_EVERY`), or when the account's row was saved since. A failure writes an activity-log line.
- The webhook (`message_template_status_update`, `template_category_update`) updates single rows between refreshes (`whatsapp-webhook-events.md`).

## Who reads them?
- `pg_whatsapp_template_find($name, $language)` for senders (WA5-WA7: the category read at enqueue); `pg_whatsapp_templates_list()`, `pg_whatsapp_template_status_label()` and `_category_label()` for the list.

## Where is the list shown?
- Not yet: WhatsApp templates join Communication › Templates (MA6, `plans/messages-area-steps.md`), built under #9295's rule (drawing, look A, screenshots approved by the owner): step WA3b.

## How was it verified?
- `tests/whatsapp_templates_test.php` (request, parse, plan, due, labels, wiring). No database and no Meta account in the cloud session.

Related: docs/memory/platform/whatsapp-outbox-queue.md, docs/memory/platform/whatsapp-cloud-api-facts.md, docs/memory/plans/whatsapp-channel-storage.md
