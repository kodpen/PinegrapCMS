---
name: messages-area-templates-tab
description: Communication › Templates (MA6, #9294): the SMS texts as look A card rows with kind, state, own or built-in text, edited on the SMS Messages card; e-mail designs and WhatsApp as Soon rows
module: messages_templates
---

## What does the Templates tab list?
- Built in look A's recipes, the drawing's card pattern (`plans/messages-area/messages-area.html`, the Campaigns card), under the architect's rule of #9295: no screen of the area in the old look, real screenshots and the owner's yes before ready.
- "SMS texts" card (role <= 2, who may open the settings): every event the SMS Messages card offers, in its order (`pg_messages_template_sms_rows()`, pure). An ERP event only while the module is on, a booking event once bookings are set up (`pg_sms_message_event_shown()`).
- A row (`.pg-list-row`): amber chat tile, the event's name leading to the card, a line with its kind, "Built-in" when no own text is saved, and the text (two lines at most); an On (teal) or Off (gray) pill. The card's "Edit the texts" button leads to the card too: texts are still edited there.
- "Other templates" card: E-mail designs and WhatsApp templates as "Soon" rows, the WhatsApp one leading to its card for role <= 2.
- `.pg-messages-card` (padding, head with the button at its right, rows split by a line) in `backend.src.css` only places the recipes; `.pg-card` has no padding of its own, so a card without it puts its title on the border (one of #9295's faults).

## What is still to come?
- WhatsApp templates: a Soon row to the WhatsApp card until the WhatsApp plan's template list lands (WA3, `plans/whatsapp-channel-steps.md`).
- E-mail designs: a Soon row until MA5 gives campaigns their design page (`plans/messages-area-decided.md`).
- A role 3 Campaign Manager sees only the Other templates card for now.

Test: `tests/messages_templates_test.php`.

Related: docs/memory/ui/messages-area-shell.md
