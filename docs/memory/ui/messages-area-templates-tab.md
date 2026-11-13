---
name: messages-area-templates-tab
description: Communication › Templates (MA6, #9294): the SMS texts listed with kind, state, own or built-in text and fields, edited on the SMS Messages card; WhatsApp and e-mail designs still to come
module: messages_templates
---

## What does the Templates tab list?
- SMS texts (role <= 2, who may open the settings): every event the SMS Messages card offers, in its order (`pg_messages_template_sms_rows()` in `includes/fn/messages_templates.php`, pure). An ERP event only while the module is on, a booking event once bookings are set up (`pg_sms_message_event_shown()`).
- Columns Message (a link to the card), Kind, State (On / Off), Text, Fields. An empty saved text shows the built-in one with a "Built-in" badge. A printed list in browser mode (`messages_templates_sms`), with its own search box; its sort is not kept.
- The texts are still edited on the card (`pg_settings_link('contact', 'pgset-sms-messages')`, "Edit the texts"), as the census proposed (`platform/messages-area-census-tabs.md`).

## What is still to come?
- WhatsApp templates: a row to the WhatsApp card until the WhatsApp plan's template list lands (WA3, `plans/whatsapp-channel-steps.md`).
- E-mail designs: a line saying they will be listed, until MA5 gives campaigns their design page (`plans/messages-area-decided.md`).
- A role 3 Campaign Manager sees only that line for now.

Test: `tests/messages_templates_test.php`.

Related: docs/memory/ui/messages-area-shell.md
