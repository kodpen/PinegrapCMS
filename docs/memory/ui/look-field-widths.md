---
name: look-field-widths
description: Field widths of the add and edit screens in look A (DL5c, #9658): the 16 px field grid, the span per field kind from md up, pg-field-span-N for a field alone in its row, the screens done
---

## How wide is a field on an add or edit screen?
- As wide as its content, on the card's 12-column grid: a price, a rate, a count, a date or a time `col-12 md:col-3`; a code (SKU, barcode, GTIN, MPN) or a phone `md:col-4`; a select, a page picker or an e-mail address `md:col-6`; a name, a label, a subject, a text or a web address the whole row (`col-12`, no span). Below md (768 px) every field takes the whole row.
- The sheet draws price, rate, count 3, code 4, select 6, name and text 12 (`add-page.html`); dates, phone, e-mail, web address and the breakpoint are the architect's (channel 25, message 4183; `look-edit-screens.md`).
- DL5c ends with the product and page screens: every other screen's field widths go with its group of the panel-old-screens plan, a screen passed once (architect, message 4234).
- Fields side by side take the span on the column (`col-12 md:col-N`). A narrow field alone in its row takes `col-12 pg-field-span-N` (N 3, 4 or 6): the column keeps the row, so the label stays on one line and the help text runs the row's width; only the control is capped to N columns (architect, channel 25 message 4234).
- A field's other classes (`my-3`, a row's style and id) stay. Radios, switches and the card's full-width wrappers were left as they were.

## Where is the gap set?
- In the "Look A: add and edit screens" block of `backend.src.css`: `body.pg-measure-edit .order-1 .card-body .row { --bs-gutter-x: 16px; }` (Bootstrap's row gutter was 24 px), and the `.pg-field-span-N` rules (from md, every child but `.form-label`, `.form-text`, `.invalid-feedback`, `legend` gets `max-width: calc((100% + gutter) * N / 12 - gutter)`). The vertical spacing (`my-3`) is unchanged.

## Which screens are done?
- #9658: `product_builder.php`, `add_page.php`, `edit_page.php`, `includes/forms/page_form.php`. `tests/look_field_widths_test.php` pins the rules and a sample of spans.
- The product screen's attribute dialog (Name, Label) is a dialog, not a card, and kept its spans.
