---
name: look-field-widths
description: Field widths of the add and edit screens in look A (DL5c1, #9658): the 16 px field grid, the span per field kind from md up, the product and page screens done
---

## How wide is a field on an add or edit screen?
- As wide as its content, on the card's 12-column grid: a price, a rate, a count, a date or a time `col-12 md:col-3`; a code (SKU, barcode, GTIN, MPN) `md:col-4`; a select, a page picker or an e-mail address `md:col-6`; a name, a label, a subject or a text the whole row (`col-12`, no span). Below md (768 px) every field takes the whole row.
- The sheet draws price, rate, count 3, code 4, select 6, name and text 12 (`add-page.html`); dates, e-mail and the breakpoint were asked in channel 25 (message 4149).
- A field's other classes (`my-3`, a row's style and id) stay; only the `sm:`/`md:`/`lg:`/`xl:` spans were replaced. Radios, switches and the card's full-width wrappers were left as they were.

## Where is the gap set?
- One rule in the "Look A: add and edit screens" block of `backend.src.css`: `body.pg-measure-edit .order-1 .card-body .row { --bs-gutter-x: 16px; }` (Bootstrap's row gutter was 24 px). The vertical spacing (`my-3` on a field) is unchanged.

## Which screens are done?
- DL5c1 (#9658): `product_builder.php`, `add_page.php`, `edit_page.php`, `includes/forms/page_form.php`. `tests/look_field_widths_test.php` pins the gap rule and a sample of spans.
- The product screen's attribute dialog (Name, Label) is a dialog, not a card, and kept its spans.
