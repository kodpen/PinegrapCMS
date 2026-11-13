---
name: whatsapp-order-messages
description: WhatsApp order moments (WA5, #9347): the WhatsApp Messages card, one approved template per event and a value per variable, connect_apps whatsapp_messages, where each is queued, off by default
module: whatsapp_messages
---

## What sends an order's WhatsApp message?
- `includes/fn/whatsapp_messages.php` (loaded by `functions.php`; it loads `whatsapp.php` only when an event is on). Four events (`pg_whatsapp_message_events()`): `order_received` (kind `order`), `order_shipped`, `order_delivered` (kind `shipping`), `stock_back` (kind `stock`), with the SMS events' values of the same name ([[sms-order-messages]]).
- Queued beside the SMS call at the same place, with the same reference (`order:<id>:received`, `order:<id>:shipped:<ship_to_id>`, `order:<id>:delivered:<ship_to_id>`, `stock:<row id>`; its own table, so no clash) and the two-day expiry. Marketplace orders are not messaged (`pg_sms_message_order()`). The shipped values come from `pg_sms_order_shipped_fields()`, shared with the text.
- The e-mail and the SMS of the same moment go as before: WhatsApp never replaces them. The card says to switch the SMS off if WhatsApp is to take its place.

## Where are the choices kept?
- `connect_apps` row `whatsapp_messages`, `options` = `{"events":{"<event>":{"on":bool,"template":"name|language","vars":{"<slot>":"<value>"}}}}`; not sealed, no schema. Slot keys: `header:<var>`, `body:<var>`, `button:<index>`.
- Every event is off by default; on without a template is refused on save.

## Which templates can be chosen?
- Approved ones in `whatsapp_templates` whose variables a text can fill (`pg_whatsapp_message_offered()`): no image, video, document or location header, and no copy-code or other button but URL, quick reply and phone. Those wait for WA9 and WA10.
- A template no longer approved stays stored while its event is off, listed as "(not approved)"; switching it on then is refused. At send a template not approved is skipped with an activity-log line.
- Category is Meta's, read at send: a marketing template goes only with marketing consent (`pg_whatsapp_enqueue()`), so the card asks for a utility one.

## How is a variable filled?
- `pg_whatsapp_message_components()` (pure): header, body and URL-button parameters in Meta's order, `parameter_name` for NAMED templates. A value is one line, cut to 60 (header) or 1000 characters; an empty value is sent as `-` (Meta refuses an empty parameter); a button value is percent-encoded, as the end of the link.
- The card shows one list per variable for the chosen template; its script shows another template's lists on change (field names `pg_whatsapp_message_var_name()`).

## How does back in stock get a number?
- The request form asks for a number while the SMS or the WhatsApp event is on (`pg_stock_notify_phone_asked()`). Asked for WhatsApp alone, any mobile `pg_sms_number()` reads is kept. The WhatsApp message goes once per number, beside the text or the mail ([[back-in-stock-sms]]). The visitor's answer still says "one e-mail" when only WhatsApp goes.

## When may it be switched on?
- The plan asks for the owner's first live send with his own Meta app and number before WA5 is switched on anywhere. It ships off.

## How was it verified?
- `tests/whatsapp_messages_test.php` (variables, slots, components, offered templates, the card's decision, wiring), `stock_notify_item_view_test.php`. No database, no Meta account and no sandbox send in the cloud session.

Related: docs/memory/platform/whatsapp-outbox-queue.md, docs/memory/platform/whatsapp-templates.md, docs/memory/platform/sms-order-messages.md
