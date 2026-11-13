---
name: whatsapp-webhook-events
description: whatsapp_webhook.php events (WA4, #9351): delivery states, template changes, a customer's message into whatsapp_inbox (11.0.198), its bell notice and customer-history line
module: whatsapp_webhook
---

## Where is a signed post read?
- `pg_whatsapp_webhook_receive()` (`includes/fn/whatsapp.php`) checks the call (`whatsapp-connection.md`), then hands the decoded body to `includes/fn/whatsapp_webhook.php`: `pg_whatsapp_webhook_events()` (pure) and `pg_whatsapp_webhook_apply()`. A signed post is answered 200 even when it is not JSON: a retry would not change it.
- Statuses and messages are read only from changes whose `metadata.phone_number_id` is the stored number; template changes are the account's. At most 500 events a post. Nothing is written on a demo site; a table the upgrade has not made is skipped.

## What does a delivery state do?
- Matched by `wamid` to the `whatsapp_outbox` row; `pg_whatsapp_delivery_change()` (pure) only goes forward: sent < failed < delivered < read. `played` counts as read; a read message gets `delivered_at` too. A failure after delivered is ignored; a failure writes `error_code` and `last_error`, the row's `status` stays the queue's (`sent`).

## What does a template change do?
- `message_template_status_update` sets `status` (Meta's event) by `meta_id`; the reason is kept for REJECTED, PAUSED, DISABLED, FLAGGED and cleared otherwise; `other_info`'s description is added to it. DELETED removes the row. `template_category_update` sets `category`. Between WA3's refreshes only.

## What happens to a customer's message?
- `whatsapp_inbox` (step 11.0.198, approved in #9085): one row per wamid (`INSERT IGNORE` on the UNIQUE key, so Meta's second delivery is neither kept nor announced again). Kept only while the card's switch is on.
- Text: the text, a caption, a document's file name, a button's or list item's title, a reaction's emoji, a place's name; cut at 4096 characters. Media keeps Meta's media id and type only (not downloaded).
- Contact: the newest contact whose mobile phone is the number however typed (`pg_sms_number_variants()`, `pg_sms_number_sql()`), else the contact of the same WhatsApp user id's last message (a username without a number).
- Bell: action `whatsapp_message` (title the text, order_total the sender, form_id the row), for role below 3; the user role needs the contacts right and the row's contact in one of its contact groups (`pg_notification_whatsapp_visible()`, `pg_contact_owner_sql()`), so an unmatched number is staff only; module Shop (`pg_notification_module()`); it opens the contact, else Contacts. No device banner.
- History: source `whatsapp` of the Workspace customer history (`workspace-customer-history.md`) under the contacts right and only for a contact the reader may open (`ws_customer_history_whatsapp_contact()`, `ws_viewer_contacts_open()`; `customers:read` over the API), kind `whatsapp`, at the arrival time, detail sender and text, opening the contact.
- Kept two years (`pg_whatsapp_inbox_sweep()` in `job.php`, 1000 rows a run); a contact holder (merge move, erase find) and erased by number or contact as "WhatsApp messages received".

## How was it verified?
- `tests/whatsapp_webhook_test.php` (events, states, template changes, notice, bell visibility, history line, wiring), `privacy_erase_test.php`, `contact_holders_test.php`. No database and no Meta account in the cloud session: the step, the writes and a real post were not run.

Related: docs/memory/platform/whatsapp-connection.md, docs/memory/platform/whatsapp-outbox-queue.md, docs/memory/platform/whatsapp-cloud-api-webhooks.md
