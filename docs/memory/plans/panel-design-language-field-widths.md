---
name: panel-design-language-field-widths
description: DL5c's field widths of the add and edit screens cut into screen groups DL5c1-DL5c4: which screens each takes, the spans by field kind, what is asked
---

## Which groups are there?
- M each, in order; each waits for the one before. One PR a group.
- DL5c1 the product screen (`product_builder.php`) and the page screens (`add_page.php`, `edit_page.php`, `includes/forms/page_form.php`). Done in #9658 (`ui/look-field-widths.md`).
- DL5c2 the shop's shared forms: shipping method, product group, product attribute, gift card, container, arrival date, country, state, zone, tax zone, currency, verified shipping address, ship date adjustment, referral source, invoice order, key code.
- DL5c3 content, people and site forms: calendar, event and location, contact and contact group, user, field, submitted form, form list view, menu and menu item, ad and ad region, the region forms, short link, design and theme file, custom style, comment, auto dialog, e-mail campaign and profile.
- DL5c4 the ERP add and edit screens.

## How wide is each field kind?
- The sheet (`panel-design-language-sheet.md`, `add-page.html`): on the card's 12-column grid with 16 px gaps a price, a rate or a count 3 columns; a code 4; a select 6; a name or a text the whole row.
