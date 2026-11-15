---
name: look-field-widths
description: Field widths of the add and edit screens in look A (DL5c, #9658): the 16 px field grid, the span per field kind from md up, pg-field-span-N for a field alone in its row, who sets which screen's spans
---

## How wide is a field on an add or edit screen?
- As wide as its content, on the card's 12-column grid: a price, a rate, a count, a date or a time `col-12 md:col-3`; a code (SKU, barcode, GTIN, MPN) or a phone `md:col-4`; a select, a page picker or an e-mail address `md:col-6`; a name, a label, a subject, a text or a web address the whole row (`col-12`, no span). Below md (768 px) every field takes the whole row.
- The sheet draws price, rate, count 3, code 4, select 6, name and text 12 (`add-page.html`); dates, phone, e-mail, web address and the breakpoint are the architect's (channel 25, message 4183; `look-edit-screens.md`).
- DL5c ends with #9658: every screen's field widths go with its group of the panel-old-screens plan, a screen passed once (architect, message 4234).
- Fields side by side take the span on the column (`col-12 md:col-N`). A narrow field alone in its row takes `col-12 pg-field-span-N` (N 3, 4 or 6): the column keeps the row, so the label stays on one line and the help text runs the row's width; only the control is capped to N columns (architect, channel 25 message 4234).
- A field's other classes (`my-3`, a row's style and id) stay. Radios, switches and the card's full-width wrappers were left as they were.

## What did the architect answer where the sheet is silent?
- Architect, 2026-11-15: a date or a time 3 columns (3 + 3 side by side), a date with its time in one input 4.
- Architect, 2026-11-15: a phone 4, an e-mail 6, a web address the whole row.
- Architect, 2026-11-15: spans from md (768 px), whole row below; a value cut at 1024 px may take the next span up from lg.
- Architect, 2026-11-15: one 16 px column gap rule; `my-3` leaves all fields in one later step.
- Architect, 2026-11-15: a narrow field alone in its row keeps the row (`pg-field-span-N`): label on one line, help the row's width, only the control spans.

## Where is the gap set?
- In the "Look A: add and edit screens" block of `backend.src.css`: `body.pg-measure-edit .order-1 .card-body .row { --bs-gutter-x: 16px; }` (Bootstrap's row gutter was 24 px), and the `.pg-field-span-N` rules (from md, every child but `.form-label`, `.form-text`, `.invalid-feedback`, `legend` gets `max-width: calc((100% + gutter) * N / 12 - gutter)`). The vertical spacing (`my-3`) is unchanged.

## Which screens set their spans?
- #9658 sets no screen's spans: it adds the 16 px gap (on every `pg-measure-edit` screen) and `.pg-field-span-N`. Each group of the panel-old-screens plan sets its own screens' spans. `tests/look_field_widths_test.php` pins the two rules.
- The owner's kept screens (`workflow/decisions-made-panel-3.md`: `product_builder`, `edit_user`, `edit_offer` and the rest) take a family's shared rules, the gap included, but no group changes their own markup; a change of their own waits for the owner's look of its picture, asked by the architect (architect, 2026-11-15, channel 25 message 4409). Add Product's spans wait for that poll, kept on branch `plan/panel-design-language-product-spans`.
- Owner (2026-11-15): the classic page screens (Add Page, Edit Page, `includes/forms/page_form.php`) get no look work: that area is to be removed soon.
