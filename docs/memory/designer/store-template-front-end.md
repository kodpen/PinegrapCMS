---
name: store-template-front-end
description: Store template front-end fixes (#3621): footer contact column, no card hint at checkout, product tabs on one line, the product page's edit pencil on the widget and clear of headings.
---

## Why is the store footer's contact column col-lg-3?
- At `col-6 col-lg-2` (160 px in a 1063 px window) the e-mail broke inside a word ("hello@example.co" / "m") and the phone's last "00" fell to its own line (#3331).
- Now the about column is `col-12 col-lg-3` and the contact column `col-6 col-lg-3` (with the three `col-lg-2` link lists still 12); the phone link has `text-nowrap`, the e-mail keeps `text-break` for a long real address. Template version 1.1.2 (`includes/design_templates/pinegrap-store.php`).
- `tests/design_template_store_footer_test.php`.

## Why does the checkout's card box carry no hint?
- `.pg-eo-cc-fields` held "The card fields appear when Credit/Debit Card is selected.", but the box is shown only while the card is picked, so the customer read it inside the fields it promised.
- Removed from both twins: the PHP default tree (`includes/fn/widgets_express_order.php`) and the editor's starter (`style_designer.src.js`). A saved tree that still has the paragraph keeps it until the designer removes it.

## How do the product page's tabs stay on one line?
- At 390 px "Reviews" fell to a second line under "Details / Checkout Options".
- The `catalog_item_view` starter's tab menu (JS only; the PHP side has no tab tree) is `nav nav-tabs nav-justified flex-nowrap`, each `nav-link` also `h-100`: equal widths, a long label wraps inside its tab, the tabs keep one height. Existing saved trees keep their classes.
- `tests/store_template_front_end_test.php` (also the hint and the pencil source checks).

## Where does the product page's edit pencil go?
- `catalog_item_view` put it on the loop's first element, which on a designed page can be a section inside the product; the pencil covered the first letters of headings ("Frequently bought together").
- Now `pg_sw_add_edit_chip()` runs on the widget's whole output after the loop is put into the static frame, so the pencil sits on the widget's first element.
- `pg_sw_edit_chip_css()` adds `:is(h1..h6,.breadcrumb).pg-edit-host` and `.pg-edit-chip+:is(h1..h6,nav,.breadcrumb)` → `padding-inline-start:2.4rem`: a heading or breadcrumb that hosts the pencil or comes right after it starts its text past the pencil (signed-in editors only see this). `tests/widget_edit_chip_test.php`.
