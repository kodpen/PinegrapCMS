---
name: canvas-sample-order
description: The sample order the designer canvas draws in the order view, its receipt e-mail, cart, checkout and its steps: one record whose lines add up to its totals (CS2, #9214)
---

## What does the order family draw on the canvas?
- One sample order (`_sdOrderSample()` in `style_designer.src.js`), for widgets whose `regionType` is in `_SD_ORDER_SAMPLE_REGIONS`: `order_view` (the order page and the order receipt e-mail), `shopping_cart`, `express_order`, `order_preview`, `shipping_address_and_arrival`, `shipping_method`, `billing_information`.
- `_displayValueForBinding()` reads it after the HTML previews and before every other map, so it beats the money, short label and name heuristics there; outside the family nothing changes.
- Canvas only: never in a tree, never on the site or in an e-mail (`canvas-sample-overview.md`).

## Which values agree?
- Two lines: Linen Shirt 2 × 450 = 900 (PRD-1001) and Ceramic Mug 1 × 150 (PRD-1002); subtotal 1,050, offer code WELCOME10 takes 105, shipping 49.90, 20% tax on the discounted 945 = 189, total 1,183.90. `_sdOrderAmounts()` holds them; `tests/widget_condition_eval_test.php` checks they add up. Change them there and together.
- Billing: Alex Morgan, Example Ltd., Springfield / Illinois; shipping (the one recipient): Sam Carter, Cooper Studio, Portland / Oregon (`_sdOrderAddress()`). In Turkish both names are translated keys; "Jane Cooper" was not used because it shares Alex Morgan's Turkish.

## How are line two, the product form and the lists drawn?
- Line one is the item loop's card; line two (`_sdOrderSampleLineTwo()`) its one sample row: in the main loop of the family and in the `order.items` and `cart.items` lists (`_SD_ORDER_LINE_LISTS`).
- Line one carries a product form: one block (Personalisation) of Monogram (card), Thread colour, Gift message and an information field "Care instructions" (rows in `SD_LIST_SAMPLE_ROWS` for `order.item_form_fields` and `cart.item_form_fields`). `__field_is_info` is empty on the answers and "1" on the information row, so the owner's picture (#9076: three ‹Information (HTML)›) is gone. Line two has no form; neither line has a gift card.
- Lists that draw their card alone (`_SD_ONE_ROW_LISTS`): item forms, shipments, checkout recipients, gift cards, the recurring schedule, returns and their lines and deliveries.
- `^^token^^` written in a node's text or attribute reads the list row and the sample order too (`_sdResolveAttrTokens(val, allowHtml, node)`), escaped where the result is markup.

## Which slots draw a control?
- HTML previews in `_SD_HTML_PREVIEW_TOKENS` mirror the page's markup, disabled: the offer code form, Update and Checkout buttons, applied offers, quick add, the quantity stepper, the saved cart link, the checkout consent sentence and line form, the return actions and reason options, the field options, the three `…_address_html` blocks as `<br>` lines.

## What is still not a sample?
- Flags meant to be empty in a shipped order (`__order_is_tax_exempt`, `__item_added_by_offer` …) stay empty; the rest is in `canvas-sample-overview.md`.

## How do I see it in a sandbox?
- After `bash tools/setup_sandbox.sh`: sign in with Playwright, open `add_system_style.php` and read `StyleDesigner.starterTree(kind)` for each kind `pg_design_template_starter_kinds(pg_design_template('pinegrap-market'))` names (`StyleDesigner` is a `const`, not on `window`).
- From PHP CLI (`require 'init.php'`): `pg_design_template_install('pinegrap-market', array('id' => <user_id>, 'role' => 0), array('starters' => $trees, 'seo' => false))`; the user table's columns are `user_id`, `user_role`.
- Then `edit_system_style.php?id=<style_id>&page=<page_id>` (pages: `order`, `email_order`, `cart`, `checkout` keys of the result); the canvas is `#sd-canvas-iframe`. A tall viewport (3600 px) gets the whole canvas in one picture.
