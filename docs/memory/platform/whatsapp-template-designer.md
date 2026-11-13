---
name: whatsapp-template-designer
description: WhatsApp template designer, first half (WA8, #9350): whatsapp_template.php, drafts in whatsapp_templates, the draft check, samples column, phone preview and its JS twin, Send myself a test
module: whatsapp_design
---

## Where is it?
- `whatsapp_template.php` (new: no id; a template: `?id=`), role <= 2 as the WhatsApp card. Functions in `includes/fn/whatsapp_design.php`, script `assets/js/whatsapp_template.js` (served as written, no twin), CSS block "Look A: WhatsApp template designer" in `backend.src.css`. Drawn after `plans/whatsapp-channel/template-designer.html`.
- No link leads to it yet: the WhatsApp list of Communication › Templates (WA3b) links New template and each row to it. Breadcrumb and Cancel go to `messages_templates.php`.

## What does a draft hold?
- A `whatsapp_templates` row with `meta_id` NULL and status DRAFT: name, language, category, `parameter_format` POSITIONAL, `components` as Meta's API reads them (HEADER and BUTTONS kept as stored, BODY with `example.body_text`, FOOTER when not empty).
- `samples` JSON `{"body":[{"label","value"}]}` in the body's variable order: label is the chip's name (presets: Customer's name, Order number, …), value the sample the preview and Meta's review read. A template read from Meta has no samples: Meta's examples stand in (`pg_whatsapp_design_from_row()`).
- Save updates only while the row is still a draft (`meta_id IS NULL AND status = 'DRAFT'`); name and language must be free (`uq_name_language`), refused on the Name field.

## What does the check refuse?
- `pg_whatsapp_design_check()` (pure, tested): name not `[a-z0-9_]{1,512}`; type or language not offered; empty body or over 1024 characters; a variable not written `{{n}}`, numbers with a gap, more than 20, a body starting or ending with a variable; a missing sample (marked on its sample field); samples and labels over 200; footer over 60 or with braces.
- Languages: `pg_whatsapp_design_languages()`, Meta's codes with each language's own name (no translation); a template from Meta in another language is shown by its code.

## How is the preview drawn?
- A phone in WhatsApp's colours (tokens `--pg-wa-*` with `light-dark()`), redrawn as the body, footer and samples are typed. `*bold*`, `_italic_`, `~strike~`, ```` ```code``` ```` and each variable as its sample in a `<mark>`: `pg_whatsapp_design_format_html()` and `pgWaFormat()` are twins; change both.
- The sample rows follow the chips; a row whose chip is removed keeps its value till the page is left.

## What is Send myself a test?
- Only for an APPROVED template, read-only otherwise. Sent at once with `pg_whatsapp_cloud_send()` to the signed-in person's mobile (`pg_whatsapp_design_own_number()`), filled with the samples (`pg_whatsapp_design_send_components()`, header text and named parameters too), in the card test's bucket of ten an hour. Disabled without a mobile number.
- Refused for now: a picture, video or document header, and buttons that take a value (copy code, URL with a variable, OTP): WA9 adds them.

## What did the owner decide about variables?
- Owner (2026-11-13): `{{1}}` in the message box confuses a user; each variable is a draggable Bootstrap chip naming what it stands for (customer name, order number …), dragged in, moved or removed; Meta's `{{n}}` stays in the stored body (channel 25 #2222). Built in WA8b (#9376): `ui/variable-chips.md`; the labels are the chips' names (`pg_whatsapp_design_chip_labels()`: "Value 2" without one).

## What is left to WA9?
- Header media from Files, buttons, Send for approval, editing a rejected template. The type Authentication has no own form yet.

## How was it verified?
- `tests/whatsapp_design_test.php`. On the sandbox: new draft, a missing sample, saved, a taken name; pictures dark and light, wide and phone. No Meta account: the test send was not run against Meta.

Related: docs/memory/platform/whatsapp-templates.md, docs/memory/platform/whatsapp-connection.md, docs/memory/plans/whatsapp-channel-storage.md
