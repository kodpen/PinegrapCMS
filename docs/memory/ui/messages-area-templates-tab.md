---
name: messages-area-templates-tab
description: Communication › Templates (MA6 #9294, WA3b #9355): SMS texts edited on their card, WhatsApp templates with state, reason and Refresh, e-mail designs as Soon
module: messages_templates
---

## What does the Templates tab list?
- Built in look A's recipes, the drawing's card pattern (`plans/messages-area/messages-area.html`, the Campaigns card), under the architect's rule of #9295: no screen of the area in the old look, real screenshots and the owner's yes before ready.
- Owner (2026-11-13): approved as shown in the four screenshots of #9294 (dark and light, 1440 and 390 px), the two "Soon" rows and the old tab strip, which is left to the look step, included.
- "SMS texts" card (role <= 2, who may open the settings): every event the SMS Messages card offers, in its order (`pg_messages_template_sms_rows()`, pure). An ERP event only while the module is on, a booking event once bookings are set up (`pg_sms_message_event_shown()`).
- A row (`.pg-list-row`): amber chat tile, the event's name leading to the card, a line with its kind, "Built-in" when no own text is saved, and the text (two lines at most); an On (teal) or Off (gray) pill. The card's "Edit the texts" button leads to the card too: texts are still edited there.
- "WhatsApp templates" card (WA3b, #9355), for everyone who may open the tab: a row per stored template (`pg_messages_template_whatsapp_rows()`, pure), the name, a line "language · type", for a REJECTED one "Reason:" in words (`_reason()`; an old reason on another state is not shown), the state pill (`_tone()`: approved teal; in review, in appeal, paused, flagged amber; rejected, disabled, over the limit rose; the rest gray).
- Its line: not upgraded, a demo site, WhatsApp not set up, or "Read from Meta every six hours, last …". A failed last read is a rose row with Meta's error. Refresh (role <= 2, set up, not demo) posts `templates_action=whatsapp_refresh` to the tab: `pg_whatsapp_templates_refresh()`, at most 30 an hour per site; a notice counts added, updated, removed. Without Refresh, role <= 2 gets "WhatsApp settings".
- "Other templates" card: E-mail designs as a "Soon" row.
- `.pg-messages-card` (padding, head with the button at its right, rows split by a line) in `backend.src.css` only places the recipes; `.pg-card` has no padding of its own, so a card without it puts its title on the border (one of #9295's faults).

## What is still to come?
- Opening a WhatsApp template in the designer, once WA8 and WA9 land.
- E-mail designs: a Soon row until MA5 gives campaigns their design page (`plans/messages-area-decided.md`).
- A role 3 Campaign Manager sees only the Other templates card for now.

Test: `tests/messages_templates_test.php`.

Related: docs/memory/ui/messages-area-shell.md
